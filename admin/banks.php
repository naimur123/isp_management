<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = post('action');

    if ($action === 'create' || $action === 'update') {
        $id = (int) post('id');
        $name = post('bank_name');
        $status = post('status', 'active');

        if ($name === '') $errors[] = 'Bank Name is required.';
        if (!in_array($status, ['Active', 'Inactive'], true)) $errors[] = 'Invalid status.';

        if (!$errors) {
            $dupSql = 'SELECT id FROM banks WHERE bank_name = ?' . ($id ? ' AND id != ?' : '');
            $dupParams = $id ? [$name, $id] : [$name];
            if (run_row($dupSql, $dupParams)) $errors[] = 'This bank name already exists.';
        }

        if (!$errors) {
            if ($action === 'create') {
                db()->prepare('INSERT INTO banks (bank_name, status, created_at, created_by, updated_at) VALUES (?,?,NOW(),?,NOW())')->execute([$name, $status, current_user_id()]);
                log_activity('Bank Created', 'Banks', $name, '');
                flash_set('success', 'Bank created successfully.');
            } else {
                db()->prepare('UPDATE banks SET bank_name=?, status=?, updated_by=?, updated_at=NOW() WHERE id=?')->execute([$name, $status, current_user_id(), $id]);
                log_activity('Bank Updated', 'Banks', $name, '');
                flash_set('success', 'Bank updated successfully.');
            }
            redirect(base_url('admin/banks.php'));
        }
    } elseif ($action === 'toggle') {
        $id = (int) post('id');
        $z = run_row('SELECT * FROM banks WHERE id = ?', [$id]);
        if ($z) {
            $newStatus = $z['status'] === 'Active' ? 'Inactive' : 'Active';
            db()->prepare('UPDATE banks SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
            log_activity('Bank ' . ($newStatus === 'Active' ? 'Activated' : 'Deactivated'), 'Banks', $z['bank_name'], '');
            flash_set('success', 'Bank "' . e($z['bank_name']) . '" is now ' . e($newStatus) . '.');
        }
        redirect(base_url('admin/banks.php'));
    }
}

$rows = run_all('SELECT * FROM banks ORDER BY bank_name');

$pageTitle = 'Banks';
$activeNav = 'banks';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><div class="section-title">All Banks</div><div class="section-sub">New banks automatically appear across dashboards and reports.</div></div>
  <button class="btn btn-primary" onclick="openModal()"><i class="fa-solid fa-plus me-1"></i>Add Bank</button>
</div>

<?php if ($errors): ?><div class="alert alert-danger small"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead>
        <tr>
          <th>Bank Name</th>
          <th>Status</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td class="fw-semibold"><?= e($r['bank_name']) ?></td>
          <td><?= status_badge($r['status']) ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-primary" onclick='openModal(<?= json_encode($r) ?>)'><i class="fa-solid fa-pen"></i></button>
            <form action="<?= e(base_url('admin/banks.php')) ?>" method="post" class="d-inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-<?= $r['status'] === 'Active' ? 'warning' : 'success' ?>"><i class="fa-solid fa-<?= $r['status'] === 'Active' ? 'ban' : 'check' ?>"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="bankModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="mAction" value="create"><input type="hidden" name="id" id="mId" value="">
        <div class="modal-header"><h5 class="modal-title" id="mTitle">Add Bank</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label required">Bank Name</label><input type="text" name="bank_name" id="mName" class="form-control" required></div>
          <div class="mb-3"><label class="form-label required">Status</label><select name="status" id="mStatus" class="form-select"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>
</div>
<?php $extraScripts = "<script>
function openModal(row){
  var isEdit = !!row;
  document.getElementById('mTitle').textContent = isEdit ? 'Edit Bank' : 'Add Bank';
  document.getElementById('mAction').value = isEdit ? 'update' : 'create';
  document.getElementById('mId').value = isEdit ? row.id : '';
  document.getElementById('mName').value = isEdit ? row.bank_name : '';
  document.getElementById('mStatus').value = isEdit ? row.status : 'Active';
  new bootstrap.Modal(document.getElementById('bankModal')).show();
}
</script>";
require_once __DIR__ . '/../includes/footer.php'; ?>
