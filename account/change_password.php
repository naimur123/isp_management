<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $current = post('current_password');
    $new = post('new_password');
    $confirm = post('confirm_password');

    $user = current_user();

    if (!password_verify($current, $user['password'])) {
        $errors[] = 'Your current password is incorrect.';
    }
    if (strlen($new) < 8) {
        $errors[] = 'New password must be at least 8 characters long.';
    }
    if ($new !== $confirm) {
        $errors[] = 'New password and confirmation do not match.';
    }

    if (!$errors) {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        db()->prepare('UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?')
            ->execute([$hash, current_user_id()]);
        log_activity('Password Changed', 'Users', current_user_id(), 'User changed their own password.');
        flash_set('success', 'Your password has been updated successfully.');
        redirect(base_url('dashboard.php'));
    }
}

$pageTitle = 'Change Password';
$activeNav = 'change_password';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="section-title">Change Password</div>
<div class="section-sub mb-3">Update your account password. Choose something you don't use anywhere else.</div>

<?php if (!empty(current_user()['must_change_password'])): ?>
<div class="alert alert-warning small"><i class="fa-solid fa-triangle-exclamation me-1"></i>
For security, you must set a new password before continuing to use the system.
</div>
<?php endif; ?>

<div class="row">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-body p-4">
        <?php if ($errors): ?>
          <div class="alert alert-danger small">
            <ul class="mb-0 ps-3">
              <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label required">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label required">New Password</label>
            <input type="password" name="new_password" class="form-control" minlength="8" required>
            <div class="form-text">Minimum 8 characters.</div>
          </div>
          <div class="mb-4">
            <label class="form-label required">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" minlength="8" required>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Update Password</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
