<?php
declare(strict_types=1);

function public_shell(string $title, string $inner, bool $narrow = true): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . ' · JoinPath</title><meta name="robots" content="noindex">'
        . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;700&display=swap">'
        . '<link rel="stylesheet" href="/assets/app.css?v=2"></head><body><div style="max-width:' . ($narrow ? '720' : '960') . 'px;margin:0 auto;padding:32px 20px 56px">'
        . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:36px;gap:12px;flex-wrap:wrap"><a class="h" href="/" style="font-size:22px">JoinPath</a>'
        . '<nav style="display:flex;gap:18px;font-size:14px" class="muted"><a href="/terms">Terms</a><a href="/privacy">Privacy</a><a href="/refund">Refunds</a><a href="/report">Report abuse</a></nav></div>'
        . $inner . '</div></body></html>';
}

function public_footer_links(): string
{
    return '<p class="muted" style="font-size:13px;text-align:center;margin-top:20px"><a href="/terms" style="text-decoration:underline">Terms</a> · <a href="/privacy" style="text-decoration:underline">Privacy</a> · <a href="/refund" style="text-decoration:underline">Refunds</a> · <a href="/report" style="text-decoration:underline">Report abuse</a></p>';
}

function page_legal(string $key): void
{
    $title = lp_legal_title($key);
    public_shell($title, '<h1 style="font-size:34px;margin-bottom:8px">' . e($title) . '</h1><div class="legal" style="line-height:1.65">' . lp_legal_body($key) . '</div>'
        . '<style>.legal h2{font-size:18px;margin:28px 0 8px}.legal p,.legal li{font-size:15.5px}.legal a{text-decoration:underline}</style>');
}

function page_terms_doc(): void { page_legal('terms'); }
function page_privacy_doc(): void { page_legal('privacy'); }
function page_refund_doc(): void { page_legal('refund'); }

/* ---------- report abuse ---------- */
function report_form(string $slugPrefill = '', string $error = '', array $old = []): void
{
    session_boot();
    $reasons = '';
    foreach (LP_REPORT_REASONS as $k => $l) {
        $reasons .= '<option value="' . e($k) . '"' . (($old['reason'] ?? '') === $k ? ' selected' : '') . '>' . e($l) . '</option>';
    }
    $domain = (string)cfg('pages_domain', 'joinpath.in');
    $val = $old['page'] ?? ($slugPrefill !== '' ? $slugPrefill . '.' . $domain : '');
    public_shell('Report abuse', '<h1 style="font-size:32px;margin-bottom:8px">Report a page</h1>'
        . '<p class="muted" style="margin:0 0 24px">If a page hosted on JoinPath is misleading, a scam, or breaks the law, tell us. We review every report.</p>'
        . ($error !== '' ? '<div class="flash bad" role="alert" style="margin-bottom:20px">' . e($error) . '</div>' : '')
        . '<form method="post" action="/report" class="card">' . csrf_field()
        . '<div class="field"><label for="r_page">Page address</label><input id="r_page" name="page" type="text" value="' . e($val) . '" placeholder="name.' . e($domain) . '" required autocomplete="off"></div>'
        . '<div class="field"><label for="r_reason">What is the problem?</label><select id="r_reason" name="reason" required><option value="">Choose one</option>' . $reasons . '</select></div>'
        . '<div class="field"><label for="r_details">Details</label><textarea id="r_details" name="details" required maxlength="2000" placeholder="What did you see? What was promised?">' . e($old['details'] ?? '') . '</textarea></div>'
        . '<div class="field"><label for="r_email">Your email (optional)</label><input id="r_email" name="email" type="email" value="' . e($old['email'] ?? '') . '" autocomplete="email"><div class="help">Only if you want us to reply. We do not share it with the page owner.</div></div>'
        . '<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Leave this empty <input name="website" tabindex="-1" autocomplete="off"></label></div>'
        . '<input type="hidden" name="ts" value="' . time() . '"><button class="btn" type="submit">Send report</button></form>', true);
}

function page_report(): void
{
    session_boot();
    $p = strtolower(trim((string)($_GET['p'] ?? '')));
    report_form(preg_match('/^[a-z0-9-]{2,40}$/', $p) ? $p : '');
}

function do_report(): void
{
    session_boot();
    csrf_check();
    $old = ['page' => trim((string)($_POST['page'] ?? '')), 'reason' => (string)($_POST['reason'] ?? ''), 'details' => trim((string)($_POST['details'] ?? '')), 'email' => trim((string)($_POST['email'] ?? ''))];
    if (!empty($_POST['website']) || time() - (int)($_POST['ts'] ?? 0) < 2) {
        public_shell('Report sent', '<h1 style="font-size:30px">Thank you</h1><p>We received your report.</p>');
        return;
    }
    if (!rate_hit('report:' . client_ip(), 5, 3600)) {
        report_form('', 'Too many reports from your network. Try again in an hour.', $old);
        return;
    }
    if ($old['page'] === '' || !isset(LP_REPORT_REASONS[$old['reason']]) || strlen($old['details']) < 10) {
        report_form('', 'Please give the page address, choose a reason and write at least a short description.', $old);
        return;
    }
    if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        report_form('', 'That email address does not look right.', $old);
        return;
    }
    $domain = preg_quote((string)cfg('pages_domain', 'joinpath.in'), '/');
    $slug = '';
    $pageId = null;
    if (preg_match('/([a-z0-9][a-z0-9-]{1,38})\.' . $domain . '/i', $old['page'], $m)) {
        $slug = strtolower($m[1]);
        $pg = db_one('SELECT id FROM pages WHERE slug=?', [$slug]);
        $pageId = $pg ? (int)$pg['id'] : null;
    }
    $id = db_insert('abuse_reports', [
        'page_id' => $pageId, 'slug' => clip($slug !== '' ? $slug : $old['page'], 80), 'reason' => $old['reason'],
        'details' => clip($old['details'], 2000), 'reporter_email' => clip($old['email'], 190), 'ip' => clip(client_ip(), 64),
        'status' => 'open', 'admin_note' => '', 'created_at' => now_utc(),
    ]);
    lp_mail(support_email(), 'Abuse report #' . $id . ': ' . ($slug ?: 'unknown page'),
        "A page was reported on JoinPath.\n\nPage: " . $old['page'] . "\nReason: " . LP_REPORT_REASONS[$old['reason']] . "\nFrom: " . ($old['email'] ?: 'not given')
        . "\n\n" . $old['details'] . "\n\nReview it: " . rtrim((string)cfg('app_url'), '/') . '/app/admin/reports', $old['email']);
    public_shell('Report sent', '<h1 style="font-size:30px;margin-bottom:8px">Thank you</h1><p>We received your report and will review it. If you gave an email, we may write to you.</p><p><a class="btn ghost" href="/">Back</a></p>');
}

/* ---------- terms acceptance for customers ---------- */
function page_app_terms(): void
{
    $u = require_login();
    if ($u['terms_accepted_at'] || $u['is_admin']) {
        redirect('/app');
    }
    public_shell('Accept terms', '<h1 style="font-size:30px;margin-bottom:8px">Before you start</h1><p class="muted">Please read and accept our terms to use your account.</p>'
        . '<div class="card" style="margin:20px 0"><p style="margin:0 0 12px"><strong>In short:</strong></p><ul style="margin:0 0 12px;padding-left:20px;line-height:1.7">'
        . '<li>You are responsible for your pages, ads and channel, and for following the law and the rules of Meta and Telegram.</li>'
        . '<li>No guaranteed returns, scams, betting, adult content or anything illegal.</li>'
        . '<li>We can pause a page or account that breaks the rules.</li>'
        . '<li>Refunds follow the <a href="/refund" style="text-decoration:underline">Refund Policy</a>.</li></ul>'
        . '<p style="margin:0"><a href="/terms" target="_blank" rel="noopener" style="text-decoration:underline">Read the full Terms</a> · <a href="/privacy" target="_blank" rel="noopener" style="text-decoration:underline">Privacy Policy</a></p></div>'
        . '<form method="post" action="/app/terms">' . csrf_field()
        . '<label style="display:flex;gap:12px;align-items:flex-start;font-weight:500;margin-bottom:20px"><input type="checkbox" name="agree" value="1" required style="width:22px;height:22px;margin-top:2px"> <span>I have read and I accept the Terms of Service and Privacy Policy.</span></label>'
        . '<button class="btn" type="submit">Accept and continue</button></form>');
}

function do_app_terms(): void
{
    $u = require_login();
    csrf_check();
    if (empty($_POST['agree'])) {
        redirect('/app/terms');
    }
    db_update('users', (int)$u['id'], ['terms_accepted_at' => now_utc(), 'terms_version' => LP_TERMS_VERSION, 'terms_ip' => clip(client_ip(), 64)]);
    redirect('/app');
}


/* ---------- welcome: after the SuperProfile payment ---------- */
function welcome_form(string $error = '', array $old = []): void
{
    session_boot();
    public_shell('Welcome', '<h1 style="font-size:32px;margin-bottom:8px">Payment received. Thank you!</h1>'
        . '<p class="muted" style="margin:0 0 24px">Tell us where to create your JoinPath login. We check your payment and send your login details, usually within an hour. Renewing? Use the same email as before.</p>'
        . ($error !== '' ? '<div class="flash bad" role="alert" style="margin-bottom:20px">' . e($error) . '</div>' : '')
        . '<form method="post" action="/welcome" class="card">' . csrf_field()
        . '<div class="field"><label for="w_name">Your name</label><input id="w_name" name="name" type="text" value="' . e($old['name'] ?? '') . '" required maxlength="110" autocomplete="name"></div>'
        . '<div class="field"><label for="w_email">Email for your login</label><input id="w_email" name="email" type="email" value="' . e($old['email'] ?? '') . '" required autocomplete="email"></div>'
        . '<div class="field"><label for="w_biz">Business or channel name</label><input id="w_biz" name="business" type="text" value="' . e($old['business'] ?? '') . '" maxlength="150"></div>'
        . '<div class="field"><label for="w_ref">Phone number or email used for the payment</label><input id="w_ref" name="pay_ref" type="text" value="' . e($old['pay_ref'] ?? '') . '" required maxlength="190"><div class="help">So we can match your payment quickly.</div></div>'
        . '<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Leave this empty <input name="website" tabindex="-1" autocomplete="off"></label></div>'
        . '<input type="hidden" name="ts" value="' . time() . '"><button class="btn" type="submit">Send</button></form>'
        . '<p class="muted" style="font-size:13px;margin-top:16px">Questions? <a href="mailto:' . e(support_email()) . '" style="text-decoration:underline">' . e(support_email()) . '</a></p>', true);
}

function page_welcome(): void
{
    welcome_form();
}

function do_welcome(): void
{
    session_boot();
    csrf_check();
    $old = ['name' => trim((string)($_POST['name'] ?? '')), 'email' => strtolower(trim((string)($_POST['email'] ?? ''))),
        'business' => trim((string)($_POST['business'] ?? '')), 'pay_ref' => trim((string)($_POST['pay_ref'] ?? ''))];
    if (!empty($_POST['website']) || time() - (int)($_POST['ts'] ?? 0) < 2) {
        public_shell('Received', '<h1 style="font-size:30px">Thank you</h1><p>We received your details.</p>');
        return;
    }
    if (!rate_hit('welcome:' . client_ip(), 5, 3600)) {
        welcome_form('Too many tries from your network. Please try again in an hour.', $old);
        return;
    }
    if ($old['name'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL) || $old['pay_ref'] === '') {
        welcome_form('Please fill your name, a valid email and the phone or email used for the payment.', $old);
        return;
    }
    $dup = db_one("SELECT id FROM signup_requests WHERE email=? AND status='pending'", [$old['email']]);
    if (!$dup) {
        $id = db_insert('signup_requests', ['name' => clip($old['name'], 110), 'email' => clip($old['email'], 190), 'business' => clip($old['business'], 150),
            'pay_ref' => clip($old['pay_ref'], 190), 'ip' => clip(client_ip(), 64), 'status' => 'pending', 'created_at' => now_utc()]);
        lp_mail(support_email(), 'New JoinPath sign-up request #' . $id,
            "Someone filled the welcome form after paying.\n\nName: " . $old['name'] . "\nEmail: " . $old['email'] . "\nBusiness: " . $old['business'] . "\nPayment ref: " . $old['pay_ref']
            . "\n\nCheck the payment on SuperProfile, then approve: " . rtrim((string)cfg('app_url'), '/') . '/app/admin/requests');
    }
    public_shell('Received', '<h1 style="font-size:30px;margin-bottom:8px">Got it, thank you!</h1><p>We will check your payment and send your login details to <strong>' . e($old['email']) . '</strong>, usually within an hour.</p><p class="muted">If you do not hear from us, write to <a href="mailto:' . e(support_email()) . '" style="text-decoration:underline">' . e(support_email()) . '</a>.</p>');
}
