<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = post('action');

    if ($action === 'create' || $action === 'update') {
        $id = (int) post('id');
        $name = post('zone_name');
        $status = post('status', 'active');

        if ($name === '') $errors[] = 'Zone Name is required.';
        if (!in_array($status, ['active', 'inactive'], true)) $errors[] = 'Invalid status.';

        if (!$errors) {
            $dupSql = 'SELECT id FROM zones WHERE zone_name = ?' . ($id ? ' AND id != ?' : '');
            $dupParams = $id ? [$name, $id] : [$name];
            if (run_row($dupSql, $dupParams)) $errors[] = 'This zone name already exists.';
        }

        if (!$errors) {
            if ($action === 'create') {
                db()->prepare('INSERT INTO zones (zone_name, status, created_at, updated_at) VALUES (?,?,NOW(),NOW())')->execute([$name, $status]);
                log_activity('Zone Created', 'Zones', $name, '');
                flash_set('success', 'Zone created successfully.');
            } else {
                db()->prepare('UPDATE zones SET zone_name=?, status=?, updated_at=NOW() WHERE id=?')->execute([$name, $status, $id]);
                log_activity('Zone Updated', 'Zones', $name, '');
                flash_set('success', 'Zone updated successfully.');
            }
            redirect(base_url('admin/zones.php'));
        }
    } elseif ($action === 'toggle') {
        $id = (int) post('id');
        $z = run_row('SELECT * FROM zones WHERE id = ?', [$id]);
        if ($z) {
            $newStatus = $z['status'] === 'active' ? 'inactive' : 'active';
            db()->prepare('UPDATE zones SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
            log_activity('Zone ' . ($newStatus === 'active' ? 'Activated' : 'Deactivated'), 'Zones', $z['zone_name'], '');
            flash_set('success', 'Zone "' . e($z['zone_name']) . '" is now ' . e($newStatus) . '.');
        }
        redirect(base_url('admin/zones.php'));
    }
}

$rows = run_all('SELECT z.*, (SELECT COUNT(*) FROM customers c WHERE c.zone_id=z.id AND c.deleted_at IS NULL) customer_count FROM zones z ORDER BY z.zone_name');

$pageTitle = 'Zones';
$activeNav = 'admin_zones';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><div class="section-title">Area / Zone Master</div><div class="section-sub">New zones automatically appear across dashboards and reports.</div></div>
  <button class="btn btn-primary" onclick="openModal()"><i class="fa-solid fa-plus me-1"></i>Add Zone</button>
</div>

<?php if ($errors): ?><div class="alert alert-danger small"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead><tr><th>Zone Name</th><th>Customers</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td class="fw-semibold"><?= e($r['zone_name']) ?></td>
          <td><?= (int) $r['customer_count'] ?></td>
          <td><?= $r['status'] === 'active' ? '<span class="badge bg-success-subtle text-success-emphasis border">Active</span>' : '<span class="badge bg-danger-subtle text-danger-emphasis border">Inactive</span>' ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-primary" onclick='openModal(<?= json_encode($r) ?>)'><i class="fa-solid fa-pen"></i></button>
            <form action="<?= e(base_url('admin/zones.php')) ?>" method="post" class="d-inline">
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

<div class="modal fade" id="zoneModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="mAction" value="create"><input type="hidden" name="id" id="mId" value="">
        <div class="modal-header"><h5 class="modal-title" id="mTitle">Add Zone</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label required">Zone Name</label><input type="text" name="zone_name" id="mName" class="form-control" required></div>
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
  document.getElementById('mTitle').textContent = isEdit ? 'Edit Zone' : 'Add Zone';
  document.getElementById('mAction').value = isEdit ? 'update' : 'create';
  document.getElementById('mId').value = isEdit ? row.id : '';
  document.getElementById('mName').value = isEdit ? row.zone_name : '';
  document.getElementById('mStatus').value = isEdit ? row.status : 'active';
  new bootstrap.Modal(document.getElementById('zoneModal')).show();
}
</script>";
require_once __DIR__ . '/../includes/footer.php'; ?>
