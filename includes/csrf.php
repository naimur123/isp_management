<?php
/**
 * CSRF token generation & verification.
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string) $token);
}

function csrf_require(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
        http_response_code(419);
        if (is_ajax_request()) {
            json_response(['success' => false, 'message' => 'Your session has expired. Please refresh the page and try again.'], 419);
        }
        flash_set('danger', 'Your session has expired or the request could not be verified. Please try again.');
        $redirect = $_SERVER['HTTP_REFERER'] ?? base_url('dashboard.php');
        redirect($redirect);
    }
}
