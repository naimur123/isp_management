<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/import_helpers.php';
require_admin();

$token = get_param('token');
$ext = get_param('ext');
if (!$token || !preg_match('/^[a-f0-9]{32}$/', $token) || !in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
    flash_set('danger', 'Invalid or expired import session.');
    redirect(base_url('import/upload.php'));
}
$path = import_upload_dir() . '/' . $token . '.' . $ext;
if (!is_file($path)) {
    flash_set('danger', 'The uploaded file could not be found. Please upload it again.');
    redirect(base_url('import/upload.php'));
}

try {
    $parsed = parse_import_file($path);
} catch (Throwable $e) {
    flash_set('danger', 'This file could not be read. Please make sure it is a valid Excel/CSV file. (' . e($e->getMessage()) . ')');
    redirect(base_url('import/upload.php'));
}

$summary = $parsed['summary'];
$originalName = $_SESSION['import_' . $token]['original_name'] ?? basename($path);

$pageTitle = 'Excel Preview';
$activeNav = 'import_upload';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="section-title">Excel Import Preview</div>
<div class="section-sub mb-3">File: <strong><?= e($originalName) ?></strong> &middot; Nothing has been saved yet.</div>

<div class="row g-3 mb-3">
  <div class="col-md-3 col-6"><div class="card kpi-card"><div class="kpi-label">Total Rows</div><div class="kpi-value"><?= $summary['total'] ?></div></div></div>
  <div class="col-md-3 col-6"><div class="card kpi-card"><div class="kpi-label">Valid</div><div class="kpi-value text-success"><?= $summary['valid'] ?></div></div></div>
  <div class="col-md-3 col-6"><div class="card kpi-card"><div class="kpi-label">Duplicates</div><div class="kpi-value text-warning"><?= $summary['duplicate'] ?></div></div></div>
  <div class="col-md-3 col-6"><div class="card kpi-card"><div class="kpi-label">Errors</div><div class="kpi-value text-danger"><?= $summary['error'] ?></div></div></div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <?php $readyCount = $summary['valid'] + $summary['duplicate']; ?>
    <?php if ($readyCount > 0): ?>
      <p class="mb-3"><strong><?= $readyCount ?></strong> record(s) are ready for import<?= $summary['error'] ? ' (' . $summary['error'] . ' row(s) will be skipped due to errors)' : '' ?>.</p>
      <form method="post" action="<?= e(base_url('import/process.php')) ?>" class="d-flex flex-wrap align-items-center gap-3">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="ext" value="<?= e($ext) ?>">
        <div>
          <label class="form-label mb-1">If a monthly record already exists:</label>
          <div class="d-flex gap-3">
            <div class="form-check"><input class="form-check-input" type="radio" name="duplicate_mode" value="skip" id="dmSkip" checked><label class="form-check-label" for="dmSkip">Skip Existing Records (recommended)</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="duplicate_mode" value="update" id="dmUpdate"><label class="form-check-label" for="dmUpdate">Update Existing Records</label></div>
          </div>
        </div>
        <div class="ms-auto d-flex gap-2">
          <a href="<?= e(base_url('import/upload.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
          <?php if ($summary['error']): ?>
          <a href="<?= e(base_url('import/error_report.php?stage=preview&token=' . $token . '&ext=' . $ext)) ?>" class="btn btn-outline-danger"><i class="fa-solid fa-file-arrow-down me-1"></i>Download Error Report</a>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Confirm Import</button>
        </div>
      </form>
    <?php else: ?>
      <div class="alert alert-danger mb-0">All rows in this file failed validation. Please fix the errors and upload again.</div>
      <a href="<?= e(base_url('import/error_report.php?stage=preview&token=' . $token . '&ext=' . $ext)) ?>" class="btn btn-outline-danger mt-2"><i class="fa-solid fa-file-arrow-down me-1"></i>Download Error Report</a>
      <a href="<?= e(base_url('import/upload.php')) ?>" class="btn btn-outline-secondary mt-2">Upload Again</a>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header">Row-by-Row Preview</div>
  <div class="table-responsive" style="max-height:600px;">
    <table class="table table-hover mb-0 align-middle">
      <thead>
        <tr><th>Row</th><th>Customer ID</th><th>Customer Name</th><th>Company</th><th>Category</th><th>Zone</th><th>Billing Month</th><th>Billing</th><th>BW</th><th>Status</th><th>Validation</th></tr>
      </thead>
      <tbody>
        <?php foreach ($parsed['rows'] as $r): ?>
        <tr class="<?= $r['row_status'] === 'error' ? 'table-danger' : ($r['row_status'] === 'duplicate' ? 'table-warning' : '') ?>">
          <td><?= $r['excel_row'] ?></td>
          <td><?= e($r['customer_id_raw'] ?: '(new)') ?></td>
          <td><?= e($r['customer_name']) ?></td>
          <td><?= e($r['company_raw']) ?></td>
          <td><?= e($r['category_raw']) ?></td>
          <td><?= e($r['zone_raw']) ?></td>
          <td><?= $r['billing_month_norm'] ? format_month($r['billing_month_norm']) : e((string) $r['billing_month_raw']) ?></td>
          <td><?= $r['billing_norm'] !== null ? format_currency($r['billing_norm']) : e((string) $r['billing_raw']) ?></td>
          <td><?= $r['bw_norm'] !== null ? format_bandwidth($r['bw_norm']) : e((string) $r['bw_raw']) ?></td>
          <td><?= e($r['status_norm']) ?></td>
          <td>
            <?php foreach ($r['badges'] as $b):
              $cls = 'bg-secondary';
              if ($b === 'Valid') $cls = 'bg-success';
              elseif (str_starts_with($b, 'Duplicate')) $cls = 'bg-warning text-dark';
              elseif (str_starts_with($b, 'Invalid') || str_starts_with($b, 'Missing')) $cls = 'bg-danger';
              elseif ($b === 'Auto Generate ID') $cls = 'bg-info text-dark';
              elseif (str_starts_with($b, 'Possible Existing')) $cls = 'bg-warning text-dark';
            ?>
              <span class="badge <?= $cls ?> mb-1"><?= e($b) ?></span>
            <?php endforeach; ?>
            <?php if ($r['errors']): ?><div class="small text-danger mt-1"><?= e(implode(' ', $r['errors'])) ?></div><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
