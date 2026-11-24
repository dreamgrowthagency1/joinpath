<?php
declare(strict_types=1);

const LP_TERMS_VERSION = '2026-11-24';

const LP_REPORT_REASONS = [
    'misleading' => 'Misleading or false claims',
    'scam' => 'Scam or fraud',
    'adult' => 'Adult content',
    'copyright' => 'Copyright or impersonation',
    'hate' => 'Hate, violence or harassment',
    'other' => 'Something else',
];

function support_email(): string
{
    return (string)cfg('support_email', 'dreamgrowthagency1@gmail.com');
}

/** Best-effort email. The dashboard is always the source of truth. */
function lp_mail(string $to, string $subject, string $body, string $replyTo = ''): bool
{
    $host = preg_replace('/^www\./', '', (string)parse_url((string)cfg('app_url', 'https://localhost'), PHP_URL_HOST));
    $headers = 'From: JoinPath <noreply@' . ($host ?: 'localhost') . ">\r\nContent-Type: text/plain; charset=utf-8\r\nX-Mailer: JoinPath";
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers .= "\r\nReply-To: " . $replyTo;
    }
    $subject = str_replace(["\r", "\n"], ' ', $subject);
    try {
        return (bool)@mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    } catch (Throwable $e) {
        return false;
    }
}

/** Words that tend to break Meta ad rules or Indian advertising and securities rules. Advisory only. */
function lp_risky_terms(string $text): array
{
    $text = strtolower(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $rules = [
        'guaranteed returns or profit' => '/guarantee[sd]?\s+(?:\w+\s+)?(?:returns?|profits?|income|earnings?|results?)|assured\s+(?:returns?|profits?)/',
        'sure-shot tips' => '/sure[\s-]?shot|100\s?%\s*(?:sure|accura|profit|win|safe)|never\s+loss/',
        'double your money' => '/double\s+your\s+money|paisa\s+double|paise\s+double|money\s+double/',
        'get rich claims' => '/get\s+rich|crorepati|lakhpati|become\s+rich|rich\s+quick/',
        'risk-free claims' => '/risk[\s-]?free|zero\s+risk|no\s+risk/',
        'betting or gambling' => '/\bbetting\b|\bsatta\b|\bmatka\b|\bcasino\b|\bjackpot\b|teen\s?patti/',
        'loan without checks' => '/loan\s+without|instant\s+loan|no\s+cibil/',
        'adult content' => '/\bxxx\b|\bporn|18\s?\+|adult\s+content/',
        'miracle health claims' => '/miracle\s+cure|cure\s+(?:cancer|diabetes)|lose\s+\d+\s?kg/',
        'insider or operator tips' => '/insider\s+(?:tips?|info)|operator\s+tips?|pump\s+and\s+dump/',
    ];
    $hits = [];
    foreach ($rules as $label => $re) {
        if (preg_match($re, $text)) {
            $hits[] = $label;
        }
    }
    return $hits;
}

/** Plain text of everything a visitor can read on a page, for the word check. */
function lp_page_text(array $page): string
{
    if ($page['template'] === 'custom') {
        return (string)$page['custom_html'];
    }
    $c = page_content($page);
    return implode(' ', [$c['headline'], $c['sub'], $c['badge'], $c['bullets'], $c['button'], $c['note'], $c['members'], $c['footer']]);
}

function lp_legal_title(string $key): string
{
    return ['terms' => 'Terms of Service', 'privacy' => 'Privacy Policy', 'refund' => 'Refund Policy'][$key] ?? 'Legal';
}

/** Draft legal text. Have a lawyer review before relying on it. */
function lp_legal_body(string $key): string
{
    $mail = e(support_email());
    $owner = 'Dream Growth Agency';
    $updated = '<p class="muted">Last updated: 24 November 2026</p>';
    if ($key === 'privacy') {
        return $updated . '
<h2>Who we are</h2><p>JoinPath is a service run by ' . $owner . ' ("we", "us"). It helps channel owners track which ad clicks lead to a Telegram join and report that to Meta. Contact: <a href="mailto:' . $mail . '">' . $mail . '</a>.</p>
<h2>Two kinds of people</h2><p><strong>Our customers</strong> (the people who log in) and <strong>visitors</strong> (people who open a customer\'s landing page). For visitor data, the customer decides why and how it is used. We process it on the customer\'s behalf.</p>
<h2>What we collect about customers</h2><p>Name, email, business name, the Pixel ID, Conversions API token, Telegram bot token and channel details they enter, and usage records. Tokens are stored encrypted.</p>
<h2>What we collect about visitors</h2><p>When someone opens a customer\'s page we store: a random visitor ID (cookie), the page address, UTM and ad click parameters (such as fbclid), Meta browser identifiers (fbp, fbc), IP address and browser user agent. When someone joins the channel through a personal invite link, we store the time of the join and match it to that visit. We do not collect names, phone numbers or Telegram usernames of visitors. A one-way hashed Telegram user ID is kept only to avoid counting the same join twice.</p>
<h2>How it is used</h2><p>To create a personal Telegram invite link, to count joins, and to send the conversion event to the customer\'s own Meta Pixel through the Conversions API. Meta receives the hashed identifiers listed above and handles them under its own policies.</p>
<h2>Sharing</h2><p>We share data only with Meta (as above), Telegram (to create invite links and receive join notices), and our hosting provider. We do not sell data.</p>
<h2>Retention and deletion</h2><p>We keep records while the account is active and for a short period after, then delete or anonymise them. A customer or a visitor can ask us to delete their data by writing to <a href="mailto:' . $mail . '">' . $mail . '</a>.</p>
<h2>Your rights</h2><p>You can ask us to access, correct or delete your personal data under applicable Indian law, including the Digital Personal Data Protection Act, 2023.</p>
<h2>Changes</h2><p>We may update this policy. The date above shows the latest version.</p>';
    }
    if ($key === 'refund') {
        return $updated . '
<h2>The plan</h2><p>JoinPath is billed monthly. You can cancel any time. After you cancel, your plan stays active until the end of the month you have paid for. We do not give partial refunds for unused days.</p>
<h2>7-day refund</h2><p>You can ask for a refund within <strong>7 days of your first payment</strong> by writing to <a href="mailto:' . $mail . '">' . $mail . '</a> from your registered email. We refund if all of these are true:</p>
<ul><li>You completed the setup (Pixel, token, bot and channel) and the Setup health page showed all checks passing.</li>
<li>Your landing page received at least 100 visitors.</li>
<li>Join tracking still did not work properly, and we could not fix it after you contacted support.</li></ul>
<h2>What is not refunded</h2><ul><li>Poor ad performance, a high cost per lead, or low joins. Ad results depend on your ads, offer and audience, not on JoinPath.</li>
<li>Setup you did not finish, or a Pixel, token, bot or channel that was wrongly set up.</li>
<li>Accounts suspended for breaking our Terms.</li>
<li>Renewals after the first month, unless tracking failed through our fault and you told us within 7 days of that payment.</li></ul>
<h2>How refunds are paid</h2><p>Approved refunds go back to the original payment method within 5 to 10 working days, depending on your bank.</p>';
    }
    return $updated . '
<h2>1. About these terms</h2><p>These terms are between you and ' . $owner . ' ("we", "us") for the JoinPath service. By creating an account or using the service you accept them.</p>
<h2>2. The service</h2><p>JoinPath hosts landing pages, creates single-use Telegram invite links, detects joins through your bot, and sends conversion events to your Meta Pixel. We try to keep it running but do not promise it will be uninterrupted or error-free. We do not control Meta or Telegram, and changes they make can affect the service.</p>
<h2>3. Your account</h2><p>Keep your password safe. You are responsible for what happens under your account and for the pages, channels, ads and accounts you connect. You confirm you own or are allowed to manage the Pixel, bot and channel you connect.</p>
<h2>4. What you must not promote</h2><p>You must not use JoinPath for any of these:</p>
<ul><li>Guaranteed returns, assured profits, "sure-shot" tips, or any claim that is false or misleading.</li>
<li>Investment advice without the registration the law requires, or content that breaks SEBI rules.</li>
<li>Scams, fake offers, phishing, pyramid or get-rich-quick schemes, fake loan apps.</li>
<li>Betting, gambling, adult content, drugs, weapons, or anything illegal in India.</li>
<li>Content that infringes copyright or trademarks, impersonates others, or harasses or threatens people.</li>
<li>Anything that breaks Meta\'s or Telegram\'s rules.</li></ul>
<h2>5. Our right to act</h2><p>We can review, pause or remove any page, and suspend or close any account, at any time and without prior notice, if we think these terms or the law are being broken, if we receive a credible complaint, or to protect other customers. A suspended account is not refunded. Anyone can report a page using the "Report abuse" link on it.</p>
<h2>6. Your content and responsibility</h2><p>You are fully responsible for your pages, ads and channel content and for following the law and the rules of Meta and Telegram. You agree to compensate us for losses, claims and costs that arise from your content or your breach of these terms.</p>
<h2>7. Data</h2><p>You are responsible for having a lawful basis to collect visitor data through your pages and for telling your visitors about it. Our <a href="/privacy">Privacy Policy</a> explains what we process.</p>
<h2>8. Payment and cancellation</h2><p>The plan is billed monthly in advance. You can cancel any time; it then ends when the paid month ends. Refunds follow our <a href="/refund">Refund Policy</a>. We may change the price with at least 30 days\' notice.</p>
<h2>9. Limits of liability</h2><p>The service is provided "as is". To the extent the law allows, we are not liable for indirect or consequential loss, lost profit, ad spend, or loss of data. Our total liability for any claim is limited to the fees you paid us in the 3 months before the claim.</p>
<h2>10. Changes</h2><p>We may update these terms. If the change is important we will tell you in the dashboard or by email. Continuing to use the service means you accept the new terms.</p>
<h2>11. Law</h2><p>These terms are governed by the laws of India. Disputes are subject to the courts of competent jurisdiction in India.</p>
<h2>12. Contact</h2><p><a href="mailto:' . $mail . '">' . $mail . '</a></p>';
}
