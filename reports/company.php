<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$month = get_param('month');
$year = get_param('year');
$status = get_param('status');

$mrExtra = '';
$mrExtraParams = [];
if ($month !== '') { $mrExtra = ' AND m.billing_month = ?'; $mrExtraParams[] = $month . '-01'; }
elseif ($year !== '') { $mrExtra = ' AND YEAR(m.billing_month) = ?'; $mrExtraParams[] = $year; }
if ($status !== '') { $mrExtra .= ' AND m.status = ?'; $mrExtraParams[] = $status; }

$custExtra = '';
$custExtraParams = [];
if ($status !== '') { $custExtra = ' AND c.status = ?'; $custExtraParams[] = $status; }

$rows = run_all(
    "SELECT co.id, co.company_name,
        SUM(CASE WHEN c.status='Active' THEN 1 ELSE 0 END) active_customers,
        SUM(CASE WHEN c.status='Inactive' THEN 1 ELSE 0 END) inactive_customers,
        COUNT(DISTINCT c.id) total_customers,
        COALESCE(SUM(m.billing_amount),0) billing,
        COALESCE(SUM(m.bandwidth_mbps),0) bw
     FROM companies co
     LEFT JOIN customers c ON c.company_id = co.id AND c.deleted_at IS NULL $custExtra
     LEFT JOIN monthly_records m ON m.customer_id = c.id AND m.deleted_at IS NULL $mrExtra
     GROUP BY co.id ORDER BY co.company_name",
    array_merge($custExtraParams, $mrExtraParams)
);

$grandBilling = array_sum(array_column($rows, 'billing'));
$grandCustomers = array_sum(array_column($rows, 'total_customers'));

$years = year_options();
$pageTitle = 'Company Report';
$activeNav = 'report_company';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
  <div><div class="section-title">Company Report</div><div class="section-sub">Click a company to drill down into its customers</div></div>
  <div class="d-flex gap-2">
    <a href="<?= e(base_url('reports/export.php?type=company&format=xlsx&' . build_query())) ?>" class="btn btn-outline-success"><i class="fa-solid fa-file-excel me-1"></i>Excel</a>
    <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Print</button>
  </div>
</div>

<div class="card mb-3 no-print">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3"><label class="form-label">Month</label><input type="month" name="month" class="form-control" value="<?= e($month) ?>"></div>
      <div class="col-md-3"><label class="form-label">Year</label><select name="year" class="form-select"><option value="">All</option><?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= (string) $year === (string) $y ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option><option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option><option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
      <div class="col-md-3 d-flex gap-1"><button class="btn btn-primary flex-fill" type="submit"><i class="fa-solid fa-filter"></i> Apply</button><a href="<?= e(base_url('reports/company.php')) ?>" class="btn btn-outline-secondary flex-fill"><i class="fa-solid fa-rotate-left"></i></a></div>
    </form>
  </div>
</div>

<div class="print-only mb-3"><h5 class="fw-bold mb-0"><?= e(setting('software_name')) ?> — Company Report</h5><div class="small">Generated: <?= format_date(date('Y-m-d')) ?> <?= format_time(date('Y-m-d H:i:s')) ?></div></div>

<div class="row g-3">
  <?php foreach ($rows as $r): ?>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <h5 class="fw-bold mb-0"><?= e($r['company_name']) ?></h5>
          <span class="badge bg-primary-subtle text-primary-emphasis border"><?= format_percent(safe_percent($r['total_customers'], $grandCustomers)) ?> of customers</span>
        </div>
        <table class="table table-borderless table-sm small mb-2">
          <tr><th class="text-muted">Active Customers</th><td class="text-end fw-semibold"><?= number_format($r['active_customers']) ?></td></tr>
          <tr><th class="text-muted">Inactive Customers</th><td class="text-end"><?= number_format($r['inactive_customers']) ?></td></tr>
          <tr><th class="text-muted">Total Customers</th><td class="text-end fw-semibold"><?= number_format($r['total_customers']) ?></td></tr>
          <tr><th class="text-muted">Billing</th><td class="text-end fw-semibold"><?= format_currency($r['billing']) ?></td></tr>
          <tr><th class="text-muted">BW Sold</th><td class="text-end"><?= format_bandwidth($r['bw']) ?></td></tr>
          <tr><th class="text-muted">Average Billing</th><td class="text-end"><?= format_currency(safe_divide($r['billing'], $r['active_customers'])) ?></td></tr>
          <tr><th class="text-muted">Billing Share %</th><td class="text-end"><?= format_percent(safe_percent($r['billing'], $grandBilling)) ?></td></tr>
        </table>
        <a href="<?= e(base_url('customers/index.php?company=' . $r['id'])) ?>" class="btn btn-sm btn-outline-primary w-100 no-print"><i class="fa-solid fa-list me-1"></i>View Customers</a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
