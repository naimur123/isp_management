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

$companies = list_companies(); // dynamic - never hard-coded to TCL/JOL/DNL

$zoneRows = run_all(
    "SELECT z.id, z.zone_name,
        SUM(CASE WHEN c.status='Active' THEN 1 ELSE 0 END) active_customers,
        SUM(CASE WHEN c.status='Inactive' THEN 1 ELSE 0 END) inactive_customers,
        COUNT(DISTINCT c.id) total_customers,
        COALESCE(SUM(m.billing_amount),0) billing,
        COALESCE(SUM(m.bandwidth_mbps),0) bw
     FROM zones z
     LEFT JOIN customers c ON c.zone_id = z.id AND c.deleted_at IS NULL $custExtra
     LEFT JOIN monthly_records m ON m.customer_id = c.id AND m.deleted_at IS NULL $mrExtra
     GROUP BY z.id ORDER BY z.zone_name",
    array_merge($custExtraParams, $mrExtraParams)
);

// Per-zone, per-company customer counts (dynamic column set).
$companyCountsRaw = run_all(
    "SELECT z.id zone_id, co.id company_id, COUNT(DISTINCT c.id) cnt
     FROM zones z
     JOIN customers c ON c.zone_id = z.id AND c.deleted_at IS NULL $custExtra
     JOIN companies co ON co.id = c.company_id
     GROUP BY z.id, co.id",
    $custExtraParams
);
$companyCounts = [];
foreach ($companyCountsRaw as $r) {
    $companyCounts[$r['zone_id']][$r['company_id']] = (int) $r['cnt'];
}

$grandBilling = array_sum(array_column($zoneRows, 'billing'));

$years = year_options();
$pageTitle = 'Zone Report';
$activeNav = 'report_zone';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
  <div><div class="section-title">Zone Report</div><div class="section-sub">Company columns are generated dynamically from your Company Master</div></div>
  <div class="d-flex gap-2">
    <a href="<?= e(base_url('reports/export.php?type=zone&format=xlsx&' . build_query())) ?>" class="btn btn-outline-success"><i class="fa-solid fa-file-excel me-1"></i>Excel</a>
    <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Print</button>
  </div>
</div>

<div class="card mb-3 no-print">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3"><label class="form-label">Month</label><input type="month" name="month" class="form-control" value="<?= e($month) ?>"></div>
      <div class="col-md-3"><label class="form-label">Year</label><select name="year" class="form-select"><option value="">All</option><?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= (string) $year === (string) $y ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option><option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option><option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
      <div class="col-md-3 d-flex gap-1"><button class="btn btn-primary flex-fill" type="submit"><i class="fa-solid fa-filter"></i> Apply</button><a href="<?= e(base_url('reports/zone.php')) ?>" class="btn btn-outline-secondary flex-fill"><i class="fa-solid fa-rotate-left"></i></a></div>
    </form>
  </div>
</div>

<div class="print-only mb-3"><h5 class="fw-bold mb-0"><?= e(setting('software_name')) ?> — Zone Report</h5><div class="small">Generated: <?= format_date(date('Y-m-d')) ?> <?= format_time(date('Y-m-d H:i:s')) ?></div></div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 datatable">
      <thead>
        <tr>
          <th>Area/Zone</th><th>Active</th><th>Inactive</th><th>Total</th>
          <?php foreach ($companies as $co): ?><th><?= e($co['company_code']) ?></th><?php endforeach; ?>
          <th>Billing</th><th>BW Sold</th><th>Avg Billing</th><th>Billing Share %</th><th class="no-print">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($zoneRows as $r): ?>
        <tr>
          <td class="fw-semibold"><?= e($r['zone_name']) ?></td>
          <td><?= number_format($r['active_customers']) ?></td>
          <td><?= number_format($r['inactive_customers']) ?></td>
          <td><?= number_format($r['total_customers']) ?></td>
          <?php foreach ($companies as $co): ?>
          <td><?= number_format($companyCounts[$r['id']][$co['id']] ?? 0) ?></td>
          <?php endforeach; ?>
          <td><?= format_currency($r['billing']) ?></td>
          <td><?= format_bandwidth($r['bw']) ?></td>
          <td><?= format_currency(safe_divide($r['billing'], $r['active_customers'])) ?></td>
          <td><?= format_percent(safe_percent($r['billing'], $grandBilling)) ?></td>
          <td class="no-print"><a href="<?= e(base_url('reports/zone_customers.php?zone=' . $r['id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-list"></i></a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
