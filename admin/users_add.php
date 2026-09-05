<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$errors = [];
$form = ['full_name' => '', 'username' => '', 'email' => '', 'role' => 'viewer', 'status' => 'active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    foreach (['full_name', 'username', 'email', 'role', 'status'] as $f) $form[$f] = post($f);
    $password = post('password');
    $confirm = post('confirm_password');

    if ($form['full_name'] === '') $errors[] = 'Full Name is required.';
    if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $form['username'])) $errors[] = 'Username must be 3-60 characters (letters, numbers, dot, underscore, hyphen only).';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please provide a valid email address.';
    if (!in_array($form['role'], ['admin', 'viewer'], true)) $errors[] = 'Invalid role.';
    if (!in_array($form['status'], ['active', 'inactive'], true)) $errors[] = 'Invalid status.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Password and confirmation do not match.';

    if (!$errors) {
        $dup = run_row('SELECT id FROM users WHERE username = ? OR email = ?', [$form['username'], $form['email']]);
        if ($dup) $errors[] = 'This username or email is already in use.';
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        db()->prepare('INSERT INTO users (full_name, username, email, password, role, status, must_change_password, created_at, updated_at) VALUES (?,?,?,?,?,?,1,NOW(),NOW())')
            ->execute([$form['full_name'], $form['username'], $form['email'], $hash, $form['role'], $form['status']]);
        log_activity('User Created', 'Users', $form['username'], 'Created user account ' . $form['username']);
        flash_set('success', 'User "' . e($form['full_name']) . '" created successfully.');
        redirect(base_url('admin/users.php'));
    }
}

$pageTitle = 'Add User';
$activeNav = 'admin_users';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="section-title">Add User</div>
<div class="section-sub mb-3">Create a new Full Access or View Only account.</div>

<div class="row">
  <div class="col-lg-6">
    <div class="card"><div class="card-body p-4">
      <?php if ($errors): ?><div class="alert alert-danger small"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label required">Full Name</label><input type="text" name="full_name" class="form-control" required value="<?= e($form['full_name']) ?>"></div>
        <div class="mb-3"><label class="form-label required">Username</label><input type="text" name="username" class="form-control" required value="<?= e($form['username']) ?>"></div>
        <div class="mb-3"><label class="form-label required">Email</label><input type="email" name="email" class="form-control" required value="<?= e($form['email']) ?>"></div>
        <div class="row">
          <div class="col-md-6 mb-3"><label class="form-label required">Password</label><input type="password" name="password" class="form-control" minlength="8" required></div>
          <div class="col-md-6 mb-3"><label class="form-label required">Confirm Password</label><input type="password" name="confirm_password" class="form-control" minlength="8" required></div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label required">Role</label>
            <select name="role" class="form-select">
              <option value="admin" <?= $form['role'] === 'admin' ? 'selected' : '' ?>>Full Access / Administrator</option>
              <option value="viewer" <?= $form['role'] === 'viewer' ? 'selected' : '' ?>>View Only</option>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label required">Status</label>
            <select name="status" class="form-select">
              <option value="active" <?= $form['status'] === 'active' ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Create User</button>
        <a href="<?= e(base_url('admin/users.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
      </form>
    </div></div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
