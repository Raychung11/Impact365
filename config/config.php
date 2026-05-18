<?php
/**
 * IMPACT365 — Central Configuration
 * -----------------------------------------------------------------------------
 * Single source of truth for app-wide constants. Edit the DB credentials and
 * BASE_URL for your Hostinger account, then everything else derives from here.
 *
 * Keep this file OUTSIDE version control in production if it holds live
 * secrets, or override via environment variables on the server.
 */

declare(strict_types=1);

if (defined('IMPACT365_CONFIG_LOADED')) {
    return;
}
define('IMPACT365_CONFIG_LOADED', true);

/*
 * Optional local override written by the web installer (install.php).
 * It uses putenv()/$_SERVER so the env() reads below pick the values up
 * without redefining constants. Keep this file out of public reach
 * (config/.htaccess denies direct access).
 */
if (is_file(__DIR__ . '/local.php')) {
    require __DIR__ . '/local.php';
}

/* -------------------------------------------------------------------------- */
/* Environment helper                                                          */
/* -------------------------------------------------------------------------- */
if (!function_exists('env')) {
    /**
     * Read an environment variable with a fallback default.
     */
    function env(string $key, $default = null)
    {
        $val = getenv($key);
        if ($val === false || $val === '') {
            return $default;
        }
        return $val;
    }
}

/* -------------------------------------------------------------------------- */
/* Application                                                                 */
/* -------------------------------------------------------------------------- */
define('APP_NAME', 'IMPACT365');
define('APP_TAGLINE', 'Learn. Contribute. Transform Communities.');
define('APP_ENV', env('APP_ENV', 'production'));      // production | development
define('APP_DEBUG', filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL));

/*
 * BASE_URL — public root of the site, no trailing slash.
 * Auto-detected when not provided so the app works on localhost and on
 * Hostinger without manual edits. Set APP_URL env var to force a value.
 */
$detectedScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? null) == 443)
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        ? 'https' : 'http';
$detectedHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$detectedBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
// When a script lives in a sub-folder (e.g. /admin) strip it to reach app root.
foreach (['/admin', '/member', '/organizer', '/corporate', '/trainer', '/auth', '/public'] as $seg) {
    if (str_ends_with($detectedBase, $seg)) {
        $detectedBase = substr($detectedBase, 0, -strlen($seg));
        break;
    }
}
define('BASE_URL', rtrim((string) env('APP_URL', $detectedScheme . '://' . $detectedHost . $detectedBase), '/'));

/* -------------------------------------------------------------------------- */
/* Filesystem paths                                                            */
/* -------------------------------------------------------------------------- */
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', __DIR__);
define('INC_PATH', ROOT_PATH . '/inc');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');
define('ASSET_URL', BASE_URL . '/assets');

/* -------------------------------------------------------------------------- */
/* Database (PDO / MySQL)                                                       */
/* -------------------------------------------------------------------------- */
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'impact365'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_PORT', (int) env('DB_PORT', 3306));
define('DB_CHARSET', 'utf8mb4');

/* -------------------------------------------------------------------------- */
/* Security                                                                    */
/* -------------------------------------------------------------------------- */
define('SESSION_NAME', 'IMPACT365SESS');
define('SESSION_LIFETIME', 60 * 60 * 4);             // 4 hours
define('CSRF_TOKEN_KEY', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 8);
define('LOGIN_MAX_ATTEMPTS', 5);                     // per email / 15 min window
define('LOGIN_LOCKOUT_MINUTES', 15);
define('APP_KEY', env('APP_KEY', 'change-this-32char-application-key'));

/* Upload validation */
define('MAX_UPLOAD_BYTES', 4 * 1024 * 1024);         // 4 MB
define('ALLOWED_IMAGE_EXT', ['jpg', 'jpeg', 'png', 'webp']);
define('ALLOWED_DOC_EXT', ['pdf']);

/* -------------------------------------------------------------------------- */
/* Membership / Payments                                                       */
/* -------------------------------------------------------------------------- */
define('MEMBERSHIP_PRICE', 365.00);
define('MEMBERSHIP_CURRENCY', 'MYR');

/* Billplz — values can also be managed at runtime via the `settings` table.  */
define('BILLPLZ_MODE', env('BILLPLZ_MODE', 'sandbox'));   // sandbox | production
define('BILLPLZ_API_KEY', env('BILLPLZ_API_KEY', ''));
define('BILLPLZ_COLLECTION_ID', env('BILLPLZ_COLLECTION_ID', ''));
define('BILLPLZ_X_SIGNATURE', env('BILLPLZ_X_SIGNATURE', ''));

/* -------------------------------------------------------------------------- */
/* Mail                                                                        */
/* -------------------------------------------------------------------------- */
define('MAIL_FROM', env('MAIL_FROM', 'no-reply@impact365.my'));
define('MAIL_FROM_NAME', APP_NAME);

/* -------------------------------------------------------------------------- */
/* Error reporting                                                             */
/* -------------------------------------------------------------------------- */
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');

date_default_timezone_set('Asia/Kuala_Lumpur');
