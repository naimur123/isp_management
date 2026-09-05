<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$page = current_page();
$perPage = page_size();
$offset = ($page - 1) * $perPage;

$total = (int) db()->query('SELECT COUNT(*) c FROM import_history')->fetch()['c'];
$stmt = db()->prepare(
    "SELECT i.*, u.full_name AS uploaded_by_name FROM import_history i LEFT JOIN users u ON u.id = i.uploaded_by
     ORDER BY i.id DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute();
$rows = $stmt->fetchAll();

$pageTitle = 'Import History';
$activeNav = 'import_history';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <div class="section-title">Import History</div>
    <div class="section-sub"><?= number_format($total) ?> import(s)</div>
  </div>
  <a href="<?= e(base_url('import/upload.php')) ?>" class="btn btn-primary"><i class="fa-solid fa-upload me-1"></i>New Import</a>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead><tr><th>Import ID</th><th>File Name</th><th>Uploaded By</th><th>Upload Date/Time</th><th>Total</th><th>Imported</th><th>Updated</th><th>Skipped</th><th>Failed</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php if (!$rows): ?>
        <tr><td colspan="11"><div class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i>No imports yet.</div></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td>#<?= $r['id'] ?></td>
          <td><?= e($r['filename']) ?></td>
          <td><?= e($r['uploaded_by_name'] ?? '-') ?></td>
          <td><?= format_datetime($r['created_at']) ?></td>
          <td><?= (int) $r['total_rows'] ?></td>
          <td class="text-success"><?= (int) $r['imported_rows'] ?></td>
          <td class="text-primary"><?= (int) $r['updated_rows'] ?></td>
          <td class="text-warning"><?= (int) $r['skipped_rows'] ?></td>
          <td class="text-danger"><?= (int) $r['failed_rows'] ?></td>
          <td><span class="badge bg-success-subtle text-success-emphasis border"><?= e(ucfirst($r['status'])) ?></span></td>
          <td class="text-end"><a href="<?= e(base_url('import/history_detail.php?id=' . $r['id'])) ?>" class="btn btn-sm btn-outline-secondary">View Details</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white"><?= render_pagination($total, $page, $perPage) ?></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
