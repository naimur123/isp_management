<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$rows = run_all("SELECT * FROM users ORDER BY id DESC");

$pageTitle = 'User Management';
$activeNav = 'admin_users';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><div class="section-title">User Management</div><div class="section-sub"><?= count($rows) ?> user account(s)</div></div>
  <a href="<?= e(base_url('admin/users_add.php')) ?>" class="btn btn-primary"><i class="fa-solid fa-user-plus me-1"></i>Add User</a>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Created Date</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td class="fw-semibold"><?= e($r['full_name']) ?></td>
          <td><?= e($r['username']) ?></td>
          <td><?= e($r['email']) ?></td>
          <td><span class="badge <?= $r['role'] === 'admin' ? 'bg-primary' : 'bg-secondary' ?>"><?= $r['role'] === 'admin' ? 'Full Access' : 'View Only' ?></span></td>
          <td><?= $r['status'] === 'active' ? '<span class="badge bg-success-subtle text-success-emphasis border">Active</span>' : '<span class="badge bg-danger-subtle text-danger-emphasis border">Inactive</span>' ?></td>
          <td><?= format_datetime($r['last_login_at']) ?></td>
          <td><?= format_date($r['created_at']) ?></td>
          <td class="text-end">
            <a href="<?= e(base_url('admin/users_edit.php?id=' . $r['id'])) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fa-solid fa-pen"></i></a>
            <?php if ((int) $r['id'] !== current_user_id()): ?>
            <form action="<?= e(base_url('admin/users_toggle.php')) ?>" method="post" class="d-inline">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-<?= $r['status'] === 'active' ? 'warning' : 'success' ?>" title="<?= $r['status'] === 'active' ? 'Disable' : 'Enable' ?>">
                <i class="fa-solid fa-<?= $r['status'] === 'active' ? 'lock' : 'unlock' ?>"></i>
              </button>
            </form>
            <form action="<?= e(base_url('admin/users_reset_password.php')) ?>" method="post" class="d-inline" data-confirm="Reset this user's password? A new temporary password will be generated.">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-secondary" title="Reset Password"><i class="fa-solid fa-key"></i></button>
            </form>
            <?php else: ?>
            <span class="badge bg-light text-muted border">You</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
