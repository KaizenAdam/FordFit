<?php
/**
 * FordFit - the two emails contact.php sends: the enquiry notification to Toby and the
 * auto-reply to the enquirer. Kept apart from contact.php so the layout can be previewed
 * without sending anything.
 *
 * Table layout + inline styles: email clients strip <style> blocks and handle flex/grid
 * unpredictably. The header is black with the white logo (email-logo.png), so it also
 * reads properly in dark-mode mail apps. Colours: FordFit black #000000, smoke #F5F5F5,
 * slate #565A7C.
 */

const EMAIL_FONT = "'Poppins',-apple-system,'Segoe UI',Roboto,Arial,sans-serif";
const EMAIL_PHONE = '07495 141804';
const EMAIL_PHONE_HREF = 'tel:+447495141804';

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** Shared branded shell: black logo header, white card, black footer. */
function emailShell(string $preheader, string $bodyHtml, string $contactEmail): string {
    $f = EMAIL_FONT;
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<meta name="color-scheme" content="light only"><meta name="supported-color-schemes" content="light only">'
    . '<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet"></head>'
    . '<body style="margin:0;padding:0;background:#F5F5F5;font-family:' . $f . ';">'
    . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . e($preheader) . '</div>'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F5F5;padding:32px 16px;"><tr><td align="center">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e4e4e4;">'
    . '<tr><td style="background:#000000;padding:26px 32px;"><img src="https://fordfitpt.com/assets/img/email-logo.png" alt="FordFit Personal Training" width="168" height="56" style="display:block;width:168px;height:56px;border:0;"></td></tr>'
    . '<tr><td style="padding:32px 32px 0;">' . $bodyHtml . '</td></tr>'
    . '<tr><td style="height:36px;font-size:0;line-height:0;">&nbsp;</td></tr>'
    . '<tr><td style="background:#000000;padding:26px 32px;font-size:12px;line-height:1.8;color:#b8b8b8;">'
    . '<strong style="color:#ffffff;font-size:13px;letter-spacing:2px;">FORDFIT PERSONAL TRAINING</strong><br>'
    . 'Long Stratton &middot; Wymondham &middot; Home &amp; Mobile &middot; Online<br>'
    . '<a href="mailto:' . e($contactEmail) . '" style="color:#ffffff;">' . e($contactEmail) . '</a> &middot; '
    . '<a href="' . EMAIL_PHONE_HREF . '" style="color:#ffffff;">' . EMAIL_PHONE . '</a> &middot; '
    . '<a href="https://fordfitpt.com" style="color:#ffffff;">fordfitpt.com</a>'
    . '</td></tr></table>'
    . '<p style="margin:16px 0 0;font-size:11px;letter-spacing:3px;color:#98a1b3;">MAKE A DIFFERENCE.</p>'
    . '</td></tr></table></body></html>';
}

/** Small tracked-caps label above a heading. */
function emailEyebrow(string $text): string {
    return '<p style="margin:0 0 8px;font-size:11px;font-weight:600;letter-spacing:2px;color:#565A7C;">' . e(strtoupper($text)) . '</p>';
}

function emailButton(string $href, string $label, bool $primary = true): string {
    $style = $primary
        ? 'background:#000000;color:#ffffff;border:2px solid #000000;'
        : 'background:#ffffff;color:#000000;border:2px solid #000000;';
    return '<a href="' . e($href) . '" style="display:inline-block;' . $style . 'border-radius:8px;padding:11px 20px;'
        . 'font-size:13px;font-weight:600;letter-spacing:1px;text-decoration:none;margin:0 8px 8px 0;">' . e($label) . '</a>';
}

/**
 * Email 1: notification to Toby.
 * $rows: label => value. $message may be ''. Returns [html, text].
 */
function notifyEmail(bool $isTrial, string $who, string $firstName, array $rows, string $message, string $email, string $phone, string $contactEmail): array {
    $text = "New enquiry from the FordFit website\n\n";
    foreach ($rows as $k => $v) $text .= "$k: $v\n";
    if ($message !== '') $text .= "\nMessage:\n$message\n";
    $text .= "\nReplying to this email goes straight to $who.\n";

    $rowsHtml = '';
    $last = array_key_last($rows);
    foreach ($rows as $k => $v) {
        $border = $k === $last ? '' : 'border-bottom:1px solid #ececec;';
        $value = e($v);
        if ($k === 'Email') $value = '<a href="mailto:' . e($v) . '" style="color:#000000;">' . e($v) . '</a>';
        if ($k === 'Phone') $value = '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $v)) . '" style="color:#000000;">' . e($v) . '</a>';
        $rowsHtml .= '<tr><td style="padding:12px 16px;' . $border . 'color:#565A7C;font-size:13px;vertical-align:top;white-space:nowrap;width:30%;">' . e($k) . '</td>'
            . '<td style="padding:12px 16px;' . $border . 'font-weight:600;color:#000000;font-size:14px;">' . $value . '</td></tr>';
    }

    $html = emailEyebrow($isTrial ? '7 day free trial' : 'Start Your Journey')
        . '<h1 style="margin:0 0 22px;font-size:22px;line-height:1.3;color:#000000;">' . ($isTrial ? 'New free trial request' : 'New website enquiry') . '</h1>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border:1px solid #ececec;border-radius:12px;border-collapse:separate;margin-bottom:20px;">' . $rowsHtml . '</table>';
    if ($message !== '') {
        $html .= '<div style="color:#565A7C;font-size:13px;margin-bottom:6px;">Message</div>'
            . '<div style="white-space:pre-wrap;padding:14px 16px;background:#F5F5F5;border-radius:12px;font-size:14px;line-height:1.6;color:#000000;margin-bottom:20px;">' . e($message) . '</div>';
    }
    $html .= '<div style="margin-top:4px;">'
        . emailButton('mailto:' . $email, $firstName !== '' ? "Email $firstName" : 'Email back')
        . emailButton('tel:' . preg_replace('/[^0-9+]/', '', $phone), 'Call ' . $phone, false)
        . '</div>'
        . '<p style="margin:12px 0 0;font-size:12px;color:#565A7C;line-height:1.6;">Replying to this email goes straight to ' . e($who) . '.</p>';

    return [emailShell("New enquiry from $who", $html, $contactEmail), $text];
}

/** Email 2: auto-reply to the enquirer. Returns [subject, html, text]. */
function replyEmail(bool $isTrial, string $firstName, string $contactEmail): array {
    $heading = $firstName !== '' ? "Thanks, $firstName." : 'Thanks for getting in touch.';
    if ($isTrial) {
        $subject = 'Your FordFit free trial';
        $preheader = 'Your 7 day free trial of the FordFit App - Toby will be in touch to set you up.';
        $lead = 'Thanks for signing up for your 7 day free trial of the FordFit App. Toby will be in touch to get you set up.';
        $steps = [
            'Toby gets in touch to set you up on the FordFit App.',
            'You start your 7 day free trial.',
            'Track your workouts, meals, habits and more.',
        ];
    } else {
        $subject = 'Thanks for getting in touch - FordFit Personal Training';
        $preheader = 'Thanks for getting in touch - Toby will be in touch soon.';
        $lead = 'Thanks for getting in touch with FordFit Personal Training. Toby will be in touch to book your consultation and talk through the best plan for your goals.';
        $steps = [
            'Toby reads through your details.',
            'He gets in touch to book your consultation.',
            'Together you plan the training that fits your goals.',
        ];
    }

    $stepsHtml = '';
    foreach ($steps as $i => $s) {
        $stepsHtml .= '<tr><td style="padding:0 14px 12px 0;vertical-align:top;width:28px;">'
            . '<div style="width:28px;height:28px;line-height:28px;border-radius:14px;background:#000000;color:#ffffff;font-size:13px;font-weight:700;text-align:center;">' . ($i + 1) . '</div></td>'
            . '<td style="padding:4px 0 12px;font-size:14px;line-height:1.5;color:#333333;">' . e($s) . '</td></tr>';
    }

    $html = emailEyebrow($isTrial ? '7 day free trial' : 'Start Your Journey')
        . '<h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#000000;">' . e($heading) . '</h1>'
        . '<p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#333333;">' . e($lead) . '</p>'
        . '<div style="padding:20px 20px 8px;background:#F5F5F5;border-radius:12px;margin-bottom:24px;">'
        . '<p style="margin:0 0 14px;font-size:11px;font-weight:600;letter-spacing:2px;color:#565A7C;">WHAT HAPPENS NEXT</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;">' . $stepsHtml . '</table></div>'
        . '<p style="margin:0;font-size:14px;line-height:1.7;color:#333333;">Want to add anything in the meantime? Just reply to this email, or call Toby on '
        . '<a href="' . EMAIL_PHONE_HREF . '" style="color:#000000;font-weight:600;">' . EMAIL_PHONE . '</a>.</p>';

    $text = ($firstName !== '' ? "Hi $firstName," : 'Hi,') . "\n\n$lead\n\nWhat happens next:\n";
    foreach ($steps as $i => $s) $text .= ($i + 1) . ". $s\n";
    $text .= "\nWant to add anything in the meantime? Just reply to this email, or call Toby on " . EMAIL_PHONE . ".\n\n"
        . "FordFit Personal Training - Make a difference.\n$contactEmail - " . EMAIL_PHONE . " - fordfitpt.com\n";

    return [$subject, emailShell($preheader, $html, $contactEmail), $text];
}
