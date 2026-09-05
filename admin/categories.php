<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = post('action');

    if ($action === 'create' || $action === 'update') {
        $id = (int) post('id');
        $name = post('category_name');
        $status = post('status', 'active');

        if ($name === '') $errors[] = 'Category Name is required.';
        if (!in_array($status, ['active', 'inactive'], true)) $errors[] = 'Invalid status.';

        if (!$errors) {
            $dupSql = 'SELECT id FROM categories WHERE category_name = ?' . ($id ? ' AND id != ?' : '');
            $dupParams = $id ? [$name, $id] : [$name];
            if (run_row($dupSql, $dupParams)) $errors[] = 'This category name already exists.';
        }

        if (!$errors) {
            if ($action === 'create') {
                db()->prepare('INSERT INTO categories (category_name, status, created_at, updated_at) VALUES (?,?,NOW(),NOW())')->execute([$name, $status]);
                log_activity('Category Created', 'Categories', $name, '');
                flash_set('success', 'Category created successfully.');
            } else {
                db()->prepare('UPDATE categories SET category_name=?, status=?, updated_at=NOW() WHERE id=?')->execute([$name, $status, $id]);
                log_activity('Category Updated', 'Categories', $name, '');
                flash_set('success', 'Category updated successfully.');
            }
            redirect(base_url('admin/categories.php'));
        }
    } elseif ($action === 'toggle') {
        $id = (int) post('id');
        $c = run_row('SELECT * FROM categories WHERE id = ?', [$id]);
        if ($c) {
            $newStatus = $c['status'] === 'active' ? 'inactive' : 'active';
            db()->prepare('UPDATE categories SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
            log_activity('Category ' . ($newStatus === 'active' ? 'Activated' : 'Deactivated'), 'Categories', $c['category_name'], '');
            flash_set('success', 'Category "' . e($c['category_name']) . '" is now ' . e($newStatus) . '.');
        }
        redirect(base_url('admin/categories.php'));
    }
}

$rows = run_all('SELECT cat.*, (SELECT COUNT(*) FROM customers c WHERE c.category_id=cat.id AND c.deleted_at IS NULL) customer_count FROM categories cat ORDER BY cat.category_name');

$pageTitle = 'Categories';
$activeNav = 'admin_categories';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><div class="section-title">Category Master</div><div class="section-sub">Categories are deactivated, never deleted, so historical reports stay accurate.</div></div>
  <button class="btn btn-primary" onclick="openModal()"><i class="fa-solid fa-plus me-1"></i>Add Category</button>
</div>

<?php if ($errors): ?><div class="alert alert-danger small"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead><tr><th>Category Name</th><th>Customers</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td class="fw-semibold"><?= e($r['category_name']) ?></td>
          <td><?= (int) $r['customer_count'] ?></td>
          <td><?= $r['status'] === 'active' ? '<span class="badge bg-success-subtle text-success-emphasis border">Active</span>' : '<span class="badge bg-danger-subtle text-danger-emphasis border">Inactive</span>' ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-primary" onclick='openModal(<?= json_encode($r) ?>)'><i class="fa-solid fa-pen"></i></button>
            <form action="<?= e(base_url('admin/categories.php')) ?>" method="post" class="d-inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-<?= $r['status'] === 'active' ? 'warning' : 'success' ?>"><i class="fa-solid fa-<?= $r['status'] === 'active' ? 'ban' : 'check' ?>"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="catModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="mAction" value="create"><input type="hidden" name="id" id="mId" value="">
        <div class="modal-header"><h5 class="modal-title" id="mTitle">Add Category</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label required">Category Name</label><input type="text" name="category_name" id="mName" class="form-control" required></div>
          <div class="mb-3"><label class="form-label required">Status</label><select name="status" id="mStatus" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>
</div>
<?php $extraScripts = "<script>
function openModal(row){
  var isEdit = !!row;
  document.getElementById('mTitle').textContent = isEdit ? 'Edit Category' : 'Add Category';
  document.getElementById('mAction').value = isEdit ? 'update' : 'create';
  document.getElementById('mId').value = isEdit ? row.id : '';
  document.getElementById('mName').value = isEdit ? row.category_name : '';
  document.getElementById('mStatus').value = isEdit ? row.status : 'active';
  new bootstrap.Modal(document.getElementById('catModal')).show();
}
</script>";
require_once __DIR__ . '/../includes/footer.php'; ?>
