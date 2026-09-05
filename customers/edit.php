<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$id = (int) get_param('id');
$stmt = db()->prepare('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$id]);
$customer = $stmt->fetch();
if (!$customer) {
    flash_set('danger', 'Customer not found.');
    redirect(base_url('customers/index.php'));
}

$errors = [];
$form = $customer;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $form['customer_name'] = post('customer_name');
    $form['category_id'] = post('category_id');
    $form['zone_id'] = post('zone_id');
    $form['status'] = post('status', 'Active');
    $form['remarks'] = post('remarks');
    // Note: Company and Customer ID are permanent and cannot be changed once assigned.

    if ($form['customer_name'] === '') $errors[] = 'Customer Name is required.';
    if ($form['category_id'] === '') $errors[] = 'Category is required.';
    if ($form['zone_id'] === '') $errors[] = 'Area/Zone is required.';
    if (!in_array($form['status'], ['Active', 'Inactive'], true)) $errors[] = 'Invalid status.';

    if (!$errors) {
        db()->prepare(
            'UPDATE customers SET customer_name=?, category_id=?, zone_id=?, status=?, remarks=?, updated_by=?, updated_at=NOW() WHERE id=?'
        )->execute([$form['customer_name'], $form['category_id'], $form['zone_id'], $form['status'], $form['remarks'] ?: null, current_user_id(), $id]);

        log_activity('Customer Updated', 'Customers', $customer['customer_id'], 'Updated customer ' . $customer['customer_id']);
        flash_set('success', 'Customer updated successfully.');
        redirect(base_url('customers/view.php?id=' . $id));
    }
}

$companies = list_companies(true);
$categories = list_categories(true);
$zones = list_zones(true);
$currentCompany = db()->prepare('SELECT * FROM companies WHERE id = ?');
$currentCompany->execute([$customer['company_id']]);
$currentCompany = $currentCompany->fetch();
$currentCategory = db()->prepare('SELECT * FROM categories WHERE id = ?');
$currentCategory->execute([$customer['category_id']]);
$currentCategory = $currentCategory->fetch();
$currentZone = db()->prepare('SELECT * FROM zones WHERE id = ?');
$currentZone->execute([$customer['zone_id']]);
$currentZone = $currentZone->fetch();

$pageTitle = 'Edit Customer';
$activeNav = 'customer_list';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="section-title">Edit Customer — <?= e($customer['customer_id']) ?></div>
<div class="section-sub mb-3">Customer ID and Company are permanent and cannot be changed.</div>

<div class="row">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-body p-4">
        <?php if ($errors): ?>
          <div class="alert alert-danger small"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Customer ID</label>
              <input type="text" class="form-control fw-bold" value="<?= e($customer['customer_id']) ?>" disabled>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Company</label>
              <input type="text" class="form-control" value="<?= e($currentCompany['company_name'] ?? '') ?>" disabled>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label required">Customer Name</label>
            <input type="text" name="customer_name" class="form-control" required value="<?= e($form['customer_name']) ?>">
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label required">Category</label>
              <select name="category_id" class="form-select" required>
                <?= options_html($categories, 'id', 'category_name', $form['category_id'], $currentCategory) ?>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label required">Area / Zone</label>
              <select name="zone_id" class="form-select" required>
                <?= options_html($zones, 'id', 'zone_name', $form['zone_id'], $currentZone) ?>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label required">Status</label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="status" value="Active" id="stActive" <?= $form['status'] === 'Active' ? 'checked' : '' ?>>
                <label class="form-check-label" for="stActive">Active</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="status" value="Inactive" id="stInactive" <?= $form['status'] === 'Inactive' ? 'checked' : '' ?>>
                <label class="form-check-label" for="stInactive">Inactive</label>
              </div>
            </div>
          </div>
          <div class="mb-4">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" class="form-control" rows="3"><?= e($form['remarks']) ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Save Changes</button>
          <a href="<?= e(base_url('customers/view.php?id=' . $id)) ?>" class="btn btn-outline-secondary">Cancel</a>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
