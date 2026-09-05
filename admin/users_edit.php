<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$id = (int) get_param('id');
$user = run_row('SELECT * FROM users WHERE id = ?', [$id]);
if (!$user) {
    flash_set('danger', 'User not found.');
    redirect(base_url('admin/users.php'));
}

$errors = [];
$form = $user;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $form['full_name'] = post('full_name');
    $form['email'] = post('email');
    $form['role'] = post('role');
    $form['status'] = post('status');

    if ($form['full_name'] === '') $errors[] = 'Full Name is required.';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please provide a valid email address.';
    if (!in_array($form['role'], ['admin', 'viewer'], true)) $errors[] = 'Invalid role.';
    if (!in_array($form['status'], ['active', 'inactive'], true)) $errors[] = 'Invalid status.';
    if ((int) $user['id'] === current_user_id() && $form['role'] !== 'admin') $errors[] = 'You cannot remove your own Full Access role.';
    if ((int) $user['id'] === current_user_id() && $form['status'] !== 'active') $errors[] = 'You cannot deactivate your own account.';

    if (!$errors) {
        $dup = run_row('SELECT id FROM users WHERE email = ? AND id != ?', [$form['email'], $id]);
        if ($dup) $errors[] = 'This email is already used by another account.';
    }

    if (!$errors) {
        db()->prepare('UPDATE users SET full_name=?, email=?, role=?, status=?, updated_at=NOW() WHERE id=?')
            ->execute([$form['full_name'], $form['email'], $form['role'], $form['status'], $id]);
        log_activity('User Updated', 'Users', $user['username'], 'Updated user account ' . $user['username']);
        flash_set('success', 'User updated successfully.');
        redirect(base_url('admin/users.php'));
    }
}

$pageTitle = 'Edit User';
$activeNav = 'admin_users';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="section-title">Edit User — <?= e($user['username']) ?></div>

<div class="row">
  <div class="col-lg-6">
    <div class="card"><div class="card-body p-4">
      <?php if ($errors): ?><div class="alert alert-danger small"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">Username</label><input type="text" class="form-control" value="<?= e($user['username']) ?>" disabled></div>
        <div class="mb-3"><label class="form-label required">Full Name</label><input type="text" name="full_name" class="form-control" required value="<?= e($form['full_name']) ?>"></div>
        <div class="mb-3"><label class="form-label required">Email</label><input type="email" name="email" class="form-control" required value="<?= e($form['email']) ?>"></div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label required">Role</label>
            <select name="role" class="form-select" <?= (int) $user['id'] === current_user_id() ? 'disabled' : '' ?>>
              <option value="admin" <?= $form['role'] === 'admin' ? 'selected' : '' ?>>Full Access / Administrator</option>
              <option value="viewer" <?= $form['role'] === 'viewer' ? 'selected' : '' ?>>View Only</option>
            </select>
            <?php if ((int) $user['id'] === current_user_id()): ?><input type="hidden" name="role" value="<?= e($form['role']) ?>"><?php endif; ?>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label required">Status</label>
            <select name="status" class="form-select" <?= (int) $user['id'] === current_user_id() ? 'disabled' : '' ?>>
              <option value="active" <?= $form['status'] === 'active' ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <?php if ((int) $user['id'] === current_user_id()): ?><input type="hidden" name="status" value="<?= e($form['status']) ?>"><?php endif; ?>
          </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Save Changes</button>
        <a href="<?= e(base_url('admin/users.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
      </form>
    </div></div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
