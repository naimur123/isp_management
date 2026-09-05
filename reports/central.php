<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$month = get_param('month');
$year = get_param('year');
$companyId = get_param('company');
$categoryId = get_param('category');
$zoneId = get_param('zone');
$status = get_param('status');

$custConds = ['c.deleted_at IS NULL'];
$custParams = [];
if ($companyId !== '') { $custConds[] = 'c.company_id = ?'; $custParams[] = $companyId; }
if ($categoryId !== '') { $custConds[] = 'c.category_id = ?'; $custParams[] = $categoryId; }
if ($zoneId !== '') { $custConds[] = 'c.zone_id = ?'; $custParams[] = $zoneId; }
if ($status !== '') { $custConds[] = 'c.status = ?'; $custParams[] = $status; }
$custWhereSql = implode(' AND ', $custConds);

$mrConds = array_merge(['m.deleted_at IS NULL'], $custConds);
$mrParams = $custParams;
if ($status !== '') { $mrConds[] = 'm.status = ?'; $mrParams[] = $status; }
if ($month !== '') { $mrConds[] = 'm.billing_month = ?'; $mrParams[] = $month . '-01'; }
elseif ($year !== '') { $mrConds[] = 'YEAR(m.billing_month) = ?'; $mrParams[] = $year; }
$mrWhereSql = implode(' AND ', $mrConds);

$grandBilling = (float) run_scalar("SELECT COALESCE(SUM(m.billing_amount),0) FROM monthly_records m JOIN customers c ON c.id=m.customer_id WHERE $mrWhereSql", $mrParams);

$rows = run_all(
    "SELECT z.zone_name, co.company_name, cat.category_name,
            SUM(CASE WHEN c.status='Active' THEN 1 ELSE 0 END) active_customers,
            COUNT(DISTINCT c.id) total_customers,
            COALESCE(SUM(m.billing_amount),0) billing,
            COALESCE(SUM(m.bandwidth_mbps),0) bw
     FROM customers c
     JOIN companies co ON co.id=c.company_id
     JOIN categories cat ON cat.id=c.category_id
     JOIN zones z ON z.id=c.zone_id
     LEFT JOIN monthly_records m ON m.customer_id = c.id AND m.deleted_at IS NULL" .
     ($month !== '' ? ' AND m.billing_month = ?' : ($year !== '' ? ' AND YEAR(m.billing_month) = ?' : '')) .
     ($status !== '' ? ' AND m.status = ?' : '') .
    " WHERE $custWhereSql
     GROUP BY z.id, co.id, cat.id
     HAVING total_customers > 0
     ORDER BY z.zone_name, co.company_name, cat.category_name",
    array_merge($custParams, $month !== '' ? [$month . '-01'] : ($year !== '' ? [$year] : []), $status !== '' ? [$status] : [])
);

$companies = list_companies();
$categories = list_categories();
$zones = list_zones();
$years = year_options();

$pageTitle = 'Central Report';
$activeNav = 'report_central';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
  <div>
    <div class="section-title">Central Report</div>
    <div class="section-sub">Zone &times; Company &times; Category breakdown</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= e(base_url('reports/export.php?type=central&format=xlsx&' . build_query())) ?>" class="btn btn-outline-success"><i class="fa-solid fa-file-excel me-1"></i>Excel</a>
    <a href="<?= e(base_url('reports/export.php?type=central&format=csv&' . build_query())) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv me-1"></i>CSV</a>
    <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Print</button>
  </div>
</div>

<div class="card mb-3 no-print">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-2"><label class="form-label">Month</label><input type="month" name="month" class="form-control" value="<?= e($month) ?>"></div>
      <div class="col-md-2"><label class="form-label">Year</label><select name="year" class="form-select"><option value="">All</option><?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= (string) $year === (string) $y ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label">Company</label><select name="company" class="form-select"><option value="">All</option><?= options_html($companies, 'id', 'company_name', $companyId) ?></select></div>
      <div class="col-md-2"><label class="form-label">Category</label><select name="category" class="form-select"><option value="">All</option><?= options_html($categories, 'id', 'category_name', $categoryId) ?></select></div>
      <div class="col-md-2"><label class="form-label">Zone</label><select name="zone" class="form-select"><option value="">All</option><?= options_html($zones, 'id', 'zone_name', $zoneId) ?></select></div>
      <div class="col-md-1"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option><option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option><option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
      <div class="col-md-1 d-flex gap-1"><button class="btn btn-primary flex-fill" type="submit"><i class="fa-solid fa-filter"></i></button><a href="<?= e(base_url('reports/central.php')) ?>" class="btn btn-outline-secondary flex-fill"><i class="fa-solid fa-rotate-left"></i></a></div>
    </form>
  </div>
</div>

<div class="print-only mb-3">
  <h5 class="fw-bold mb-0"><?= e(setting('software_name')) ?> — Central Report</h5>
  <div class="small">Generated: <?= format_date(date('Y-m-d')) ?> <?= format_time(date('Y-m-d H:i:s')) ?> by <?= e(current_user()['full_name'] ?? '') ?>
  <?= $month !== '' ? ' &middot; Month: ' . format_month($month . '-01') : '' ?><?= $year !== '' ? ' &middot; Year: ' . e($year) : '' ?></div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 datatable">
      <thead><tr><th>Area/Zone</th><th>Company</th><th>Category</th><th>Active Customers</th><th>Total Customers</th><th>Billing</th><th>BW Sold</th><th>Avg Billing</th><th>Billing Share %</th></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="9"><div class="empty-state"><i class="fa-solid fa-table"></i>No data found for the selected filters.</div></td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="<?= e(base_url('reports/zone_customers.php?zone_name=' . urlencode($r['zone_name']))) ?>"><?= e($r['zone_name']) ?></a></td>
          <td><?= e($r['company_name']) ?></td>
          <td><?= e($r['category_name']) ?></td>
          <td><?= number_format($r['active_customers']) ?></td>
          <td><?= number_format($r['total_customers']) ?></td>
          <td><?= format_currency($r['billing']) ?></td>
          <td><?= format_bandwidth($r['bw']) ?></td>
          <td><?= format_currency(safe_divide($r['billing'], $r['active_customers'])) ?></td>
          <td><?= format_percent(safe_percent($r['billing'], $grandBilling)) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
