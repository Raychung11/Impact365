<?php
/**
 * IMPACT365 — Email verification
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/layout.php';

$token = (string) input('token', '');
$ok = $token !== '' && verify_email_token($token);

flash($ok ? 'success' : 'error',
    $ok ? 'Your email has been verified. You can now sign in.'
        : 'This verification link is invalid or has already been used.');
redirect('auth/login.php');
