<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/import_helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('import/upload.php'));
}
csrf_require();

$token = post('token');
$ext = post('ext');
$duplicateMode = post('duplicate_mode', 'skip') === 'update' ? 'update' : 'skip';

if (!$token || !preg_match('/^[a-f0-9]{32}$/', $token) || !in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
    flash_set('danger', 'Invalid or expired import session.');
    redirect(base_url('import/upload.php'));
}
$path = import_upload_dir() . '/' . $token . '.' . $ext;
if (!is_file($path)) {
    flash_set('danger', 'The uploaded file could not be found. Please upload it again.');
    redirect(base_url('import/upload.php'));
}

$originalName = $_SESSION['import_' . $token]['original_name'] ?? basename($path);

$parsed = parse_import_file($path);
$result = process_import_rows($parsed, $duplicateMode);

$stmt = db()->prepare(
    'INSERT INTO import_history (filename, uploaded_by, total_rows, imported_rows, updated_rows, skipped_rows, failed_rows, duplicate_mode, status, created_at)
     VALUES (?,?,?,?,?,?,?,?,?,NOW())'
);
$stmt->execute([
    $originalName, current_user_id(), $parsed['summary']['total'],
    $result['imported'], $result['updated'], $result['skipped'], $result['failed'],
    $duplicateMode, 'completed',
]);
$importId = (int) db()->lastInsertId();

if ($result['errorLog']) {
    $errStmt = db()->prepare(
        'INSERT INTO import_errors (import_id, excel_row, customer_id, customer_name, error_type, error_description) VALUES (?,?,?,?,?,?)'
    );
    foreach ($result['errorLog'] as $e) {
        $errStmt->execute([$importId, $e['excel_row'], $e['customer_id'], $e['customer_name'], $e['error_type'], $e['error_description']]);
    }
}

@unlink($path);
unset($_SESSION['import_' . $token]);

log_activity('Excel Imported', 'Import', $importId, "Imported '$originalName': {$result['imported']} new, {$result['updated']} updated, {$result['skipped']} skipped, {$result['failed']} failed.");

$pageTitle = 'Import Completed';
$activeNav = 'import_upload';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="section-title">Import Completed</div>
<div class="section-sub mb-3">File: <strong><?= e($originalName) ?></strong></div>

<div class="row g-3 mb-3">
  <div class="col-md-2 col-6"><div class="card kpi-card"><div class="kpi-label">Total Rows</div><div class="kpi-value"><?= $parsed['summary']['total'] ?></div></div></div>
  <div class="col-md-2 col-6"><div class="card kpi-card"><div class="kpi-label">Imported</div><div class="kpi-value text-success"><?= $result['imported'] ?></div></div></div>
  <div class="col-md-2 col-6"><div class="card kpi-card"><div class="kpi-label">Updated</div><div class="kpi-value text-primary"><?= $result['updated'] ?></div></div></div>
  <div class="col-md-2 col-6"><div class="card kpi-card"><div class="kpi-label">Skipped</div><div class="kpi-value text-warning"><?= $result['skipped'] ?></div></div></div>
  <div class="col-md-2 col-6"><div class="card kpi-card"><div class="kpi-label">Failed</div><div class="kpi-value text-danger"><?= $result['failed'] ?></div></div></div>
</div>

<div class="alert alert-success"><i class="fa-solid fa-circle-check me-1"></i>Excel import completed successfully.</div>

<div class="d-flex gap-2">
  <a href="<?= e(base_url('billing/index.php')) ?>" class="btn btn-primary"><i class="fa-solid fa-receipt me-1"></i>View Imported Records</a>
  <a href="<?= e(base_url('import/history_detail.php?id=' . $importId)) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-list me-1"></i>View Import Details</a>
  <a href="<?= e(base_url('import/upload.php')) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-upload me-1"></i>Import Another File</a>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
