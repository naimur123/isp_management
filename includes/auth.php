<?php
/**
 * Authentication & authorization.
 */

const MAX_FAILED_ATTEMPTS = 5;
const LOCKOUT_MINUTES = 15;

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function current_user(): array
{
    static $user = null;

    if ($user !== null) {
        return $user;
    }
    if (!is_logged_in()) {
        return [];
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([current_user_id()]);
    $row = $stmt->fetch();

    if (!$row || $row['status'] !== 'active') {
        session_unset();
        session_destroy();
        return [];
    }

    $user = $row;
    return $user;
}

function is_admin(): bool
{
    $u = current_user();
    return !empty($u) && $u['role'] === 'admin';
}

function require_login(): void
{
    if (!is_logged_in() || empty(current_user())) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? base_url('dashboard.php');
        redirect(base_url('login.php'));
    }
    session_activity_check();
}

/** Full Access / Administrator only. Enforced server-side, never just hidden in the UI. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        redirect(base_url('errors/403.php'));
    }
}

function session_activity_check(): void
{
    $timeout = (int) setting('session_timeout_minutes', 60) * 60;
    if ($timeout > 0 && !empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        session_start();
        flash_set('warning', 'Your session timed out due to inactivity. Please login again.');
        redirect(base_url('login.php'));
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Attempt to log a user in. Returns true on success, false on failure.
 * $error is populated with a user-facing message on failure.
 */
function attempt_login(string $identifier, string $password, ?string &$error): bool
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'Invalid username/email or password.';
        return false;
    }

    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        $mins = ceil((strtotime($user['locked_until']) - time()) / 60);
        $error = "This account is temporarily locked due to repeated failed logins. Try again in {$mins} minute(s).";
        return false;
    }

    if ($user['status'] !== 'active') {
        $error = 'This account has been disabled. Please contact your administrator.';
        return false;
    }

    if (!password_verify($password, $user['password'])) {
        $attempts = (int) $user['failed_attempts'] + 1;
        $lockedUntil = null;
        if ($attempts >= MAX_FAILED_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60);
        }
        $upd = $pdo->prepare('UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?');
        $upd->execute([$attempts, $lockedUntil, $user['id']]);

        $error = $lockedUntil
            ? 'Too many failed login attempts. This account has been locked for ' . LOCKOUT_MINUTES . ' minutes.'
            : 'Invalid username/email or password.';
        return false;
    }

    // Success: reset failed attempts, regenerate session id, record login.
    $pdo->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?')
        ->execute([$user['id']]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['last_activity'] = time();

    log_activity('Login', 'Auth', $user['id'], 'User logged in.');

    return true;
}

function do_logout(): void
{
    if (is_logged_in()) {
        log_activity('Logout', 'Auth', current_user_id(), 'User logged out.');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    setcookie('remember_token', '', time() - 42000, '/', '', false, true);
    session_unset();
    session_destroy();
}
