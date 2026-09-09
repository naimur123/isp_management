<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$id = (int) get_param('id');
$stmt = db()->prepare(
    'SELECT m.*, c.customer_id AS cust_code, c.customer_name, co.company_name, cat.category_name, z.zone_name
     FROM monthly_records m
     JOIN customers c ON c.id = m.customer_id
     JOIN companies co ON co.id = c.company_id
     JOIN categories cat ON cat.id = c.category_id
     JOIN zones z ON z.id = c.zone_id
     WHERE m.id = ? AND m.deleted_at IS NULL'
);
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    flash_set('danger', 'Monthly record not found.');
    redirect(base_url('billing/index.php'));
}

$errors = [];
$form = $record;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $form['billing_month'] = post('billing_month');
    $form['collection_segment'] = post('week_segment');
    $form['billing_amount'] = post('billing_amount');
    $form['bandwidth_mbps'] = post('bandwidth_mbps');
    $form['status'] = post('status');
    $form['remarks'] = post('remarks');

    $monthNorm = normalize_billing_month($form['billing_month']);

    if (!$monthNorm) $errors[] = 'Please provide a valid billing month.';
    if ($form['billing_amount'] === '' || !is_numeric($form['billing_amount']) || (float) $form['billing_amount'] < 0) $errors[] = 'Monthly Billing Amount must be a number greater than or equal to 0.';
    if ($form['bandwidth_mbps'] === '' || !is_numeric($form['bandwidth_mbps']) || (float) $form['bandwidth_mbps'] < 0) $errors[] = 'BW Sold must be a number greater than or equal to 0.';
    if (!in_array($form['status'], ['Active', 'Inactive', 'Hold'], true)) $errors[] = 'Invalid status.';

    if (!$errors && $monthNorm !== $record['billing_month']) {
        $dup = db()->prepare('SELECT id FROM monthly_records WHERE customer_id = ? AND billing_month = ? AND deleted_at IS NULL AND id != ?');
        $dup->execute([$record['customer_id'], $monthNorm, $id]);
        if ($dup->fetch()) {
            $errors[] = 'A monthly billing record already exists for this customer for ' . format_month($monthNorm) . '.';
        }
    }

    if (!$errors) {
        db()->prepare(
            'UPDATE monthly_records SET billing_month=?, billing_amount=?, bandwidth_mbps=?, status=?, remarks=?, updated_by=?, updated_at=NOW(), collection_segment=? WHERE id=?'
        )->execute([$monthNorm, $form['billing_amount'], $form['bandwidth_mbps'], $form['status'], $form['remarks'] ?: null, current_user_id(), $form['collection_segment'], $id]);

        log_activity('Monthly Record Updated', 'Billing', $record['cust_code'], 'Updated billing for ' . format_month($monthNorm));
        flash_set('success', 'Monthly billing record updated successfully.');
        redirect(base_url('customers/view.php?id=' . $record['customer_id']));
    }
}

$pageTitle = 'Edit Monthly Record';
$activeNav = 'billing_list';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="section-title">Edit Monthly Record — <?= e($record['cust_code']) ?></div>
<div class="section-sub mb-3"><?= e($record['customer_name']) ?> &middot; <?= e($record['company_name']) ?> / <?= e($record['category_name']) ?> / <?= e($record['zone_name']) ?></div>

<div class="row">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-body p-4">
        <?php if ($errors): ?>
          <div class="alert alert-danger small"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label required">Billing Month</label>
              <input type="month" id="billingMonth" name="billing_month" class="form-control" required value="<?= e(substr($form['billing_month'], 0, 7)) ?>">
            </div>

            <!-- Tentative Collection Date -->
            <div class="col-md-4 mb-3">
              <label class="form-label required">Tentative Collection Date</label>
              <select name="week_segment" id="weekSegment" class="form-select" required>
                <option value="">Month Segment</option>
                <?php 
                  $currentMonth = substr($form['billing_month'], 0, 7);
                  $segments = get_month_week_segments($currentMonth);
                  foreach ($segments as $seg): 
                ?>
                  <option value="<?= e($seg['value']) ?>" <?= ($form['collection_segment'] ?? '') === $seg['value'] ? 'selected' : '' ?>>
                    <?= e($seg['label']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label required">Monthly Billing Amount (BDT)</label>
              <input type="number" step="0.01" min="0" name="billing_amount" class="form-control" required value="<?= e($form['billing_amount']) ?>">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label required">BW Sold (Mbps)</label>
              <input type="number" step="0.01" min="0" name="bandwidth_mbps" class="form-control" required value="<?= e($form['bandwidth_mbps']) ?>">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label required">Status</label>
            <div class="d-flex gap-3">
              <div class="form-check"><input class="form-check-input" type="radio" name="status" value="Active" id="mrActive" <?= $form['status'] === 'Active' ? 'checked' : '' ?>><label class="form-check-label" for="mrActive">Active</label></div>
              <div class="form-check"><input class="form-check-input" type="radio" name="status" value="Inactive" id="mrInactive" <?= $form['status'] === 'Inactive' ? 'checked' : '' ?>><label class="form-check-label" for="mrInactive">Inactive</label></div>
              <div class="form-check"><input class="form-check-input" type="radio" name="status" value="Hold" id="mrHold" <?= $form['status'] === 'Hold' ? 'checked' : '' ?>><label class="form-check-label" for="mrHold">Hold</label></div>
            </div>
          </div>
          <div class="mb-4">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" class="form-control" rows="2"><?= e($form['remarks']) ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Save Changes</button>
          <a href="<?= e(base_url('billing/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  document.getElementById('billingMonth').addEventListener('change', function() {
    var val = this.value;
    if (!val) return;
    
    var parts = val.split('-');
    var daysInMonth = new Date(parts[0], parts[1], 0).getDate();
    var select = document.getElementById('weekSegment');
    
    if (select.options[4]) {
      select.options[4].value = '23-' + daysInMonth;
      select.options[4].text = '23 to ' + daysInMonth;
    }
  });
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
