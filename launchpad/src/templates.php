<?php
declare(strict_types=1);

/* ============================================================
 * Built-in landing page layouts. Pure functions, no database.
 * ============================================================ */

const LP_LAYOUTS = [
    'classic' => ['Classic card', 'Clean card on a soft background. Works for anything.'],
    'dark' => ['Dark hero', 'Dark page with a glow. Strong for trading and tech.'],
    'split' => ['Image and text', 'Big picture on top, message below. Good with a photo.'],
    'minimal' => ['Minimal', 'Large type, no decoration. Fast and serious.'],
    'bold' => ['Bold colour', 'Full colour page with a loud headline. Good for offers.'],
    'profile' => ['Channel profile', 'Looks like a Telegram channel card. Builds trust.'],
];

function lp_hex_rgb(string $hex): array
{
    return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
}

function lp_rgb_hex(float $r, float $g, float $b): string
{
    return sprintf('#%02X%02X%02X', max(0, min(255, (int)round($r))), max(0, min(255, (int)round($g))), max(0, min(255, (int)round($b))));
}

/** Text colour that is readable on top of $hex. */
function lp_on_color(string $hex): string
{
    [$r, $g, $b] = lp_hex_rgb($hex);
    return (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255 > 0.62 ? '#14171F' : '#FFFFFF';
}

/** Mix a colour toward white ($t 0..1). */
function lp_tint(string $hex, float $t): string
{
    [$r, $g, $b] = lp_hex_rgb($hex);
    return lp_rgb_hex($r + (255 - $r) * $t, $g + (255 - $g) * $t, $b + (255 - $b) * $t);
}

/** Mix a colour toward black ($t 0..1). */
function lp_shade(string $hex, float $t): string
{
    [$r, $g, $b] = lp_hex_rgb($hex);
    return lp_rgb_hex($r * (1 - $t), $g * (1 - $t), $b * (1 - $t));
}

function lp_bullets($raw): array
{
    $lines = is_array($raw) ? $raw : preg_split('/\r\n|\r|\n/', (string)$raw);
    $out = [];
    foreach ($lines as $l) {
        $l = trim(preg_replace('/^[\-\*\x{2022}\x{2713}\s]+/u', '', (string)$l));
        if ($l !== '') {
            $out[] = clip($l, 90);
        }
        if (count($out) >= 6) {
            break;
        }
    }
    return $out;
}

function lp_safe_image(string $url): string
{
    $url = trim($url);
    return preg_match('~^https://[^\s"\'<>\\\\]{4,300}$~', $url) ? $url : '';
}

function lp_defaults(): array
{
    return [
        'layout' => 'classic',
        'headline' => 'Join our free Telegram channel',
        'sub' => 'Daily updates, explained simply. Tap the button and you are in.',
        'button' => 'Join on Telegram',
        'join_link' => '',
        'accent' => '#0B6B5C',
        'badge' => '',
        'bullets' => '',
        'note' => '',
        'members' => '',
        'image' => '',
        'footer' => '',
    ];
}

function lp_sample_content(): array
{
    return [
        'layout' => 'classic', 'headline' => 'Learn something useful every day',
        'sub' => 'A free channel with short lessons you can finish in five minutes.',
        'button' => 'Join on Telegram', 'join_link' => 'https://t.me/example', 'accent' => '#0B6B5C',
        'badge' => 'Free to join', 'bullets' => "One short lesson every morning\nSimple steps, no jargon\nLeave any time",
        'note' => 'Free. No spam.', 'members' => '12,000+ members', 'image' => '', 'footer' => '',
    ];
}

/** Starter texts people can pick before editing. */
function lp_presets(): array
{
    return [
        'education' => ['Learning and skills', [
            'headline' => 'Learn a new skill, one short lesson a day',
            'sub' => 'Free lessons, notes and practice tasks on Telegram. Made for beginners.',
            'button' => 'Join the free channel', 'badge' => 'Free to join',
            'bullets' => "Short daily lessons\nNotes you can save\nPractice tasks and answers", 'note' => 'Free. Leave any time.', 'footer' => '']],
        'trading' => ['Trading education', [
            'headline' => 'Learn how the stock market works',
            'sub' => 'Chart basics, risk management and market explainers in simple language.',
            'button' => 'Join the learning channel', 'badge' => 'Education',
            'bullets' => "Chart reading basics\nRisk management lessons\nMarket explainers after the close", 'note' => 'Free to join.',
            'footer' => 'For education only. Not investment advice. Investments in securities are subject to market risk.']],
        'jobs' => ['Jobs and careers', [
            'headline' => 'Get new job openings on your phone',
            'sub' => 'Fresh vacancies, exam dates and application links, posted every day.',
            'button' => 'Join for job alerts', 'badge' => 'Daily updates',
            'bullets' => "New openings every day\nExam and result dates\nDirect apply links", 'note' => 'Free. No spam.', 'footer' => '']],
        'deals' => ['Deals and offers', [
            'headline' => 'Best offers, shared the moment they go live',
            'sub' => 'Price drops and coupon codes picked by hand. Join before the next one.',
            'button' => 'Join for deals', 'badge' => 'Free',
            'bullets' => "Price drops as they happen\nWorking coupon codes\nOnly offers worth your time", 'note' => 'Free. Mute any time.', 'footer' => '']],
        'news' => ['News and updates', [
            'headline' => 'The day in five minutes',
            'sub' => 'A short summary of what matters, sent once a day. No noise.',
            'button' => 'Join the channel', 'badge' => 'Daily',
            'bullets' => "One summary every morning\nPlain language\nNo ads between posts", 'note' => 'Free to join.', 'footer' => '']],
    ];
}

function lp_icon_plane(int $s = 20): string
{
    return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z"/></svg>';
}

function lp_icon_people(): string
{
    return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM22 21v-2a4 4 0 00-3-3.9M16 3.1a4 4 0 010 7.8"/></svg>';
}

/** Full HTML document for a built-in layout. */
function render_builtin(array $c): string
{
    $c += lp_defaults();
    $layout = isset(LP_LAYOUTS[$c['layout']]) ? (string)$c['layout'] : 'classic';
    $a = valid_hex_color((string)$c['accent']);
    $on = lp_on_color($a);
    $tint = lp_tint($a, 0.9);
    $deep = lp_shade($a, 0.3);
    $link = safe_join_link((string)$c['join_link']);
    $img = lp_safe_image((string)$c['image']);
    $bul = lp_bullets($c['bullets']);
    $headline = (string)$c['headline'];

    $h1 = '<h1>' . e($headline) . '</h1>';
    $sub = trim((string)$c['sub']) !== '' ? '<p class="sub">' . e($c['sub']) . '</p>' : '';
    $badge = trim((string)$c['badge']) !== '' ? '<span class="badge">' . e(clip((string)$c['badge'], 40)) . '</span>' : '';
    $pts = '';
    if ($bul) {
        $pts = '<ul class="pts">';
        foreach ($bul as $b) {
            $pts .= '<li>' . e($b) . '</li>';
        }
        $pts .= '</ul>';
    }
    $btn = '<a class="btn" href="' . e($link ?: '#') . '" data-lp-join>' . lp_icon_plane() . '<span>' . e(clip((string)$c['button'], 40)) . '</span></a>';
    $note = trim((string)$c['note']) !== '' ? '<p class="note">' . e(clip((string)$c['note'], 90)) . '</p>' : '';
    $mem = trim((string)$c['members']) !== '' ? '<div class="mem">' . lp_icon_people() . '<span>' . e(clip((string)$c['members'], 40)) . '</span></div>' : '';
    $foot = trim((string)$c['footer']) !== '' ? '<p class="foot">' . e(clip((string)$c['footer'], 240)) . '</p>' : '';
    $imgTag = $img !== '' ? '<img class="hero" src="' . e($img) . '" alt="" width="640" height="400" decoding="async">' : '';

    $base = '*{box-sizing:border-box}html{-webkit-text-size-adjust:100%}'
        . 'body{margin:0;min-height:100vh;font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans Devanagari","Noto Sans",Arial,sans-serif;line-height:1.5;color:#14171F;background:#fff}'
        . 'h1{margin:0;font-weight:800;line-height:1.12;letter-spacing:-.01em}'
        . '.sub{margin:0;font-size:17px;line-height:1.55}'
        . '.btn{display:flex;align-items:center;justify-content:center;gap:10px;text-decoration:none;font-weight:700;font-size:18px;padding:16px 22px;border-radius:14px;min-height:56px;background:' . $a . ';color:' . $on . ';-webkit-tap-highlight-color:transparent}'
        . '.btn:active{transform:translateY(1px)}'
        . '.pts{list-style:none;margin:0;padding:0}.pts li{position:relative;padding-left:34px;margin:12px 0;font-size:16px;line-height:1.45}'
        . '.pts li:before{content:"\2713";position:absolute;left:0;top:0;width:23px;height:23px;border-radius:50%;background:' . $a . ';color:' . $on . ';font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center}'
        . '.badge{display:inline-block;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:6px 12px;border-radius:999px;background:' . $tint . ';color:' . $deep . '}'
        . '.mem{display:inline-flex;align-items:center;gap:8px;font-size:14px;font-weight:600}'
        . '.note{margin:12px 0 0;text-align:center;font-size:14px}.foot{margin:24px 0 0;font-size:12px;line-height:1.5;opacity:.7}'
        . '.hero{display:block;width:100%;height:auto;object-fit:cover}';

    switch ($layout) {
        case 'dark':
            $css = 'body{background:#0E1117;color:#fff;background-image:radial-gradient(700px 360px at 50% -80px,' . $a . '55,transparent 70%);background-repeat:no-repeat}'
                . '.wrap{max-width:480px;margin:0 auto;padding:36px 22px 40px}'
                . '.badge{background:' . $a . '33;color:#fff;border:1px solid ' . $a . '88}'
                . 'h1{font-size:clamp(32px,9vw,44px);margin:18px 0 14px}.sub{color:#B7BDCB}'
                . '.hero{border-radius:18px;margin:24px 0 0;aspect-ratio:8/5}'
                . '.box{margin:26px 0;padding:6px 20px;border:1px solid #232A38;border-radius:18px;background:#141922}.pts li{color:#E8EBF2}'
                . '.mem{color:#B7BDCB;margin-top:6px}.btn{box-shadow:0 12px 32px -12px ' . $a . '}.note{color:#8D95A6}.foot{color:#8D95A6}';
            $body = '<div class="wrap">' . $badge . $h1 . $sub . $imgTag . ($pts ? '<div class="box">' . $pts . '</div>' : '<div style="height:26px"></div>') . $btn . $note . ($mem ? '<div style="text-align:center;margin-top:16px">' . $mem . '</div>' : '') . $foot . '</div>';
            break;
        case 'split':
            $css = '.page{display:flex;flex-direction:column;min-height:100vh}'
                . '.vis{background:linear-gradient(135deg,' . $a . ',' . $deep . ');color:' . $on . ';display:flex;align-items:center;justify-content:center;min-height:220px;overflow:hidden}'
                . '.vis .hero{height:100%;max-height:340px}.vis svg{width:84px;height:84px;opacity:.9}'
                . '.txt{padding:28px 22px 36px;max-width:560px;width:100%;margin:0 auto}'
                . 'h1{font-size:clamp(30px,8vw,40px);margin:14px 0 12px}.sub{color:#555B6A}.pts{margin:22px 0}.mem{color:#555B6A;margin-bottom:18px;display:flex}'
                . '@media(min-width:860px){.page{flex-direction:row}.vis{flex:1 1 50%;min-height:100vh}.vis .hero{max-height:none;height:100%}.txt{flex:1 1 50%;max-width:none;padding:64px;display:flex;flex-direction:column;justify-content:center}.txt>*{max-width:520px}}';
            $vis = $img !== '' ? $imgTag : lp_icon_plane(84);
            $body = '<div class="page"><div class="vis">' . $vis . '</div><div class="txt">' . $badge . $h1 . $sub . $pts . $mem . $btn . $note . $foot . '</div></div>';
            break;
        case 'minimal':
            $css = '.wrap{max-width:600px;margin:0 auto;padding:48px 24px 48px}'
                . '.badge{background:none;color:' . $deep . ';padding:0;font-size:13px}'
                . 'h1{font-size:clamp(36px,10vw,56px);letter-spacing:-.025em;margin:14px 0 18px}.sub{color:#555B6A;font-size:19px}'
                . '.hero{border-radius:12px;margin:28px 0 0;aspect-ratio:8/5}'
                . '.pts{margin:30px 0;border-top:1px solid #E4E0D6}.pts li{margin:0;padding:15px 0 15px 34px;border-bottom:1px solid #E4E0D6}.pts li:before{top:15px}'
                . '.mem{color:#555B6A;margin:0 0 20px}.btn{border-radius:10px}.note{text-align:left}';
            $body = '<div class="wrap">' . $badge . $h1 . $sub . $imgTag . $pts . ($mem ? '<div>' . $mem . '</div>' : '') . $btn . $note . $foot . '</div>';
            break;
        case 'bold':
            $css = 'body{background:' . $a . ';color:' . $on . '}'
                . '.wrap{max-width:520px;margin:0 auto;padding:40px 22px 44px}'
                . '.badge{background:' . $on . ';color:' . $a . '}'
                . 'h1{font-size:clamp(36px,11vw,54px);text-transform:none;margin:18px 0 14px;letter-spacing:-.02em}.sub{opacity:.92}'
                . '.hero{border-radius:20px;margin:24px 0 0;aspect-ratio:8/5;border:3px solid ' . $on . '}'
                . '.box{margin:26px 0;padding:6px 20px;border-radius:18px;background:rgba(255,255,255,.14)}'
                . '.pts li:before{background:' . $on . ';color:' . $a . '}'
                . '.btn{background:' . $on . ';color:' . $a . ';box-shadow:0 14px 30px -14px rgba(0,0,0,.45)}.mem{margin-top:6px}.note{opacity:.9}';
            $body = '<div class="wrap">' . $badge . $h1 . $sub . $imgTag . ($pts ? '<div class="box">' . $pts . '</div>' : '<div style="height:26px"></div>') . $btn . $note . ($mem ? '<div style="text-align:center;margin-top:16px">' . $mem . '</div>' : '') . $foot . '</div>';
            break;
        case 'profile':
            $first = clip(trim($headline) ?: 'T', 1);
            $initial = e(function_exists('mb_strtoupper') ? mb_strtoupper($first, 'UTF-8') : strtoupper($first));
            $av = $img !== '' ? '<img class="av" src="' . e($img) . '" alt="" width="104" height="104" decoding="async">' : '<div class="av ph">' . $initial . '</div>';
            $css = 'body{background:' . lp_tint($a, 0.86) . ';padding:20px}'
                . '.card{max-width:440px;margin:24px auto;background:#fff;border-radius:24px;padding:0 24px 28px;box-shadow:0 24px 48px -28px rgba(20,23,31,.4);overflow:hidden}'
                . '.cover{height:92px;margin:0 -24px;background:linear-gradient(135deg,' . $a . ',' . $deep . ')}'
                . '.av{display:block;width:104px;height:104px;border-radius:50%;border:5px solid #fff;margin:-52px auto 0;object-fit:cover;background:#fff}'
                . '.av.ph{display:flex;align-items:center;justify-content:center;background:' . $a . ';color:' . $on . ';font-size:44px;font-weight:800}'
                . 'h1{font-size:26px;text-align:center;margin:14px 0 6px}.mem{display:flex;justify-content:center;color:#555B6A;margin-bottom:14px}'
                . '.badge{display:block;width:max-content;margin:0 auto 10px}.sub{text-align:center;color:#555B6A;font-size:16px}.pts{margin:22px 0}';
            $body = '<main class="card"><div class="cover"></div>' . $av . $h1 . ($mem ?: '') . $badge . $sub . $pts . $btn . $note . $foot . '</main>';
            break;
        default: // classic
            $css = 'body{background:#F5F3EE;display:flex;align-items:center;justify-content:center;padding:24px}'
                . '.card{background:#fff;border-radius:24px;padding:36px 26px 30px;max-width:440px;width:100%;box-shadow:0 24px 48px -28px rgba(20,23,31,.35)}'
                . 'h1{font-size:clamp(27px,7.5vw,32px);margin:14px 0 12px}.sub{color:#555B6A}.hero{border-radius:16px;margin:22px 0 0;aspect-ratio:8/5}'
                . '.pts{margin:24px 0}.mem{color:#555B6A;margin:0 0 16px;display:flex}.card{text-align:left}';
            $body = '<main class="card">' . $badge . $h1 . $sub . $imgTag . $pts . $mem . $btn . $note . $foot . '</main>';
    }

    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>' . e($headline) . '</title><style>' . $base . $css . '</style></head><body>' . $body . '</body></html>';
}
