<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$id = (int) get_param('id');
$stmt = db()->prepare('SELECT i.*, u.full_name AS uploaded_by_name FROM import_history i LEFT JOIN users u ON u.id=i.uploaded_by WHERE i.id = ?');
$stmt->execute([$id]);
$import = $stmt->fetch();
if (!$import) {
    flash_set('danger', 'Import record not found.');
    redirect(base_url('import/history.php'));
}

$errStmt = db()->prepare('SELECT * FROM import_errors WHERE import_id = ? ORDER BY excel_row');
$errStmt->execute([$id]);
$errorRows = $errStmt->fetchAll();

$pageTitle = 'Import Details';
$activeNav = 'import_history';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <div class="section-title">Import #<?= $import['id'] ?> — <?= e($import['filename']) ?></div>
    <div class="section-sub">Uploaded by <?= e($import['uploaded_by_name'] ?? '-') ?> on <?= format_datetime($import['created_at']) ?></div>
  </div>
  <?php if ($errorRows): ?>
  <a href="<?= e(base_url('import/error_report.php?stage=final&import_id=' . $id)) ?>" class="btn btn-outline-danger"><i class="fa-solid fa-file-arrow-down me-1"></i>Download Error Report</a>
  <?php endif; ?>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-2 col-6"><div class="card kpi-card"><div class="kpi-label">Total</div><div class="kpi-value"><?= (int) $import['total_rows'] ?></div></div></div>
  <div class="col-md-2 col-6"><div class="card kpi-card"><div class="kpi-label">Imported</div><div class="kpi-value text-success"><?= (int) $import['imported_rows'] ?></div></div></div>
  <div class="col-md-2 col-6"><div class="card kpi-card"><div class="kpi-label">Updated</div><div class="kpi-value text-primary"><?= (int) $import['updated_rows'] ?></div></div></div>
  <div class="col-md-2 col-6"><div class="card kpi-card"><div class="kpi-label">Skipped</div><div class="kpi-value text-warning"><?= (int) $import['skipped_rows'] ?></div></div></div>
  <div class="col-md-2 col-6"><div class="card kpi-card"><div class="kpi-label">Failed</div><div class="kpi-value text-danger"><?= (int) $import['failed_rows'] ?></div></div></div>
</div>

<div class="card">
  <div class="card-header">Error Report (<?= count($errorRows) ?>)</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Excel Row</th><th>Customer ID</th><th>Customer Name</th><th>Error Type</th><th>Error Description</th></tr></thead>
      <tbody>
        <?php if (!$errorRows): ?>
        <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-circle-check"></i>No errors — every row imported successfully.</div></td></tr>
        <?php endif; ?>
        <?php foreach ($errorRows as $r): ?>
        <tr>
          <td><?= (int) $r['excel_row'] ?></td>
          <td><?= e($r['customer_id']) ?: '-' ?></td>
          <td><?= e($r['customer_name']) ?: '-' ?></td>
          <td><span class="badge bg-danger-subtle text-danger-emphasis border"><?= e($r['error_type']) ?></span></td>
          <td class="small"><?= e($r['error_description']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
