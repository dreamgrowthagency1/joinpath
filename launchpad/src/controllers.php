<?php
declare(strict_types=1);

require_once LP_SRC . '/ui.php';
require_once LP_SRC . '/public_pages.php';
require_once LP_SRC . '/admin.php';

const RESERVED_SLUGS = ['www', 'app', 'api', 'mail', 'admin', 'dashboard', 'login', 'static', 'assets', 'cdn', 'ftp', 'smtp', 'cpanel', 'webmail', 'hook', 'test'];

function account_pages(int $accountId): array
{
    return db_all('SELECT * FROM pages WHERE account_id=? ORDER BY id DESC', [$accountId]);
}

function own_page(array $user, int $id): ?array
{
    return db_one('SELECT * FROM pages WHERE id=? AND account_id=?', [$id, $user['account_id']]);
}

function in_clause(array $ids): string
{
    return implode(',', array_fill(0, count($ids), '?'));
}

function sec_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
}

/* ======================================================= routing */
function app_main(): void
{
    if (!lp_installed()) {
        require_once LP_SRC . '/install.php';
        install_main();
        return;
    }
    lp_migrate();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    $path = $path !== '/' ? rtrim($path, '/') : '/';

    if (preg_match('#^/hook/(\d+)/([a-f0-9]{32})$#', $path, $m)) {
        if ($method !== 'POST') {
            http_response_code(405);
            return;
        }
        lp_webhook((int)$m[1], $m[2]);
        return;
    }
    if ($path === '/cron') {
        // optional web cron: /cron?key=...
        if (!hash_equals((string)cfg('cron_key', 'x'), (string)($_GET['key'] ?? ''))) {
            http_response_code(403);
            return;
        }
        require_once LP_ROOT . '/cron.php';
        cron_run();
        echo "done\n";
        return;
    }
    sec_headers();
    header('Content-Security-Policy: frame-ancestors \'none\'');

    $routes = [
        ['GET', '#^/$#', 'page_home'],
        ['GET', '#^/login$#', 'page_login'],
        ['POST', '#^/login$#', 'do_login'],
        ['POST', '#^/logout$#', 'do_logout'],
        ['GET', '#^/app$#', 'page_overview'],
        ['GET', '#^/app/pages$#', 'page_pages'],
        ['GET', '#^/app/pages/new$#', 'page_page_form'],
        ['POST', '#^/app/pages/new$#', 'do_page_save'],
        ['GET', '#^/app/pages/(\d+)$#', 'page_page_form'],
        ['POST', '#^/app/pages/(\d+)$#', 'do_page_save'],
        ['GET', '#^/app/tpl$#', 'page_tpl'],
        ['POST', '#^/app/preview$#', 'do_preview'],
        ['GET', '#^/app/joins$#', 'page_joins'],
        ['GET', '#^/app/reconciliation$#', 'page_reconciliation'],
        ['GET', '#^/app/health$#', 'page_health'],
        ['POST', '#^/app/health/run$#', 'do_health_run'],
        ['POST', '#^/app/health/test$#', 'do_health_test'],
        ['GET', '#^/app/account$#', 'page_account'],
        ['POST', '#^/app/account$#', 'do_account'],
        ['GET', '#^/report$#', 'page_report'],
        ['POST', '#^/report$#', 'do_report'],
        ['GET', '#^/terms$#', 'page_terms_doc'],
        ['GET', '#^/privacy$#', 'page_privacy_doc'],
        ['GET', '#^/refund$#', 'page_refund_doc'],
        ['GET', '#^/welcome$#', 'page_welcome'],
        ['POST', '#^/welcome$#', 'do_welcome'],
        ['GET', '#^/app/terms$#', 'page_app_terms'],
        ['POST', '#^/app/terms$#', 'do_app_terms'],
        ['GET', '#^/app/admin$#', 'page_admin'],
        ['GET', '#^/app/admin/pages$#', 'page_admin_pages'],
        ['GET', '#^/app/admin/reports$#', 'page_admin_reports'],
        ['GET', '#^/app/admin/requests$#', 'page_admin_requests'],
        ['POST', '#^/app/admin/request$#', 'do_admin_request'],
        ['POST', '#^/app/admin/create$#', 'do_admin_create'],
        ['POST', '#^/app/admin/reset$#', 'do_admin_reset'],
        ['POST', '#^/app/admin/account$#', 'do_admin_account'],
        ['POST', '#^/app/admin/page$#', 'do_admin_page'],
        ['POST', '#^/app/admin/report$#', 'do_admin_report'],
    ];
    foreach ($routes as [$mth, $re, $fn]) {
        if ($mth === $method && preg_match($re, $path, $m)) {
            array_shift($m);
            $fn(...array_map(fn($x) => (int)$x, $m));
            return;
        }
    }
    http_response_code(404);
    echo '<!doctype html><title>Not found</title><p style="font-family:sans-serif;padding:40px">Page not found. <a href="/">Home</a></p>';
}

/* ======================================================= public pages */
function page_home(): void
{
    session_boot();
    if (current_user()) {
        redirect('/app');
    }
    header('Content-Type: text/html; charset=utf-8');
    echo lp_home_html();
}

function auth_page(string $title, string $inner): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . ' · JoinPath</title><meta name="robots" content="noindex">'
        . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;700&display=swap">'
        . '<link rel="stylesheet" href="/assets/app.css?v=2"></head><body><div class="auth"><div class="card">'
        . '<div class="h" style="font-size:22px;margin-bottom:24px">JoinPath</div>' . flash_html() . $inner . '</div>' . public_footer_links() . '</div></body></html>';
}

function page_login(): void
{
    session_boot();
    if (current_user()) {
        redirect('/app');
    }
    auth_page('Log in', '<h1 style="font-size:26px;margin-bottom:20px">Log in</h1><form method="post" action="/login">' . csrf_field()
        . '<div class="field"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="username" required autofocus></div>'
        . '<div class="field"><label for="pw">Password</label><input id="pw" name="password" type="password" autocomplete="current-password" required></div>'
        . '<button class="btn" type="submit" style="width:100%">Log in</button></form>');
}

function do_login(): void
{
    csrf_check();
    $ip = client_ip();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pw = (string)($_POST['password'] ?? '');
    $recent = (int)db_val('SELECT COUNT(*) FROM login_attempts WHERE ip=? AND created_at>?', [$ip, utc_plus(-900)]);
    if ($recent >= 10) {
        flash('bad', 'Too many attempts. Wait 15 minutes and try again.');
        redirect('/login');
    }
    $u = $email !== '' ? db_one('SELECT * FROM users WHERE email=?', [$email]) : null;
    $hash = $u ? $u['password_hash'] : '$2y$10$abcdefghijklmnopqrstuuJ0C8mSxY9m6yBq2kM1m0Z8zQwq3mR1a';
    $ok = password_verify($pw, $hash) && $u;
    if (!$ok) {
        db_insert('login_attempts', ['ip' => $ip, 'email' => clip($email, 180), 'created_at' => now_utc()]);
        flash('bad', 'Email or password is not right.');
        redirect('/login');
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['csrf'] = random_hex(16);
    db_update('users', (int)$u['id'], ['last_login_at' => now_utc()]);
    redirect('/app');
}

function do_logout(): void
{
    csrf_check();
    $_SESSION = [];
    session_destroy();
    redirect('/login');
}

/* ======================================================= overview */
function page_overview(): void
{
    $u = require_login();
    $pages = account_pages((int)$u['account_id']);
    $days = (int)($_GET['days'] ?? 7);
    $days = in_array($days, [1, 7, 30], true) ? $days : 7;
    $pid = (int)($_GET['page'] ?? 0);
    $sel = array_values(array_filter($pages, fn($p) => !$pid || (int)$p['id'] === $pid));
    $ids = array_map(fn($p) => (int)$p['id'], $sel);

    $sinceTs = time() - $days * 86400;
    $since = gmdate('Y-m-d H:i:s', $sinceTs);
    $visits = $clicks = $joins = $sent = $unmatched = 0;
    $campaigns = $feed = [];
    $dayJoins = $daySent = [];
    if ($ids) {
        $in = in_clause($ids);
        $visits = (int)db_val("SELECT COUNT(*) FROM visitors WHERE page_id IN ($in) AND first_seen>=?", array_merge($ids, [$since]));
        $clicks = (int)db_val("SELECT COUNT(*) FROM invite_links WHERE page_id IN ($in) AND created_at>=?", array_merge($ids, [$since]));
        $rows = db_all("SELECT joined_at, sent_status, matched FROM joins WHERE page_id IN ($in) AND joined_at>=?", array_merge($ids, [$since]));
        foreach ($rows as $r) {
            $joins++;
            $d = local_day($r['joined_at']);
            $dayJoins[$d] = ($dayJoins[$d] ?? 0) + 1;
            if ($r['sent_status'] === 'sent') {
                $sent++;
                $daySent[$d] = ($daySent[$d] ?? 0) + 1;
            }
            if (!(int)$r['matched']) {
                $unmatched++;
            }
        }
        $cv = db_all("SELECT utm_campaign c, COUNT(*) n FROM visitors WHERE page_id IN ($in) AND first_seen>=? GROUP BY utm_campaign", array_merge($ids, [$since]));
        $cj = db_all("SELECT v.utm_campaign c, COUNT(*) n FROM joins j JOIN visitors v ON v.id=j.visitor_id WHERE j.page_id IN ($in) AND j.joined_at>=? GROUP BY v.utm_campaign", array_merge($ids, [$since]));
        foreach ($cv as $r) {
            $campaigns[$r['c']] = ['visits' => (int)$r['n'], 'joins' => 0];
        }
        foreach ($cj as $r) {
            $campaigns[$r['c']] = ($campaigns[$r['c']] ?? ['visits' => 0, 'joins' => 0]);
            $campaigns[$r['c']]['joins'] = (int)$r['n'];
        }
        uasort($campaigns, fn($a, $b) => $b['joins'] <=> $a['joins'] ?: $b['visits'] <=> $a['visits']);
        $campaigns = array_slice($campaigns, 0, 8, true);
        $feed = db_all("SELECT j.*, p.name pname, v.utm_campaign FROM joins j JOIN pages p ON p.id=j.page_id LEFT JOIN visitors v ON v.id=j.visitor_id WHERE j.page_id IN ($in) ORDER BY j.id DESC LIMIT 8", $ids);
    }
    $gap = $joins > 0 ? round(($joins - $sent) / $joins * 100, 1) : 0.0;
    $labels = [];
    $vJ = $vS = [];
    $chartDays = $days === 1 ? 7 : $days;
    foreach (last_days($chartDays) as $d) {
        $labels[] = day_label($d);
        $vJ[] = $dayJoins[$d] ?? 0;
        $vS[] = $daySent[$d] ?? 0;
    }

    $opts = '<form method="get" style="display:flex;gap:10px;flex-wrap:wrap"><select name="page" aria-label="Page" style="width:auto"><option value="0">All pages</option>';
    foreach ($pages as $p) {
        $opts .= '<option value="' . $p['id'] . '"' . ((int)$p['id'] === $pid ? ' selected' : '') . '>' . e($p['name']) . '</option>';
    }
    $opts .= '</select><select name="days" aria-label="Range" style="width:auto">';
    foreach ([1 => 'Last 24 hours', 7 => 'Last 7 days', 30 => 'Last 30 days'] as $k => $l) {
        $opts .= '<option value="' . $k . '"' . ($k === $days ? ' selected' : '') . '>' . $l . '</option>';
    }
    $opts .= '</select><button class="btn ghost sm" type="submit">Apply</button></form>';
    layout_start('Overview', 'overview', $opts);

    if (!$pages) {
        echo '<div class="card"><h2>Start here</h2><p class="muted">Create your first page, connect Meta and Telegram, then publish.</p><a class="btn" href="/app/pages/new">Add a page</a></div>';
        layout_end();
        return;
    }
    $unhealthy = (int)db_val('SELECT COUNT(*) FROM health_checks WHERE status=\'bad\' AND page_id IN (' . in_clause(array_map(fn($p) => (int)$p['id'], $pages)) . ')', array_map(fn($p) => (int)$p['id'], $pages));
    if ($unhealthy > 0) {
        echo '<div class="card" style="background:#FDF4E1;border-color:#EBD6A3"><strong>' . $unhealthy . ' setup check' . ($unhealthy > 1 ? 's' : '') . ' failing.</strong> <a href="/app/health" style="text-decoration:underline">Open the health check</a></div>';
    }
    echo '<div class="grid g5">'
        . kpi_card('Visits', number_format($visits), 'Unique visitors', 'eye')
        . kpi_card('Join-link clicks', number_format($clicks), $visits ? round($clicks / $visits * 100, 1) . '% of visits' : '', 'link')
        . kpi_card('Telegram joins', number_format($joins), $visits ? round($joins / $visits * 100, 1) . '% of visits' : '', 'users')
        . kpi_card('Sent to Meta', number_format($sent), ($joins - $sent) . ' not sent', 'send')
        . kpi_card('Not sent', $gap . '%', $unmatched . ' joined without a JoinPath link', 'scale')
        . '</div>';

    echo '<div class="split"><div class="a"><div class="card"><div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px"><h2 style="margin:0">Joins per day</h2>'
        . legend_html([['Telegram joins', '#0B6B5C', false], ['Sent to Meta', '#B9440B', true]]) . '</div>'
        . svg_line_chart($labels, [['values' => $vJ, 'color' => '#0B6B5C'], ['values' => $vS, 'color' => '#B9440B', 'dashed' => true]], 'Line chart of Telegram joins and joins sent to Meta per day') . '</div>';

    $bar = function (string $label, int $val, float $pct, string $color): string {
        return '<div style="margin-bottom:18px"><div style="display:flex;justify-content:space-between;font-size:14px;margin-bottom:8px"><span style="font-weight:500">' . e($label)
            . '</span><span class="num" style="font-size:15px">' . number_format($val) . '</span></div><div class="bar"><i style="width:' . max(1, min(100, $pct)) . '%;background:' . $color . '"></i></div></div>';
    };
    $base = max(1, $visits);
    echo '<div class="card"><h2>Visitor to join</h2>'
        . $bar('Visited the page', $visits, 100, '#B9B4A4')
        . $bar('Clicked the join button', $clicks, $clicks / $base * 100, '#8A9E98')
        . $bar('Joined Telegram', $joins, $joins / $base * 100, '#0B6B5C')
        . $bar('Sent to Meta', $sent, $sent / $base * 100, '#14171F') . '</div></div>';

    echo '<div class="b"><div class="card"><h2>Latest joins</h2><div class="feed">';
    if (!$feed) {
        echo '<p class="muted" style="margin:0">No joins yet. They appear here the moment someone joins your channel.</p>';
    }
    foreach ($feed as $f) {
        echo '<div><div><div style="font-weight:600">Joined channel</div><div class="muted" style="font-size:13px">'
            . e($f['utm_campaign'] ?: e($f['pname'])) . ' · ' . e(ago($f['joined_at'])) . '</div></div>' . status_chip($f['sent_status']) . '</div>';
    }
    echo '</div></div></div></div>';

    echo '<div class="card" style="padding-bottom:12px"><h2>Campaigns</h2><div class="scroll"><table style="min-width:480px"><thead><tr><th>Campaign (utm_campaign)</th><th class="r">Visits</th><th class="r">Joins</th><th class="r">Join rate</th></tr></thead><tbody>';
    if (!$campaigns) {
        echo '<tr><td colspan="4" class="muted">No data yet. Add <span class="mono">utm_campaign={{campaign.name}}</span> to your ad URL parameters.</td></tr>';
    }
    foreach ($campaigns as $c => $r) {
        echo '<tr><td>' . e($c !== '' ? $c : '(no campaign)') . '</td><td class="r">' . number_format($r['visits']) . '</td><td class="r">' . number_format($r['joins']) . '</td><td class="r">'
            . ($r['visits'] ? round($r['joins'] / $r['visits'] * 100, 1) . '%' : '–') . '</td></tr>';
    }
    echo '</tbody></table></div></div>';
    layout_end();
}

/* ======================================================= pages */
function page_pages(): void
{
    $u = require_login();
    $pages = account_pages((int)$u['account_id']);
    layout_start('Pages', 'pages', '<a class="btn" href="/app/pages/new">Add a page</a>');
    echo '<div class="card" style="padding-bottom:12px"><div class="scroll"><table style="min-width:620px"><thead><tr><th>Page</th><th>Address</th><th>Status</th><th>Channel</th><th></th></tr></thead><tbody>';
    if (!$pages) {
        echo '<tr><td colspan="5" class="muted">No pages yet.</td></tr>';
    }
    foreach ($pages as $p) {
        $url = 'https://' . $p['slug'] . '.' . cfg('pages_domain', 'joinpath.in');
        $st = ['live' => ['ok', 'Live'], 'draft' => ['mute', 'Draft'], 'paused' => ['warn', 'Paused']][$p['status']] ?? ['mute', $p['status']];
        echo '<tr><td style="font-weight:600">' . e($p['name']) . '</td><td><a class="mono" href="' . e($url) . '" target="_blank" rel="noopener">' . e($p['slug'] . '.' . cfg('pages_domain')) . '</a></td><td>'
            . chip($st[0], $st[1]) . '</td><td>' . e($p['chat_title'] ?: 'Not connected') . '</td><td class="r"><a class="btn ghost sm" href="/app/pages/' . $p['id'] . '">Edit</a></td></tr>';
    }
    echo '</tbody></table></div></div>';
    layout_end();
}

function page_page_form(int $id = 0): void
{
    $u = require_login();
    $page = null;
    if ($id) {
        $page = own_page($u, $id);
        if (!$page) {
            http_response_code(404);
            echo 'Page not found';
            return;
        }
    }
    $old = $_SESSION['form_old'] ?? null;
    unset($_SESSION['form_old']);
    $g = function (string $k, $def = '') use ($page, $old) {
        if (is_array($old) && array_key_exists($k, $old)) {
            return $old[$k];
        }
        if ($page && array_key_exists($k, $page)) {
            return $page[$k];
        }
        return $def;
    };
    $c = $page ? page_content($page) : page_content(['content_json' => '']);
    if (is_array($old)) {
        foreach (['layout', 'headline', 'sub', 'badge', 'bullets', 'button', 'join_link', 'note', 'members', 'accent', 'footer'] as $k) {
            if (isset($old[$k])) {
                $c[$k] = $old[$k];
            }
        }
    }
    $domain = (string)cfg('pages_domain', 'joinpath.in');
    layout_start($page ? 'Edit page' : 'Add a page', 'pages');
    $act = $page ? '/app/pages/' . $page['id'] : '/app/pages/new';
    $field = function (string $label, string $name, string $val, string $help = '', string $type = 'text', bool $mono = false, string $ph = '', bool $req = false) {
        return '<div class="field"><label for="f_' . $name . '">' . e($label) . '</label><input id="f_' . $name . '" name="' . $name . '" type="' . $type . '" value="' . e($val) . '"'
            . ($mono ? ' class="mono"' : '') . ($ph !== '' ? ' placeholder="' . e($ph) . '"' : '') . ($req ? ' required' : '') . ' autocomplete="off">'
            . ($help !== '' ? '<div class="help">' . $help . '</div>' : '') . '</div>';
    };
    echo '<form method="post" action="' . $act . '" enctype="multipart/form-data" id="pageform">' . csrf_field() . '<div class="split"><div class="a">';

    echo '<div class="card"><h2>Basics</h2>' . $field('Page name', 'name', (string)$g('name'), '', 'text', false, '', true);
    if ($page) {
        echo '<div class="field"><label>Address</label><div class="mono">' . e($page['slug'] . '.' . $domain) . '</div></div>';
    } else {
        echo '<div class="field"><label for="f_slug">Address</label><div style="display:flex;align-items:center;gap:8px"><input id="f_slug" name="slug" type="text" class="mono" value="' . e((string)$g('slug')) . '" required pattern="[a-z0-9\-]{3,30}" placeholder="yourname" autocomplete="off"><span class="mono">.' . e($domain) . '</span></div>'
            . '<div class="help">Letters, numbers and dashes. You cannot change it later.</div></div>';
    }
    echo '<div class="field"><label for="f_status">Status</label><select id="f_status" name="status">';
    foreach (['draft' => 'Draft (not public)', 'live' => 'Live', 'paused' => 'Paused'] as $k => $l) {
        echo '<option value="' . $k . '"' . ($g('status', 'draft') === $k ? ' selected' : '') . '>' . $l . '</option>';
    }
    echo '</select></div></div>';

    echo '<div class="card"><h2>Connect Meta</h2><p class="muted" style="margin-top:-8px">Your Pixel receives the joins.</p>'
        . $field('Pixel ID', 'pixel_id', (string)$g('pixel_id'), 'Events Manager, Data sources, your Pixel.', 'text', true, '1234567890123456');
    $hasTok = $page && $page['capi_token_enc'];
    echo '<div class="field"><label for="f_capi_token">Conversions API access token</label><input id="f_capi_token" name="capi_token" type="password" class="mono" autocomplete="new-password" placeholder="' . ($hasTok ? 'Saved. Paste a new one to replace it' : 'Paste the token') . '">'
        . '<div class="help">Events Manager, Settings, Conversions API, Generate access token. Stored encrypted and never shown again.</div></div>'
        . $field('Test event code (optional)', 'test_event_code', (string)$g('test_event_code'), 'Events Manager, Test events. Needed for the "Send test event" button.', 'text', true, 'TEST12345')
        . '<div class="field"><label for="f_event_name">Event sent to Meta on each join</label><input id="f_event_name" name="event_name" type="text" class="mono" value="' . e((string)$g('event_name', 'Lead')) . '" list="evlist">'
        . '<datalist id="evlist"><option value="Lead"><option value="Subscribe"><option value="CompleteRegistration"><option value="Contact"></datalist>'
        . '<div class="help">"Lead" is a standard event you can optimise campaigns for. Use the same name your ad set optimises on.</div></div></div>';

    echo '</div><div class="b">';
    echo '<div class="card"><h2>Connect Telegram</h2><p class="muted" style="margin-top:-8px">Your bot watches for joins.</p>';
    $hasBot = $page && $page['bot_token_enc'];
    echo '<div class="field"><label for="f_bot_token">Bot token</label><input id="f_bot_token" name="bot_token" type="password" class="mono" autocomplete="new-password" placeholder="' . ($hasBot ? 'Saved' . ($page['bot_username'] ? ' for @' . $page['bot_username'] : '') . '. Paste a new one to replace it' : 'Paste the token from BotFather') . '">'
        . '<div class="help">Create a bot in BotFather and paste its token. Stored encrypted.</div></div>'
        . $field('Channel or group', 'chat_ref', (string)$g('chat_ref'), 'Use @channelname. For a private channel, leave this empty, add the bot as admin and we detect it.' . ($page && $page['chat_title'] ? ' Connected: <strong>' . e($page['chat_title']) . '</strong>' : ''), 'text', false, '@yourchannel')
        . '<div class="help">The bot must be an admin with permission to invite users via link.</div></div>';

    echo '</div></div>';
    echo lp_design_editor($c, (string)$g('template', 'channel'), (string)$g('custom_html', $page['custom_html'] ?? ''));
    echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:24px"><button class="btn" type="submit">Save</button>';
    if ($page) {
        echo '<a class="btn ghost" href="/app/health?page=' . $page['id'] . '">Health check</a>';
        if ($page['status'] === 'live') {
            echo '<a class="btn ghost" target="_blank" rel="noopener" href="' . e('https://' . $page['slug'] . '.' . $domain) . '">View page</a>';
        }
    }
    echo '</div></form>';
    layout_end();
}

function do_page_save(int $id = 0): void
{
    $u = require_login();
    csrf_check();
    $page = null;
    if ($id) {
        $page = own_page($u, $id);
        if (!$page) {
            http_response_code(404);
            return;
        }
    }
    $p = fn(string $k) => trim((string)($_POST[$k] ?? ''));
    $prevRef = $page ? (string)$page['chat_ref'] : '';
    $errors = [];
    $name = clip($p('name'), 150);
    if ($name === '') {
        $errors[] = 'Give the page a name.';
    }
    $status = in_array($p('status'), ['draft', 'live', 'paused'], true) ? $p('status') : 'draft';
    $template = $p('template') === 'custom' ? 'custom' : 'channel';
    $pixel = $p('pixel_id');
    if ($pixel !== '' && !valid_pixel_id($pixel)) {
        $errors[] = 'Pixel ID should be digits only.';
    }
    $event = $p('event_name') ?: 'Lead';
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{1,39}$/', $event)) {
        $errors[] = 'Event name can use letters, numbers and underscores.';
    }
    $testCode = strtoupper($p('test_event_code'));
    if ($testCode !== '' && !preg_match('/^[A-Z0-9]{3,30}$/', $testCode)) {
        $errors[] = 'Test event code looks wrong. Copy it from Events Manager, Test events.';
    }
    $joinLink = $p('join_link');
    if ($joinLink !== '' && safe_join_link($joinLink) === '') {
        $errors[] = 'Telegram link should look like https://t.me/yourchannel';
    }
    $html = (string)($_POST['custom_html'] ?? '');
    if (strlen($html) > 600000) {
        $errors[] = 'Your HTML is too large (600 KB limit).';
    }
    if ($template === 'custom' && trim($html) === '' && $status === 'live') {
        $errors[] = 'Paste your HTML or switch to the built-in template.';
    }
    if ($template === 'channel' && $status === 'live' && safe_join_link($joinLink) === '') {
        $errors[] = 'Add your Telegram link before going live.';
    }
    $slug = '';
    if (!$page) {
        $slug = strtolower($p('slug'));
        if (!preg_match('/^[a-z0-9][a-z0-9-]{2,29}$/', $slug) || in_array($slug, RESERVED_SLUGS, true)) {
            $errors[] = 'Choose another address (3 to 30 letters, numbers or dashes).';
        } elseif (db_one('SELECT id FROM pages WHERE slug=?', [$slug])) {
            $errors[] = 'That address is taken. Try another.';
        }
    }
    $botToken = $p('bot_token');
    if ($botToken !== '' && !tg_token_looks_valid($botToken)) {
        $errors[] = 'That bot token does not look right. Copy it again from BotFather.';
    }
    if ($errors) {
        foreach ($errors as $er) {
            flash('bad', $er);
        }
        $_SESSION['form_old'] = $_POST;
        redirect($page ? '/app/pages/' . $page['id'] : '/app/pages/new');
    }

    $cdata = lp_content_from_post();
    $cdata['image'] = lp_safe_image((string)($_POST['image_current'] ?? ''));
    if (!empty($_POST['remove_image'])) {
        $cdata['image'] = '';
    }
    $up = lp_upload_image($page ? (string)$page['slug'] : $slug);
    if (!empty($up['error'])) {
        flash('bad', $up['error']);
    } elseif (!empty($up['url'])) {
        $cdata['image'] = $up['url'];
    }
    if ($template === 'custom' && trim($html) !== '' && !preg_match('~t\.me/|telegram\.me/|telegram\.dog/|data-lp-join|tg://~i', $html)) {
        flash('info', 'Your HTML has no Telegram link. Add a link to your t.me address, or put data-lp-join on your button, so visitors get a personal invite link.');
    }
    $content = json_encode($cdata, JSON_UNESCAPED_UNICODE);
    $row = [
        'name' => $name, 'status' => $status, 'template' => $template, 'content_json' => $content, 'custom_html' => $html,
        'pixel_id' => $pixel, 'test_event_code' => $testCode, 'event_name' => $event, 'chat_ref' => clip($p('chat_ref'), 110),
        'updated_at' => now_utc(),
    ];
    $capi = $p('capi_token');
    if ($capi !== '') {
        $row['capi_token_enc'] = enc($capi);
    }
    if ($page) {
        db_update('pages', (int)$page['id'], $row);
        $pageId = (int)$page['id'];
    } else {
        $row += ['account_id' => $u['account_id'], 'slug' => $slug, 'webhook_key' => random_hex(16), 'webhook_secret' => random_hex(24), 'created_at' => now_utc()];
        $pageId = db_insert('pages', $row);
    }
    $page = db_one('SELECT * FROM pages WHERE id=?', [$pageId]);
    $risky = lp_risky_terms(lp_page_text($page));
    db_update('pages', $pageId, ['flag_note' => clip(implode(', ', $risky), 290)]);
    if ($risky) {
        flash('info', 'Heads up: your page has wording that often breaks Meta ad rules or Indian law (' . implode(', ', $risky) . '). Meta may reject ads that link to it. Please rewrite it.');
    }

    // Telegram wiring
    $token = '';
    if ($botToken !== '') {
        $me = tg_get_me($botToken);
        if (!$me['ok']) {
            flash('bad', 'Telegram did not accept that bot token: ' . $me['error']);
        } else {
            db_update('pages', $pageId, ['bot_token_enc' => enc($botToken), 'bot_id' => (int)$me['result']['id'], 'bot_username' => clip((string)($me['result']['username'] ?? ''), 70)]);
            $page = db_one('SELECT * FROM pages WHERE id=?', [$pageId]);
            $token = $botToken;
        }
    } elseif ($page['bot_token_enc']) {
        $token = page_bot_token($page);
    }
    if ($token !== '') {
        $ref = (string)$page['chat_ref'];
        if ($ref !== '' && (empty($page['chat_id']) || $ref !== $prevRef || $botToken !== '')) {
            $ch = tg_resolve_chat($token, $ref);
            if ($ch['ok']) {
                db_update('pages', $pageId, ['chat_id' => (int)$ch['result']['id'], 'chat_title' => clip((string)($ch['result']['title'] ?? ''), 180)]);
            } else {
                flash('info', 'Could not read the channel yet (' . ($ch['error'] ?? 'unknown') . '). Add the bot as an admin, then save again.');
            }
        } elseif ($ref === '' && empty($page['chat_id'])) {
            flash('info', 'No channel yet. Add the bot as an admin of your channel and we will detect it.');
        }
        $page = db_one('SELECT * FROM pages WHERE id=?', [$pageId]);
        $w = tg_set_webhook($token, page_webhook_url($page), (string)$page['webhook_secret']);
        if ($w['ok']) {
            db_update('pages', $pageId, ['webhook_set_at' => now_utc()]);
        } else {
            flash('bad', 'Could not connect the Telegram webhook: ' . $w['error']);
        }
    }
    flash('ok', 'Saved.');
    redirect('/app/pages/' . $pageId);
}

/* ======================================================= joins */
function page_joins(): void
{
    $u = require_login();
    $pages = account_pages((int)$u['account_id']);
    $ids = array_map(fn($p) => (int)$p['id'], $pages);
    layout_start('Joins', 'joins');
    echo '<div class="card" style="padding-bottom:12px"><div class="scroll"><table style="min-width:640px"><thead><tr><th>When</th><th>Page</th><th>Campaign</th><th>Matched</th><th>Meta</th></tr></thead><tbody>';
    $rows = $ids ? db_all('SELECT j.*, p.name pname, v.utm_campaign FROM joins j JOIN pages p ON p.id=j.page_id LEFT JOIN visitors v ON v.id=j.visitor_id WHERE j.page_id IN (' . in_clause($ids) . ') ORDER BY j.id DESC LIMIT 200', $ids) : [];
    if (!$rows) {
        echo '<tr><td colspan="5" class="muted">No joins yet.</td></tr>';
    }
    foreach ($rows as $r) {
        echo '<tr><td>' . e(to_local($r['joined_at'], 'd M, H:i')) . '</td><td>' . e($r['pname']) . '</td><td>' . e($r['utm_campaign'] ?: '–') . '</td><td>'
            . ((int)$r['matched'] ? 'Yes' : 'No') . '</td><td>' . status_chip($r['sent_status']) . ($r['last_error'] && $r['sent_status'] !== 'sent' ? ' <span class="muted" style="font-size:13px">' . e($r['last_error']) . '</span>' : '') . '</td></tr>';
    }
    echo '</tbody></table></div></div><p class="muted">Showing the latest 200. Times are in India time.</p>';
    layout_end();
}

/* ======================================================= reconciliation */
function page_reconciliation(): void
{
    $u = require_login();
    $pages = account_pages((int)$u['account_id']);
    $ids = array_map(fn($p) => (int)$p['id'], $pages);
    $days = (int)($_GET['days'] ?? 7);
    $days = in_array($days, [7, 14, 30], true) ? $days : 7;
    $since = gmdate('Y-m-d H:i:s', time() - $days * 86400);
    $rows = $ids ? db_all('SELECT joined_at, sent_status, matched FROM joins WHERE page_id IN (' . in_clause($ids) . ') AND joined_at>=?', array_merge($ids, [$since])) : [];
    $d = [];
    $tot = ['j' => 0, 's' => 0, 'u' => 0, 'f' => 0];
    foreach ($rows as $r) {
        $k = local_day($r['joined_at']);
        $d[$k] = $d[$k] ?? ['j' => 0, 's' => 0];
        $d[$k]['j']++;
        $tot['j']++;
        if ($r['sent_status'] === 'sent') {
            $d[$k]['s']++;
            $tot['s']++;
        }
        if (!(int)$r['matched']) {
            $tot['u']++;
        } elseif ($r['sent_status'] === 'failed' || $r['sent_status'] === 'pending') {
            $tot['f']++;
        }
    }
    $labels = $vj = $vs = [];
    $tableRows = '';
    $list = last_days($days);
    foreach ($list as $day) {
        $labels[] = day_label($day);
        $j = $d[$day]['j'] ?? 0;
        $s = $d[$day]['s'] ?? 0;
        $vj[] = $j;
        $vs[] = $s;
    }
    foreach (array_reverse($list) as $day) {
        $j = $d[$day]['j'] ?? 0;
        $s = $d[$day]['s'] ?? 0;
        $tableRows .= '<tr><td>' . e(day_label($day)) . '</td><td class="r">' . $j . '</td><td class="r">' . $s . '</td><td class="r">' . ($j - $s) . '</td><td class="r">' . ($j ? round(($j - $s) / $j * 100, 1) . '%' : '–') . '</td></tr>';
    }
    $gapPct = $tot['j'] ? round(($tot['j'] - $tot['s']) / $tot['j'] * 100, 1) : 0;
    layout_start('Reconciliation', 'reconciliation', '<form method="get"><select name="days" aria-label="Range" style="width:auto" onchange="this.form.submit()">'
        . implode('', array_map(fn($k) => '<option value="' . $k . '"' . ($k === $days ? ' selected' : '') . '>Last ' . $k . ' days</option>', [7, 14, 30])) . '</select></form>');
    echo '<p class="muted" style="margin:-8px 0 0">Your joins against what was sent to Meta. Ads Manager numbers are added when you connect your ad account (coming next).</p>';
    echo '<div class="grid g4">' . kpi_card('Telegram joins', number_format($tot['j']), 'Counted from your bot', 'users')
        . kpi_card('Sent to Meta', number_format($tot['s']), ($tot['j'] - $tot['s']) . ' not sent', 'send')
        . kpi_card('Joined without a link', number_format($tot['u']), 'Counted, never sent', 'link')
        . kpi_card('Not sent', $gapPct . '%', $tot['f'] . ' still retrying or failed', 'scale') . '</div>';
    echo '<div class="split"><div class="a"><div class="card"><div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px"><h2 style="margin:0">Daily comparison</h2>'
        . legend_html([['Telegram joins', '#0B6B5C', false], ['Sent to Meta', '#B9440B', true]]) . '</div>'
        . svg_line_chart($labels, [['values' => $vj, 'color' => '#0B6B5C'], ['values' => $vs, 'color' => '#B9440B', 'dashed' => true]], 'Daily Telegram joins versus joins sent to Meta', 760, 260) . '</div>'
        . '<div class="card" style="padding-bottom:12px"><h2>Day by day</h2><div class="scroll"><table style="min-width:480px"><thead><tr><th>Day</th><th class="r">Telegram joins</th><th class="r">Sent to Meta</th><th class="r">Not sent</th><th class="r">Not sent (%)</th></tr></thead><tbody>'
        . $tableRows . '</tbody></table></div></div></div>'
        . '<div class="b"><div class="card"><h2>Why a join may not be sent</h2><p class="muted" style="margin:0 0 12px">A join is sent only when we know which ad visit it came from.</p>'
        . '<p style="margin:0 0 12px"><strong>' . $tot['u'] . '</strong> joined through a link that did not come from your page, so there is no ad to match.</p>'
        . '<p style="margin:0"><strong>' . $tot['f'] . '</strong> are waiting to be retried or failed. Open <a href="/app/health" style="text-decoration:underline">Setup health</a> to see why.</p></div></div></div>';
    layout_end();
}

/* ======================================================= health */
function page_health(): void
{
    $u = require_login();
    $pages = account_pages((int)$u['account_id']);
    layout_start('Setup health', 'health');
    if (!$pages) {
        echo '<div class="card"><p class="muted">Add a page first.</p><a class="btn" href="/app/pages/new">Add a page</a></div>';
        layout_end();
        return;
    }
    $pid = (int)($_GET['page'] ?? $pages[0]['id']);
    $page = own_page($u, $pid) ?: $pages[0];
    echo '<form method="get" style="max-width:360px"><label for="pg">Page</label><select id="pg" name="page" onchange="this.form.submit()">';
    foreach ($pages as $p) {
        echo '<option value="' . $p['id'] . '"' . ((int)$p['id'] === (int)$page['id'] ? ' selected' : '') . '>' . e($p['name']) . '</option>';
    }
    echo '</select></form>';
    $hc = health_get((int)$page['id']);
    $pass = 0;
    foreach (HEALTH_KEYS as $k => $_) {
        if (($hc[$k]['status'] ?? '') === 'ok') {
            $pass++;
        }
    }
    echo '<div class="split"><div class="a"><div class="card"><div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap"><div><div class="muted" style="font-weight:500">Checks passing</div><div class="num" style="font-size:44px;margin-top:6px">'
        . $pass . ' of ' . count(HEALTH_KEYS) . '</div></div>'
        . '<form method="post" action="/app/health/run">' . csrf_field() . '<input type="hidden" name="page" value="' . $page['id'] . '"><button class="btn ghost" type="submit">Run all checks</button></form></div></div>'
        . '<div class="card"><h2>Setup checks</h2>';
    foreach (HEALTH_KEYS as $k => $label) {
        $r = $hc[$k] ?? null;
        $st = $r ? $r['status'] : 'warn';
        $chipLabel = ['ok' => 'Passing', 'warn' => 'Needs a look', 'bad' => 'Failing'][$st] ?? 'Not checked';
        echo '<div class="check"><div style="display:flex;gap:16px;align-items:flex-start;flex:1 1 320px"><div style="padding-top:2px">' . ($r ? chip($st, $chipLabel) : chip('mute', 'Not checked')) . '</div>'
            . '<div><div class="t">' . e($label) . '</div><div class="muted" style="font-size:14px;margin-top:4px">' . e($r ? $r['detail'] : 'Press "Run all checks".') . '</div></div></div></div>';
    }
    echo '</div></div><div class="b"><div class="card"><h2>Test event</h2><p class="muted" style="margin-top:-8px">Sends one event to your Pixel with your test code. It appears in Events Manager under Test events within a few seconds.</p>'
        . '<form method="post" action="/app/health/test">' . csrf_field() . '<input type="hidden" name="page" value="' . $page['id'] . '"><button class="btn" type="submit" style="width:100%">Send test event</button></form>';
    if ($page['last_test_at']) {
        echo '<div style="margin-top:20px;display:flex;justify-content:space-between;align-items:center"><strong>Last result</strong><span class="muted">' . e(ago($page['last_test_at'])) . '</span></div><div style="margin-top:10px">'
            . ((int)$page['last_test_ok'] ? chip('ok', 'Received by Meta') : chip('bad', 'Failed')) . '</div>'
            . ($page['last_test_msg'] ? '<p class="muted" style="font-size:14px">' . e($page['last_test_msg']) . '</p>' : '');
    }
    echo '</div></div></div>';
    layout_end();
}

function do_health_run(): void
{
    $u = require_login();
    csrf_check();
    $page = own_page($u, (int)($_POST['page'] ?? 0));
    if ($page) {
        health_run_all($page);
        flash('ok', 'Checks finished.');
    }
    redirect('/app/health?page=' . (int)($_POST['page'] ?? 0));
}

function do_health_test(): void
{
    $u = require_login();
    csrf_check();
    $page = own_page($u, (int)($_POST['page'] ?? 0));
    if ($page) {
        if (!rate_hit('test:' . $page['id'], 10, 600)) {
            flash('bad', 'Too many test events. Wait a few minutes.');
        } else {
            $r = health_send_test_event($page);
            flash($r['ok'] ? 'ok' : 'bad', $r['ok'] ? 'Test event sent. Check Events Manager, Test events.' : 'Meta said: ' . $r['msg']);
            health_run_all(db_one('SELECT * FROM pages WHERE id=?', [$page['id']]));
        }
    }
    redirect('/app/health?page=' . (int)($_POST['page'] ?? 0));
}

/* ======================================================= account */
function page_account(): void
{
    $u = require_login();
    layout_start('Account', 'account');
    echo '<div class="card" style="max-width:520px"><h2>Change password</h2><form method="post" action="/app/account">' . csrf_field()
        . '<div class="field"><label for="cur">Current password</label><input id="cur" name="current" type="password" autocomplete="current-password" required></div>'
        . '<div class="field"><label for="new">New password</label><input id="new" name="new" type="password" autocomplete="new-password" minlength="10" required><div class="help">At least 10 characters.</div></div>'
        . '<button class="btn" type="submit">Update password</button></form></div>'
        . '<div class="card" style="max-width:520px"><h2>Your account</h2><p style="margin:0">' . e($u['account_name']) . '</p><p class="muted" style="margin:4px 0 0">' . e($u['email']) . '</p></div>';
    layout_end();
}

function do_account(): void
{
    $u = require_login();
    csrf_check();
    $cur = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['new'] ?? '');
    if (!password_verify($cur, $u['password_hash'])) {
        flash('bad', 'Current password is not right.');
    } elseif (strlen($new) < 10) {
        flash('bad', 'Use at least 10 characters.');
    } else {
        db_update('users', (int)$u['id'], ['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
        flash('ok', 'Password updated.');
    }
    redirect('/app/account');
}

/* ======================================================= page design helpers */
function lp_content_from_post(): array
{
    $p = fn(string $k) => trim((string)($_POST[$k] ?? ''));
    return [
        'layout' => isset(LP_LAYOUTS[$p('layout')]) ? $p('layout') : 'classic',
        'headline' => clip($p('headline') ?: 'Join our Telegram channel', 120),
        'sub' => clip($p('sub'), 240),
        'badge' => clip($p('badge'), 40),
        'bullets' => implode("\n", lp_bullets((string)($_POST['bullets'] ?? ''))),
        'button' => clip($p('button') ?: 'Join on Telegram', 40),
        'join_link' => $p('join_link'),
        'note' => clip($p('note'), 90),
        'members' => clip($p('members'), 40),
        'accent' => valid_hex_color($p('accent')),
        'footer' => clip($p('footer'), 240),
        'image' => '',
    ];
}

/** Optional image upload. Returns ['none'=>true] | ['url'=>..] | ['error'=>..]. */
function lp_upload_image(string $slug): array
{
    $f = $_FILES['image_file'] ?? null;
    if (!$f || (int)($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['none' => true];
    }
    $err = (int)$f['error'];
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || (int)$f['size'] > 2 * 1024 * 1024) {
        return ['error' => 'That image is too big. Use a file under 2 MB.'];
    }
    if ($err !== UPLOAD_ERR_OK) {
        return ['error' => 'The image did not upload. Try again.'];
    }
    $info = @getimagesize($f['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif'];
    if (defined('IMAGETYPE_WEBP')) {
        $types[IMAGETYPE_WEBP] = 'webp';
    }
    if (!$info || !isset($types[$info[2]])) {
        return ['error' => 'Use a JPG, PNG or WebP image.'];
    }
    if ($info[0] > 5000 || $info[1] > 5000) {
        return ['error' => 'That image is very large in pixels. Resize it under 3000 px wide.'];
    }
    $dir = (string)cfg('uploads_dir', dirname(LP_ROOT) . '/joinpath.in/u');
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return ['error' => 'Could not create the uploads folder.'];
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, 'Options -Indexes -ExecCGI' . "\n" . '<FilesMatch "\.(php|phtml|phar|pl|py|cgi|sh)$">' . "\n" . 'Require all denied' . "\n" . '</FilesMatch>' . "\n");
    }
    $name = preg_replace('/[^a-z0-9-]/', '', $slug) . '-' . random_hex(5) . '.' . $types[$info[2]];
    $dest = $dir . '/' . $name;
    $ok = is_uploaded_file($f['tmp_name']) ? move_uploaded_file($f['tmp_name'], $dest) : (!empty($GLOBALS['LP_TESTING']) && copy($f['tmp_name'], $dest));
    if (!$ok) {
        return ['error' => 'Could not save the image.'];
    }
    @chmod($dest, 0644);
    return ['url' => 'https://' . $slug . '.' . cfg('pages_domain', 'joinpath.in') . '/u/' . $name];
}

function page_tpl(): void
{
    require_login();
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: frame-ancestors 'self'");
    $k = (string)($_GET['l'] ?? 'classic');
    $c = lp_sample_content();
    $c['layout'] = isset(LP_LAYOUTS[$k]) ? $k : 'classic';
    if ($c['layout'] === 'profile') {
        $c['headline'] = 'Daily Learning';
    }
    header('Content-Type: text/html; charset=utf-8');
    echo render_builtin($c);
}

function render_builtin_preview(array $c): string
{
    $data = '';
    if (strpos((string)$c['image'], 'data:image/') === 0) {
        $data = (string)$c['image'];
        $c['image'] = 'https://preview.invalid/img';
    }
    $html = render_builtin($c);
    return $data !== '' ? str_replace('https://preview.invalid/img', $data, $html) : $html;
}

function do_preview(): void
{
    require_login();
    csrf_check();
    header('Content-Type: text/html; charset=utf-8');
    if (($_POST['template'] ?? '') === 'custom') {
        $h = (string)($_POST['custom_html'] ?? '');
        echo strlen($h) > 600000 ? '<p>Too large</p>' : ($h !== '' ? $h : '<p style="font-family:sans-serif;padding:24px;color:#555B6A">Paste or load your HTML to see it here.</p>');
        return;
    }
    $c = lp_content_from_post();
    $c['image'] = lp_safe_image((string)($_POST['image_current'] ?? ''));
    if (!empty($_POST['remove_image'])) {
        $c['image'] = '';
    }
    $f = $_FILES['image_file'] ?? null;
    if ($f && (int)$f['error'] === UPLOAD_ERR_OK && (int)$f['size'] <= 2 * 1024 * 1024) {
        $info = @getimagesize($f['tmp_name']);
        $ok = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];
        if (defined('IMAGETYPE_WEBP')) {
            $ok[] = IMAGETYPE_WEBP;
        }
        if ($info && in_array($info[2], $ok, true)) {
            $c['image'] = 'data:' . $info['mime'] . ';base64,' . base64_encode((string)file_get_contents($f['tmp_name']));
        }
    }
    echo render_builtin_preview($c);
}

function lp_design_editor(array $c, string $template, string $customHtml): string
{
    $f = function (string $label, string $name, string $val, string $help = '', string $ph = '', string $type = 'text') {
        return '<div class="field"><label for="f_' . $name . '">' . e($label) . '</label><input id="f_' . $name . '" name="' . $name . '" type="' . $type . '" value="' . e($val) . '"'
            . ($ph !== '' ? ' placeholder="' . e($ph) . '"' : '') . ' autocomplete="off">' . ($help !== '' ? '<div class="help">' . $help . '</div>' : '') . '</div>';
    };
    $tpl = $template === 'custom' ? 'custom' : 'channel';
    $h = '<div class="split" style="margin-top:24px"><div class="a"><div class="card"><h2>Page design</h2>'
        . '<div class="seg" role="radiogroup" aria-label="Page type"><label class="segopt"><input type="radio" name="template" value="channel"' . ($tpl === 'channel' ? ' checked' : '') . '><span>Built-in design</span></label>'
        . '<label class="segopt"><input type="radio" name="template" value="custom"' . ($tpl === 'custom' ? ' checked' : '') . '><span>My own HTML</span></label></div>';

    $h .= '<div id="sec-built"' . ($tpl === 'custom' ? ' hidden' : '') . '>';
    $h .= '<div class="field"><label for="preset">Start from an example (optional)</label><select id="preset"><option value="">Choose an example text</option>';
    foreach (lp_presets() as $k => $pr) {
        $h .= '<option value="' . e($k) . '">' . e($pr[0]) . '</option>';
    }
    $h .= '</select><div class="help">Fills in the words. You can change everything after.</div></div>';
    $h .= '<label>Design</label><div class="tpls">';
    foreach (LP_LAYOUTS as $k => $meta) {
        $h .= '<label class="tplc"><input type="radio" name="layout" value="' . e($k) . '"' . ($c['layout'] === $k ? ' checked' : '') . '><span class="tplb">'
            . '<span class="thumb"><iframe src="/app/tpl?l=' . e($k) . '" loading="lazy" tabindex="-1" title="" aria-hidden="true"></iframe></span>'
            . '<span class="tn">' . e($meta[0]) . '</span><span class="td">' . e($meta[1]) . '</span></span></label>';
    }
    $h .= '</div><div style="height:20px"></div>'
        . $f('Headline', 'headline', (string)$c['headline']) . $f('Sub text', 'sub', (string)$c['sub'])
        . $f('Small badge (optional)', 'badge', (string)$c['badge'], '', 'Free to join')
        . '<div class="field"><label for="f_bullets">What they get (one per line, up to 6)</label><textarea id="f_bullets" name="bullets" style="min-height:120px">' . e((string)$c['bullets']) . '</textarea></div>'
        . $f('Button text', 'button', (string)$c['button'])
        . $f('Telegram link', 'join_link', (string)$c['join_link'], 'Your normal t.me link. Each visitor gets a personal one-time link instead.', 'https://t.me/yourchannel', 'url')
        . $f('Line under the button (optional)', 'note', (string)$c['note'], '', 'Free. No spam.')
        . $f('Members line (optional)', 'members', (string)$c['members'], 'Only write a number that is true.', '12,000+ members')
        . '<div class="field"><label for="f_accent">Main colour</label><input id="f_accent" name="accent" type="color" value="' . e(valid_hex_color((string)$c['accent'])) . '" style="width:84px;min-height:48px;padding:4px"></div>'
        . '<div class="field"><label for="f_image_file">Picture or logo (optional)</label><input id="f_image_file" name="image_file" type="file" accept="image/jpeg,image/png,image/webp,image/gif">'
        . '<div class="help">JPG, PNG or WebP under 2 MB. Used as the picture, or as the round logo in "Channel profile".</div>';
    if ($c['image'] !== '') {
        $h .= '<div style="margin-top:10px;display:flex;align-items:center;gap:12px"><img src="' . e($c['image']) . '" alt="" style="height:56px;border-radius:8px;border:1px solid #E4E0D6"> <label style="margin:0;font-weight:500"><input type="checkbox" name="remove_image" value="1"> Remove picture</label></div>';
    }
    $h .= '<input type="hidden" name="image_current" value="' . e((string)$c['image']) . '"></div>'
        . $f('Small print at the bottom (optional)', 'footer', (string)$c['footer'], 'Good place for a disclaimer.')
        . '</div>';

    $h .= '<div id="sec-custom"' . ($tpl === 'channel' ? ' hidden' : '') . '>'
        . '<div class="field"><label for="htmlfile">Load an .html file</label><input id="htmlfile" type="file" accept=".html,.htm,text/html"><div class="help">Or paste the code below. One single file works best.</div></div>'
        . '<div class="field"><label for="f_custom_html">Your HTML</label><textarea id="f_custom_html" name="custom_html" class="mono" style="min-height:260px" placeholder="&lt;!doctype html&gt;...">' . e($customHtml) . '</textarea></div>'
        . '<div class="flash info" style="font-size:14px;font-weight:400"><strong>For it to work well:</strong><br>'
        . '1. Link your button to your t.me address, or add <span class="mono">data-lp-join</span> to any button. We swap in a personal invite link.<br>'
        . '2. Images and fonts must be online with full <span class="mono">https://</span> addresses. Files on your computer will not load.<br>'
        . '3. Remove your own Pixel code. We add the Pixel you saved above and remove any other one.</div>'
        . '</div></div></div>';

    $h .= '<div class="b"><div class="card pvcard"><h2>Preview</h2><div class="phone"><iframe id="pv" title="Page preview" sandbox="" referrerpolicy="no-referrer"></iframe></div>'
        . '<p class="muted" style="font-size:13px;margin:10px 0 0">Shows how it looks on a phone. Save to publish changes.</p></div></div></div>';

    $presets = [];
    foreach (lp_presets() as $k => $pr) {
        $presets[$k] = $pr[1];
    }
    $h .= '<script>(function(){var form=document.getElementById("pageform"),pv=document.getElementById("pv"),t=null;'
        . 'var PRESETS=' . json_encode($presets, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';'
        . 'function mode(){var m=form.querySelector("input[name=template]:checked").value;document.getElementById("sec-built").hidden=m!=="channel";document.getElementById("sec-custom").hidden=m!=="custom";}'
        . 'function render(){var fd=new FormData(form);fetch("/app/preview",{method:"POST",body:fd,credentials:"same-origin"}).then(function(r){return r.text()}).then(function(h){pv.srcdoc=h}).catch(function(){});}'
        . 'function later(){clearTimeout(t);t=setTimeout(render,350);}'
        . 'form.addEventListener("input",function(e){if(e.target.id==="preset")return;later()});'
        . 'form.addEventListener("change",function(e){if(e.target.name==="template")mode();if(e.target.id!=="preset")later()});'
        . 'document.getElementById("preset").addEventListener("change",function(){var p=PRESETS[this.value];if(!p)return;for(var k in p){var el=document.getElementById("f_"+k);if(el)el.value=p[k]}later()});'
        . 'document.getElementById("htmlfile").addEventListener("change",function(){var f=this.files[0];if(!f)return;if(f.size>600000){alert("File is over 600 KB");return}var r=new FileReader();r.onload=function(){document.getElementById("f_custom_html").value=r.result;later()};r.readAsText(f)});'
        . 'mode();render();})();</script>';
    return $h;
}
