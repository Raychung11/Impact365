<?php
/**
 * IMPACT365 — Core Helper Library
 * -----------------------------------------------------------------------------
 * Reusable, dependency-free helpers shared by every module. Bootstraps the
 * session, exposes DB query wrappers (PDO prepared statements only), CSRF
 * protection, flash messages, validation, secure uploads and small utilities.
 *
 * Include this once at the top of every entry point.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db_config.php';

/* -------------------------------------------------------------------------- */
/* Session bootstrap                                                           */
/* -------------------------------------------------------------------------- */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(SESSION_NAME);
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Lightweight idle timeout + periodic id rotation.
    $now = time();
    if (isset($_SESSION['_last']) && ($now - (int) $_SESSION['_last']) > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['_last'] = $now;
    if (!isset($_SESSION['_born'])) {
        $_SESSION['_born'] = $now;
    } elseif ($now - (int) $_SESSION['_born'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_born'] = $now;
    }
}
start_session();

/* -------------------------------------------------------------------------- */
/* Output escaping                                                             */
/* -------------------------------------------------------------------------- */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape for use inside a URL query string. */
function eu(?string $value): string
{
    return rawurlencode((string) $value);
}

/* -------------------------------------------------------------------------- */
/* Input helpers                                                               */
/* -------------------------------------------------------------------------- */
function input(string $key, $default = null)
{
    $val = $_POST[$key] ?? $_GET[$key] ?? $default;
    if (is_string($val)) {
        $val = trim($val);
    }
    return $val;
}

function post(string $key, $default = null)
{
    $val = $_POST[$key] ?? $default;
    return is_string($val) ? trim($val) : $val;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function int_val($v): int
{
    return (int) filter_var($v, FILTER_SANITIZE_NUMBER_INT);
}

/* -------------------------------------------------------------------------- */
/* Database query wrappers — prepared statements ONLY                          */
/* -------------------------------------------------------------------------- */
function db_run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_one(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}

function db_val(string $sql, array $params = [])
{
    $val = db_run($sql, $params)->fetchColumn();
    return $val === false ? null : $val;
}

function db_insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $place = array_map(static fn ($c) => ':' . $c, $cols);
    $sql = sprintf(
        'INSERT INTO `%s` (`%s`) VALUES (%s)',
        $table,
        implode('`,`', $cols),
        implode(',', $place)
    );
    db_run($sql, $data);
    return (int) db()->lastInsertId();
}

function db_update(string $table, array $data, string $where, array $whereParams = []): int
{
    $set = implode(',', array_map(static fn ($c) => "`$c` = :set_$c", array_keys($data)));
    $params = $whereParams;
    foreach ($data as $k => $v) {
        $params['set_' . $k] = $v;
    }
    $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, $set, $where);
    return db_run($sql, $params)->rowCount();
}

/* -------------------------------------------------------------------------- */
/* Settings (key/value table, cached per request)                              */
/* -------------------------------------------------------------------------- */
function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all('SELECT skey, svalue FROM settings') as $r) {
            $cache[$r['skey']] = $r['svalue'];
        }
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, ?string $value): void
{
    db_run(
        'INSERT INTO settings (skey, svalue) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE svalue = :v2',
        ['k' => $key, 'v' => $value, 'v2' => $value]
    );
}

/* -------------------------------------------------------------------------- */
/* CSRF protection                                                             */
/* -------------------------------------------------------------------------- */
function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_KEY];
}

function csrf_field(): string
{
    return '<input type="hidden" name="' . CSRF_TOKEN_KEY . '" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = (string) ($_POST[CSRF_TOKEN_KEY] ?? '');
    if (!hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Invalid or expired security token. Please go back and try again.');
    }
}

/* -------------------------------------------------------------------------- */
/* Flash messages                                                              */
/* -------------------------------------------------------------------------- */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function render_flashes(): string
{
    $out = '';
    foreach (take_flashes() as $f) {
        $cls = match ($f['type']) {
            'success' => 'alert-success',
            'error'   => 'alert-error',
            'warning' => 'alert-warning',
            default   => 'alert-info',
        };
        $out .= '<div class="alert ' . $cls . '">' . e($f['msg']) . '</div>';
    }
    return $out;
}

/* -------------------------------------------------------------------------- */
/* Navigation                                                                  */
/* -------------------------------------------------------------------------- */
/**
 * Root-relative app URL — resolves against whatever host/scheme the browser
 * used. Use this for all in-page links, assets and redirects.
 */
function url(string $path = ''): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

/**
 * Absolute URL — only for things that leave the browser: verification &
 * reset emails, Billplz callback/redirect URLs, and displaying the callback
 * URL in admin settings.
 */
function abs_url(string $path = ''): string
{
    return ABS_ORIGIN . BASE_PATH . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    $loc = preg_match('#^https?://#i', $path) ? $path : url($path);
    header('Location: ' . $loc);
    exit;
}

function back(string $fallback = '/'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    redirect($ref !== '' ? $ref : $fallback);
}

function old(string $key, string $default = ''): string
{
    $val = $_SESSION['_old'][$key] ?? $_POST[$key] ?? $default;
    return e(is_string($val) ? $val : $default);
}

function flash_old(array $data): void
{
    unset($data[CSRF_TOKEN_KEY], $data['password'], $data['password_confirm']);
    $_SESSION['_old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

/* -------------------------------------------------------------------------- */
/* Validation                                                                  */
/* -------------------------------------------------------------------------- */
function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Malaysian phone numbers: 01x-xxxxxxx, +60..., 60..., landlines 0x-xxxxxxx.
 */
function valid_my_phone(string $phone): bool
{
    $p = preg_replace('/[\s\-()]/', '', $phone);
    return (bool) preg_match('/^(\+?60|0)[1-9]\d{7,9}$/', (string) $p);
}

function normalize_my_phone(string $phone): string
{
    $p = preg_replace('/[\s\-()]/', '', $phone);
    if (str_starts_with((string) $p, '+60')) {
        return $p;
    }
    if (str_starts_with((string) $p, '60')) {
        return '+' . $p;
    }
    if (str_starts_with((string) $p, '0')) {
        return '+6' . $p;
    }
    return (string) $p;
}

/* -------------------------------------------------------------------------- */
/* Tokens, codes & slugs                                                       */
/* -------------------------------------------------------------------------- */
function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function random_code(int $len = 8): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // no ambiguous chars
    $out = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

function slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower(trim((string) $text, '-'));
    $text = preg_replace('~[^-\w]+~', '', $text);
    return $text === '' ? 'item' : $text;
}

/**
 * Guarantee a unique slug within a table.
 */
function unique_slug(string $table, string $base, ?int $ignoreId = null): string
{
    $slug = slugify($base);
    $candidate = $slug;
    $i = 1;
    while (true) {
        $sql = "SELECT id FROM `$table` WHERE slug = :s";
        $params = ['s' => $candidate];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }
        if (db_one($sql, $params) === null) {
            return $candidate;
        }
        $candidate = $slug . '-' . (++$i);
    }
}

/* -------------------------------------------------------------------------- */
/* Formatting                                                                  */
/* -------------------------------------------------------------------------- */
function money($amount, string $currency = 'MYR'): string
{
    $sym = $currency === 'MYR' ? 'RM' : ($currency . ' ');
    return $sym . number_format((float) $amount, 2);
}

function fdate(?string $dt, string $fmt = 'd M Y'): string
{
    if (!$dt || $dt === '0000-00-00' || str_starts_with($dt, '0000')) {
        return '-';
    }
    $ts = strtotime($dt);
    return $ts ? date($fmt, $ts) : '-';
}

function fdatetime(?string $dt): string
{
    return fdate($dt, 'd M Y, g:i A');
}

function time_ago(?string $dt): string
{
    if (!$dt) {
        return '-';
    }
    $diff = time() - (int) strtotime($dt);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
    return fdate($dt);
}

function status_badge(string $status): string
{
    $map = [
        'active' => 'ok', 'approved' => 'ok', 'paid' => 'ok', 'attended' => 'ok',
        'success' => 'ok', 'completed' => 'ok', 'fulfilled' => 'ok',
        'pending' => 'warn', 'requested' => 'warn', 'clicked' => 'warn',
        'registered' => 'info', 'draft' => 'muted', 'free' => 'info',
        'rejected' => 'bad', 'failed' => 'bad', 'cancelled' => 'bad',
        'suspended' => 'bad', 'expired' => 'bad', 'declined' => 'bad',
        'no_show' => 'bad', 'void' => 'muted',
    ];
    $cls = $map[$status] ?? 'muted';
    return '<span class="badge badge-' . $cls . '">' . e(ucfirst(str_replace('_', ' ', $status))) . '</span>';
}

/* -------------------------------------------------------------------------- */
/* Pagination                                                                  */
/* -------------------------------------------------------------------------- */
function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page  = max(1, min($page, $pages));
    return [
        'page'   => $page,
        'pages'  => $pages,
        'offset' => ($page - 1) * $perPage,
        'total'  => $total,
        'per'    => $perPage,
    ];
}

function pager_html(array $p, string $baseUrl): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $sep = str_contains($baseUrl, '?') ? '&' : '?';
    $html = '<nav class="pager">';
    if ($p['page'] > 1) {
        $html .= '<a href="' . e($baseUrl . $sep . 'page=' . ($p['page'] - 1)) . '">&laquo; Prev</a>';
    }
    $html .= '<span class="pager-info">Page ' . $p['page'] . ' of ' . $p['pages'] . '</span>';
    if ($p['page'] < $p['pages']) {
        $html .= '<a href="' . e($baseUrl . $sep . 'page=' . ($p['page'] + 1)) . '">Next &raquo;</a>';
    }
    return $html . '</nav>';
}

/* -------------------------------------------------------------------------- */
/* Secure file uploads                                                         */
/* -------------------------------------------------------------------------- */
/**
 * Validate and store an uploaded file. Returns the relative path
 * (e.g. "events/abc123.jpg") on success, or null when no file was sent.
 * Throws RuntimeException on a validation failure.
 *
 * @param string $kind  one of: events | esg | profiles | resources
 * @param string $type  'image' or 'doc'
 */
function handle_upload(string $field, string $kind, string $type = 'image'): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];

    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload failed (code ' . $f['error'] . ').');
    }
    if ($f['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('File too large. Maximum is ' . (MAX_UPLOAD_BYTES / 1048576) . ' MB.');
    }
    if (!is_uploaded_file($f['tmp_name'])) {
        throw new RuntimeException('Invalid upload.');
    }

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowed = $type === 'doc' ? ALLOWED_DOC_EXT : ALLOWED_IMAGE_EXT;
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Unsupported file type. Allowed: ' . implode(', ', $allowed) . '.');
    }

    // Verify real MIME, never trust the client-provided type.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($f['tmp_name']);
    $okMimes = $type === 'doc'
        ? ['application/pdf']
        : ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $okMimes, true)) {
        throw new RuntimeException('File content does not match its extension.');
    }
    if ($type === 'image' && @getimagesize($f['tmp_name']) === false) {
        throw new RuntimeException('Uploaded image is not valid.');
    }

    $dirMap = [
        'events'    => 'events',
        'esg'       => 'esg',
        'profiles'  => 'profiles',
        'resources' => 'resources',
    ];
    $sub = $dirMap[$kind] ?? 'misc';
    $destDir = UPLOAD_PATH . '/' . $sub;
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = $destDir . '/' . $name;
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        throw new RuntimeException('Could not store the uploaded file.');
    }
    @chmod($dest, 0644);

    return $sub . '/' . $name;
}

function upload_url(?string $relative, string $fallback = ''): string
{
    if (!$relative) {
        return $fallback;
    }
    return UPLOAD_URL . '/' . ltrim($relative, '/');
}

/* -------------------------------------------------------------------------- */
/* Misc                                                                        */
/* -------------------------------------------------------------------------- */
function client_ip(): string
{
    return (string) ($_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0');
}

function user_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

function json_response($data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function csv_download(string $filename, array $headers, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $r) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

function excerpt(?string $text, int $len = 140): string
{
    $text = trim(strip_tags((string) $text));
    if (mb_strlen($text) <= $len) {
        return $text;
    }
    return mb_substr($text, 0, $len) . '…';
}
