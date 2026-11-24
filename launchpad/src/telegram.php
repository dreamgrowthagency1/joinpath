<?php
declare(strict_types=1);

function tg_call(string $token, string $method, array $params = []): array
{
    $r = http_json('POST', 'https://api.telegram.org/bot' . $token . '/' . $method, $params ?: new stdClass(), 10);
    $b = $r['body'] ?? null;
    if ($r['status'] === 200 && is_array($b) && !empty($b['ok'])) {
        return ['ok' => true, 'result' => $b['result'] ?? null, 'error' => '', 'code' => 200];
    }
    $err = is_array($b) && isset($b['description']) ? (string)$b['description'] : ($r['error'] ?: 'HTTP ' . $r['status']);
    return ['ok' => false, 'result' => null, 'error' => clip($err, 200), 'code' => (int)$r['status']];
}

function tg_token_looks_valid(string $t): bool
{
    return (bool)preg_match('/^\d{6,12}:[A-Za-z0-9_-]{30,50}$/', $t);
}

function tg_get_me(string $token): array
{
    return tg_call($token, 'getMe');
}

/** chat_ref: @username or numeric id like -1001234567890 */
function tg_resolve_chat(string $token, string $ref): array
{
    $ref = trim($ref);
    if ($ref === '') {
        return ['ok' => false, 'error' => 'empty'];
    }
    if (preg_match('~^(?:https?://)?t\.me/([A-Za-z][A-Za-z0-9_]{3,})$~', $ref, $m)) {
        $ref = '@' . $m[1];
    }
    if (!preg_match('/^(@[A-Za-z][A-Za-z0-9_]{3,}|-?\d{5,20})$/', $ref)) {
        return ['ok' => false, 'error' => 'Use @channelname or the numeric chat id'];
    }
    return tg_call($token, 'getChat', ['chat_id' => $ref]);
}

function tg_create_invite(string $token, int $chatId, string $name, int $ttlSeconds): array
{
    return tg_call($token, 'createChatInviteLink', [
        'chat_id' => $chatId,
        'name' => clip($name, 32),
        'member_limit' => 1,
        'expire_date' => time() + $ttlSeconds,
    ]);
}

function tg_set_webhook(string $token, string $url, string $secret): array
{
    return tg_call($token, 'setWebhook', [
        'url' => $url,
        'secret_token' => $secret,
        'allowed_updates' => ['chat_member', 'my_chat_member'],
        'max_connections' => 20,
        'drop_pending_updates' => false,
    ]);
}

function tg_webhook_info(string $token): array
{
    return tg_call($token, 'getWebhookInfo');
}

function tg_bot_member(string $token, int $chatId, int $botId): array
{
    return tg_call($token, 'getChatMember', ['chat_id' => $chatId, 'user_id' => $botId]);
}

function tg_send_message(string $token, int $chatId, string $text): array
{
    return tg_call($token, 'sendMessage', ['chat_id' => $chatId, 'text' => $text]);
}

function page_bot_token(array $page): string
{
    return dec($page['bot_token_enc'] ?? '');
}

function page_webhook_url(array $page): string
{
    return rtrim((string)cfg('app_url'), '/') . '/hook/' . $page['id'] . '/' . $page['webhook_key'];
}
