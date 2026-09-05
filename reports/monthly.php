<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$year = get_param('year');
$companyId = get_param('company');
$categoryId = get_param('category');
$zoneId = get_param('zone');

$conds = ['m.deleted_at IS NULL', 'c.deleted_at IS NULL'];
$params = [];
if ($companyId !== '') { $conds[] = 'c.company_id = ?'; $params[] = $companyId; }
if ($categoryId !== '') { $conds[] = 'c.category_id = ?'; $params[] = $categoryId; }
if ($zoneId !== '') { $conds[] = 'c.zone_id = ?'; $params[] = $zoneId; }
if ($year !== '') { $conds[] = 'YEAR(m.billing_month) = ?'; $params[] = $year; }
$whereSql = implode(' AND ', $conds);

$rows = run_all(
    "SELECT DATE_FORMAT(m.billing_month,'%Y-%m') ym, m.billing_month,
        SUM(CASE WHEN m.status='Active' THEN 1 ELSE 0 END) active_records,
        COUNT(DISTINCT m.customer_id) customers,
        SUM(m.billing_amount) billing,
        SUM(m.bandwidth_mbps) bw
     FROM monthly_records m JOIN customers c ON c.id = m.customer_id
     WHERE $whereSql GROUP BY ym ORDER BY ym",
    $params
);

$companies = list_companies();
$categories = list_categories();
$zones = list_zones();
$years = year_options();

$pageTitle = 'Monthly Report';
$activeNav = 'report_monthly';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
  <div><div class="section-title">Monthly Report</div><div class="section-sub">Month-by-month performance</div></div>
  <div class="d-flex gap-2">
    <a href="<?= e(base_url('reports/export.php?type=monthly&format=xlsx&' . build_query())) ?>" class="btn btn-outline-success"><i class="fa-solid fa-file-excel me-1"></i>Excel</a>
    <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Print</button>
  </div>
</div>

<div class="card mb-3 no-print">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3"><label class="form-label">Year</label><select name="year" class="form-select"><option value="">All</option><?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= (string) $year === (string) $y ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><label class="form-label">Company</label><select name="company" class="form-select"><option value="">All</option><?= options_html($companies, 'id', 'company_name', $companyId) ?></select></div>
      <div class="col-md-3"><label class="form-label">Category</label><select name="category" class="form-select"><option value="">All</option><?= options_html($categories, 'id', 'category_name', $categoryId) ?></select></div>
      <div class="col-md-2"><label class="form-label">Zone</label><select name="zone" class="form-select"><option value="">All</option><?= options_html($zones, 'id', 'zone_name', $zoneId) ?></select></div>
      <div class="col-md-1 d-flex gap-1"><button class="btn btn-primary flex-fill" type="submit"><i class="fa-solid fa-filter"></i></button></div>
    </form>
  </div>
</div>

<div class="print-only mb-3"><h5 class="fw-bold mb-0"><?= e(setting('software_name')) ?> — Monthly Report</h5><div class="small">Generated: <?= format_date(date('Y-m-d')) ?></div></div>

<div class="card mb-3 no-print">
  <div class="card-header">Monthly Billing &amp; Bandwidth Trend</div>
  <div class="card-body"><div class="chart-box"><canvas id="chMonthly"></canvas></div></div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 datatable">
      <thead><tr><th>Month</th><th>Active Records</th><th>Customers</th><th>Total Billing</th><th>BW Sold</th><th>Avg Billing</th><th>Avg BW</th></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-calendar-days"></i>No data found.</div></td></tr><?php endif; ?>
        <?php foreach (array_reverse($rows) as $r): ?>
        <tr>
          <td class="fw-semibold"><?= format_month($r['billing_month']) ?></td>
          <td><?= number_format($r['active_records']) ?></td>
          <td><?= number_format($r['customers']) ?></td>
          <td><?= format_currency($r['billing']) ?></td>
          <td><?= format_bandwidth($r['bw']) ?></td>
          <td><?= format_currency(safe_divide($r['billing'], $r['customers'])) ?></td>
          <td><?= format_bandwidth(safe_divide($r['bw'], $r['customers'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$labels = array_map(fn($r) => format_month($r['billing_month']), $rows);
$billingVals = array_map(fn($r) => (float) $r['billing'], $rows);
$bwVals = array_map(fn($r) => (float) $r['bw'], $rows);
$extraScripts = "<script>
document.addEventListener('DOMContentLoaded', function(){
  new Chart(document.getElementById('chMonthly'), {
    type: 'line',
    data: { labels: " . json_encode($labels) . ", datasets: [
      { label: 'Billing (BDT)', data: " . json_encode($billingVals) . ", borderColor:'#2563eb', backgroundColor:'rgba(37,99,235,.1)', tension:.3, fill:true, yAxisID:'y' },
      { label: 'Bandwidth (Mbps)', data: " . json_encode($bwVals) . ", borderColor:'#16a34a', backgroundColor:'rgba(22,163,74,.1)', tension:.3, fill:true, yAxisID:'y1' }
    ]},
    options: { responsive:true, maintainAspectRatio:false, interaction:{mode:'index',intersect:false},
      scales: { y:{position:'left'}, y1:{position:'right', grid:{drawOnChartArea:false}} } }
  });
});
</script>";
require_once __DIR__ . '/../includes/footer.php'; ?>
