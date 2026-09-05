<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$q = get_param('q');
$rows = [];
if ($q !== '') {
    $rows = run_all(
        "SELECT c.id, c.customer_id, c.customer_name, co.company_name, cat.category_name, z.zone_name, c.status
         FROM customers c JOIN companies co ON co.id=c.company_id JOIN categories cat ON cat.id=c.category_id JOIN zones z ON z.id=c.zone_id
         WHERE c.deleted_at IS NULL AND (c.customer_id LIKE ? OR c.customer_name LIKE ?)
         ORDER BY c.customer_name LIMIT 100",
        ["%$q%", "%$q%"]
    );
    // Exact Customer ID match -> go straight to the profile.
    if (count($rows) === 1 && strcasecmp($rows[0]['customer_id'], $q) === 0) {
        redirect(base_url('customers/view.php?id=' . $rows[0]['id']));
    }
}

$pageTitle = 'Customer Report';
$activeNav = 'report_customer';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="section-title">Customer Report</div>
<div class="section-sub mb-3">Search by Customer ID or Name to open a full customer profile with monthly history.</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" class="row g-2">
      <div class="col-md-6"><input type="text" name="q" class="form-control" placeholder="Enter Customer ID or Name..." value="<?= e($q) ?>" autofocus></div>
      <div class="col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-magnifying-glass me-1"></i>Search</button></div>
    </form>
  </div>
</div>

<?php if ($q !== ''): ?>
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Customer ID</th><th>Customer Name</th><th>Company</th><th>Category</th><th>Zone</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-magnifying-glass"></i>No customers found matching "<?= e($q) ?>".</div></td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td class="fw-semibold"><?= e($r['customer_id']) ?></td>
          <td><?= e($r['customer_name']) ?></td>
          <td><?= e($r['company_name']) ?></td>
          <td><?= e($r['category_name']) ?></td>
          <td><?= e($r['zone_name']) ?></td>
          <td><?= status_badge($r['status']) ?></td>
          <td><a href="<?= e(base_url('customers/view.php?id=' . $r['id'])) ?>" class="btn btn-sm btn-outline-primary">View Report</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
