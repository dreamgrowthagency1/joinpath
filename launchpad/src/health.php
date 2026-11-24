<?php
declare(strict_types=1);

const HEALTH_KEYS = [
    'pixel' => 'Pixel found on your page',
    'token' => 'Conversions API token is valid',
    'test' => 'Test event reached Meta',
    'bot' => 'Bot is admin with invite permission',
    'webhook' => 'Webhook is receiving updates',
    'links' => 'Invite links are being created',
    'https' => 'Page loads over HTTPS',
];

function page_public_url(array $page): string
{
    return 'https://' . $page['slug'] . '.' . cfg('pages_domain', 'joinpath.in') . '/';
}

function health_run_all(array $page): array
{
    $out = [];
    $pageUrl = page_public_url($page);
    $fetch = http_get_raw($pageUrl . '?lp_check=1', 12);

    // 1 + 7: pixel and https
    if ($page['status'] !== 'live') {
        $out['pixel'] = ['bad', 'Publish the page first (status is ' . $page['status'] . ')'];
        $out['https'] = ['bad', 'Page is not live yet'];
    } else {
        $hasPixel = valid_pixel_id((string)$page['pixel_id']) && strpos($fetch['raw'], "fbq('init','" . $page['pixel_id'] . "')") !== false;
        if ($page['pixel_id'] === '') {
            $out['pixel'] = ['bad', 'Add your Pixel ID'];
        } elseif ($hasPixel || ($fetch['status'] === 200 && strpos($fetch['raw'], (string)$page['pixel_id']) !== false)) {
            $out['pixel'] = ['ok', 'Pixel ending ' . substr((string)$page['pixel_id'], -4) . ' is on ' . $page['slug'] . '.' . cfg('pages_domain')];
        } else {
            $out['pixel'] = ['bad', 'Pixel code not found on the page'];
        }
        if ($fetch['error'] !== '') {
            $out['https'] = ['bad', 'Could not load the page securely: ' . clip($fetch['error'], 120)];
        } elseif ($fetch['status'] >= 400 || $fetch['status'] === 0) {
            $out['https'] = ['bad', 'Page returned HTTP ' . $fetch['status']];
        } else {
            $out['https'] = ['ok', 'Certificate valid on ' . $page['slug'] . '.' . cfg('pages_domain')];
        }
    }

    // 2: token
    if (page_capi_token($page) === '') {
        $out['token'] = ['bad', 'Add your Conversions API token'];
    } else {
        $t = meta_check_token($page);
        $out['token'] = [$t['ok'] ? 'ok' : 'bad', $t['msg']];
    }

    // 3: test event (remembered from the last time someone pressed the button)
    if (empty($page['last_test_at'])) {
        $out['test'] = ['warn', 'No test event sent yet. Press "Send test event".'];
    } elseif (!(int)$page['last_test_ok']) {
        $out['test'] = ['bad', 'Last test event failed: ' . $page['last_test_msg']];
    } else {
        $age = time() - strtotime($page['last_test_at'] . ' UTC');
        $out['test'] = $age > 86400
            ? ['warn', 'Last test event was ' . ago($page['last_test_at']) . '. Send a new one to be sure.']
            : ['ok', 'Test event received by Meta ' . ago($page['last_test_at'])];
    }

    // 4 + 5: bot and webhook
    $token = page_bot_token($page);
    if ($token === '' || !$page['bot_id']) {
        $out['bot'] = ['bad', 'Add your bot token'];
        $out['webhook'] = ['bad', 'Connect the bot first'];
    } elseif (!$page['chat_id']) {
        $out['bot'] = ['bad', 'No channel connected. Add the bot as admin to your channel.'];
        $out['webhook'] = ['warn', 'Waiting for a channel'];
    } else {
        $m = tg_bot_member($token, (int)$page['chat_id'], (int)$page['bot_id']);
        $st = $m['ok'] ? ($m['result']['status'] ?? '') : '';
        if (!$m['ok']) {
            $out['bot'] = ['bad', 'Telegram says: ' . $m['error']];
        } elseif ($st !== 'administrator' && $st !== 'creator') {
            $out['bot'] = ['bad', 'Bot is in the channel but not an admin'];
        } elseif (empty($m['result']['can_invite_users'])) {
            $out['bot'] = ['bad', 'Bot is admin but cannot invite users. Turn on "Invite users via link".'];
        } else {
            $out['bot'] = ['ok', 'Bot can create invite links in ' . ($page['chat_title'] ?: 'your channel')];
        }
        $w = tg_webhook_info($token);
        if (!$w['ok']) {
            $out['webhook'] = ['bad', 'Telegram says: ' . $w['error']];
        } elseif (($w['result']['url'] ?? '') !== page_webhook_url($page)) {
            $out['webhook'] = ['bad', 'Webhook is not set to JoinPath. Save the page to reconnect.'];
        } elseif (!empty($w['result']['last_error_message']) && (time() - (int)($w['result']['last_error_date'] ?? 0)) < 3600) {
            $out['webhook'] = ['bad', 'Telegram could not deliver: ' . clip((string)$w['result']['last_error_message'], 120)];
        } else {
            $out['webhook'] = ['ok', empty($page['last_webhook_at']) ? 'Webhook is set. Waiting for the first update.' : 'Last update from Telegram ' . ago($page['last_webhook_at'])];
        }
    }

    // 6: invite links
    $n = (int)db_val('SELECT COUNT(*) FROM invite_links WHERE page_id=? AND created_at>?', [$page['id'], utc_plus(-7 * 86400)]);
    $out['links'] = $n > 0
        ? ['ok', number_format($n) . ' single-use links created in the last 7 days']
        : ['warn', 'No links created yet. They appear when visitors tap your join button.'];

    $now = now_utc();
    foreach (HEALTH_KEYS as $k => $_) {
        [$st, $detail] = $out[$k] ?? ['warn', 'Not checked'];
        $row = db_one('SELECT id FROM health_checks WHERE page_id=? AND check_key=?', [$page['id'], $k]);
        if ($row) {
            db_update('health_checks', (int)$row['id'], ['status' => $st, 'detail' => clip($detail, 390), 'checked_at' => $now]);
        } else {
            db_insert('health_checks', ['page_id' => $page['id'], 'check_key' => $k, 'status' => $st, 'detail' => clip($detail, 390), 'checked_at' => $now]);
        }
    }
    return $out;
}

function health_get(int $pageId): array
{
    $rows = db_all('SELECT * FROM health_checks WHERE page_id=?', [$pageId]);
    $by = [];
    foreach ($rows as $r) {
        $by[$r['check_key']] = $r;
    }
    return $by;
}

function health_send_test_event(array $page): array
{
    $eventId = 'test_' . random_hex(5);
    $ev = meta_build_event($page, null, $eventId, time(), page_public_url($page));
    $res = meta_send($page, [$ev], true);
    db_insert('meta_events', [
        'page_id' => $page['id'], 'join_id' => null, 'kind' => 'test', 'event_name' => $ev['event_name'], 'event_id' => $eventId,
        'http_status' => $res['status'], 'response' => $res['ok'] ? 'ok' : $res['msg'], 'created_at' => now_utc(),
    ]);
    db_update('pages', (int)$page['id'], [
        'last_test_at' => now_utc(), 'last_test_ok' => $res['ok'] ? 1 : 0, 'last_test_msg' => $res['ok'] ? '' : clip($res['msg'], 250),
    ]);
    $res['event_id'] = $eventId;
    return $res;
}
