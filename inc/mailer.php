<?php
/**
 * IMPACT365 — Minimal Mailer
 * -----------------------------------------------------------------------------
 * Wraps PHP mail() (available on Hostinger shared hosting). In development or
 * when mail() is unavailable, messages are written to uploads/_maillog.txt so
 * flows (verification, password reset, receipts) remain testable.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function send_mail(string $to, string $subject, string $htmlBody): bool
{
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'Reply-To: ' . MAIL_FROM,
        'X-Mailer: IMPACT365',
    ];

    $wrapped = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:600px;margin:auto">'
        . '<div style="background:#1f3b2c;color:#fff;padding:18px 24px;font-size:20px;font-weight:700">'
        . APP_NAME . '</div>'
        . '<div style="padding:24px;color:#222;line-height:1.6">' . $htmlBody . '</div>'
        . '<div style="padding:16px 24px;color:#888;font-size:12px;border-top:1px solid #eee">'
        . APP_NAME . ' — ' . APP_TAGLINE . '</div></div>';

    $sent = false;
    if (function_exists('mail') && APP_ENV === 'production') {
        $sent = @mail($to, $subject, $wrapped, implode("\r\n", $headers));
    }

    if (!$sent) {
        // Fallback log so flows remain usable in dev / when SMTP is off.
        $log = UPLOAD_PATH . '/_maillog.txt';
        @file_put_contents(
            $log,
            '[' . date('Y-m-d H:i:s') . "] TO: $to | SUBJ: $subject\n" . strip_tags($htmlBody) . "\n\n",
            FILE_APPEND | LOCK_EX
        );
    }
    return $sent;
}
