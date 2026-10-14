<?php
/**
 * FordFit - form handler for the Start Your Journey form and the 7 day free trial popup
 * (same pattern as the tinyFIRES site). Replaces the GHL forms.
 *
 * A submission sends two emails via Resend: a notification to Toby, and a branded
 * auto-reply to the enquirer confirming receipt.
 *
 * We send from the `send.fordfitpt.com` subdomain, which has its own Resend DKIM/SPF
 * records, so it can't affect Toby's real mailbox or clash with the root domain's SPF/DMARC.
 *
 * Reply-To differs between the two emails so a reply always reaches a person:
 *   - Notification (to Toby): Reply-To is the enquirer.
 *   - Auto-reply (to the enquirer): Reply-To is toby@fordfitpt.com.
 *
 * Settings (Resend key, recipients, sender) live in fordfit-config.php in the hosting
 * account's home folder, outside public_html. The repository is public, so the key must
 * never be committed. api/config.php is also accepted, and api/.htaccess blocks it.
 *
 * Accepts JSON (from the site's script) or a normal form POST (no-JS fallback, which
 * redirects back to the page with ?sent=1 or ?error=1).
 */

const MAX = ['form' => 20, 'name' => 100, 'phone' => 40, 'email' => 200];
const CONTACT_EMAIL = 'toby@fordfitpt.com';
const SERVICES = [
    'Online Coaching',
    'Personal Training - Long Stratton',
    'Personal Training - Wymondham',
    'Personal Training - Home and Mobile',
];
// Where each form lives, for the no-JS redirect.
const PAGES = ['journey' => '/start-your-journey-page', 'trial' => '/'];

// TEST MODE: while this list is non-empty, notifications go ONLY to these addresses,
// overriding the 'to' list in the config (Toby is left off until Kaizen has tested the
// forms end to end). Set to [] to go live.
const TEST_RECIPIENTS = [];

$isJson = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
$formType = 'journey';

function respond(bool $ok, string $error = '', int $status = 200): void {
    global $isJson, $formType;
    if ($isJson) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    } else {
        $page = PAGES[$formType] ?? '/';
        header('Location: ' . $page . '?' . ($ok ? 'sent=1' : 'error=1') . '#' . $formType . '-form', true, 303);
    }
    exit;
}

function clean($v, int $max): string {
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(false, 'Method not allowed', 405);
}

$body = $isJson ? json_decode(file_get_contents('php://input'), true) : $_POST;
if (!is_array($body)) respond(false, 'Invalid request.', 400);

$f = [];
foreach (MAX as $k => $max) $f[$k] = clean($body[$k] ?? '', $max);
$formType = isset(PAGES[$f['form']]) ? $f['form'] : 'journey';
$isTrial = $formType === 'trial';

// Honeypot: a field hidden from humans. Bots fill it in.
if (clean($body['website'] ?? '', 100) !== '') respond(true);

// Services: only the four real options are accepted.
$services = $body['services'] ?? [];
if (!is_array($services)) $services = [$services];
$services = array_values(array_intersect(SERVICES, array_map(fn($s) => clean($s, 100), $services)));

if ($f['phone'] === '' || $f['email'] === '' || ($isTrial && $f['name'] === '') || (!$isTrial && !$services)) {
    respond(false, 'Please fill in all required fields.', 400);
}
// Deliberately permissive - real addresses are stranger than most regexes allow.
if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/', $f['email'])) {
    respond(false, "That email address doesn't look right.", 400);
}
if (!preg_match('/^[0-9+()\s.-]{7,40}$/', $f['phone'])) {
    respond(false, "That phone number doesn't look right.", 400);
}
// Header-injection guard: names and emails go into email headers.
foreach (['name', 'email'] as $k) {
    if (preg_match('/[\r\n<>"]/', $f[$k])) respond(false, 'Invalid characters in your details.', 400);
}

$fallback = 'Sorry - we couldn\'t send that. Please email ' . CONTACT_EMAIL . ' or call 07495 141804 instead.';
$config = null;
foreach ([dirname(__DIR__, 2) . '/fordfit-config.php', __DIR__ . '/config.php'] as $path) {
    if (is_readable($path)) { $config = require $path; break; }
}
if (!is_array($config) || empty($config['resend_api_key']) || $config['resend_api_key'] === 'PASTE_KEY_HERE') {
    error_log('FordFit form: fordfit-config.php missing or key not set');
    respond(false, $fallback, 500);
}
$to   = $config['to']   ?? [CONTACT_EMAIL];
if (TEST_RECIPIENTS) $to = TEST_RECIPIENTS;
$from = $config['from'] ?? 'do-not-reply@send.fordfitpt.com';

$name      = $f['name'];
$who       = $name !== '' ? $name : $f['email'];
$firstName = $name !== '' ? explode(' ', $name)[0] : '';
$what      = $isTrial ? '7 day free trial (FordFit App)' : implode(', ', $services);
$rows = [
    'Form'    => $isTrial ? '7 day free trial popup' : 'Start Your Journey',
    'Name'    => $name !== '' ? $name : '-',
    'Phone'   => $f['phone'],
    'Email'   => $f['email'],
    ($isTrial ? 'Wants' : 'Services') => $what,
];

/**
 * Shared branded shell for both emails. Table layout + inline styles -
 * email clients strip <style> blocks and flex/grid unpredictably.
 * Colours: FordFit black #000000, smoke #F5F5F5, slate #565A7C.
 */
function emailShell(string $preheader, string $bodyHtml): string {
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
    . '<body style="margin:0;padding:0;background:#F5F5F5;font-family:-apple-system,\'Segoe UI\',Roboto,sans-serif;">'
    . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . e($preheader) . '</div>'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F5F5;padding:32px 16px;"><tr><td align="center">'
    . '<table role="presentation" width="100%" style="max-width:560px;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e4e4e4;">'
    . '<tr><td style="background:#000000;padding:22px 32px;"><img src="https://fordfitpt.com/assets/img/email-logo.png" alt="FordFit Personal Training" width="180" height="60" style="display:block;width:180px;height:60px;border:0;"></td></tr>'
    . '<tr><td style="padding:28px 32px 0;">' . $bodyHtml . '</td></tr>'
    . '<tr><td style="height:32px;font-size:0;line-height:0;">&nbsp;</td></tr>'
    . '<tr><td style="background:#000000;padding:22px 32px;font-size:12px;line-height:1.7;color:#b8b8b8;letter-spacing:1px;">'
    . 'MAKE A DIFFERENCE.<br>'
    . '<a href="mailto:' . CONTACT_EMAIL . '" style="color:#ffffff;">' . CONTACT_EMAIL . '</a> &middot; '
    . '<a href="tel:+447495141804" style="color:#ffffff;">07495 141804</a> &middot; '
    . '<a href="https://fordfitpt.com" style="color:#ffffff;">fordfitpt.com</a>'
    . '</td></tr></table></td></tr></table></body></html>';
}

// ── Email 1: notification, to Toby ───────────────────────────────────────
$notifyText = "New enquiry from the FordFit website\n\n";
foreach ($rows as $k => $v) $notifyText .= "$k: $v\n";

$rowsHtml = '';
foreach ($rows as $k => $v) {
    $rowsHtml .= '<tr><td style="padding:5px 16px 5px 0;color:#565A7C;font-size:13px;vertical-align:top;white-space:nowrap;">' . e($k) . '</td>'
        . '<td style="padding:5px 0;font-weight:600;color:#000000;font-size:14px;">' . e($v) . '</td></tr>';
}
$notifyHtml = emailShell("New enquiry from $who",
    '<h1 style="margin:0 0 20px;font-size:20px;color:#000000;">' . ($isTrial ? 'New free trial request' : 'New website enquiry') . '</h1>'
    . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:8px;">' . $rowsHtml . '</table>'
    . '<p style="margin-top:20px;font-size:12px;color:#565A7C;line-height:1.6;">Replying to this email goes straight to ' . e($who) . '.</p>'
);

// ── Email 2: auto-reply, to the enquirer ─────────────────────────────────
$hi = $firstName !== '' ? "Hi $firstName," : 'Hi,';
if ($isTrial) {
    $replyLead = 'Thanks for signing up for your 7 day free trial of the FordFit App. Toby will be in touch to get you set up.';
} else {
    $replyLead = 'Thanks for getting in touch with FordFit Personal Training. Toby will be in touch to book your consultation and talk through the best plan for your goals.';
}
$replyText = "$hi\n\n$replyLead\n\n"
    . "If you'd like to add anything in the meantime, just reply to this email.\n\n"
    . "FordFit Personal Training - Make a difference.\n" . CONTACT_EMAIL . " - 07495 141804\n";
$replyHtml = emailShell($isTrial ? 'Your FordFit free trial - Toby will be in touch.' : "Thanks for getting in touch - Toby will be in touch soon.",
    '<h1 style="margin:0 0 16px;font-size:20px;color:#000000;">' . e($firstName !== '' ? "Thanks, $firstName." : 'Thanks for getting in touch.') . '</h1>'
    . '<p style="margin:0 0 16px;font-size:14px;line-height:1.7;color:#333333;">' . e($replyLead) . '</p>'
    . '<p style="margin:0;font-size:14px;line-height:1.7;color:#333333;">Want to add anything in the meantime? Just reply to this email.</p>'
);

function resendSend(string $apiKey, array $payload): array {
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $res = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$status >= 200 && $status < 300, $status, $err ?: (string) $res];
}

$replyTo = $name !== '' ? "$name <{$f['email']}>" : $f['email'];

[$notifyOk, $nStatus, $nBody] = resendSend($config['resend_api_key'], [
    'from'     => "FordFit Website <$from>",
    'to'       => $to,
    'reply_to' => $replyTo,
    'subject'  => ($isTrial ? 'Free trial request - ' : 'New enquiry - ') . "$who - $what",
    'text'     => $notifyText,
    'html'     => $notifyHtml,
]);

if (!$notifyOk) {
    // The notification must not fail silently - log and surface it.
    error_log("FordFit form: Resend (notify) failed $nStatus $nBody");
    respond(false, $fallback, 502);
}

// The auto-reply is best-effort: the enquiry has already reached Toby.
[$replyOk, $rStatus, $rBody] = resendSend($config['resend_api_key'], [
    'from'     => "FordFit Personal Training <$from>",
    'to'       => [$replyTo],
    'reply_to' => CONTACT_EMAIL,
    'subject'  => $isTrial ? 'Your FordFit free trial' : 'Thanks for getting in touch - FordFit Personal Training',
    'text'     => $replyText,
    'html'     => $replyHtml,
]);
if (!$replyOk) error_log("FordFit form: Resend (auto-reply) failed $rStatus $rBody");

respond(true);
