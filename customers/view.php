<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int) get_param('id');
$stmt = db()->prepare(
    'SELECT c.*, co.company_name, co.customer_prefix, cat.category_name, z.zone_name,
            uc.full_name AS created_by_name, uu.full_name AS updated_by_name
     FROM customers c
     JOIN companies co ON co.id = c.company_id
     JOIN categories cat ON cat.id = c.category_id
     JOIN zones z ON z.id = c.zone_id
     LEFT JOIN users uc ON uc.id = c.created_by
     LEFT JOIN users uu ON uu.id = c.updated_by
     WHERE c.id = ? AND c.deleted_at IS NULL'
);
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    flash_set('danger', 'Customer not found.');
    redirect(base_url('customers/index.php'));
}

$mrStmt = db()->prepare(
    'SELECT * FROM monthly_records WHERE customer_id = ? AND deleted_at IS NULL ORDER BY billing_month ASC'
);
$mrStmt->execute([$id]);
$records = $mrStmt->fetchAll();

$totalBilling = 0;
$latest = null;
$first = null;
foreach ($records as $r) {
    $totalBilling += (float) $r['billing_amount'];
    if ($first === null) $first = $r;
    $latest = $r;
}

$pageTitle = 'Customer Detail — ' . $customer['customer_id'];
$activeNav = 'customer_list';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
  <div>
    <div class="section-title"><?= e($customer['customer_name']) ?> <span class="text-muted">(<?= e($customer['customer_id']) ?>)</span></div>
    <div class="section-sub">Customer Profile &amp; Monthly History</div>
  </div>
  <div class="d-flex gap-2">
    <?php if (is_admin()): ?>
    <a href="<?= e(base_url('customers/edit.php?id=' . $id)) ?>" class="btn btn-outline-primary"><i class="fa-solid fa-pen me-1"></i>Edit</a>
    <a href="<?= e(base_url('billing/add.php?customer=' . $id)) ?>" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Add Monthly Billing</a>
    <?php endif; ?>
    <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Print</button>
  </div>
</div>

<div class="print-only mb-3">
  <h5 class="fw-bold mb-0"><?= e(setting('software_name')) ?></h5>
  <div class="small">Customer Profile Report &middot; Generated: <?= format_date(date('Y-m-d')) ?> <?= format_time(date('Y-m-d H:i:s')) ?> &middot; By: <?= e(current_user()['full_name'] ?? '') ?></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3 col-6">
    <div class="card kpi-card h-100"><div class="d-flex justify-content-between"><div><div class="kpi-label">Status</div><div class="kpi-value" style="font-size:1.1rem;"><?= status_badge($customer['status']) ?></div></div><div class="kpi-icon bg-icon-blue"><i class="fa-solid fa-signal"></i></div></div></div>
  </div>
  <div class="col-md-3 col-6">
    <div class="card kpi-card h-100"><div class="d-flex justify-content-between"><div><div class="kpi-label">Total Historical Billing</div><div class="kpi-value"><?= format_currency($totalBilling) ?></div></div><div class="kpi-icon bg-icon-green"><i class="fa-solid fa-sack-dollar"></i></div></div></div>
  </div>
  <div class="col-md-3 col-6">
    <div class="card kpi-card h-100"><div class="d-flex justify-content-between"><div><div class="kpi-label">Current Billing</div><div class="kpi-value" style="font-size:1.3rem;"><?= $latest ? format_currency($latest['billing_amount']) : '-' ?></div></div><div class="kpi-icon bg-icon-amber"><i class="fa-solid fa-file-invoice-dollar"></i></div></div></div>
  </div>
  <div class="col-md-3 col-6">
    <div class="card kpi-card h-100"><div class="d-flex justify-content-between"><div><div class="kpi-label">Current Bandwidth</div><div class="kpi-value" style="font-size:1.2rem;"><?= $latest ? format_bandwidth($latest['bandwidth_mbps']) : '-' ?></div></div><div class="kpi-icon bg-icon-purple"><i class="fa-solid fa-gauge"></i></div></div></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header">Customer Profile</div>
      <div class="card-body small">
        <table class="table table-borderless table-sm mb-0">
          <tr><th class="text-muted" width="45%">Customer ID</th><td class="fw-semibold"><?= e($customer['customer_id']) ?></td></tr>
          <tr><th class="text-muted">Customer Name</th><td><?= e($customer['customer_name']) ?></td></tr>
          <tr><th class="text-muted">Company</th><td><?= e($customer['company_name']) ?></td></tr>
          <tr><th class="text-muted">Category</th><td><?= e($customer['category_name']) ?></td></tr>
          <tr><th class="text-muted">Area/Zone</th><td><?= e($customer['zone_name']) ?></td></tr>
          <tr><th class="text-muted">Status</th><td><?= status_badge($customer['status']) ?></td></tr>
          <tr><th class="text-muted">Remarks</th><td><?= e($customer['remarks']) ?: '-' ?></td></tr>
          <tr><th class="text-muted">First Record Date</th><td><?= $first ? format_month($first['billing_month']) : format_date($customer['created_at']) ?></td></tr>
          <tr><th class="text-muted">Last Updated</th><td><?= format_datetime($customer['updated_at']) ?></td></tr>
          <tr><th class="text-muted">Created By</th><td><?= e($customer['created_by_name'] ?? '-') ?> on <?= format_date($customer['created_at']) ?> <?= format_time($customer['created_at']) ?></td></tr>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header">Monthly Billing Trend</div>
      <div class="card-body">
        <?php if ($records): ?>
        <div class="chart-box"><canvas id="trendChart"></canvas></div>
        <?php else: ?>
        <div class="empty-state py-3"><i class="fa-solid fa-chart-line"></i>No billing history yet.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Monthly History</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Month</th><th>Billing</th><th>BW Sold</th><th>Status</th><th>Remarks</th></tr></thead>
          <tbody>
            <?php if (!$records): ?>
            <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-receipt"></i>No monthly records found for this customer.</div></td></tr>
            <?php endif; ?>
            <?php foreach (array_reverse($records) as $r): ?>
            <tr>
              <td class="fw-semibold"><?= format_month($r['billing_month']) ?></td>
              <td><?= format_currency($r['billing_amount']) ?></td>
              <td><?= format_bandwidth($r['bandwidth_mbps']) ?></td>
              <td><?= status_badge($r['status']) ?></td>
              <td class="text-muted small"><?= e($r['remarks']) ?: '-' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php if ($records):
$labels = array_map(fn($r) => format_month($r['billing_month']), $records);
$billing = array_map(fn($r) => (float) $r['billing_amount'], $records);
$bw = array_map(fn($r) => (float) $r['bandwidth_mbps'], $records);
$extraScripts = "<script>
document.addEventListener('DOMContentLoaded', function(){
  new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
      labels: " . json_encode($labels) . ",
      datasets: [
        { label: 'Billing (BDT)', data: " . json_encode($billing) . ", borderColor:'#2563eb', backgroundColor:'rgba(37,99,235,.1)', tension:.3, fill:true, yAxisID:'y' },
        { label: 'Bandwidth (Mbps)', data: " . json_encode($bw) . ", borderColor:'#16a34a', backgroundColor:'rgba(22,163,74,.1)', tension:.3, fill:true, yAxisID:'y1' }
      ]
    },
    options: {
      responsive:true, maintainAspectRatio:false,
      interaction:{mode:'index', intersect:false},
      scales: { y:{ position:'left', title:{display:true,text:'BDT'} }, y1:{ position:'right', grid:{drawOnChartArea:false}, title:{display:true,text:'Mbps'} } }
    }
  });
});
</script>";
endif;
require_once __DIR__ . '/../includes/footer.php'; ?>
