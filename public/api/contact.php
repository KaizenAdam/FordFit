<?php
/**
 * FordFit - form handler for the Start Your Journey form and the 7 day free trial popup
 * (same pattern as the tinyFIRES site). Replaces the GHL forms.
 *
 * A submission sends two emails via Resend: a notification to Toby, and a branded
 * auto-reply to the enquirer confirming receipt.
 *
 * We send from do-not-reply@fordfitpt.com. Resend's DKIM sits on resend._domainkey and its
 * SPF/MX on the `send` subdomain, so they don't touch Toby's Google Workspace records.
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

const MAX = ['form' => 20, 'name' => 100, 'phone' => 40, 'email' => 200, 'message' => 2000];
const CONTACT_EMAIL = 'toby@fordfitpt.com';
const SERVICES = [
    'Online Coaching',
    'Personal Training - Long Stratton',
    'Personal Training - Wymondham',
    'Personal Training - Home and Mobile',
];
// Where each form lives, for the no-JS redirect.
const PAGES = ['journey' => '/start-your-journey-page', 'trial' => '/'];

require __DIR__ . '/emails.php';

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
$from = $config['from'] ?? 'do-not-reply@fordfitpt.com';

$name      = $f['name'];
$who       = $name !== '' ? $name : $f['email'];
$firstName = $name !== '' ? explode(' ', $name)[0] : '';
$what      = $isTrial ? '7 day free trial (FordFit App)' : implode(', ', $services);
$rows = [
    'Name'    => $name !== '' ? $name : '-',
    'Phone'   => $f['phone'],
    'Email'   => $f['email'],
    ($isTrial ? 'Wants' : 'Services') => $what,
];

$message = $isTrial ? '' : $f['message'];
[$notifyHtml, $notifyText] = notifyEmail($isTrial, $who, $firstName, $rows, $message, $f['email'], $f['phone'], CONTACT_EMAIL);
[$replySubject, $replyHtml, $replyText] = replyEmail($isTrial, $firstName, CONTACT_EMAIL);

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
    'subject'  => $replySubject,
    'text'     => $replyText,
    'html'     => $replyHtml,
]);
if (!$replyOk) error_log("FordFit form: Resend (auto-reply) failed $rStatus $rBody");

respond(true);
