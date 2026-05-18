<?php
/**
 * IMPACT365 — Authentication & Authorization
 * -----------------------------------------------------------------------------
 * Role-based access control, secure session-backed auth, login throttling,
 * registration with optional referral capture, password reset & email
 * verification token management.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/audit.php';

const ROLES = ['public', 'member', 'organizer', 'corporate', 'trainer', 'admin'];

/* -------------------------------------------------------------------------- */
/* Current user                                                                */
/* -------------------------------------------------------------------------- */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']['id']);
}

function user_id(): ?int
{
    return isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
}

function user_role(): string
{
    return $_SESSION['user']['role'] ?? 'public';
}

function has_role(string ...$roles): bool
{
    return in_array(user_role(), $roles, true);
}

/** Admins implicitly satisfy any role gate. */
function user_can(string ...$roles): bool
{
    return user_role() === 'admin' || has_role(...$roles);
}

/* -------------------------------------------------------------------------- */
/* Guards                                                                      */
/* -------------------------------------------------------------------------- */
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? '/';
        flash('warning', 'Please sign in to continue.');
        redirect('auth/login.php');
    }
}

function require_role(string ...$roles): void
{
    require_login();
    if (!user_can(...$roles)) {
        http_response_code(403);
        flash('error', 'You do not have permission to access that area.');
        redirect(dashboard_url());
    }
}

/** Landing dashboard for the logged-in role. */
function dashboard_url(): string
{
    return match (user_role()) {
        'admin'     => 'admin/index.php',
        'organizer' => 'organizer/index.php',
        'corporate' => 'corporate/index.php',
        'trainer'   => 'trainer/index.php',
        'member'    => 'member/index.php',
        default      => 'member/index.php',
    };
}

/* -------------------------------------------------------------------------- */
/* Session establishment                                                       */
/* -------------------------------------------------------------------------- */
function establish_session(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'            => (int) $user['id'],
        'name'          => $user['name'],
        'email'         => $user['email'],
        'role'          => $user['role'],
        'status'        => $user['status'],
        'profile_image' => $user['profile_image'] ?? null,
        'referral_code' => $user['referral_code'] ?? null,
    ];
    $_SESSION['_born'] = time();
}

function refresh_session_user(): void
{
    if (!is_logged_in()) {
        return;
    }
    $u = db_one('SELECT * FROM users WHERE id = :id', ['id' => user_id()]);
    if ($u) {
        establish_session($u);
    } else {
        logout();
    }
}

/* -------------------------------------------------------------------------- */
/* Login throttling                                                            */
/* -------------------------------------------------------------------------- */
function login_locked(string $email): bool
{
    // LOGIN_LOCKOUT_MINUTES is a trusted integer constant — safe to inline,
    // and avoids placeholder-in-INTERVAL edge cases on native prepares.
    $mins = (int) LOGIN_LOCKOUT_MINUTES;
    $fails = (int) db_val(
        "SELECT COUNT(*) FROM login_logs
         WHERE email = :e AND status = 'failed'
           AND created_at > (NOW() - INTERVAL $mins MINUTE)",
        ['e' => $email]
    );
    return $fails >= LOGIN_MAX_ATTEMPTS;
}

function log_login(?int $userId, string $email, string $status): void
{
    db_insert('login_logs', [
        'user_id'    => $userId,
        'email'      => $email,
        'ip'         => client_ip(),
        'user_agent' => user_agent(),
        'status'     => $status,
    ]);
}

/* -------------------------------------------------------------------------- */
/* Authentication actions                                                      */
/* -------------------------------------------------------------------------- */
/**
 * @return array{ok:bool,message:string}
 */
function attempt_login(string $email, string $password): array
{
    $email = strtolower(trim($email));

    if ($email === '' || $password === '') {
        return ['ok' => false, 'message' => 'Email and password are required.'];
    }
    if (login_locked($email)) {
        log_login(null, $email, 'locked');
        return ['ok' => false, 'message' => 'Too many failed attempts. Try again in ' . LOGIN_LOCKOUT_MINUTES . ' minutes.'];
    }

    $user = db_one('SELECT * FROM users WHERE email = :e', ['e' => $email]);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        log_login($user['id'] ?? null, $email, 'failed');
        return ['ok' => false, 'message' => 'Invalid email or password.'];
    }
    if ($user['status'] === 'suspended') {
        return ['ok' => false, 'message' => 'Your account has been suspended. Contact the administrator.'];
    }
    if ($user['status'] === 'pending') {
        return ['ok' => false, 'message' => 'Your account is awaiting administrator approval.'];
    }
    if ($user['status'] === 'rejected') {
        return ['ok' => false, 'message' => 'Your account application was not approved.'];
    }

    // Opportunistic rehash if PHP's default cost changed.
    if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
        db_update('users', ['password_hash' => password_hash($password, PASSWORD_BCRYPT)], 'id = :id', ['id' => $user['id']]);
    }

    db_update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);
    log_login((int) $user['id'], $email, 'success');
    establish_session($user);
    audit('login', 'user', $user['id']);

    return ['ok' => true, 'message' => 'Welcome back, ' . $user['name'] . '.'];
}

function logout(): void
{
    if (is_logged_in()) {
        audit('logout', 'user', user_id());
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* -------------------------------------------------------------------------- */
/* Registration                                                                */
/* -------------------------------------------------------------------------- */
function generate_referral_code(string $name): string
{
    $base = strtoupper(substr(preg_replace('/[^a-z]/i', '', $name) . 'IMP', 0, 3));
    do {
        $code = $base . random_int(1000, 9999);
    } while (db_one('SELECT id FROM users WHERE referral_code = :c', ['c' => $code]) !== null);
    return $code;
}

/**
 * @return array{ok:bool,message:string,user_id?:int}
 */
function register_user(array $in): array
{
    $name     = trim((string) ($in['name'] ?? ''));
    $email    = strtolower(trim((string) ($in['email'] ?? '')));
    $phone    = trim((string) ($in['phone'] ?? ''));
    $password = (string) ($in['password'] ?? '');
    $confirm  = (string) ($in['password_confirm'] ?? '');
    $role     = in_array($in['role'] ?? '', ['member', 'organizer', 'corporate', 'trainer'], true)
        ? $in['role'] : 'member';
    $refCode  = trim((string) ($in['referral_code'] ?? ''));

    if ($name === '' || mb_strlen($name) < 2) {
        return ['ok' => false, 'message' => 'Please enter your full name.'];
    }
    if (!valid_email($email)) {
        return ['ok' => false, 'message' => 'Please enter a valid email address.'];
    }
    if ($phone !== '' && !valid_my_phone($phone)) {
        return ['ok' => false, 'message' => 'Please enter a valid Malaysian phone number.'];
    }
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return ['ok' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.'];
    }
    if ($password !== $confirm) {
        return ['ok' => false, 'message' => 'Passwords do not match.'];
    }
    if (db_one('SELECT id FROM users WHERE email = :e', ['e' => $email]) !== null) {
        return ['ok' => false, 'message' => 'An account with this email already exists.'];
    }

    // Resolve referrer (optional). Code may also arrive via ?ref= cookie.
    $referrerId = null;
    if ($refCode === '' && !empty($_SESSION['_ref'])) {
        $refCode = $_SESSION['_ref'];
    }
    if ($refCode !== '') {
        $ref = db_one('SELECT id FROM users WHERE referral_code = :c', ['c' => strtoupper($refCode)]);
        $referrerId = $ref['id'] ?? null;
    }

    // Organizer/corporate/trainer self-service accounts require admin approval.
    $status = in_array($role, ['organizer', 'corporate', 'trainer'], true) ? 'pending' : 'active';

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $userId = db_insert('users', [
            'name'           => $name,
            'email'          => $email,
            'phone'          => $phone !== '' ? normalize_my_phone($phone) : null,
            'password_hash'  => password_hash($password, PASSWORD_BCRYPT),
            'role'           => $role,
            'status'         => $status,
            'email_verified' => 0,
            'verify_token'   => random_token(16),
            'referral_code'  => generate_referral_code($name),
            'referred_by'    => $referrerId,
        ]);
        db_insert('user_profiles', ['user_id' => $userId]);
        db_insert('referral_wallets', ['user_id' => $userId]);

        if ($referrerId) {
            $referralId = db_insert('referrals', [
                'referrer_id'      => $referrerId,
                'referred_user_id' => $userId,
                'code'             => strtoupper($refCode),
                'source'           => substr((string) ($_SESSION['_ref_src'] ?? 'direct'), 0, 60),
                'status'           => 'registered',
            ]);
            $reward = (float) setting('referral_signup_reward', '10.00');
            if ($reward > 0) {
                db_insert('referral_rewards', [
                    'referral_id' => $referralId,
                    'referrer_id' => $referrerId,
                    'amount'      => $reward,
                    'type'        => 'signup',
                    'status'      => 'pending',
                    'note'        => 'Signup referral: ' . $name,
                ]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('[IMPACT365] register failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Registration could not be completed. Please try again.'];
    }

    unset($_SESSION['_ref'], $_SESSION['_ref_src']);
    audit('register', 'user', $userId, 'role=' . $role);

    $msg = $status === 'pending'
        ? 'Account created. An administrator will review and approve your ' . $role . ' account.'
        : 'Account created successfully. You can now sign in.';

    return ['ok' => true, 'message' => $msg, 'user_id' => $userId];
}

/* -------------------------------------------------------------------------- */
/* Password reset                                                              */
/* -------------------------------------------------------------------------- */
function create_password_reset(string $email): ?array
{
    $user = db_one('SELECT id, name, email FROM users WHERE email = :e', ['e' => strtolower(trim($email))]);
    if (!$user) {
        return null; // caller shows a generic message regardless
    }
    $token = random_token(24);
    db_update('users', [
        'reset_token'   => hash('sha256', $token),
        'reset_expires' => date('Y-m-d H:i:s', time() + 3600),
    ], 'id = :id', ['id' => $user['id']]);
    audit('password_reset_requested', 'user', $user['id']);
    return ['user' => $user, 'token' => $token];
}

function consume_password_reset(string $token, string $newPassword): array
{
    if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
        return ['ok' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.'];
    }
    $user = db_one(
        'SELECT id FROM users WHERE reset_token = :t AND reset_expires > NOW()',
        ['t' => hash('sha256', $token)]
    );
    if (!$user) {
        return ['ok' => false, 'message' => 'This reset link is invalid or has expired.'];
    }
    db_update('users', [
        'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
        'reset_token'   => null,
        'reset_expires' => null,
    ], 'id = :id', ['id' => $user['id']]);
    audit('password_reset_completed', 'user', $user['id']);
    return ['ok' => true, 'message' => 'Password updated. You can now sign in.'];
}

function verify_email_token(string $token): bool
{
    $user = db_one('SELECT id FROM users WHERE verify_token = :t', ['t' => $token]);
    if (!$user) {
        return false;
    }
    db_update('users', ['email_verified' => 1, 'verify_token' => null], 'id = :id', ['id' => $user['id']]);
    audit('email_verified', 'user', $user['id']);
    return true;
}
