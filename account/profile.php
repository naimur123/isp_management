<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $fullName = post('full_name');
    $email = post('email');

    if ($fullName === '') $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please provide a valid email address.';

    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $user['id']]);
        if ($stmt->fetch()) {
            $errors[] = 'This email address is already used by another account.';
        }
    }

    if (!$errors) {
        db()->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ?')->execute([$fullName, $email, $user['id']]);
        log_activity('Profile Updated', 'Users', $user['id'], 'User updated their own profile.');
        flash_set('success', 'Profile updated successfully.');
        redirect(base_url('account/profile.php'));
    }
    $user = array_merge($user, ['full_name' => $fullName, 'email' => $email]);
}

$pageTitle = 'My Profile';
$activeNav = 'profile';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="section-title">My Profile</div>
<div class="section-sub mb-3">Your account details.</div>

<div class="row">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-body p-4">
        <?php if ($errors): ?>
          <div class="alert alert-danger small">
            <ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label required">Full Name</label>
            <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" value="<?= e($user['username']) ?>" disabled>
          </div>
          <div class="mb-3">
            <label class="form-label required">Email</label>
            <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
          </div>
          <div class="mb-4">
            <label class="form-label">Role</label>
            <div><span class="badge <?= $user['role'] === 'admin' ? 'bg-primary' : 'bg-secondary' ?>"><?= $user['role'] === 'admin' ? 'Full Access / Administrator' : 'View Only' ?></span></div>
          </div>
          <div class="mb-4 text-muted small">
            Last Login: <?= format_datetime($user['last_login_at'] ?? null) ?><br>
            Account Created: <?= format_date($user['created_at'] ?? null) ?>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Save Changes</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
