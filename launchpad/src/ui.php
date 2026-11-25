<?php
declare(strict_types=1);

/* ---------- session, csrf, auth ---------- */
function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('lpsid');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    session_boot();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = random_hex(16);
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    session_boot();
    $t = (string)($_POST['_csrf'] ?? '');
    if ($t === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $t)) {
        http_response_code(400);
        echo 'Your session expired. Go back, refresh the page and try again.';
        exit;
    }
}

function current_user(): ?array
{
    session_boot();
    static $u = false;
    if ($u === false) {
        $id = (int)($_SESSION['uid'] ?? 0);
        $u = $id ? db_one('SELECT u.*, a.name AS account_name, a.status AS account_status, a.paid_until FROM users u JOIN accounts a ON a.id=u.account_id WHERE u.id=?', [$id]) : null;
    }
    return $u;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        redirect('/login');
    }
    if (!$u['is_admin'] && empty($u['terms_accepted_at'])) {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if ($path !== '/app/terms') {
            redirect('/app/terms');
        }
    }
    return $u;
}

function flash(string $kind, string $msg): void
{
    session_boot();
    $_SESSION['flash'][] = [$kind, $msg];
}

function flash_html(): string
{
    session_boot();
    $h = '';
    foreach ($_SESSION['flash'] ?? [] as [$k, $m]) {
        $h .= '<div class="flash ' . e($k) . '" role="status">' . e($m) . '</div>';
    }
    unset($_SESSION['flash']);
    return $h;
}

/* ---------- icons and bits ---------- */
function icon(string $name, int $size = 18): string
{
    static $p = [
        'home' => 'M3 11l9-8 9 8M5 10v10h14V10',
        'pages' => 'M4 4h16v16H4zM4 9h16M9 9v11',
        'users' => 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM22 21v-2a4 4 0 00-3-3.9M16 3.1a4 4 0 010 7.8',
        'scale' => 'M12 3v18M5 7h14M5 7l-3 7a3 3 0 006 0zM19 7l-3 7a3 3 0 006 0z',
        'pulse' => 'M3 12h4l2-5 4 10 2-5h6',
        'eye' => 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 15a3 3 0 100-6 3 3 0 000 6z',
        'link' => 'M10 13a5 5 0 007 0l3-3a5 5 0 00-7-7l-1 1M14 11a5 5 0 00-7 0l-3 3a5 5 0 007 7l1-1',
        'send' => 'M22 2L11 13M22 2l-7 20-4-9-9-4z',
        'check' => 'M5 12l5 5 9-10',
        'alert' => 'M12 9v4M12 17h.01M10.3 3.9L2 18a2 2 0 001.7 3h16.6a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z',
        'x' => 'M6 6l12 12M18 6L6 18',
        'building' => 'M3 21h18M5 21V5l8-2v18M13 9h6v12M8 8h2M8 12h2M8 16h2',
        'user' => 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z',
    ];
    $d = $p[$name] ?? $p['home'];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex:none"><path d="' . $d . '"/></svg>';
}

function chip(string $kind, string $text): string
{
    $i = ['ok' => 'check', 'warn' => 'alert', 'bad' => 'x'][$kind] ?? '';
    return '<span class="chip ' . e($kind) . '">' . ($i ? icon($i, 14) : '') . e($text) . '</span>';
}

function status_chip(string $s): string
{
    $map = ['sent' => ['ok', 'Sent to Meta'], 'pending' => ['warn', 'Sending'], 'failed' => ['bad', 'Failed'], 'skipped' => ['mute', 'Not sent']];
    [$k, $t] = $map[$s] ?? ['mute', $s];
    return chip($k, $t);
}

function kpi_card(string $label, string $value, string $sub = '', string $icon = 'eye', string $accent = ''): string
{
    $k = ['visit' => 'var(--c-visit)', 'click' => 'var(--c-click)', 'join' => 'var(--c-join)', 'sent' => 'var(--c-sent)', 'bad' => 'var(--c-bad)'][$accent] ?? '';
    return '<div class="card kpi"' . ($k !== '' ? ' style="--k:' . $k . '"' : '') . '><div class="l"><span>' . e($label) . '</span><span class="ico">' . icon($icon, 16) . '</span></div><div class="v num">' . e($value) . '</div>'
        . ($sub !== '' ? '<div class="s">' . e($sub) . '</div>' : '') . '</div>';
}

/* ---------- layout ---------- */
function layout_start(string $title, string $active = '', string $topRight = ''): void
{
    $u = require_login();
    $nav = [
        ['overview', 'Overview', 'home', '/app'],
        ['pages', 'Pages', 'pages', '/app/pages'],
        ['joins', 'Joins', 'users', '/app/joins'],
        ['reconciliation', 'Reconciliation', 'scale', '/app/reconciliation'],
        ['health', 'Setup health', 'pulse', '/app/health'],
    ];
    if ($u['is_admin']) {
        $nav[] = ['admin', 'Admin', 'building', '/app/admin'];
    }
    $nav[] = ['account', 'Account', 'user', '/app/account'];
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e($title) . ' · JoinPath</title><meta name="robots" content="noindex">'
        . '<script>try{document.documentElement.setAttribute(\'data-theme\',localStorage.getItem(\'jp_theme\')||\'dark\')}catch(e){document.documentElement.setAttribute(\'data-theme\',\'dark\')}</script>'
        . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Instrument+Sans:wght@400;500;600&display=swap">'
        . '<link rel="stylesheet" href="/assets/app.css?v=3"></head><body><div class="shell"><aside class="side">'
        . '<div class="logo"><span class="logo-mark"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18c6 0 4-12 16-12M15 6h5v5"/></svg></span><span class="h" style="font-size:21px">JoinPath</span></div>';
    foreach ($nav as [$key, $label, $ic, $href]) {
        echo '<a class="nav' . ($key === $active ? ' on' : '') . '" href="' . $href . '">' . icon($ic) . e($label) . '</a>';
    }
    echo '<div class="who"><b>' . e($u['account_name']) . '</b><span>' . e($u['email']) . '</span><div class="acts">'
        . '<button class="btn ghost sm" type="button" id="jpt" aria-label="Switch between dark and light theme"></button>'
        . '<form method="post" action="/logout">' . csrf_field() . '<button class="btn ghost sm" type="submit">Log out</button></form></div></div></aside>'
        . '<script>(function(){var b=document.getElementById("jpt"),h=document.documentElement;function s(){b.textContent=h.getAttribute("data-theme")==="dark"?"Light":"Dark"}s();b.onclick=function(){var t=h.getAttribute("data-theme")==="dark"?"light":"dark";h.setAttribute("data-theme",t);try{localStorage.setItem("jp_theme",t)}catch(e){}s()}})()</script>'
        . '<main class="main"><div class="top"><h1>' . e($title) . '</h1><div>' . $topRight . '</div></div>'
        . (($u['account_status'] ?? '') === 'suspended' ? '<div class="flash bad" role="alert">Your account is suspended and your pages are offline. Write to ' . e(support_email()) . ' to sort this out.</div>' : '')
        . flash_html();
}

function layout_end(): void
{
    echo '</main></div></body></html>';
}

/* ---------- charts ---------- */
function svg_line_chart(array $labels, array $series, string $aria, int $w = 640, int $h = 240): string
{
    $pl = 44; $pr = 30; $pt = 14; $pb = 34;
    $iw = $w - $pl - $pr;
    $ih = $h - $pt - $pb;
    $n = count($labels);
    $max = 1;
    foreach ($series as $s) {
        $max = max($max, max($s['values'] ?: [0]));
    }
    $step = max(1, (int)ceil($max / 4 / 5) * 5);
    $ymax = $step * 4;
    $x = fn($i) => $pl + ($n > 1 ? $iw * $i / ($n - 1) : $iw / 2);
    $y = fn($v) => $pt + $ih * (1 - $v / $ymax);
    $g = '';
    for ($k = 0; $k <= 4; $k++) {
        $v = $step * $k;
        $yy = $y($v);
        $g .= '<line x1="' . $pl . '" y1="' . round($yy, 1) . '" x2="' . ($w - $pr) . '" y2="' . round($yy, 1) . '" class="gl"/>';
        $g .= '<text x="' . ($pl - 8) . '" y="' . round($yy + 4, 1) . '" text-anchor="end" class="gt">' . $v . '</text>';
    }
    $every = $n > 10 ? (int)ceil($n / 8) : 1;
    foreach ($labels as $i => $l) {
        if ($i % $every === 0 || $i === $n - 1) {
            $g .= '<text x="' . round($x($i), 1) . '" y="' . ($h - 10) . '" text-anchor="middle" class="gt">' . e($l) . '</text>';
        }
    }
    $body = '';
    foreach ($series as $s) {
        $pts = [];
        foreach ($s['values'] as $i => $v) {
            $pts[] = round($x($i), 1) . ',' . round($y($v), 1);
        }
        $da = !empty($s['dashed']) ? ' stroke-dasharray="6 5"' : '';
        $body .= '<polyline points="' . implode(' ', $pts) . '" fill="none" style="stroke:' . $s['color'] . '" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"' . $da . '/>';
        if ($n <= 14) {
            foreach ($s['values'] as $i => $v) {
                $body .= '<circle cx="' . round($x($i), 1) . '" cy="' . round($y($v), 1) . '" r="3.5" class="gd" style="stroke:' . $s['color'] . '" stroke-width="2"/>';
            }
        }
    }
    return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" style="width:100%;height:auto;display:block" role="img" aria-label="' . e($aria) . '">' . $g . $body . '</svg>';
}

function legend_html(array $items): string
{
    $o = '';
    foreach ($items as [$name, $color, $dashed]) {
        $o .= '<span><svg width="28" height="8" aria-hidden="true"><line x1="0" y1="4" x2="28" y2="4" style="stroke:' . $color . '" stroke-width="3"' . ($dashed ? ' stroke-dasharray="5 4"' : '') . '/></svg>' . e($name) . '</span>';
    }
    return '<div class="legend">' . $o . '</div>';
}

function last_days(int $n): array
{
    $days = [];
    for ($i = $n - 1; $i >= 0; $i--) {
        $days[] = to_local(gmdate('Y-m-d H:i:s', time() - $i * 86400), 'Y-m-d');
    }
    return $days;
}

function day_label(string $ymd): string
{
    return date('D j', strtotime($ymd . ' 12:00:00 UTC'));
}
