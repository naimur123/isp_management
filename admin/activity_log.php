<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$userId = get_param('user');
$module = get_param('module');
$dateFrom = get_param('date_from');
$dateTo = get_param('date_to');

$where = ['1=1'];
$params = [];
if ($userId !== '') { $where[] = 'a.user_id = ?'; $params[] = $userId; }
if ($module !== '') { $where[] = 'a.module = ?'; $params[] = $module; }
if ($dateFrom !== '') { $where[] = 'DATE(a.created_at) >= ?'; $params[] = $dateFrom; }
if ($dateTo !== '') { $where[] = 'DATE(a.created_at) <= ?'; $params[] = $dateTo; }
$whereSql = implode(' AND ', $where);

$total = (int) run_scalar("SELECT COUNT(*) FROM activity_logs a WHERE $whereSql", $params);
$page = current_page();
$perPage = page_size();
$offset = ($page - 1) * $perPage;

$rows = run_all("SELECT a.* FROM activity_logs a WHERE $whereSql ORDER BY a.id DESC LIMIT $perPage OFFSET $offset", $params);

$users = run_all('SELECT id, full_name FROM users ORDER BY full_name');
$modules = run_all('SELECT DISTINCT module FROM activity_logs ORDER BY module');

$pageTitle = 'Activity Log';
$activeNav = 'admin_activity';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="section-title">Activity / Audit Log</div>
<div class="section-sub mb-3">Full Access / Administrator visibility only.</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3"><label class="form-label">User</label><select name="user" class="form-select"><option value="">All</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= (string) $userId === (string) $u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><label class="form-label">Module</label><select name="module" class="form-select"><option value="">All</option><?php foreach ($modules as $m): ?><option value="<?= e($m['module']) ?>" <?= $module === $m['module'] ? 'selected' : '' ?>><?= e($m['module']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>"></div>
      <div class="col-md-2"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>"></div>
      <div class="col-md-2 d-flex gap-1"><button class="btn btn-primary flex-fill" type="submit"><i class="fa-solid fa-filter"></i></button><a href="<?= e(base_url('admin/activity_log.php')) ?>" class="btn btn-outline-secondary flex-fill"><i class="fa-solid fa-rotate-left"></i></a></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Date/Time</th><th>User</th><th>Action</th><th>Module</th><th>Record</th><th>Description</th><th>IP Address</th></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-list-check"></i>No activity recorded yet.</div></td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td class="text-nowrap"><?= format_datetime($r['created_at']) ?></td>
          <td><?= e($r['user_name'] ?? 'System') ?></td>
          <td><span class="badge bg-light text-dark border"><?= e($r['action']) ?></span></td>
          <td><?= e($r['module']) ?></td>
          <td><?= e($r['record_id']) ?: '-' ?></td>
          <td class="small text-muted"><?= e($r['description']) ?></td>
          <td class="small"><?= e($r['ip_address']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white"><?= render_pagination($total, $page, $perPage) ?></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
