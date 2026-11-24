<?php
declare(strict_types=1);

function tg_is_member_status(array $m): bool
{
    $s = $m['status'] ?? '';
    if (in_array($s, ['member', 'administrator', 'creator'], true)) {
        return true;
    }
    return $s === 'restricted' && !empty($m['is_member']);
}

/**
 * Handle one Telegram update for a page. Returns the join id to send to Meta (or 0).
 * Separated from HTTP so it can be tested.
 */
function process_update(array $page, array $u): int
{
    if (isset($u['my_chat_member'])) {
        $m = $u['my_chat_member'];
        $chat = $m['chat'] ?? [];
        $newStatus = $m['new_chat_member']['status'] ?? '';
        $isBot = (int)($m['new_chat_member']['user']['id'] ?? 0) === (int)$page['bot_id'];
        $type = $chat['type'] ?? '';
        if ($isBot && $newStatus === 'administrator' && in_array($type, ['channel', 'supergroup', 'group'], true) && empty($page['chat_id'])) {
            db_update('pages', (int)$page['id'], [
                'chat_id' => (int)$chat['id'],
                'chat_title' => clip((string)($chat['title'] ?? ''), 180),
                'updated_at' => now_utc(),
            ]);
        }
        return 0;
    }
    if (!isset($u['chat_member'])) {
        return 0;
    }
    $cm = $u['chat_member'];
    $chat = $cm['chat'] ?? [];
    if (empty($page['chat_id']) || (int)($chat['id'] ?? 0) !== (int)$page['chat_id']) {
        return 0;
    }
    $old = $cm['old_chat_member'] ?? [];
    $new = $cm['new_chat_member'] ?? [];
    if (!tg_is_member_status($new) || tg_is_member_status($old)) {
        return 0;
    }
    if (!empty($new['user']['is_bot'])) {
        return 0;
    }
    $updateId = (int)($u['update_id'] ?? 0);
    $linkUrl = (string)($cm['invite_link']['invite_link'] ?? '');
    $linkRow = $linkUrl !== '' ? db_one('SELECT * FROM invite_links WHERE page_id=? AND link=?', [$page['id'], clip($linkUrl, 190)]) : null;
    $userId = (string)($new['user']['id'] ?? '0');
    $hash = hash_hmac('sha256', $userId, app_key());
    $matched = $linkRow ? 1 : 0;
    $eventId = $linkRow ? 'lp_' . $linkRow['id'] : 'lp_u' . $updateId;
    try {
        $joinId = db_insert('joins', [
            'page_id' => $page['id'],
            'visitor_id' => $linkRow ? $linkRow['visitor_id'] : null,
            'invite_link_id' => $linkRow ? $linkRow['id'] : null,
            'tg_update_id' => $updateId,
            'tg_user_hash' => $hash,
            'matched' => $matched,
            'event_id' => $eventId,
            'sent_status' => $matched ? 'pending' : 'skipped',
            'last_error' => $matched ? '' : 'Joined without a JoinPath link',
            'joined_at' => now_utc(),
        ]);
    } catch (PDOException $e) {
        return 0; // duplicate delivery of the same update
    }
    if ($linkRow) {
        db_exec('UPDATE invite_links SET used_at=? WHERE id=?', [now_utc(), $linkRow['id']]);
    }
    return $matched ? $joinId : 0;
}

function lp_webhook(int $pageId, string $key): void
{
    $page = db_one('SELECT * FROM pages WHERE id=?', [$pageId]);
    if (!$page || !hash_equals((string)$page['webhook_key'], $key)) {
        http_response_code(404);
        return;
    }
    $hdr = (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
    if (!hash_equals((string)$page['webhook_secret'], $hdr)) {
        http_response_code(403);
        return;
    }
    $u = request_json();
    db_update('pages', $pageId, ['last_webhook_at' => now_utc()]);
    $joinId = 0;
    try {
        $joinId = process_update($page, $u);
    } catch (Throwable $e) {
        error_log('webhook error page ' . $pageId . ': ' . $e->getMessage());
    }
    header('Content-Type: text/plain');
    echo 'ok';
    if ($joinId > 0) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            @ob_end_flush();
            @flush();
        }
        try {
            send_join_event($joinId);
        } catch (Throwable $e) {
            error_log('send_join_event error: ' . $e->getMessage());
        }
    }
}
