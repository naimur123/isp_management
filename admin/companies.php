<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = post('action');

    if ($action === 'create') {
        $name = post('company_name');
        $code = strtoupper(post('company_code'));
        $prefix = strtoupper(post('customer_prefix'));
        $status = post('status', 'active');

        if ($name === '') $errors[] = 'Company Name is required.';
        if (!preg_match('/^[A-Z0-9]{2,10}$/', $code)) $errors[] = 'Company Code must be 2-10 letters/numbers.';
        if (!preg_match('/^[A-Z0-9]{2,10}$/', $prefix)) $errors[] = 'Customer ID Prefix must be 2-10 letters/numbers.';
        if (!in_array($status, ['active', 'inactive'], true)) $errors[] = 'Invalid status.';

        if (!$errors && run_row('SELECT id FROM companies WHERE company_code = ? OR customer_prefix = ?', [$code, $prefix])) {
            $errors[] = 'Company Code or Customer ID Prefix is already in use.';
        }

        if (!$errors) {
            db()->prepare('INSERT INTO companies (company_name, company_code, customer_prefix, status, created_at, updated_at) VALUES (?,?,?,?,NOW(),NOW())')
                ->execute([$name, $code, $prefix, $status]);
            log_activity('Company Created', 'Companies', $code, "Created company $name ($code)");
            flash_set('success', 'Company created successfully.');
            redirect(base_url('admin/companies.php'));
        }
    } elseif ($action === 'update') {
        $id = (int) post('id');
        $name = post('company_name');
        $status = post('status', 'active');

        if ($name === '') $errors[] = 'Company Name is required.';
        if (!in_array($status, ['active', 'inactive'], true)) $errors[] = 'Invalid status.';

        if (!$errors) {
            $existing = run_row('SELECT company_code FROM companies WHERE id = ?', [$id]);
            db()->prepare('UPDATE companies SET company_name=?, status=?, updated_at=NOW() WHERE id=?')
                ->execute([$name, $status, $id]);
            log_activity('Company Updated', 'Companies', $existing['company_code'] ?? '', "Updated company $name");
            flash_set('success', 'Company updated successfully.');
            redirect(base_url('admin/companies.php'));
        }
    } elseif ($action === 'toggle') {
        $id = (int) post('id');
        $c = run_row('SELECT * FROM companies WHERE id = ?', [$id]);
        if ($c) {
            $newStatus = $c['status'] === 'active' ? 'inactive' : 'active';
            db()->prepare('UPDATE companies SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
            log_activity('Company ' . ($newStatus === 'active' ? 'Activated' : 'Deactivated'), 'Companies', $c['company_code'], '');
            flash_set('success', 'Company "' . e($c['company_name']) . '" is now ' . e($newStatus) . '. (Company/Category/Zone masters are deactivated, never deleted, so historical reports keep working.)');
        }
        redirect(base_url('admin/companies.php'));
    }
}

$rows = run_all('SELECT co.*, (SELECT COUNT(*) FROM customers c WHERE c.company_id=co.id AND c.deleted_at IS NULL) customer_count FROM companies co ORDER BY co.company_name');

$pageTitle = 'Companies';
$activeNav = 'admin_companies';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><div class="section-title">Company Master</div><div class="section-sub">Each company keeps its own permanent Customer ID sequence.</div></div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#companyModal" onclick="openCompanyModal()"><i class="fa-solid fa-plus me-1"></i>Add Company</button>
</div>

<?php if ($errors): ?><div class="alert alert-danger small"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead><tr><th>Company Name</th><th>Code</th><th>ID Prefix</th><th>Last Sequence</th><th>Customers</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td class="fw-semibold"><?= e($r['company_name']) ?></td>
          <td><span class="badge bg-light text-dark border"><?= e($r['company_code']) ?></span></td>
          <td><?= e($r['customer_prefix']) ?>-XXXXX</td>
          <td><?= (int) $r['last_customer_sequence'] ?></td>
          <td><?= (int) $r['customer_count'] ?></td>
          <td><?= $r['status'] === 'active' ? '<span class="badge bg-success-subtle text-success-emphasis border">Active</span>' : '<span class="badge bg-danger-subtle text-danger-emphasis border">Inactive</span>' ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-primary" onclick='openCompanyModal(<?= json_encode($r) ?>)'><i class="fa-solid fa-pen"></i></button>
            <form action="<?= e(base_url('admin/companies.php')) ?>" method="post" class="d-inline">
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

<div class="modal fade" id="companyModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="cmAction" value="create">
        <input type="hidden" name="id" id="cmId" value="">
        <div class="modal-header"><h5 class="modal-title" id="cmTitle">Add Company</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label required">Company Name</label><input type="text" name="company_name" id="cmName" class="form-control" required></div>
          <div class="mb-3"><label class="form-label required">Company Code</label><input type="text" name="company_code" id="cmCode" class="form-control" required maxlength="10" style="text-transform:uppercase;"></div>
          <div class="mb-3"><label class="form-label required">Customer ID Prefix</label><input type="text" name="customer_prefix" id="cmPrefix" class="form-control" required maxlength="10" style="text-transform:uppercase;">
            <div class="form-text" id="cmPrefixHelp">Cannot be changed after customers have been created under this company.</div>
          </div>
          <div class="mb-3"><label class="form-label required">Status</label>
            <select name="status" id="cmStatus" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>
</div>

<?php $extraScripts = "<script>
function openCompanyModal(row){
  var isEdit = !!row;
  document.getElementById('cmTitle').textContent = isEdit ? 'Edit Company' : 'Add Company';
  document.getElementById('cmAction').value = isEdit ? 'update' : 'create';
  document.getElementById('cmId').value = isEdit ? row.id : '';
  document.getElementById('cmName').value = isEdit ? row.company_name : '';
  document.getElementById('cmCode').value = isEdit ? row.company_code : '';
  document.getElementById('cmPrefix').value = isEdit ? row.customer_prefix : '';
  document.getElementById('cmCode').disabled = isEdit;
  document.getElementById('cmPrefix').disabled = isEdit;
  document.getElementById('cmStatus').value = isEdit ? row.status : 'active';
  new bootstrap.Modal(document.getElementById('companyModal')).show();
}
</script>";
require_once __DIR__ . '/../includes/footer.php'; ?>
