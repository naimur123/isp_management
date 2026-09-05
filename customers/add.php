<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$errors = [];
$form = ['customer_name' => '', 'company_id' => '', 'category_id' => '', 'zone_id' => '', 'status' => 'Active', 'remarks' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $form['customer_name'] = post('customer_name');
    $form['company_id'] = post('company_id');
    $form['category_id'] = post('category_id');
    $form['zone_id'] = post('zone_id');
    $form['status'] = post('status', 'Active');
    $form['remarks'] = post('remarks');

    if ($form['customer_name'] === '') $errors[] = 'Customer Name is required.';
    if ($form['company_id'] === '') $errors[] = 'Company is required.';
    if ($form['category_id'] === '') $errors[] = 'Category is required.';
    if ($form['zone_id'] === '') $errors[] = 'Area/Zone is required.';
    if (!in_array($form['status'], ['Active', 'Inactive'], true)) $errors[] = 'Invalid status.';

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $customerId = generate_customer_id((int) $form['company_id']);

            $stmt = $pdo->prepare(
                'INSERT INTO customers (customer_id, customer_name, company_id, category_id, zone_id, status, remarks, created_by, updated_by, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())'
            );
            $stmt->execute([
                $customerId, $form['customer_name'], $form['company_id'], $form['category_id'], $form['zone_id'],
                $form['status'], $form['remarks'] ?: null, current_user_id(), current_user_id(),
            ]);
            $newId = (int) $pdo->lastInsertId();
            $pdo->commit();

            log_activity('Customer Created', 'Customers', $customerId, 'Created customer ' . $customerId . ' - ' . $form['customer_name']);
            flash_set('success', 'Customer created successfully. Assigned Customer ID: ' . e($customerId));
            redirect(base_url('customers/view.php?id=' . $newId));
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Could not create the customer. Please try again.';
        }
    }
}

$companies = list_companies(true);
$categories = list_categories(true);
$zones = list_zones(true);

$pageTitle = 'Add New Customer';
$activeNav = 'customer_add';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="section-title">Add New Customer</div>
<div class="section-sub mb-3">Customer ID is generated automatically once you save this form.</div>

<div class="row">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-body p-4">
        <?php if ($errors): ?>
          <div class="alert alert-danger small"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Customer ID</label>
            <input type="text" class="form-control" value="Automatically generated on save" disabled>
          </div>
          <div class="mb-3">
            <label class="form-label required">Customer Name</label>
            <input type="text" name="customer_name" class="form-control" required value="<?= e($form['customer_name']) ?>">
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label required">Company</label>
              <select name="company_id" class="form-select" required>
                <option value="">Select...</option>
                <?= options_html($companies, 'id', 'company_name', $form['company_id']) ?>
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label required">Category</label>
              <select name="category_id" class="form-select" required>
                <option value="">Select...</option>
                <?= options_html($categories, 'id', 'category_name', $form['category_id']) ?>
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label required">Area / Zone</label>
              <select name="zone_id" class="form-select" required>
                <option value="">Select...</option>
                <?= options_html($zones, 'id', 'zone_name', $form['zone_id']) ?>
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
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Save Customer</button>
          <a href="<?= e(base_url('customers/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card bg-light border-0">
      <div class="card-body">
        <h6 class="fw-bold"><i class="fa-solid fa-circle-info text-primary me-1"></i>How Customer IDs work</h6>
        <p class="small text-muted mb-1">Each company has its own permanent numbering sequence, e.g. <code>TCL-00001</code>, <code>TCL-00002</code>...</p>
        <p class="small text-muted mb-0">Entry Date, Entry Time and Created By are recorded automatically — you don't need to enter them.</p>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
