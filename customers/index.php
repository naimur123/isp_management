<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$q = get_param('q');
$companyId = get_param('company');
$categoryId = get_param('category');
$zoneId = get_param('zone');
$status = get_param('status');

$where = ['c.deleted_at IS NULL'];
$params = [];

if ($q !== '') {
    $where[] = '(c.customer_id LIKE ? OR c.customer_name LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($companyId !== '') { $where[] = 'c.company_id = ?'; $params[] = $companyId; }
if ($categoryId !== '') { $where[] = 'c.category_id = ?'; $params[] = $categoryId; }
if ($zoneId !== '') { $where[] = 'c.zone_id = ?'; $params[] = $zoneId; }
if ($status !== '') { $where[] = 'c.status = ?'; $params[] = $status; }

$whereSql = implode(' AND ', $where);

$countStmt = db()->prepare("SELECT COUNT(*) c FROM customers c WHERE $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetch()['c'];

$page = current_page();
$perPage = page_size();
$offset = ($page - 1) * $perPage;

$sql = "SELECT c.*, co.company_name, co.customer_prefix, cat.category_name, z.zone_name,
               u.full_name AS created_by_name
        FROM customers c
        JOIN companies co ON co.id = c.company_id
        JOIN categories cat ON cat.id = c.category_id
        JOIN zones z ON z.id = c.zone_id
        LEFT JOIN users u ON u.id = c.created_by
        WHERE $whereSql
        ORDER BY c.id DESC
        LIMIT $perPage OFFSET $offset";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$companies = list_companies();
$categories = list_categories();
$zones = list_zones();

$pageTitle = 'Customer List';
$activeNav = 'customer_list';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <div class="section-title">Customer List</div>
    <div class="section-sub"><?= number_format($total) ?> customer(s) found</div>
  </div>
  <?php if (is_admin()): ?>
  <a href="<?= e(base_url('customers/add.php')) ?>" class="btn btn-primary"><i class="fa-solid fa-user-plus me-1"></i>Add New Customer</a>
  <?php endif; ?>
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
          <option value="">All</option>
          <?= options_html($companies, 'id', 'company_name', $companyId) ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Category</label>
        <select name="category" class="form-select">
          <option value="">All</option>
          <?= options_html($categories, 'id', 'category_name', $categoryId) ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Zone</label>
        <select name="zone" class="form-select">
          <option value="">All</option>
          <?= options_html($zones, 'id', 'zone_name', $zoneId) ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="">All</option>
          <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
          <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
      <div class="col-md-1 d-flex gap-1">
        <button class="btn btn-primary flex-fill" type="submit"><i class="fa-solid fa-filter"></i></button>
        <a href="<?= e(base_url('customers/index.php')) ?>" class="btn btn-outline-secondary flex-fill" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead>
        <tr>
          <th>SL</th><th>Customer ID</th><th>Customer Name</th><th>Company</th><th>Category</th>
          <th>Area/Zone</th><th>Status</th><th>Created Date</th><th>Created By</th><th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
        <tr><td colspan="10"><div class="empty-state"><i class="fa-solid fa-users"></i>No customers found matching your filters.</div></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $i => $r): ?>
        <tr>
          <td><?= $offset + $i + 1 ?></td>
          <td><a href="<?= e(base_url('customers/view.php?id=' . $r['id'])) ?>" class="fw-semibold text-primary"><?= e($r['customer_id']) ?></a></td>
          <td><?= e($r['customer_name']) ?></td>
          <td><span class="badge bg-light text-dark border"><?= e($r['company_name']) ?></span></td>
          <td><?= e($r['category_name']) ?></td>
          <td><?= e($r['zone_name']) ?></td>
          <td><?= status_badge($r['status']) ?></td>
          <td><?= format_date($r['created_at']) ?></td>
          <td><?= e($r['created_by_name'] ?? '-') ?></td>
          <td class="text-end">
            <a href="<?= e(base_url('customers/view.php?id=' . $r['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="fa-solid fa-eye"></i></a>
            <?php if (is_admin()): ?>
            <a href="<?= e(base_url('customers/edit.php?id=' . $r['id'])) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fa-solid fa-pen"></i></a>
            <form action="<?= e(base_url('customers/delete.php')) ?>" method="post" class="d-inline" data-confirm="Are you sure you want to delete this record?">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2 bg-white">
    <form method="get" class="d-flex align-items-center gap-2 small">
      <?php foreach (['q','company','category','zone','status'] as $k): ?>
        <input type="hidden" name="<?= $k ?>" value="<?= e($_GET[$k] ?? '') ?>">
      <?php endforeach; ?>
      <span class="text-muted">Rows:</span>
      <select name="per_page" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
        <?php foreach ([10,25,50,100] as $ps): ?>
        <option value="<?= $ps ?>" <?= $perPage === $ps ? 'selected' : '' ?>><?= $ps ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <?= render_pagination($total, $page, $perPage) ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
