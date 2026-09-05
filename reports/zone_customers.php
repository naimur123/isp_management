<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$zoneId = get_param('zone');
$zoneName = get_param('zone_name');
$companyId = get_param('company');

$zone = null;
if ($zoneId !== '') {
    $zone = run_row('SELECT * FROM zones WHERE id = ?', [$zoneId]);
} elseif ($zoneName !== '') {
    $zone = run_row('SELECT * FROM zones WHERE zone_name = ?', [$zoneName]);
}

if (!$zone) {
    flash_set('danger', 'Zone not found.');
    redirect(base_url('reports/zone.php'));
}

$where = ['c.deleted_at IS NULL', 'c.zone_id = ?'];
$params = [$zone['id']];
if ($companyId !== '') { $where[] = 'c.company_id = ?'; $params[] = $companyId; }
$whereSql = implode(' AND ', $where);

$rows = run_all(
    "SELECT c.customer_id, c.customer_name, co.company_name, cat.category_name, c.status,
        COALESCE((SELECT SUM(billing_amount) FROM monthly_records m WHERE m.customer_id=c.id AND m.deleted_at IS NULL),0) total_billing,
        COALESCE((SELECT bandwidth_mbps FROM monthly_records m WHERE m.customer_id=c.id AND m.deleted_at IS NULL ORDER BY billing_month DESC LIMIT 1),0) current_bw
     FROM customers c JOIN companies co ON co.id=c.company_id JOIN categories cat ON cat.id=c.category_id
     WHERE $whereSql ORDER BY c.customer_name",
    $params
);

$companies = list_companies();
$pageTitle = 'Zone Customer Report — ' . $zone['zone_name'];
$activeNav = 'report_zone';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-1"><li class="breadcrumb-item"><a href="<?= e(base_url('dashboard.php')) ?>">Dashboard</a></li><li class="breadcrumb-item"><a href="<?= e(base_url('reports/zone.php')) ?>">Zone Report</a></li><li class="breadcrumb-item active"><?= e($zone['zone_name']) ?></li></ol></nav>
    <div class="section-title"><?= e($zone['zone_name']) ?> — Individual Customer List</div>
    <div class="section-sub"><?= number_format(count($rows)) ?> customer(s)</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= e(base_url('reports/export.php?type=zone_customers&format=xlsx&' . build_query())) ?>" class="btn btn-outline-success"><i class="fa-solid fa-file-excel me-1"></i>Excel</a>
    <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Print</button>
  </div>
</div>

<div class="card mb-3 no-print">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <input type="hidden" name="zone" value="<?= (int) $zone['id'] ?>">
      <div class="col-md-4"><label class="form-label">Company</label><select name="company" class="form-select"><option value="">All</option><?= options_html($companies, 'id', 'company_name', $companyId) ?></select></div>
      <div class="col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter"></i> Apply</button></div>
    </form>
  </div>
</div>

<div class="print-only mb-3"><h5 class="fw-bold mb-0"><?= e(setting('software_name')) ?> — Zone Customer Report</h5><div class="small">Zone: <?= e($zone['zone_name']) ?> &middot; Generated: <?= format_date(date('Y-m-d')) ?></div></div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 datatable">
      <thead><tr><th>Customer ID</th><th>Customer Name</th><th>Company</th><th>Category</th><th>Billing (Total)</th><th>BW (Current)</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-users"></i>No customers found in this zone.</div></td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="<?= e(base_url('customers/index.php?q=' . urlencode($r['customer_id']))) ?>" class="text-primary"><?= e($r['customer_id']) ?></a></td>
          <td><?= e($r['customer_name']) ?></td>
          <td><?= e($r['company_name']) ?></td>
          <td><?= e($r['category_name']) ?></td>
          <td><?= format_currency($r['total_billing']) ?></td>
          <td><?= format_bandwidth($r['current_bw']) ?></td>
          <td><?= status_badge($r['status']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
