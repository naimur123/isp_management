<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$month = get_param('month');
$year = get_param('year');
$status = get_param('status');

$mrExtra = ''; $mrExtraParams = [];
if ($month !== '') { $mrExtra = ' AND m.billing_month = ?'; $mrExtraParams[] = $month . '-01'; }
elseif ($year !== '') { $mrExtra = ' AND YEAR(m.billing_month) = ?'; $mrExtraParams[] = $year; }
if ($status !== '') { $mrExtra .= ' AND m.status = ?'; $mrExtraParams[] = $status; }

$custExtra = ''; $custExtraParams = [];
if ($status !== '') { $custExtra = ' AND c.status = ?'; $custExtraParams[] = $status; }

$rows = run_all(
    "SELECT cat.id, cat.category_name,
        SUM(CASE WHEN c.status='Active' THEN 1 ELSE 0 END) active_customers,
        COUNT(DISTINCT c.id) total_customers,
        COALESCE(SUM(m.billing_amount),0) billing,
        COALESCE(SUM(m.bandwidth_mbps),0) bw
     FROM categories cat
     LEFT JOIN customers c ON c.category_id = cat.id AND c.deleted_at IS NULL $custExtra
     LEFT JOIN monthly_records m ON m.customer_id = c.id AND m.deleted_at IS NULL $mrExtra
     GROUP BY cat.id ORDER BY cat.category_name",
    array_merge($custExtraParams, $mrExtraParams)
);

$grandBilling = array_sum(array_column($rows, 'billing'));
$grandBw = array_sum(array_column($rows, 'bw'));

$years = year_options();
$pageTitle = 'Category Report';
$activeNav = 'report_category';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
  <div><div class="section-title">Category Report</div><div class="section-sub">Click a category to open its customer list</div></div>
  <div class="d-flex gap-2">
    <a href="<?= e(base_url('reports/export.php?type=category&format=xlsx&' . build_query())) ?>" class="btn btn-outline-success"><i class="fa-solid fa-file-excel me-1"></i>Excel</a>
    <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Print</button>
  </div>
</div>

<div class="card mb-3 no-print">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3"><label class="form-label">Month</label><input type="month" name="month" class="form-control" value="<?= e($month) ?>"></div>
      <div class="col-md-3"><label class="form-label">Year</label><select name="year" class="form-select"><option value="">All</option><?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= (string) $year === (string) $y ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option><option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option><option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
      <div class="col-md-3 d-flex gap-1"><button class="btn btn-primary flex-fill" type="submit"><i class="fa-solid fa-filter"></i> Apply</button><a href="<?= e(base_url('reports/category.php')) ?>" class="btn btn-outline-secondary flex-fill"><i class="fa-solid fa-rotate-left"></i></a></div>
    </form>
  </div>
</div>

<div class="print-only mb-3"><h5 class="fw-bold mb-0"><?= e(setting('software_name')) ?> — Category Report</h5><div class="small">Generated: <?= format_date(date('Y-m-d')) ?> <?= format_time(date('Y-m-d H:i:s')) ?></div></div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 datatable">
      <thead><tr><th>Category</th><th>Active Customers</th><th>Total Customers</th><th>Billing</th><th>BW Sold</th><th>Avg Billing</th><th>Billing Share %</th><th>Bandwidth Share %</th><th class="no-print">Action</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td class="fw-semibold"><?= e($r['category_name']) ?></td>
          <td><?= number_format($r['active_customers']) ?></td>
          <td><?= number_format($r['total_customers']) ?></td>
          <td><?= format_currency($r['billing']) ?></td>
          <td><?= format_bandwidth($r['bw']) ?></td>
          <td><?= format_currency(safe_divide($r['billing'], $r['active_customers'])) ?></td>
          <td><?= format_percent(safe_percent($r['billing'], $grandBilling)) ?></td>
          <td><?= format_percent(safe_percent($r['bw'], $grandBw)) ?></td>
          <td class="no-print"><a href="<?= e(base_url('customers/index.php?category=' . $r['id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-list"></i></a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
