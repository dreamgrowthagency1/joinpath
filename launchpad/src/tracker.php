<?php
declare(strict_types=1);

/* ============================================================
 * Client pages (served from *.joinpath.in)
 * ============================================================ */

function lp_is_bot(string $ua): bool
{
    return $ua === '' || (bool)preg_match('/bot|crawl|spider|slurp|facebookexternalhit|facebookcatalog|preview|monitor|uptime|headless|JoinPath/i', $ua);
}

function page_by_slug(string $slug): ?array
{
    if (!preg_match('/^[a-z0-9][a-z0-9-]{1,38}$/', $slug)) {
        return null;
    }
    return db_one('SELECT p.*, a.status AS account_status FROM pages p JOIN accounts a ON a.id=p.account_id WHERE p.slug=?', [$slug]);
}

function valid_pixel_id(string $id): bool
{
    return (bool)preg_match('/^\d{8,20}$/', $id);
}

function valid_hex_color(string $c, string $fallback = '#0B6B5C'): string
{
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $c) ? $c : $fallback;
}

function page_content(array $page): array
{
    $c = json_decode((string)($page['content_json'] ?? ''), true);
    $c = is_array($c) ? $c : [];
    return $c + lp_defaults();
}

function safe_join_link(string $l): string
{
    $l = trim($l);
    return preg_match('~^https://(t\.me|telegram\.me)/[A-Za-z0-9_+/-]{3,100}$~', $l) ? $l : '';
}

function render_page_html(array $page): string
{
    if ($page['template'] === 'custom') {
        return (string)$page['custom_html'];
    }
    return render_builtin(page_content($page));
}

/** Remove Meta Pixel code that a client pasted into their own HTML, so only the page's configured Pixel fires. */
function strip_existing_pixel(string $html): string
{
    $html = preg_replace_callback('~<script\b[^>]*>.*?</script>~is', function ($m) {
        return (stripos($m[0], 'fbevents.js') !== false || preg_match('~fbq\s*\(~i', $m[0])) ? '' : $m[0];
    }, $html) ?? $html;
    $html = preg_replace_callback('~<noscript\b[^>]*>.*?</noscript>~is', function ($m) {
        return stripos($m[0], 'facebook.com/tr') !== false ? '' : $m[0];
    }, $html) ?? $html;
    return $html;
}

function lp_pixel_snippet(string $pixelId): string
{
    $id = $pixelId;
    return "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};"
        . "if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;"
        . "s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');"
        . "fbq('init','{$id}');fbq('track','PageView');</script>"
        . "<noscript><img height=\"1\" width=\"1\" style=\"display:none\" src=\"https://www.facebook.com/tr?id={$id}&ev=PageView&noscript=1\"></noscript>";
}

function html_insert(string $html, string $tag, string $insert, bool $after): string
{
    if (preg_match('~' . $tag . '~i', $html, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1] + ($after ? strlen($m[0][0]) : 0);
        return substr($html, 0, $pos) . $insert . substr($html, $pos);
    }
    return '';
}

function inject_tracking(string $html, array $page): string
{
    $snippet = '';
    if (valid_pixel_id((string)$page['pixel_id'])) {
        if ($page['template'] === 'custom') {
            $html = strip_existing_pixel($html);
        }
        $snippet .= lp_pixel_snippet((string)$page['pixel_id']);
    }
    $snippet .= '<script src="/_lp/lp.js" defer></script>';
    $report = '<a href="' . e(rtrim((string)cfg('app_url', ''), '/') . '/report?p=' . $page['slug']) . '" rel="noopener" style="position:fixed;right:8px;bottom:6px;z-index:2147483000;'
        . 'font:11px/1.2 system-ui,sans-serif;color:#6b7280;background:rgba(255,255,255,.88);padding:4px 8px;border-radius:6px;text-decoration:none">Report abuse</a>';
    $withReport = html_insert($html, '</body>', $report, false);
    $html = $withReport !== '' ? $withReport : $html . $report;
    $out = html_insert($html, '</head>', $snippet, false);
    if ($out !== '') {
        return $out;
    }
    $out = html_insert($html, '<body[^>]*>', $snippet, true);
    return $out !== '' ? $out : $snippet . $html;
}

/** Create or update the visitor row for this page view. Returns the row or null (bots). */
function visitor_touch(array $page): ?array
{
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (lp_is_bot($ua)) {
        return null;
    }
    $vid = (string)($_COOKIE['lp_vid'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $vid)) {
        $vid = random_hex(16);
    }
    $now = now_utc();
    $q = $_GET;
    $fbclid = clip((string)($q['fbclid'] ?? ''), 250);
    if (!preg_match('/^[A-Za-z0-9_\-\.]*$/', $fbclid)) {
        $fbclid = '';
    }
    $fbp = (string)($_COOKIE['_fbp'] ?? '');
    $fbc = (string)($_COOKIE['_fbc'] ?? '');
    $fbp = preg_match('/^fb\.\d\.\d{10,13}\.\d{5,20}$/', $fbp) ? $fbp : '';
    $fbc = preg_match('/^fb\.\d\.\d{10,13}\.[A-Za-z0-9_\-\.]{5,300}$/', $fbc) ? $fbc : '';
    $utm = static function (string $k) use ($q): string {
        return clip(preg_replace('/[\x00-\x1F]/', '', (string)($q[$k] ?? '')), 180);
    };
    $scheme = 'https';
    $landing = clip($scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'), 580);
    $landing = preg_replace('/fbclid=[^&]*&?/', '', $landing);
    $landing = rtrim((string)$landing, '?&');

    $row = db_one('SELECT * FROM visitors WHERE page_id=? AND vid=?', [$page['id'], $vid]);
    if (!$row) {
        $id = db_insert('visitors', [
            'page_id' => $page['id'], 'vid' => $vid, 'fbclid' => $fbclid, 'fbc' => $fbc, 'fbp' => $fbp,
            'ip' => client_ip(), 'ua' => clip($ua, 390),
            'utm_source' => $utm('utm_source'), 'utm_medium' => $utm('utm_medium'), 'utm_campaign' => $utm('utm_campaign'),
            'utm_content' => $utm('utm_content'), 'utm_term' => $utm('utm_term'),
            'referrer' => clip((string)($_SERVER['HTTP_REFERER'] ?? ''), 390), 'landing_url' => $landing,
            'first_seen' => $now, 'last_seen' => $now,
        ]);
        $row = db_one('SELECT * FROM visitors WHERE id=?', [$id]);
    } else {
        $upd = ['last_seen' => $now, 'ip' => client_ip() ?: $row['ip'], 'ua' => clip($ua, 390)];
        if ($fbp !== '') {
            $upd['fbp'] = $fbp;
        }
        if ($fbclid !== '' && $fbclid !== $row['fbclid']) {
            // a fresh ad click: latest click wins
            $upd += ['fbclid' => $fbclid, 'fbc' => $fbc, 'landing_url' => $landing,
                'utm_source' => $utm('utm_source'), 'utm_medium' => $utm('utm_medium'), 'utm_campaign' => $utm('utm_campaign'),
                'utm_content' => $utm('utm_content'), 'utm_term' => $utm('utm_term')];
        } elseif ($fbc !== '') {
            $upd['fbc'] = $fbc;
        }
        db_update('visitors', (int)$row['id'], $upd);
        $row = array_merge($row, $upd);
    }
    setcookie('lp_vid', $vid, [
        'expires' => time() + 90 * 86400, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
    return $row;
}

function lp_visitor_from_cookie(array $page): ?array
{
    $vid = (string)($_COOKIE['lp_vid'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $vid)) {
        return null;
    }
    return db_one('SELECT * FROM visitors WHERE page_id=? AND vid=?', [$page['id'], $vid]);
}

function lp_sync(array $page): void
{
    $v = lp_visitor_from_cookie($page);
    if (!$v) {
        json_out(['ok' => false]);
    }
    $b = request_json();
    $upd = lp_sanitize_fb($b);
    if ($upd) {
        db_update('visitors', (int)$v['id'], $upd);
    }
    json_out(['ok' => true]);
}

function lp_sanitize_fb(array $b): array
{
    $u = [];
    $fbp = (string)($b['fbp'] ?? '');
    $fbc = (string)($b['fbc'] ?? '');
    if (preg_match('/^fb\.\d\.\d{10,13}\.\d{5,20}$/', $fbp)) {
        $u['fbp'] = $fbp;
    }
    if (preg_match('/^fb\.\d\.\d{10,13}\.[A-Za-z0-9_\-\.]{5,300}$/', $fbc)) {
        $u['fbc'] = $fbc;
    }
    return $u;
}

function lp_go(array $page): void
{
    $v = lp_visitor_from_cookie($page);
    if (!$v) {
        json_out(['ok' => false, 'error' => 'no_visitor'], 200);
    }
    $upd = lp_sanitize_fb(request_json());
    if ($upd) {
        db_update('visitors', (int)$v['id'], $upd);
    }
    if ($page['status'] !== 'live' || !$page['chat_id']) {
        json_out(['ok' => false, 'error' => 'not_ready'], 200);
    }
    if (!rate_hit('go:' . $page['id'] . ':' . client_ip(), 40, 60) || !rate_hit('gov:' . $v['id'], 8, 3600)) {
        json_out(['ok' => false, 'error' => 'rate'], 200);
    }
    $reuse = db_one(
        'SELECT link FROM invite_links WHERE visitor_id=? AND used_at IS NULL AND expires_at>? ORDER BY id DESC LIMIT 1',
        [$v['id'], utc_plus(1800)]
    );
    if ($reuse) {
        json_out(['ok' => true, 'link' => $reuse['link']]);
    }
    $token = page_bot_token($page);
    if ($token === '') {
        json_out(['ok' => false, 'error' => 'no_bot'], 200);
    }
    $r = tg_create_invite($token, (int)$page['chat_id'], 'lp-' . substr((string)$v['vid'], 0, 8), 86400);
    $link = is_array($r['result'] ?? null) ? (string)($r['result']['invite_link'] ?? '') : '';
    if (!$r['ok'] || $link === '') {
        error_log('createChatInviteLink failed for page ' . $page['id'] . ': ' . $r['error']);
        json_out(['ok' => false, 'error' => 'telegram'], 200);
    }
    db_insert('invite_links', [
        'page_id' => $page['id'], 'visitor_id' => $v['id'], 'link' => clip($link, 190),
        'created_at' => now_utc(), 'expires_at' => utc_plus(86400),
    ]);
    json_out(['ok' => true, 'link' => $link]);
}

function lp_js(): string
{
    return <<<'JS'
(function () {
  function cookie(n) {
    var m = document.cookie.match(new RegExp('(?:^|; )' + n + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : '';
  }
  function payload() { return JSON.stringify({ fbp: cookie('_fbp'), fbc: cookie('_fbc') }); }
  function post(url) {
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: payload(), keepalive: true });
  }
  setTimeout(function () { post('/_lp/v').catch(function () {}); }, 1800);

  var busy = false;
  var SEL = 'a[href*="t.me/"],a[href*="telegram.me/"],a[href*="telegram.dog/"],a[href^="tg:"],[data-lp-join],[onclick*="t.me/"],[onclick*="telegram.me/"]';
  function origin(el) {
    var h = el.getAttribute('href');
    if (h && h !== '#' && h.indexOf('javascript:') !== 0) return h;
    var oc = el.getAttribute('onclick') || '';
    var m = oc.match(/https?:\/\/(?:t|telegram)\.me\/[A-Za-z0-9_+\/\-]+/);
    return m ? m[0] : (el.getAttribute('data-lp-join') || '');
  }
  document.addEventListener('click', function (ev) {
    var t = ev.target;
    var a = t && t.closest ? t.closest(SEL) : null;
    if (!a) return;
    var original = origin(a);
    var hasOrig = original && original.charAt(0) !== '#';
    if (!hasOrig && !a.hasAttribute('data-lp-join')) return;
    ev.preventDefault();
    ev.stopPropagation();
    if (busy) return;
    busy = true;
    try { if (window.fbq) window.fbq('trackCustom', 'JoinClick'); } catch (e) {}
    var done = false;
    function go(url) { if (done) return; done = true; busy = false; if (url && url.charAt(0) !== '#') window.location.href = url; }
    var timer = setTimeout(function () { go(original); }, 4500);
    post('/_lp/go').then(function (r) { return r.json(); }).then(function (d) {
      clearTimeout(timer);
      go(d && d.ok && d.link ? d.link : original);
    }).catch(function () { clearTimeout(timer); go(original); });
  }, true);
})();
JS;
}

function lp_pages_main(): void
{
    if (!lp_installed()) {
        http_response_code(503);
        echo 'Not set up yet.';
        return;
    }
    $host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    $base = (string)cfg('pages_domain', 'joinpath.in');
    if ($host === $base || $host === 'www.' . $base) {
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo lp_home_html();
        return;
    }
    $suffix = '.' . $base;
    if (substr($host, -strlen($suffix)) !== $suffix) {
        http_response_code(404);
        echo 'Not found';
        return;
    }
    $slug = substr($host, 0, -strlen($suffix));
    $page = page_by_slug($slug);
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($path === '/_lp/lp.js') {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo lp_js();
        return;
    }
    if (!$page) {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><title>Not found</title><p style="font-family:sans-serif;padding:40px">This page does not exist.</p>';
        return;
    }
    if (($page['account_status'] ?? 'active') === 'suspended') {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><title>Not available</title><p style="font-family:sans-serif;padding:40px">This page is not available right now.</p>';
        return;
    }
    if ($path === '/_lp/v' && $method === 'POST') {
        lp_sync($page);
        return;
    }
    if ($path === '/_lp/go' && $method === 'POST') {
        lp_go($page);
        return;
    }
    if ($method !== 'GET' && $method !== 'HEAD') {
        http_response_code(405);
        return;
    }
    if ($page['status'] !== 'live') {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><title>Not available</title><p style="font-family:sans-serif;padding:40px">This page is not available right now.</p>';
        return;
    }
    $visitor = visitor_touch($page);
    $html = inject_tracking(render_page_html($page), $page);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    echo $html;
}
