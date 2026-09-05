<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(base_url('admin/users.php'));
csrf_require();

$id = (int) post('id');
if ($id === current_user_id()) {
    flash_set('danger', 'You cannot disable your own account.');
    redirect(base_url('admin/users.php'));
}
$user = run_row('SELECT * FROM users WHERE id = ?', [$id]);
if ($user) {
    $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
    db()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
    log_activity('User ' . ($newStatus === 'active' ? 'Enabled' : 'Disabled'), 'Users', $user['username'], 'Set status to ' . $newStatus);
    flash_set('success', 'User "' . e($user['full_name']) . '" is now ' . e($newStatus) . '.');
} else {
    flash_set('danger', 'User not found.');
}
redirect(base_url('admin/users.php'));
