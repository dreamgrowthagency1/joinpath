<?php
declare(strict_types=1);

/** Public marketing home page. Served on the app domain and on the pages domain root. */
function lp_home_html(): string
{
    $app = rtrim((string)cfg('app_url', ''), '/');
    $buy = (string)cfg('buy_url', 'https://superprofile.bio/vp/joinpath-1-month');
    $mail = support_email();
    $html = <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>JoinPath: count every Telegram join in Meta Ads Manager</title>
<meta name="description" content="Give each ad visitor a personal one-time Telegram invite link. JoinPath notices the join and reports it to your Meta Pixel, so your campaigns learn from real joins.">
<meta name="theme-color" content="#0D1B2A">
<meta property="og:title" content="JoinPath: count every Telegram join in Meta Ads Manager">
<meta property="og:description" content="Personal one-time invite links, landing pages and join tracking for Telegram channel ads.">
<meta property="og:type" content="website">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='9' fill='%230D1B2A'/%3E%3Cpath d='M8 21c5 0 4-10 9-10s3 6 7 6' fill='none' stroke='%232F6FEB' stroke-width='2.6' stroke-linecap='round'/%3E%3Ccircle cx='24' cy='17' r='3.2' fill='%2312C29B'/%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Instrument+Sans:wght@400;500;600&display=swap">
<style>
@property --lit{syntax:'<number>';inherits:true;initial-value:1}
:root{--paper:#F3F6FB;--white:#fff;--ink:#0D1B2A;--ink2:#16283D;--mute:#52627A;--line:#DAE2EE;--ad:#2F6FEB;--join:#0E9F7E;--joinbright:#12C29B;--key:#E8A33D;--r:14px}
*{box-sizing:border-box}
html{scroll-behavior:smooth;scroll-padding-top:84px}
body{margin:0;background:var(--paper);color:var(--ink);font-family:'Instrument Sans',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;font-size:17px;line-height:1.6;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
h1,h2,h3{font-family:'Bricolage Grotesque','Instrument Sans',system-ui,sans-serif;margin:0;line-height:1.08;letter-spacing:-.025em}
p{margin:0}
:focus-visible{outline:3px solid var(--ad);outline-offset:3px;border-radius:6px}
.wrap{width:100%;max-width:1160px;margin:0 auto;padding:0 24px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:50px;padding:0 24px;border-radius:12px;font-weight:600;font-size:16px;border:1.5px solid var(--ink);background:var(--ink);color:#fff;cursor:pointer;transition:transform .15s ease,background .15s ease}
.btn:hover{background:var(--ink2);transform:translateY(-1px)}
.btn.line{background:transparent;color:var(--ink)}
.btn.line:hover{background:#fff}
.btn.join{background:var(--join);border-color:var(--join)}
.btn.join:hover{background:#0B8A6C}
.btn.sm{min-height:42px;padding:0 18px;font-size:15px}

/* nav */
.nav{position:sticky;top:0;z-index:20;background:rgba(243,246,251,.88);backdrop-filter:saturate(1.4) blur(10px);-webkit-backdrop-filter:saturate(1.4) blur(10px);border-bottom:1px solid var(--line)}
.nav .wrap{display:flex;align-items:center;justify-content:space-between;gap:16px;min-height:68px}
.brand{display:flex;align-items:center;gap:10px;font-family:'Bricolage Grotesque',sans-serif;font-weight:800;font-size:22px;letter-spacing:-.03em}
.nav ul{display:flex;gap:28px;list-style:none;margin:0;padding:0;font-weight:500;font-size:15px;color:var(--mute)}
.nav ul a:hover{color:var(--ink)}
.nav .act{display:flex;gap:10px;align-items:center}
.nav .login{font-weight:600;font-size:15px;padding:10px 12px}

/* hero */
.hero{padding:72px 0 88px}
.hero .wrap{display:grid;grid-template-columns:minmax(0,1.08fr) minmax(0,.92fr);gap:56px;align-items:center}
.hero h1{font-size:clamp(42px,6.2vw,76px);font-weight:800;letter-spacing:-.04em;line-height:1}
.hero .lede{font-size:clamp(18px,2vw,21px);color:var(--mute);max-width:34em;margin:26px 0 34px;line-height:1.55}
.hero .cta{display:flex;gap:12px;flex-wrap:wrap}
.facts{display:flex;gap:10px 26px;flex-wrap:wrap;margin-top:34px;padding:0;list-style:none;color:var(--mute);font-size:15px}
.facts li{display:flex;align-items:center;gap:8px}
.facts svg{flex:none}

/* trace card: the one animated moment */
.trace{background:var(--ink);color:#E8EEF7;border-radius:22px;padding:26px 26px 22px;box-shadow:0 30px 60px -28px rgba(13,27,42,.55)}
.trace .top{display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:14px;color:#9BB0CC;margin-bottom:22px}
.trace .tag{font-size:12.5px;padding:4px 10px;border-radius:999px;background:#1D3350;color:#B8CBE4;font-weight:600}
.stepwrap{position:relative}
.steps{list-style:none;margin:0;padding:0}
.stepwrap::before{content:"";position:absolute;left:15px;top:16px;bottom:16px;width:2px;background:#27405F;border-radius:2px}
.rail{position:absolute;left:15px;top:16px;bottom:16px;width:2px;border-radius:2px;background:linear-gradient(var(--ad),var(--joinbright));transform-origin:top;animation:rail 12s ease-in-out infinite}
.step{--lit:1;--c:var(--ad);position:relative;display:flex;gap:16px;padding:0 0 22px;opacity:calc(.38 + .62*var(--lit))}
.step:last-child{padding-bottom:6px}
.step.j{--c:var(--joinbright)}
.step i{flex:none;width:32px;height:32px;border-radius:50%;display:grid;place-items:center;font-style:normal;font-weight:700;font-size:14px;border:2px solid color-mix(in srgb,var(--c) calc(30% + 70%*var(--lit)),#27405F);background:color-mix(in srgb,var(--c) calc(var(--lit)*100%),var(--ink));color:color-mix(in srgb,#06121F calc(var(--lit)*100%),#8EA6C4);position:relative;z-index:1}
.step b{display:block;font-weight:600;font-size:17px;color:#fff;line-height:1.3;padding-top:3px}
.step span{display:block;font-size:14.5px;color:#9BB0CC;margin-top:2px;line-height:1.45}
.step:nth-child(1){animation:s1 12s infinite}.step:nth-child(2){animation:s2 12s infinite}.step:nth-child(3){animation:s3 12s infinite}.step:nth-child(4){animation:s4 12s infinite}.step:nth-child(5){animation:s5 12s infinite}
@keyframes s1{0%,5%{--lit:0}9%,93%{--lit:1}97%,100%{--lit:0}}
@keyframes s2{0%,21%{--lit:0}26%,93%{--lit:1}97%,100%{--lit:0}}
@keyframes s3{0%,38%{--lit:0}43%,93%{--lit:1}97%,100%{--lit:0}}
@keyframes s4{0%,55%{--lit:0}60%,93%{--lit:1}97%,100%{--lit:0}}
@keyframes s5{0%,72%{--lit:0}77%,93%{--lit:1}97%,100%{--lit:0}}
@keyframes rail{0%,6%{transform:scaleY(0)}77%,93%{transform:scaleY(1)}97%,100%{transform:scaleY(0)}}
.receipt{margin-top:18px;border-top:1px dashed #2E4868;padding-top:16px;display:flex;align-items:center;gap:12px;font-size:15px;color:#CFE0F5;animation:rcp 12s infinite}
.receipt .pip{width:10px;height:10px;border-radius:50%;background:var(--joinbright);box-shadow:0 0 0 0 rgba(18,194,155,.6);animation:pulse 2.4s infinite}
.receipt strong{color:#fff;font-weight:600}
@keyframes rcp{0%,76%{opacity:.25}80%,93%{opacity:1}97%,100%{opacity:.25}}
@keyframes pulse{70%{box-shadow:0 0 0 10px rgba(18,194,155,0)}100%{box-shadow:0 0 0 0 rgba(18,194,155,0)}}

/* sections */
.sec{padding:96px 0}
.sec.alt{background:#fff;border-block:1px solid var(--line)}
.sec h2{font-size:clamp(32px,4.4vw,52px);font-weight:800;letter-spacing:-.035em;max-width:16em}
.sec .sub{color:var(--mute);font-size:19px;max-width:36em;margin-top:18px;line-height:1.55}

/* problem */
.lanes{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:48px}
.lane{border:1px solid var(--line);border-radius:var(--r);padding:26px;background:var(--paper)}
.lane.with{background:var(--ink);border-color:var(--ink);color:#E8EEF7}
.lane h3{font-size:20px;font-weight:700;letter-spacing:-.02em;margin-bottom:22px}
.flow{display:flex;align-items:center;flex-wrap:wrap;gap:8px 0}
.flow span{padding:7px 12px;border-radius:999px;border:1.5px solid #B9C6D8;font-size:14.5px;white-space:nowrap;font-weight:500;background:#fff}
.flow em{width:12px;height:2px;background:#B9C6D8;flex:none}
.flow em.gap{background:repeating-linear-gradient(90deg,#B9C6D8 0 4px,transparent 4px 8px);width:22px}
.lane.with .flow span{background:#16283D;border-color:#2E4868;color:#E8EEF7}
.lane.with .flow em{background:#2E4868}
.flow .hot{background:var(--joinbright)!important;border-color:var(--joinbright)!important;color:#05231C!important;font-weight:600}
.flow .cold{border-style:dashed;color:var(--mute);background:transparent}
.lane p{margin-top:22px;font-size:16px;color:var(--mute)}
.lane.with p{color:#9BB0CC}

/* how */
.how{display:grid;grid-template-columns:repeat(4,1fr);gap:0;margin-top:52px;border-top:2px solid var(--ink)}
.how li{list-style:none;padding:26px 26px 0 0}
.how li+li{padding-left:26px;border-left:1px solid var(--line)}
.how b{line-height:1.2;font-family:'Bricolage Grotesque',sans-serif;font-size:22px;font-weight:700;letter-spacing:-.02em;display:block;margin-bottom:8px}
.how p{color:var(--mute);font-size:16px}
.how .n{display:block;font-family:'Bricolage Grotesque',sans-serif;font-weight:800;font-size:15px;color:var(--ad);margin-bottom:14px}
ol.how{margin-bottom:0;padding-left:0}

/* features */
.bento{display:grid;grid-template-columns:repeat(6,1fr);gap:18px;margin-top:52px}
.tile{background:#fff;border:1px solid var(--line);border-radius:var(--r);padding:28px;overflow:hidden}
.tile h3{font-size:23px;font-weight:700;letter-spacing:-.02em;margin-bottom:10px}
.tile p{color:var(--mute);font-size:16px}
.t4{grid-column:span 4}.t2{grid-column:span 2}.t3{grid-column:span 3}
.tile.dark{background:var(--ink);border-color:var(--ink);color:#E8EEF7}
.tile.dark p{color:#9BB0CC}
.thumbs{display:flex;gap:14px;margin-top:26px}
.thumb{flex:1;min-width:0;border-radius:10px;border:1px solid var(--line);background:var(--paper);padding:12px;display:flex;flex-direction:column;gap:7px;aspect-ratio:3/3.4}
.thumb i{display:block;border-radius:4px;background:#C9D5E6;height:7px}
.thumb i.h{height:12px;width:78%;background:var(--ink)}
.thumb i.s{width:55%}
.thumb i.b{height:18px;border-radius:6px;background:var(--join);width:60%;margin-top:auto}
.thumb.d{background:var(--ink);border-color:var(--ink)}.thumb.d i{background:#31496A}.thumb.d i.h{background:#fff}
.thumb.sp{flex-direction:row;gap:10px}
.thumb.sp div{flex:1;display:flex;flex-direction:column;gap:7px}.thumb.sp .img{flex:1;border-radius:6px;background:linear-gradient(160deg,var(--ad),var(--joinbright))}
.links{list-style:none;margin:22px 0 0;padding:0;display:flex;flex-direction:column;gap:10px}
.links li{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:11px 14px;border-radius:10px;background:#16283D;border:1px solid #27405F;font-size:14.5px}
.links code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px;color:#BFD2EC}
.links .u{font-size:12.5px;font-weight:600;color:var(--joinbright)}
.checks{list-style:none;margin:20px 0 0;padding:0;display:grid;gap:9px}
.checks li{display:flex;align-items:center;gap:10px;font-size:15.5px;font-weight:500}
.checks svg{flex:none}
.tbl{width:100%;border-collapse:collapse;margin-top:22px;font-size:15px}
.tbl th{text-align:left;font-weight:600;color:var(--mute);padding:10px 12px;border-bottom:1px solid var(--line);font-size:14px}
.tbl td{padding:12px;border-bottom:1px solid var(--line)}
.tbl td:last-child,.tbl th:last-child{text-align:right;font-variant-numeric:tabular-nums}
.tbl tr:last-child td{border-bottom:0}
.sample{font-size:13px;color:var(--mute);margin-top:12px}

/* pricing */
.price{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,.9fr);gap:56px;align-items:center}
.pcard{background:#fff;border:1.5px solid var(--ink);border-radius:20px;padding:36px;box-shadow:10px 10px 0 var(--ink)}
.pcard .amt{font-family:'Bricolage Grotesque',sans-serif;font-weight:800;font-size:68px;letter-spacing:-.04em;line-height:1}
.pcard .amt small{font-family:'Instrument Sans',sans-serif;font-size:18px;font-weight:500;color:var(--mute);letter-spacing:0;margin-left:6px}
.pcard ul{list-style:none;margin:26px 0;padding:0;display:grid;gap:12px}
.pcard li{display:flex;gap:12px;align-items:flex-start;font-size:16.5px}
.pcard li svg{flex:none;margin-top:4px}
.pcard .btn{width:100%}
.pcard .fine{font-size:14px;color:var(--mute);margin-top:14px;text-align:center}
.pcard .fine a,.refund a{text-decoration:underline;text-underline-offset:3px}
.refund{margin-top:28px;display:grid;gap:14px;color:var(--mute);font-size:16px}
.refund b{color:var(--ink);font-weight:600}

/* faq */
.faq{margin-top:44px;max-width:820px;border-top:1px solid var(--line)}
.faq details{border-bottom:1px solid var(--line)}
.faq summary{list-style:none;cursor:pointer;padding:22px 44px 22px 0;font-weight:600;font-size:18px;position:relative}
.faq summary::-webkit-details-marker{display:none}
.faq summary::after{content:"";position:absolute;right:6px;top:50%;width:10px;height:10px;border-right:2px solid var(--ink);border-bottom:2px solid var(--ink);transform:translateY(-70%) rotate(45deg);transition:transform .2s ease}
.faq details[open] summary::after{transform:translateY(-30%) rotate(-135deg)}
.faq details p{padding:0 44px 24px 0;color:var(--mute);font-size:16.5px;max-width:42em}
.faq a{text-decoration:underline;text-underline-offset:3px}

/* closing + footer */
.close{background:var(--ink);color:#fff;padding:88px 0}
.close .wrap{display:flex;justify-content:space-between;align-items:center;gap:32px;flex-wrap:wrap}
.close h2{font-size:clamp(32px,4.4vw,52px);font-weight:800;max-width:14em}
.close p{color:#9BB0CC;margin-top:14px;font-size:18px}
.close .btn{background:#fff;color:var(--ink);border-color:#fff}
.close .btn:hover{background:#E8EEF7}
footer{background:#08121D;color:#8CA0BB;padding:44px 0 56px;font-size:15px}
footer .wrap{display:flex;flex-wrap:wrap;gap:28px 64px;justify-content:space-between}
footer .brand{color:#fff;font-size:20px;margin-bottom:12px}
footer p{max-width:26em;line-height:1.55}
footer nav{display:flex;gap:10px 28px;flex-wrap:wrap;align-content:flex-start}
footer nav a:hover{color:#fff}
footer .legal{width:100%;max-width:none;font-size:13.5px;color:#667A96;border-top:1px solid #16283D;padding-top:22px}

@media (max-width:1100px){.lanes{grid-template-columns:1fr}}
@media (max-width:980px){
 .hero .wrap,.price{grid-template-columns:1fr}
 .hero{padding:48px 0 64px}
 .how{grid-template-columns:1fr 1fr}
 .how li{padding:24px 20px 20px 0}.how li+li{padding-left:0;border-left:0}
 .how li:nth-child(even){padding-left:20px;border-left:1px solid var(--line)}
 .how li:nth-child(n+3){border-top:1px solid var(--line)}
 .t4,.t2,.t3{grid-column:span 6}
 .nav ul{display:none}
}
@media (max-width:640px){
 body{font-size:16.5px}
 .wrap{padding:0 20px}
 .sec{padding:68px 0}
 .lanes{grid-template-columns:1fr}
 .how{grid-template-columns:1fr}
 .how li,.how li:nth-child(even){padding:22px 0;border-left:0;border-top:1px solid var(--line)}
 .how li:first-child{border-top:0}
 .trace{padding:22px 18px 18px}
 .pcard{padding:26px;box-shadow:6px 6px 0 var(--ink)}
 .pcard .amt{font-size:56px}
 .nav .login{display:none}
 .thumbs{gap:10px}
}
@media (prefers-reduced-motion:reduce){
 .step,.rail,.receipt,.receipt .pip{animation:none!important}
 .step{--lit:1}.rail{transform:none}.receipt{opacity:1}
 html{scroll-behavior:auto}.btn{transition:none}
}
</style>
</head>
<body>
<header class="nav"><div class="wrap">
 <a class="brand" href="#top" aria-label="JoinPath home"><svg width="30" height="30" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="9" fill="#0D1B2A"/><path d="M8 21c5 0 4-10 9-10s3 6 7 6" fill="none" stroke="#2F6FEB" stroke-width="2.6" stroke-linecap="round"/><circle cx="24" cy="17" r="3.2" fill="#12C29B"/></svg>JoinPath</a>
 <nav aria-label="Main"><ul><li><a href="#how">How it works</a></li><li><a href="#features">Features</a></li><li><a href="#pricing">Pricing</a></li><li><a href="#faq">FAQ</a></li></ul></nav>
 <div class="act"><a class="login" href="{{APP}}/login">Log in</a><a class="btn sm" href="{{BUY}}">Get started</a></div>
</div></header>

<main id="top">
<section class="hero"><div class="wrap">
 <div>
  <h1>Every Telegram join, counted in Ads Manager.</h1>
  <p class="lede">Send people from your Meta ads to a JoinPath page. Each visitor gets a personal, one-time Telegram invite link. When they join, JoinPath tells Meta, so your campaigns learn from real joins instead of link clicks.</p>
  <div class="cta"><a class="btn join" href="{{BUY}}">Get started for ₹1,000 a month</a><a class="btn line" href="#how">See how it works</a></div>
  <ul class="facts">
   <li><svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><circle cx="9" cy="9" r="9" fill="#DDF3EC"/><path d="M5.2 9.3l2.4 2.3 5-5" fill="none" stroke="#0E9F7E" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>7-day refund if tracking fails</li>
   <li><svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><circle cx="9" cy="9" r="9" fill="#DDF3EC"/><path d="M5.2 9.3l2.4 2.3 5-5" fill="none" stroke="#0E9F7E" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>Works with your own Pixel</li>
   <li><svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><circle cx="9" cy="9" r="9" fill="#DDF3EC"/><path d="M5.2 9.3l2.4 2.3 5-5" fill="none" stroke="#0E9F7E" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>Cancel any time</li>
  </ul>
 </div>
 <aside class="trace" aria-label="Example of one visitor's journey">
  <div class="top"><span>One visitor, start to finish</span><span class="tag">Example</span></div>
  <div class="stepwrap"><span class="rail" aria-hidden="true"></span><ol class="steps">
   <li class="step"><i>1</i><div><b>Clicks your ad</b><span>The ad click ID and campaign tags are saved.</span></div></li>
   <li class="step"><i>2</i><div><b>Opens your page</b><span>Your Pixel records the page view.</span></div></li>
   <li class="step"><i>3</i><div><b>Taps Join</b><span>They get a link that only they can use, once.</span></div></li>
   <li class="step j"><i>4</i><div><b>Joins your channel</b><span>Telegram tells JoinPath through your bot.</span></div></li>
   <li class="step j"><i>5</i><div><b>Meta hears about it</b><span>The join is sent to your Pixel and matched to the ad click.</span></div></li>
  </ol></div>
  <div class="receipt"><span class="pip" aria-hidden="true"></span><span><strong>Join sent to Meta</strong> and matched to the ad that brought them</span></div>
 </aside>
</div></section>

<section class="sec alt" id="why"><div class="wrap">
 <h2>A link click is not a join.</h2>
 <p class="sub">Telegram ads usually optimise for people who click. Plenty of them never join. Meta can't see the difference, so it keeps finding more people who click.</p>
 <div class="lanes">
  <div class="lane"><h3>Without JoinPath</h3>
   <div class="flow"><span>Ad</span><em></em><span>Click</span><em class="gap"></em><span class="cold">Telegram</span><em class="gap"></em><span class="cold">Meta sees a click</span></div>
   <p>The visitor leaves your page. Whether they join happens out of sight, and Meta never hears about it.</p></div>
  <div class="lane with"><h3>With JoinPath</h3>
   <div class="flow"><span>Ad</span><em></em><span>Click</span><em></em><span>Invite link</span><em></em><span>Join</span><em></em><span class="hot">Meta sees a join</span></div>
   <p>Every join is tied back to the ad click it came from, and reported to your Pixel as a conversion.</p></div>
 </div>
</div></section>

<section class="sec" id="how"><div class="wrap">
 <h2>Live in an afternoon.</h2>
 <p class="sub">No code is needed. If you have a Pixel, a Telegram bot and a channel, you have everything required.</p>
 <ol class="how">
  <li><span class="n">1</span><b>Make your page</b><p>Pick a layout and a ready-made starting point, or paste your own HTML.</p></li>
  <li><span class="n">2</span><b>Connect your accounts</b><p>Add your Pixel ID, Conversions API token, bot and channel. Setup health checks each one.</p></li>
  <li><span class="n">3</span><b>Run your ads</b><p>Point your Meta ads at your JoinPath page address.</p></li>
  <li><span class="n">4</span><b>Watch joins arrive</b><p>See each join in your dashboard and in Ads Manager as a conversion.</p></li>
 </ol>
</div></section>

<section class="sec alt" id="features"><div class="wrap">
 <h2>Everything between the ad and the join.</h2>
 <div class="bento">
  <div class="tile t4"><h3>Landing pages that look finished</h3><p>Six layouts with ready-made starting points for education, trading, jobs, deals and news. Edit the words, colours and image with a live preview. Or bring your own HTML and we add the tracking for you.</p>
   <div class="thumbs" aria-hidden="true">
    <div class="thumb"><i class="h"></i><i></i><i class="s"></i><i class="b"></i></div>
    <div class="thumb d"><i class="h"></i><i></i><i class="s"></i><i class="b"></i></div>
    <div class="thumb sp"><div><i class="h"></i><i></i><i class="s"></i><i class="b"></i></div><span class="img"></span></div>
   </div></div>
  <div class="tile t2 dark"><h3>One link, one person</h3><p>Every visitor gets their own invite link. It works once, so each join matches one visit.</p>
   <ul class="links" aria-hidden="true"><li><code>t.me/+Qk7…a2</code><span class="u">Used</span></li><li><code>t.me/+Hn3…x9</code><span class="u">Used</span></li><li><code>t.me/+Zr2…c6</code><span class="u">Used</span></li><li><code>t.me/+Vd8…m4</code><span style="color:#9BB0CC;font-size:12.5px;font-weight:600">Waiting</span></li></ul></div>
  <div class="tile t2"><h3>Setup health</h3><p>Seven checks tell you what is working and what to fix before you spend on ads.</p>
   <ul class="checks">
    <li><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="10" fill="#DDF3EC"/><path d="M6 10.3l2.6 2.5 5.4-5.6" fill="none" stroke="#0E9F7E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>Pixel is on your page</li>
    <li><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="10" fill="#DDF3EC"/><path d="M6 10.3l2.6 2.5 5.4-5.6" fill="none" stroke="#0E9F7E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>Conversions token works</li>
    <li><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="10" fill="#DDF3EC"/><path d="M6 10.3l2.6 2.5 5.4-5.6" fill="none" stroke="#0E9F7E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>Bot can see your channel</li>
    <li><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="10" fill="#DDF3EC"/><path d="M6 10.3l2.6 2.5 5.4-5.6" fill="none" stroke="#0E9F7E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>Joins reach JoinPath</li>
    <li style="color:var(--mute)"><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="9" fill="none" stroke="#B9C6D8" stroke-width="2" stroke-dasharray="3 3"/></svg>And three more checks</li>
   </ul></div>
  <div class="tile t4"><h3>See which page, and which ad, brought each join</h3><p>Your dashboard lists every join with its page and campaign tags, and compares the joins we saw with what Meta recorded.</p>
   <table class="tbl" aria-label="Sample joins table"><thead><tr><th>Page</th><th>Campaign tag</th><th>Joins</th></tr></thead><tbody>
    <tr><td>stock-basics</td><td>reels-nov</td><td>42</td></tr>
    <tr><td>stock-basics</td><td>feed-nov</td><td>27</td></tr>
    <tr><td>job-alerts</td><td>reels-nov</td><td>18</td></tr></tbody></table>
   <p class="sample">Sample view with made-up numbers.</p></div>
 </div>
</div></section>

<section class="sec" id="pricing"><div class="wrap price">
 <div>
  <h2>One plan. Every feature.</h2>
  <p class="sub">Pay monthly, cancel when you like. Your plan stays active until the end of the month you paid for.</p>
  <div class="refund">
   <p><b>7-day refund.</b> If you finish setup, your page gets at least 100 visitors, and tracking still doesn't work after you've contacted support, we refund your first payment.</p>
   <p>Refunds don't cover poor ad results, since those depend on your ads and offer. <a href="{{APP}}/refund">Read the full refund policy</a>.</p>
  </div>
 </div>
 <div class="pcard">
  <div class="amt">₹1,000<small>per month</small></div>
  <ul>
   <li><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="10" fill="#DDF3EC"/><path d="M6 10.3l2.6 2.5 5.4-5.6" fill="none" stroke="#0E9F7E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>Landing pages from templates, or your own HTML</li>
   <li><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="10" fill="#DDF3EC"/><path d="M6 10.3l2.6 2.5 5.4-5.6" fill="none" stroke="#0E9F7E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>Personal one-time Telegram invite links</li>
   <li><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="10" fill="#DDF3EC"/><path d="M6 10.3l2.6 2.5 5.4-5.6" fill="none" stroke="#0E9F7E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>Join tracking sent to your own Meta Pixel</li>
   <li><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="10" fill="#DDF3EC"/><path d="M6 10.3l2.6 2.5 5.4-5.6" fill="none" stroke="#0E9F7E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>Setup health checks and a joins dashboard</li>
   <li><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="10" fill="#DDF3EC"/><path d="M6 10.3l2.6 2.5 5.4-5.6" fill="none" stroke="#0E9F7E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>Support by email</li>
  </ul>
  <a class="btn join" href="{{BUY}}">Get started</a>
  <p class="fine">Secure checkout on SuperProfile. After paying, you fill in a short form and we create your login, usually within an hour.</p>
 </div>
</div></section>

<section class="sec alt" id="faq"><div class="wrap">
 <h2>Questions people ask first.</h2>
 <div class="faq">
  <details><summary>Do I need to write code?</summary><p>No. You choose a layout, edit the text and connect your accounts. If you already have a page of your own, paste its HTML and JoinPath adds the Pixel and the personal invite links.</p></details>
  <details><summary>Whose Meta Pixel does it use?</summary><p>Yours. You enter your Pixel ID and Conversions API token, and joins are sent straight to your Pixel. Your Ads Manager, your data.</p></details>
  <details><summary>What do visitors see?</summary><p>They see your page. When they tap Join, Telegram opens with a link made just for them. Every page has a small "Report abuse" link so anyone can flag a problem.</p></details>
  <details><summary>What data do you collect from visitors?</summary><p>A random visitor ID, the ad click and campaign tags, the Meta browser identifiers, IP address and browser type, plus the time of the join. We don't collect visitor names, phone numbers or Telegram usernames. The <a href="{{APP}}/privacy">Privacy Policy</a> has the details.</p></details>
  <details><summary>How do I get started after paying?</summary><p>After checkout you land on a short form. Fill it in and we create your login, usually within an hour. Renewing next month works the same way, with the same email.</p></details>
  <details><summary>What can't I promote?</summary><p>No guaranteed returns, sure-shot tips, scams, betting, adult content or anything illegal. We can pause a page that breaks the <a href="{{APP}}/terms">Terms</a>.</p></details>
  <details><summary>Can I cancel?</summary><p>Yes, any time. Your plan stays active until the end of the month you paid for.</p></details>
 </div>
</div></section>

<section class="close"><div class="wrap">
 <div><h2>Find out which ads really bring members.</h2><p>Set up your first page today and see your first join counted.</p></div>
 <a class="btn" href="{{BUY}}">Get started for ₹1,000 a month</a>
</div></section>
</main>

<footer><div class="wrap">
 <div><div class="brand"><svg width="26" height="26" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="9" fill="#16283D"/><path d="M8 21c5 0 4-10 9-10s3 6 7 6" fill="none" stroke="#2F6FEB" stroke-width="2.6" stroke-linecap="round"/><circle cx="24" cy="17" r="3.2" fill="#12C29B"/></svg>JoinPath</div>
  <p>Join tracking for Telegram channel ads. Built and run by Dream Growth Agency.</p></div>
 <nav aria-label="Footer"><a href="{{APP}}/login">Log in</a><a href="{{APP}}/terms">Terms</a><a href="{{APP}}/privacy">Privacy</a><a href="{{APP}}/refund">Refunds</a><a href="{{APP}}/report">Report abuse</a><a href="mailto:{{MAIL}}">{{MAIL}}</a></nav>
 <p class="legal">© Dream Growth Agency. JoinPath is an independent product and is not affiliated with, endorsed by or sponsored by Meta Platforms, Inc. or Telegram. Facebook, Instagram and Meta are trademarks of Meta Platforms, Inc. Telegram is a trademark of its owner.</p>
</div></footer>
</body>
</html>
HTML;
    return str_replace(['{{APP}}', '{{BUY}}', '{{MAIL}}'], [e($app), e($buy), e($mail)], $html);
}
