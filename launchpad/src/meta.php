<?php
declare(strict_types=1);

function graph_url(string $path): string
{
    return 'https://graph.facebook.com/' . cfg('graph_version', 'v25.0') . '/' . ltrim($path, '/');
}

function page_capi_token(array $page): string
{
    return dec($page['capi_token_enc'] ?? '');
}

function build_fbc(array $visitor): string
{
    if (!empty($visitor['fbc'])) {
        return (string)$visitor['fbc'];
    }
    if (!empty($visitor['fbclid'])) {
        $ts = strtotime(($visitor['first_seen'] ?? now_utc()) . ' UTC') * 1000;
        return 'fb.1.' . $ts . '.' . $visitor['fbclid'];
    }
    return '';
}

/** Build one Conversions API event for a join (or a test). */
function meta_build_event(array $page, ?array $visitor, string $eventId, int $eventTime, string $sourceUrl): array
{
    $ud = [];
    if ($visitor) {
        $fbc = build_fbc($visitor);
        if ($fbc !== '') {
            $ud['fbc'] = $fbc;
        }
        if (!empty($visitor['fbp'])) {
            $ud['fbp'] = $visitor['fbp'];
        }
        if (!empty($visitor['ip'])) {
            $ud['client_ip_address'] = $visitor['ip'];
        }
        if (!empty($visitor['ua'])) {
            $ud['client_user_agent'] = $visitor['ua'];
        }
        $ud['external_id'] = [hash('sha256', (string)$visitor['vid'])];
    } else {
        $ud['client_ip_address'] = '203.0.113.10';
        $ud['client_user_agent'] = 'JoinPath health check';
        $ud['external_id'] = [hash('sha256', 'launchpad-test')];
    }
    return [
        'event_name' => $page['event_name'] ?: 'Lead',
        'event_time' => $eventTime,
        'event_id' => $eventId,
        'action_source' => 'website',
        'event_source_url' => $sourceUrl,
        'user_data' => $ud,
    ];
}

/**
 * Send events to the Conversions API.
 * Returns ['ok'=>bool,'status'=>int,'msg'=>string,'raw'=>string]
 */
function meta_send(array $page, array $events, bool $isTest): array
{
    $token = page_capi_token($page);
    if ($page['pixel_id'] === '' || $token === '') {
        return ['ok' => false, 'status' => 0, 'msg' => 'Pixel ID or token missing', 'raw' => ''];
    }
    $body = ['data' => $events, 'access_token' => $token];
    if ($isTest) {
        if ($page['test_event_code'] === '') {
            return ['ok' => false, 'status' => 0, 'msg' => 'Add a test event code first', 'raw' => ''];
        }
        $body['test_event_code'] = $page['test_event_code'];
    }
    $r = http_json('POST', graph_url($page['pixel_id'] . '/events'), $body, 12);
    $b = $r['body'];
    if ($r['status'] === 200 && is_array($b) && isset($b['events_received']) && (int)$b['events_received'] >= 1) {
        return ['ok' => true, 'status' => 200, 'msg' => 'Received by Meta', 'raw' => clip(json_encode($b), 900)];
    }
    $msg = 'Meta rejected the event';
    if (is_array($b) && isset($b['error']['message'])) {
        $msg = (string)$b['error']['message'];
    } elseif ($r['error'] !== '') {
        $msg = $r['error'];
    }
    // never leak the token
    $msg = str_replace($token, '[token]', $msg);
    return ['ok' => false, 'status' => (int)$r['status'], 'msg' => clip($msg, 240), 'raw' => clip(str_replace($token, '[token]', (string)$r['raw']), 900)];
}

/** Validate the token without sending any event. */
function meta_check_token(array $page): array
{
    $token = page_capi_token($page);
    if ($token === '') {
        return ['ok' => false, 'msg' => 'No token saved'];
    }
    $r = http_json('GET', graph_url('me?fields=id&access_token=' . rawurlencode($token)), null, 10);
    if ($r['status'] === 200 && is_array($r['body']) && isset($r['body']['id'])) {
        return ['ok' => true, 'msg' => 'Meta accepted the token'];
    }
    $m = is_array($r['body']) && isset($r['body']['error']['message']) ? (string)$r['body']['error']['message'] : ($r['error'] ?: 'Token not accepted');
    return ['ok' => false, 'msg' => clip(str_replace($token, '[token]', $m), 200)];
}

/** Send (or retry) the Conversions API event for one join row. */
function send_join_event(int $joinId): void
{
    $j = db_one('SELECT * FROM joins WHERE id=?', [$joinId]);
    if (!$j || $j['sent_status'] === 'sent' || !(int)$j['matched']) {
        return;
    }
    $page = db_one('SELECT * FROM pages WHERE id=?', [$j['page_id']]);
    $visitor = $j['visitor_id'] ? db_one('SELECT * FROM visitors WHERE id=?', [$j['visitor_id']]) : null;
    if (!$page || !$visitor) {
        db_update('joins', $joinId, ['sent_status' => 'skipped', 'last_error' => 'No visitor to match']);
        return;
    }
    $url = $visitor['landing_url'] ?: ('https://' . $page['slug'] . '.' . cfg('pages_domain', 'joinpath.in') . '/');
    $t = (int)strtotime($j['joined_at'] . ' UTC');
    if (time() - $t > 6 * 86400) {
        db_update('joins', $joinId, ['sent_status' => 'failed', 'last_error' => 'Too old to send to Meta (7 day limit)']);
        return;
    }
    $ev = meta_build_event($page, $visitor, (string)$j['event_id'], $t, $url);
    $res = meta_send($page, [$ev], false);
    db_insert('meta_events', [
        'page_id' => $page['id'], 'join_id' => $joinId, 'kind' => 'join', 'event_name' => $ev['event_name'],
        'event_id' => $j['event_id'], 'http_status' => $res['status'], 'response' => $res['ok'] ? 'ok' : $res['msg'],
        'created_at' => now_utc(),
    ]);
    $att = (int)$j['attempts'] + 1;
    if ($res['ok']) {
        db_update('joins', $joinId, ['sent_status' => 'sent', 'attempts' => $att, 'last_error' => '', 'sent_at' => now_utc()]);
    } else {
        db_update('joins', $joinId, ['sent_status' => $att >= 5 ? 'failed' : 'pending', 'attempts' => $att, 'last_error' => clip($res['msg'], 250)]);
    }
}
