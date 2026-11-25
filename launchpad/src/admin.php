<?php
declare(strict_types=1);

function require_admin(): array
{
    $u = require_login();
    if (!$u['is_admin']) {
        http_response_code(403);
        echo 'Not allowed';
        exit;
    }
    return $u;
}

function temp_password(): string
{
    return substr(strtr(base64_encode(random_bytes(12)), '+/=', 'xyz'), 0, 14);
}

function local_today(): string
{
    return to_local(now_utc(), 'Y-m-d');
}

function local_midnight_utc(): string
{
    $d = new DateTime('today', new DateTimeZone((string)cfg('timezone', 'Asia/Kolkata')));
    $d->setTimezone(new DateTimeZone('UTC'));
    return $d->format('Y-m-d H:i:s');
}

function ymd_label(string $ymd): string
{
    return date('j M', strtotime($ymd . ' 12:00:00 UTC'));
}

function plan_chip(array $a): string
{
    if (($a['status'] ?? '') === 'suspended') {
        return chip('bad', 'Suspended');
    }
    if (empty($a['paid_until'])) {
        return chip('mute', 'Not paid');
    }
    $today = local_today();
    if ($a['paid_until'] < $today) {
        return chip('bad', 'Expired ' . ymd_label($a['paid_until']));
    }
    $left = (strtotime($a['paid_until'] . ' 12:00:00 UTC') - strtotime($today . ' 12:00:00 UTC')) / 86400;
    return chip($left <= 5 ? 'warn' : 'ok', 'Paid till ' . ymd_label($a['paid_until']));
}

function admin_tabs(string $active): string
{
    $open = (int)db_val("SELECT COUNT(*) FROM abuse_reports WHERE status='open'");
    $pend = (int)db_val("SELECT COUNT(*) FROM signup_requests WHERE status='pending'");
    $tabs = ['overview' => ['Overview', '/app/admin'], 'requests' => ['Requests' . ($pend ? ' (' . $pend . ')' : ''), '/app/admin/requests'], 'pages' => ['All pages', '/app/admin/pages'], 'reports' => ['Reports' . ($open ? ' (' . $open . ')' : ''), '/app/admin/reports']];
    $h = '<div class="seg" style="margin-bottom:0;align-self:flex-start">';
    foreach ($tabs as $k => [$l, $href]) {
        $h .= '<a class="segopt' . ($k === $active ? ' on' : '') . '" href="' . $href . '"><span>' . e($l) . '</span></a>';
    }
    return $h . '</div>';
}

function client_account_ids(): array
{
    return array_map(fn($r) => (int)$r['id'], db_all('SELECT id FROM accounts WHERE id NOT IN (SELECT account_id FROM users WHERE is_admin=1)'));
}

function page_admin(): void
{
    require_admin();
    $today = local_midnight_utc();
    $d7 = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
    $cnt = fn(string $sql, array $p = []) => (int)db_val($sql, $p);

    $accts = db_all('SELECT * FROM accounts WHERE id NOT IN (SELECT account_id FROM users WHERE is_admin=1) ORDER BY id DESC');
    $todayYmd = local_today();
    $paid = $expiring = $expired = $unpaid = $susp = 0;
    foreach ($accts as $a) {
        if ($a['status'] === 'suspended') {
            $susp++;
        } elseif (empty($a['paid_until'])) {
            $unpaid++;
        } elseif ($a['paid_until'] < $todayYmd) {
            $expired++;
        } else {
            $paid++;
            if ((strtotime($a['paid_until'] . ' 12:00:00 UTC') - strtotime($todayYmd . ' 12:00:00 UTC')) / 86400 <= 5) {
                $expiring++;
            }
        }
    }
    $pagesLive = $cnt("SELECT COUNT(*) FROM pages WHERE status='live'");
    $pagesAll = $cnt('SELECT COUNT(*) FROM pages');
    $visToday = $cnt('SELECT COUNT(*) FROM visitors WHERE first_seen>=?', [$today]);
    $joinToday = $cnt('SELECT COUNT(*) FROM joins WHERE joined_at>=?', [$today]);
    $vis7 = $cnt('SELECT COUNT(*) FROM visitors WHERE first_seen>=?', [$d7]);
    $join7 = $cnt('SELECT COUNT(*) FROM joins WHERE joined_at>=?', [$d7]);
    $sent7 = $cnt("SELECT COUNT(*) FROM joins WHERE joined_at>=? AND sent_status='sent'", [$d7]);
    $openReports = $cnt("SELECT COUNT(*) FROM abuse_reports WHERE status='open'");
    $failing = $cnt("SELECT COUNT(DISTINCT page_id) FROM health_checks WHERE status='bad'");

    // chart: visits and joins per day, last 14 days
    $dv = $dj = [];
    foreach (db_all('SELECT first_seen FROM visitors WHERE first_seen>=?', [gmdate('Y-m-d H:i:s', time() - 15 * 86400)]) as $r) {
        $k = local_day($r['first_seen']);
        $dv[$k] = ($dv[$k] ?? 0) + 1;
    }
    foreach (db_all('SELECT joined_at FROM joins WHERE joined_at>=?', [gmdate('Y-m-d H:i:s', time() - 15 * 86400)]) as $r) {
        $k = local_day($r['joined_at']);
        $dj[$k] = ($dj[$k] ?? 0) + 1;
    }
    $labels = $sv = $sj = [];
    foreach (last_days(14) as $day) {
        $labels[] = day_label($day);
        $sv[] = $dv[$day] ?? 0;
        $sj[] = $dj[$day] ?? 0;
    }

    // per-client numbers
    $by = function (string $sql, array $p = []): array {
        $o = [];
        foreach (db_all($sql, $p) as $r) {
            $o[(int)$r['a']] = $r['n'];
        }
        return $o;
    };
    $pgAll = $by('SELECT account_id a, COUNT(*) n FROM pages GROUP BY account_id');
    $pgLive = $by("SELECT account_id a, COUNT(*) n FROM pages WHERE status='live' GROUP BY account_id");
    $vis = $by('SELECT p.account_id a, COUNT(*) n FROM visitors v JOIN pages p ON p.id=v.page_id WHERE v.first_seen>=? GROUP BY p.account_id', [$d7]);
    $jn = $by('SELECT p.account_id a, COUNT(*) n FROM joins j JOIN pages p ON p.id=j.page_id WHERE j.joined_at>=? GROUP BY p.account_id', [$d7]);
    $sn = $by("SELECT p.account_id a, COUNT(*) n FROM joins j JOIN pages p ON p.id=j.page_id WHERE j.joined_at>=? AND j.sent_status='sent' GROUP BY p.account_id", [$d7]);
    $bad = $by("SELECT p.account_id a, COUNT(DISTINCT p.id) n FROM health_checks h JOIN pages p ON p.id=h.page_id WHERE h.status='bad' AND p.status='live' GROUP BY p.account_id");
    $last = $by('SELECT p.account_id a, MAX(j.joined_at) n FROM joins j JOIN pages p ON p.id=j.page_id GROUP BY p.account_id');
    $lastV = $by('SELECT p.account_id a, MAX(v.first_seen) n FROM visitors v JOIN pages p ON p.id=v.page_id GROUP BY p.account_id');

    layout_start('Admin', 'admin', admin_tabs('overview'));
    if (!empty($_SESSION['new_cred'])) {
        $c = $_SESSION['new_cred'];
        unset($_SESSION['new_cred']);
        echo '<div class="flash warn"><strong>' . (!empty($c['mailed']) ? 'Login details were emailed to the client. Copy kept here as a backup, shown only once.' : 'Email could not be sent. Share these with the client yourself. The password is shown only once.') . '</strong>'
            . '<div class="secret mono" style="margin-top:12px">Login: ' . e((string)cfg('app_url')) . '/login<br>Email: ' . e($c['email']) . '<br>Password: ' . e($c['password']) . '</div></div>';
    }
    echo '<div class="grid g5">'
        . kpi_card('Clients', (string)count($accts), $paid . ' paid · ' . $unpaid . ' not paid', 'building', 'visit')
        . kpi_card('Paid', (string)$paid, $expiring . ' expiring in 5 days · ' . $expired . ' expired', 'check', 'join')
        . kpi_card('Live pages', (string)$pagesLive, $pagesAll . ' pages in total', 'pages', 'visit')
        . kpi_card('Visits today', number_format($visToday), number_format($vis7) . ' in 7 days', 'eye', 'visit')
        . kpi_card('Joins today', number_format($joinToday), number_format($join7) . ' in 7 days', 'users', 'join')
        . '</div><div class="grid g5">'
        . kpi_card('Sent to Meta (7 days)', $join7 ? round($sent7 / $join7 * 100, 1) . '%' : '–', ($join7 - $sent7) . ' not sent', 'send', 'sent')
        . kpi_card('Pages failing checks', (string)$failing, 'Open Setup health per client', 'alert', 'click')
        . kpi_card('Open reports', (string)$openReports, 'Review them in the Reports tab', 'alert', 'bad')
        . kpi_card('Suspended', (string)$susp, 'Accounts', 'x', 'bad')
        . '</div>';
    echo '<div class="card"><div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px"><h2 style="margin:0">Traffic across all clients</h2>'
        . legend_html([['Visits', 'var(--c-visit)', false], ['Telegram joins', 'var(--c-join)', false]]) . '</div>'
        . svg_line_chart($labels, [['values' => $sv, 'color' => 'var(--c-visit)'], ['values' => $sj, 'color' => 'var(--c-join)']], 'Visits and joins per day for the last 14 days', 900, 240) . '</div>';

    echo '<div class="card" style="padding-bottom:12px"><h2>Clients</h2><div class="scroll"><table style="min-width:980px"><thead><tr><th>Client</th><th>Plan</th><th class="r">Pages</th><th class="r">Visits 7d</th><th class="r">Joins 7d</th><th class="r">Sent</th><th>Checks</th><th>Last activity</th><th>Manage</th></tr></thead><tbody>';
    if (!$accts) {
        echo '<tr><td colspan="9" class="muted">No clients yet. Add the first one on the right.</td></tr>';
    }
    foreach ($accts as $a) {
        $id = (int)$a['id'];
        $users = db_all('SELECT id,email,terms_accepted_at FROM users WHERE account_id=?', [$id]);
        $ul = '';
        foreach ($users as $x) {
            $ul .= '<div style="font-size:13px;margin-top:2px" class="muted">' . e($x['email']) . ($x['terms_accepted_at'] ? '' : ' · terms pending') . '</div>';
        }
        $j = (int)($jn[$id] ?? 0);
        $s = (int)($sn[$id] ?? 0);
        $la = max((string)($last[$id] ?? ''), (string)($lastV[$id] ?? ''));
        $b = (int)($bad[$id] ?? 0);
        echo '<tr><td><div style="font-weight:600">' . e($a['name']) . '</div>' . $ul . '</td><td>' . plan_chip($a) . ($a['plan_note'] ? '<div class="muted" style="font-size:12px;margin-top:4px">' . e($a['plan_note']) . '</div>' : '') . '</td>'
            . '<td class="r">' . (int)($pgLive[$id] ?? 0) . ' / ' . (int)($pgAll[$id] ?? 0) . '</td><td class="r">' . number_format((int)($vis[$id] ?? 0)) . '</td><td class="r">' . number_format($j) . '</td>'
            . '<td class="r">' . ($j ? round($s / $j * 100) . '%' : '–') . '</td><td>' . ($b ? chip('bad', $b . ' failing') : chip('ok', 'OK')) . '</td><td>' . ($la !== '' ? e(ago($la)) : '–') . '</td><td style="min-width:260px">'
            . '<form method="post" action="/app/admin/account" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:6px">' . csrf_field() . '<input type="hidden" name="account_id" value="' . $id . '">'
            . '<input type="date" name="paid_until" value="' . e((string)$a['paid_until']) . '" aria-label="Paid until" style="width:150px;min-height:40px;padding:0 8px">'
            . '<button class="btn ghost sm" name="do" value="set" type="submit">Save</button><button class="btn ghost sm" name="do" value="extend" type="submit">+30 days</button></form>'
            . '<form method="post" action="/app/admin/account" style="display:flex;gap:6px;flex-wrap:wrap">' . csrf_field() . '<input type="hidden" name="account_id" value="' . $id . '">'
            . ($a['status'] === 'suspended'
                ? '<button class="btn ghost sm" name="do" value="resume" type="submit">Resume</button>'
                : '<button class="btn danger sm" name="do" value="suspend" type="submit">Suspend</button>')
            . '</form>';
        $resets = '';
        foreach ($users as $x) {
            $resets .= '<form method="post" action="/app/admin/reset" style="margin-top:6px">' . csrf_field() . '<input type="hidden" name="user_id" value="' . $x['id'] . '"><button class="btn ghost sm" type="submit">Reset password</button></form>';
        }
        echo $resets . '</td></tr>';
    }
    echo '</tbody></table></div></div><div class="card" style="max-width:520px"><h2>Add a client</h2><form method="post" action="/app/admin/create">' . csrf_field()
        . '<div class="field"><label for="an">Business name</label><input id="an" name="account" type="text" required></div>'
        . '<div class="field"><label for="un">Their name</label><input id="un" name="name" type="text"></div>'
        . '<div class="field"><label for="ue">Login email</label><input id="ue" name="email" type="email" required></div>'
        . '<div class="field"><label for="up">Paid until (optional)</label><input id="up" name="paid_until" type="date"></div>'
        . '<button class="btn" type="submit">Create login</button><p class="help" style="margin-top:12px">The client accepts the Terms on their first login.</p></form></div>';
    layout_end();
}

function page_admin_pages(): void
{
    require_admin();
    $d7 = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
    $vis = $jn = [];
    foreach (db_all('SELECT page_id a, COUNT(*) n FROM visitors WHERE first_seen>=? GROUP BY page_id', [$d7]) as $r) {
        $vis[(int)$r['a']] = $r['n'];
    }
    foreach (db_all('SELECT page_id a, COUNT(*) n FROM joins WHERE joined_at>=? GROUP BY page_id', [$d7]) as $r) {
        $jn[(int)$r['a']] = $r['n'];
    }
    $rows = db_all('SELECT p.*, a.name AS account_name, a.status AS account_status FROM pages p JOIN accounts a ON a.id=p.account_id ORDER BY (p.flag_note<>\'\') DESC, p.id DESC LIMIT 300');
    layout_start('All pages', 'admin', admin_tabs('pages'));
    echo '<div class="card" style="padding-bottom:12px"><div class="scroll"><table style="min-width:860px"><thead><tr><th>Page</th><th>Client</th><th>Status</th><th>Word check</th><th class="r">Visits 7d</th><th class="r">Joins 7d</th><th></th></tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="7" class="muted">No pages yet.</td></tr>';
    }
    foreach ($rows as $p) {
        $url = 'https://' . $p['slug'] . '.' . cfg('pages_domain', 'joinpath.in');
        $st = ['live' => ['ok', 'Live'], 'draft' => ['mute', 'Draft'], 'paused' => ['warn', 'Paused']][$p['status']] ?? ['mute', $p['status']];
        $id = (int)$p['id'];
        echo '<tr><td style="font-weight:600">' . e($p['name']) . '<div><a class="mono" href="' . e($url) . '" target="_blank" rel="noopener">' . e($p['slug']) . '</a></div></td><td>' . e($p['account_name']) . ($p['account_status'] === 'suspended' ? ' ' . chip('bad', 'Suspended') : '') . '</td><td>' . chip($st[0], $st[1]) . '</td>'
            . '<td>' . ($p['flag_note'] !== '' ? chip('warn', 'Check') . '<div class="muted" style="font-size:12px;margin-top:4px;max-width:240px">' . e($p['flag_note']) . '</div>' : chip('ok', 'Clean')) . '</td>'
            . '<td class="r">' . number_format((int)($vis[$id] ?? 0)) . '</td><td class="r">' . number_format((int)($jn[$id] ?? 0)) . '</td><td class="r">'
            . '<form method="post" action="/app/admin/page" style="display:flex;gap:6px;justify-content:flex-end">' . csrf_field() . '<input type="hidden" name="page_id" value="' . $id . '">'
            . ($p['status'] === 'live' ? '<button class="btn danger sm" name="do" value="pause" type="submit">Pause</button>' : '<button class="btn ghost sm" name="do" value="live" type="submit">Make live</button>')
            . '</form></td></tr>';
    }
    echo '</tbody></table></div></div><p class="muted">"Check" means the page text has words that often break Meta or SEBI rules. It is only a hint. Open the page and judge for yourself.</p>';
    layout_end();
}

function page_admin_reports(): void
{
    require_admin();
    $rows = db_all("SELECT r.*, p.status AS page_status, p.name AS page_name FROM abuse_reports r LEFT JOIN pages p ON p.id=r.page_id ORDER BY (r.status='open') DESC, r.id DESC LIMIT 200");
    layout_start('Abuse reports', 'admin', admin_tabs('reports'));
    echo '<div class="card" style="padding-bottom:12px"><div class="scroll"><table style="min-width:900px"><thead><tr><th>When</th><th>Page</th><th>Reason</th><th>Details</th><th>Status</th><th></th></tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="6" class="muted">No reports. They appear here when someone uses the Report abuse link.</td></tr>';
    }
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        $st = ['open' => ['warn', 'Open'], 'actioned' => ['ok', 'Action taken'], 'dismissed' => ['mute', 'Dismissed']][$r['status']] ?? ['mute', $r['status']];
        $pageCell = $r['page_id']
            ? '<a class="mono" href="' . e('https://' . $r['slug'] . '.' . cfg('pages_domain', 'joinpath.in')) . '" target="_blank" rel="noopener">' . e($r['slug']) . '</a><div class="muted" style="font-size:12px">' . e((string)$r['page_status']) . '</div>'
            : '<span class="mono">' . e($r['slug']) . '</span><div class="muted" style="font-size:12px">not found</div>';
        echo '<tr><td style="white-space:nowrap">' . e(to_local($r['created_at'], 'd M, H:i')) . '</td><td>' . $pageCell . '</td><td>' . e(LP_REPORT_REASONS[$r['reason']] ?? $r['reason']) . '</td>'
            . '<td style="max-width:360px"><div style="font-size:14px">' . nl2br(e(clip((string)$r['details'], 600))) . '</div>' . ($r['reporter_email'] ? '<div class="muted" style="font-size:12px;margin-top:4px">From ' . e($r['reporter_email']) . '</div>' : '') . '</td>'
            . '<td>' . chip($st[0], $st[1]) . '</td><td>';
        if ($r['status'] === 'open') {
            echo '<form method="post" action="/app/admin/report" style="display:flex;gap:6px;flex-wrap:wrap">' . csrf_field() . '<input type="hidden" name="report_id" value="' . $id . '">'
                . ($r['page_id'] ? '<button class="btn danger sm" name="do" value="pause" type="submit">Pause page</button>' : '')
                . '<button class="btn ghost sm" name="do" value="done" type="submit">Mark done</button><button class="btn ghost sm" name="do" value="dismiss" type="submit">Dismiss</button></form>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div></div>';
    layout_end();
}

function valid_ymd(string $s): bool
{
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && strtotime($s . ' 12:00:00 UTC') !== false;
}

function do_admin_create(): void
{
    require_admin();
    csrf_check();
    $acct = clip(trim((string)($_POST['account'] ?? '')), 150);
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if ($acct === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('bad', 'Enter a business name and a valid email.');
        redirect('/app/admin');
    }
    if (db_one('SELECT id FROM users WHERE email=?', [$email])) {
        flash('bad', 'That email already has a login.');
        redirect('/app/admin');
    }
    $pw = temp_password();
    $paid = trim((string)($_POST['paid_until'] ?? ''));
    $aid = db_insert('accounts', ['name' => $acct, 'status' => 'active', 'paid_until' => valid_ymd($paid) ? $paid : null, 'created_at' => now_utc()]);
    db_insert('users', ['account_id' => $aid, 'email' => $email, 'name' => clip(trim((string)($_POST['name'] ?? '')), 110),
        'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'is_admin' => 0, 'lang' => 'en', 'created_at' => now_utc()]);
    $_SESSION['new_cred'] = ['email' => $email, 'password' => $pw];
    redirect('/app/admin');
}

function do_admin_reset(): void
{
    require_admin();
    csrf_check();
    $uid = (int)($_POST['user_id'] ?? 0);
    $x = db_one('SELECT * FROM users WHERE id=?', [$uid]);
    if (!$x) {
        redirect('/app/admin');
    }
    $pw = temp_password();
    db_update('users', $uid, ['password_hash' => password_hash($pw, PASSWORD_DEFAULT)]);
    $_SESSION['new_cred'] = ['email' => $x['email'], 'password' => $pw];
    redirect('/app/admin');
}

function do_admin_account(): void
{
    $me = require_admin();
    csrf_check();
    $id = (int)($_POST['account_id'] ?? 0);
    $a = db_one('SELECT * FROM accounts WHERE id=?', [$id]);
    if (!$a || db_one('SELECT id FROM users WHERE account_id=? AND is_admin=1', [$id])) {
        flash('bad', 'Account not found.');
        redirect('/app/admin');
    }
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'set') {
        $d = trim((string)($_POST['paid_until'] ?? ''));
        db_update('accounts', $id, ['paid_until' => valid_ymd($d) ? $d : null]);
        flash('ok', 'Paid-until date saved.');
    } elseif ($do === 'extend') {
        $base = (!empty($a['paid_until']) && $a['paid_until'] >= local_today()) ? $a['paid_until'] : local_today();
        $new = gmdate('Y-m-d', strtotime($base . ' 12:00:00 UTC') + 30 * 86400);
        db_update('accounts', $id, ['paid_until' => $new]);
        flash('ok', 'Extended to ' . ymd_label($new) . '.');
    } elseif ($do === 'suspend') {
        db_update('accounts', $id, ['status' => 'suspended']);
        flash('ok', $a['name'] . ' is suspended. Their pages stop loading.');
    } elseif ($do === 'resume') {
        db_update('accounts', $id, ['status' => 'active']);
        flash('ok', $a['name'] . ' is active again.');
    }
    redirect('/app/admin');
}

function do_admin_page(): void
{
    require_admin();
    csrf_check();
    $id = (int)($_POST['page_id'] ?? 0);
    $p = db_one('SELECT * FROM pages WHERE id=?', [$id]);
    if ($p) {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'pause') {
            db_update('pages', $id, ['status' => 'paused']);
            flash('ok', 'Page paused. It stops loading right away.');
        } elseif ($do === 'live') {
            db_update('pages', $id, ['status' => 'live']);
            flash('ok', 'Page is live again.');
        }
    }
    redirect('/app/admin/pages');
}

function do_admin_report(): void
{
    require_admin();
    csrf_check();
    $id = (int)($_POST['report_id'] ?? 0);
    $r = db_one('SELECT * FROM abuse_reports WHERE id=?', [$id]);
    if ($r && $r['status'] === 'open') {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'pause' && $r['page_id']) {
            db_update('pages', (int)$r['page_id'], ['status' => 'paused']);
            db_update('abuse_reports', $id, ['status' => 'actioned', 'resolved_at' => now_utc(), 'admin_note' => 'Page paused']);
            flash('ok', 'Page paused and report closed.');
        } elseif ($do === 'done') {
            db_update('abuse_reports', $id, ['status' => 'actioned', 'resolved_at' => now_utc()]);
            flash('ok', 'Marked as done.');
        } elseif ($do === 'dismiss') {
            db_update('abuse_reports', $id, ['status' => 'dismissed', 'resolved_at' => now_utc()]);
            flash('ok', 'Report dismissed.');
        }
    }
    redirect('/app/admin/reports');
}


/* ---------- sign-up requests (after payment on SuperProfile) ---------- */
function page_admin_requests(): void
{
    require_admin();
    $rows = db_all('SELECT * FROM signup_requests ORDER BY (status=\'pending\') DESC, id DESC LIMIT 100');
    layout_start('Sign-up requests', 'admin', admin_tabs('requests'));
    echo '<div class="card" style="padding-bottom:12px"><p class="muted" style="margin:0 0 12px">Clients land here after paying on SuperProfile. <strong>Check the payment first</strong> (match the phone or email below with the order), then approve.</p><div class="scroll"><table style="min-width:860px"><thead><tr><th>When</th><th>Who</th><th>Login email</th><th>Payment reference</th><th>Status</th><th></th></tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="6" class="muted">No requests yet. They appear after someone pays and fills the welcome form.</td></tr>';
    }
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        $existing = db_one('SELECT u.id, u.account_id, a.name AS account_name, a.paid_until FROM users u JOIN accounts a ON a.id=u.account_id WHERE u.email=?', [$r['email']]);
        $st = ['pending' => ['warn', 'Pending'], 'created' => ['ok', 'Done'], 'dismissed' => ['mute', 'Dismissed']][$r['status']] ?? ['mute', $r['status']];
        echo '<tr><td style="white-space:nowrap">' . e(to_local($r['created_at'], 'd M, H:i')) . '</td><td>' . e($r['name']) . '<div class="muted" style="font-size:12px">' . e($r['business']) . '</div></td>'
            . '<td class="mono" style="font-size:13px">' . e($r['email']) . ($existing && $r['status'] === 'pending' ? '<div>' . chip('ok', 'Existing client: renewal') . '</div>' : '') . '</td>'
            . '<td style="max-width:240px;word-break:break-word">' . e($r['pay_ref']) . '</td><td>' . chip($st[0], $st[1]) . '</td><td>';
        if ($r['status'] === 'pending') {
            echo '<form method="post" action="/app/admin/request" style="display:flex;gap:6px;flex-wrap:wrap">' . csrf_field() . '<input type="hidden" name="request_id" value="' . $id . '">'
                . '<button class="btn sm" name="do" value="approve" type="submit">' . ($existing ? 'Extend +30 days' : 'Create client +30 days') . '</button>'
                . '<button class="btn ghost sm" name="do" value="dismiss" type="submit">Dismiss</button></form>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div></div>';
    layout_end();
}

function do_admin_request(): void
{
    require_admin();
    csrf_check();
    $r = db_one('SELECT * FROM signup_requests WHERE id=?', [(int)($_POST['request_id'] ?? 0)]);
    if (!$r || $r['status'] !== 'pending') {
        flash('bad', 'Request not found or already handled.');
        redirect('/app/admin/requests');
    }
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'dismiss') {
        db_update('signup_requests', (int)$r['id'], ['status' => 'dismissed', 'handled_at' => now_utc()]);
        flash('ok', 'Request dismissed.');
        redirect('/app/admin/requests');
    }
    if ($do !== 'approve') {
        redirect('/app/admin/requests');
    }
    $existing = db_one('SELECT u.account_id, a.paid_until FROM users u JOIN accounts a ON a.id=u.account_id WHERE u.email=? AND u.is_admin=0', [$r['email']]);
    if ($existing) {
        $aid = (int)$existing['account_id'];
        $base = (!empty($existing['paid_until']) && $existing['paid_until'] >= local_today()) ? $existing['paid_until'] : local_today();
        $new = gmdate('Y-m-d', strtotime($base . ' 12:00:00 UTC') + 30 * 86400);
        db_update('accounts', $aid, ['paid_until' => $new, 'status' => 'active']);
        $sent = lp_mail($r['email'], 'JoinPath renewed until ' . ymd_label($new),
            "Hi " . ($r['name'] !== '' ? $r['name'] : 'there') . ",\n\nThank you. Your JoinPath plan is renewed until " . ymd_label($new) . ".\n\nLog in: " . rtrim((string)cfg('app_url', ''), '/') . "/app\nUse your existing password.\n\nNeed help? Reply to this email or write to " . support_email() . ".\n\nJoinPath by Dream Growth Agency", support_email());
        flash('ok', 'Renewed until ' . ymd_label($new) . '. ' . ($sent ? 'Confirmation emailed to the client.' : 'Email could not be sent, please tell the client yourself.'));
    } elseif (db_one('SELECT id FROM users WHERE email=?', [$r['email']])) {
        flash('bad', 'That email belongs to an admin login. Dismiss this request.');
        redirect('/app/admin/requests');
    } else {
        $pw = temp_password();
        $paid = gmdate('Y-m-d', strtotime(local_today() . ' 12:00:00 UTC') + 30 * 86400);
        $aid = db_insert('accounts', ['name' => $r['business'] !== '' ? $r['business'] : ($r['name'] !== '' ? $r['name'] : $r['email']), 'status' => 'active', 'paid_until' => $paid, 'created_at' => now_utc()]);
        db_insert('users', ['account_id' => $aid, 'email' => $r['email'], 'name' => clip((string)$r['name'], 110),
            'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'is_admin' => 0, 'lang' => 'en', 'created_at' => now_utc()]);
        $_SESSION['new_cred'] = ['email' => $r['email'], 'password' => $pw];
        db_update('signup_requests', (int)$r['id'], ['status' => 'created', 'account_id' => $aid, 'handled_at' => now_utc()]);
        $sent = lp_mail($r['email'], 'Your JoinPath login',
            "Hi " . ($r['name'] !== '' ? $r['name'] : 'there') . ",\n\nThank you for your payment. Your JoinPath account is ready (active until " . ymd_label($paid) . ").\n\nLog in: " . rtrim((string)cfg('app_url', ''), '/') . "/app\nEmail: " . $r['email'] . "\nTemporary password: " . $pw . "\n\nPlease change your password after you log in (Account page).\n\nNeed help? Reply to this email or write to " . support_email() . ".\n\nJoinPath by Dream Growth Agency", support_email());
        $_SESSION['new_cred']['mailed'] = $sent ? 1 : 0;
        redirect('/app/admin');
    }
    db_update('signup_requests', (int)$r['id'], ['status' => 'created', 'account_id' => $aid, 'handled_at' => now_utc()]);
    redirect('/app/admin/requests');
}
