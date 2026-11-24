<?php
declare(strict_types=1);

define('LP_ROOT', dirname(__DIR__));
define('LP_SRC', __DIR__);

/* ---------- config ---------- */
function cfg(string $key, $default = null)
{
    static $c = null;
    if ($c === null) {
        $f = LP_ROOT . '/config.php';
        $c = is_file($f) ? (require $f) : [];
        if (!is_array($c)) {
            $c = [];
        }
    }
    return $c[$key] ?? $default;
}

function lp_installed(): bool
{
    return is_file(LP_ROOT . '/config.php');
}

date_default_timezone_set('UTC');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
@ini_set('error_log', LP_ROOT . '/storage/logs/php.log');

set_exception_handler(function (Throwable $e) {
    error_log('Uncaught: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Something went wrong. Please try again.\n";
    exit(1);
});

/* ---------- small helpers ---------- */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

function utc_plus(int $seconds): string
{
    return gmdate('Y-m-d H:i:s', time() + $seconds);
}

function to_local(?string $utc, string $fmt = 'd M, H:i'): string
{
    if (!$utc) {
        return '';
    }
    $dt = new DateTime($utc, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone((string)cfg('timezone', 'Asia/Kolkata')));
    return $dt->format($fmt);
}

function local_day(string $utc): string
{
    return to_local($utc, 'Y-m-d');
}

function ago(?string $utc): string
{
    if (!$utc) {
        return 'never';
    }
    $s = time() - strtotime($utc . ' UTC');
    if ($s < 10) {
        return 'just now';
    }
    if ($s < 60) {
        return $s . ' sec ago';
    }
    if ($s < 3600) {
        return (int)floor($s / 60) . ' min ago';
    }
    if ($s < 86400) {
        return (int)floor($s / 3600) . ' hr ago';
    }
    return (int)floor($s / 86400) . ' days ago';
}

function random_hex(int $bytes): string
{
    return bin2hex(random_bytes($bytes));
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;
    }
    $cv = $_SERVER['HTTP_CF_VISITOR'] ?? '';
    return strpos($cv, '"https"') !== false;
}

function client_ip(): string
{
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect(string $url, int $code = 302): void
{
    if (isset($GLOBALS['LP_REDIRECT_HOOK'])) {
        ($GLOBALS['LP_REDIRECT_HOOK'])($url);
    }
    header('Location: ' . $url, true, $code);
    exit;
}

function request_json(): array
{
    $raw = $GLOBALS['LP_RAW_BODY'] ?? file_get_contents('php://input');
    if ($raw === false || $raw === '' || strlen($raw) > 100000) {
        return [];
    }
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

function clip(string $s, int $max): string
{
    return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
}

/* ---------- database ---------- */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = cfg('db');
        if (!is_array($c)) {
            throw new RuntimeException('Database is not configured');
        }
        $pdo = new PDO($c['dsn'], $c['user'] ?? null, $c['pass'] ?? null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        if (strpos($c['dsn'], 'mysql') === 0) {
            $pdo->exec("SET NAMES utf8mb4");
            $pdo->exec("SET time_zone = '+00:00'");
        }
    }
    return $pdo;
}

function db_all(string $sql, array $p = []): array
{
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}

function db_one(string $sql, array $p = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($p);
    $r = $st->fetch();
    return $r === false ? null : $r;
}

function db_val(string $sql, array $p = [])
{
    $st = db()->prepare($sql);
    $st->execute($p);
    $r = $st->fetchColumn();
    return $r === false ? null : $r;
}

function db_exec(string $sql, array $p = []): int
{
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st->rowCount();
}

function db_insert(string $table, array $row): int
{
    $cols = array_keys($row);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    db_exec($sql, array_values($row));
    return (int)db()->lastInsertId();
}

function db_update(string $table, int $id, array $row): void
{
    $set = implode(',', array_map(fn($c) => $c . '=?', array_keys($row)));
    db_exec('UPDATE ' . $table . ' SET ' . $set . ' WHERE id=?', array_merge(array_values($row), [$id]));
}

/* ---------- crypto (tokens at rest) ---------- */
function app_key(): string
{
    $k = base64_decode((string)cfg('app_key', ''), true);
    if ($k === false || strlen($k) < 32) {
        throw new RuntimeException('app_key missing');
    }
    return substr($k, 0, 32);
}

function enc(string $plain): string
{
    if ($plain === '') {
        return '';
    }
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ct === false) {
        throw new RuntimeException('encrypt failed');
    }
    return 'v1:' . base64_encode($iv . $tag . $ct);
}

function dec(?string $blob): string
{
    if (!$blob || strpos($blob, 'v1:') !== 0) {
        return '';
    }
    $raw = base64_decode(substr($blob, 3), true);
    if ($raw === false || strlen($raw) < 29) {
        return '';
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ct = substr($raw, 28);
    $p = openssl_decrypt($ct, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $p === false ? '' : $p;
}

function mask_secret(string $s): string
{
    $n = strlen($s);
    if ($n === 0) {
        return '';
    }
    return str_repeat('•', max(0, min(12, $n - 4))) . substr($s, -4);
}

/* ---------- HTTP (curl) with a test hook ---------- */
function http_json(string $method, string $url, $body = null, int $timeout = 10): array
{
    if (isset($GLOBALS['LP_HTTP_MOCK']) && is_callable($GLOBALS['LP_HTTP_MOCK'])) {
        return ($GLOBALS['LP_HTTP_MOCK'])($method, $url, $body);
    }
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_USERAGENT => 'JoinPath/1.0',
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($resp === false) {
        return ['status' => 0, 'body' => null, 'raw' => '', 'error' => $err ?: 'network error'];
    }
    $json = json_decode((string)$resp, true);
    return ['status' => $code, 'body' => is_array($json) ? $json : null, 'raw' => (string)$resp, 'error' => ''];
}

function http_get_raw(string $url, int $timeout = 10): array
{
    if (isset($GLOBALS['LP_HTTP_MOCK']) && is_callable($GLOBALS['LP_HTTP_MOCK'])) {
        return ($GLOBALS['LP_HTTP_MOCK'])('GETRAW', $url, null);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_USERAGENT => 'JoinPath-HealthCheck/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ['status' => $code, 'raw' => $resp === false ? '' : (string)$resp, 'error' => $resp === false ? ($err ?: 'network error') : ''];
}

/* ---------- rate limit (db based, fixed window) ---------- */
function rate_hit(string $key, int $limit, int $windowSec): bool
{
    $key = clip($key, 120);
    $now = time();
    $row = db_one('SELECT hits, window_start FROM rate_limits WHERE rkey=?', [$key]);
    if (!$row) {
        try {
            db_exec('INSERT INTO rate_limits (rkey,hits,window_start) VALUES (?,?,?)', [$key, 1, $now]);
        } catch (Throwable $e) {
            // concurrent insert, ignore
        }
        return true;
    }
    if ($now - (int)$row['window_start'] >= $windowSec) {
        db_exec('UPDATE rate_limits SET hits=1, window_start=? WHERE rkey=?', [$now, $key]);
        return true;
    }
    if ((int)$row['hits'] >= $limit) {
        return false;
    }
    db_exec('UPDATE rate_limits SET hits=hits+1 WHERE rkey=?', [$key]);
    return true;
}

/* ---------- loaders ---------- */
foreach (['telegram', 'meta', 'health', 'templates', 'policy', 'home', 'migrate', 'tracker', 'webhook'] as $m) {
    require_once LP_SRC . '/' . $m . '.php';
}
