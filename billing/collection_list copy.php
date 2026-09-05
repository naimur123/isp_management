<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$q = get_param('q');
$companyId = get_param('company');
$categoryId = get_param('category');
$zoneId = get_param('zone');
$month = get_param('month');
$year = get_param('year');
$status = get_param('status');

$where = ['cr.deleted_at IS NULL'];
$params = [];

if ($q !== '') {
  $where[] = '(c.customer_id LIKE ? OR c.customer_name LIKE ?)';
  $params[] = "%$q%";
  $params[] = "%$q%";
}
if ($companyId !== '') {
  $where[] = 'c.company_id = ?';
  $params[] = $companyId;
}

if ($month !== '') {
  $where[] = 'cr.billing_month = ?';
  $params[] = $month . '-01';
}
if ($year !== '') {
  $where[] = 'YEAR(cr.billing_month) = ?';
  $params[] = $year;
}
if ($status !== '') {
  $where[] = 'cr.collection_status = ?';
  $params[] = $status;
}

$whereSql = implode(' AND ', $where);

$countStmt = db()->prepare("SELECT COUNT(*) c FROM monthly_records cr JOIN customers c ON c.id = cr.customer_id WHERE $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetch()['c'];

$page = current_page();
$perPage = page_size();
$offset = ($page - 1) * $perPage;

$sql = "SELECT cr.*, c.customer_id AS cust_code, c.customer_name, co.company_name, u.full_name AS created_by_name
        FROM monthly_records cr
        JOIN customers c ON c.id = cr.customer_id
        JOIN companies co ON co.id = c.company_id
        LEFT JOIN users u ON u.id = cr.created_by
        WHERE $whereSql
        ORDER BY cr.id DESC
        LIMIT $perPage OFFSET $offset";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$companies = list_companies();
$categories = list_categories();
$zones = list_zones();
$years = year_options();

$pageTitle = 'Month to Month Collection List';
$activeNav = 'collection_list';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <div class="section-title">Month to Month Collection List</div>
    <div class="section-sub"><?= number_format($total) ?> record(s) found</div>
  </div>
  <div class="d-flex gap-2">
    <?php if (is_admin()): ?>
      <a href="<?= e(base_url('billing/collection_add.php')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Add Collection Record</a>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label">Search</label>
        <input type="text" name="q" class="form-control" placeholder="Customer ID or Name" value="<?= e($q) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Company</label>
        <select name="company" class="form-select">
          <option value="">All</option><?= options_html($companies, 'id', 'company_name', $companyId) ?>
        </select>
      </div>

      <div class="col-md-2"><label class="form-label">Billing Month</label><input type="month" name="month" class="form-control" value="<?= e($month) ?>">
      </div>
      <div class="col-md-1"><label class="form-label">Year</label><select name="year" class="form-select">
          <option value="">All</option><?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= (string) $year === (string) $y ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select">
          <option value="">All</option>
          <option value="Paid" <?= $status === 'Paid' ? 'selected' : '' ?>>Paid</option>
          <option value="Due" <?= $status === 'Due' ? 'selected' : '' ?>>Due</option>
        </select>
      </div>

      <div class="col-md-2 d-flex gap-1">
        <button class="btn btn-primary flex-fill" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
        <a href="<?= e(base_url('billing/collection_list.php')) ?>" class="btn btn-outline-secondary flex-fill" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead>
        <tr>
          <th>SL</th>
          <th>Collection Month</th>
          <th>Customer ID</th>
          <th>Customer Name</th>
          <th>Company</th>
          <th>Monthly Billing</th>
          <th>Status</th>
          <th>Created By</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="13">
              <div class="empty-state"><i class="fa-solid fa-receipt"></i>No monthly records found matching your filters.
              </div>
            </td>
          </tr>
        <?php endif; ?>
        <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td><?= $offset + $i + 1 ?></td>
            <td class="fw-semibold"><?= format_month($r['billing_month']) ?></td>
            <td><a href="<?= e(base_url('customers/view.php?id=' . $r['customer_id'])) ?>"
                class="text-primary"><?= e($r['cust_code']) ?></a></td>
            <td><?= e($r['customer_name']) ?></td>
            <td><span class="badge bg-light text-dark border"><?= e($r['company_name']) ?></span></td>
            <td><?= format_currency($r['billing_amount']) ?></td>
            <td>
              <form action="<?= e(base_url('billing/toggle_status.php')) ?>" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to change the status of this record?');">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="current_status" value="<?= e($r['collection_status']) ?>">
                <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent" title="Click to change status">
                  <?= status_badge($r['collection_status']) ?>
                </button>
              </form>
            </td>
            <td><?= e($r['created_by_name'] ?? '-') ?></td>
            <td class="text-end">
              <?php if (is_admin()): ?>
                <a href="<?= e(base_url('billing/edit.php?id=' . $r['id'])) ?>" class="btn btn-sm btn-outline-primary"
                  title="Edit"><i class="fa-solid fa-pen"></i></a>
                <!-- <form action="<?= e(base_url('billing/delete.php')) ?>" method="post" class="d-inline"
                  data-confirm="Are you sure you want to delete this record?">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i
                      class="fa-solid fa-trash"></i></button>
                </form> -->
              <?php else: ?>
                <a href="<?= e(base_url('customers/view.php?id=' . $r['customer_id'])) ?>"
                  class="btn btn-sm btn-outline-secondary" title="View"><i class="fa-solid fa-eye"></i></a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2 bg-white">
    <form method="get" class="d-flex align-items-center gap-2 small">
      <?php foreach (['q', 'company', 'category', 'zone', 'month', 'year', 'status'] as $k): ?>
        <input type="hidden" name="<?= $k ?>" value="<?= e($_GET[$k] ?? '') ?>">
      <?php endforeach; ?>
      <span class="text-muted">Rows:</span>
      <select name="per_page" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
        <?php foreach ([10, 25, 50, 100] as $ps): ?>
          <option value="<?= $ps ?>" <?= $perPage === $ps ? 'selected' : '' ?>><?= $ps ?></option><?php endforeach; ?>
      </select>
    </form>
    <?= render_pagination($total, $page, $perPage) ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>