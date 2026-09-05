<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(base_url('admin/users.php'));
csrf_require();

$id = (int) post('id');
$user = run_row('SELECT * FROM users WHERE id = ?', [$id]);
if ($user) {
    $newPassword = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(9))), 0, 10) . '!1';
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    db()->prepare('UPDATE users SET password = ?, must_change_password = 1, failed_attempts = 0, locked_until = NULL WHERE id = ?')->execute([$hash, $id]);
    log_activity('Password Reset', 'Users', $user['username'], 'Administrator reset password for ' . $user['username']);
    flash_set('success', 'Password for "' . e($user['full_name']) . '" has been reset. Temporary password: <strong>' . e($newPassword) . '</strong> — please share this securely; the user will be asked to set a new password on next login.');
} else {
    flash_set('danger', 'User not found.');
}
redirect(base_url('admin/users.php'));
