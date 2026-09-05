<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$preselectId = (int) get_param('customer');
$preselect = null;
if ($preselectId) {
    $s = db()->prepare(
        'SELECT c.*, co.company_name, cat.category_name, z.zone_name FROM customers c
         JOIN companies co ON co.id=c.company_id JOIN categories cat ON cat.id=c.category_id JOIN zones z ON z.id=c.zone_id
         WHERE c.id=? AND c.deleted_at IS NULL'
    );
    $s->execute([$preselectId]);
    $preselect = $s->fetch();
}

$errors = [];
$form = ['customer_id' => $preselectId ?: '', 'billing_month' => date('Y-m-01'), 'billing_amount' => '', 'bandwidth_mbps' => '', 'status' => 'Active', 'remarks' => ''];
$duplicateOf = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $form['customer_id'] = post('customer_id');
    $form['billing_month'] = post('billing_month');
    $form['collection_segment'] = post('week_segment');
    $form['billing_amount'] = post('billing_amount');
    $form['bandwidth_mbps'] = post('bandwidth_mbps');
    $form['status'] = post('status', 'Active');
    $form['remarks'] = post('remarks');

    $custId = (int) $form['customer_id'];
    $monthNorm = normalize_billing_month($form['billing_month']);

    if (!$custId) $errors[] = 'Please select a customer.';
    if (!$monthNorm) $errors[] = 'Please provide a valid billing month.';
    if ($form['billing_amount'] === '' || !is_numeric($form['billing_amount']) || (float) $form['billing_amount'] < 0) $errors[] = 'Monthly Billing Amount must be a number greater than or equal to 0.';
    if ($form['bandwidth_mbps'] === '' || !is_numeric($form['bandwidth_mbps']) || (float) $form['bandwidth_mbps'] < 0) $errors[] = 'BW Sold must be a number greater than or equal to 0.';
    if (!in_array($form['status'], ['Active', 'Inactive'], true)) $errors[] = 'Invalid status.';

    if (!$errors && $custId && $monthNorm) {
        $dup = db()->prepare('SELECT id FROM monthly_records WHERE customer_id = ? AND billing_month = ? AND deleted_at IS NULL');
        $dup->execute([$custId, $monthNorm]);
        $duplicateOf = $dup->fetch();
        if ($duplicateOf) {
            $errors[] = 'A monthly billing record already exists for this customer for ' . format_month($monthNorm) . '.';
        }
    }

    if (!$errors) {
        db()->prepare(
            'INSERT INTO monthly_records (customer_id, billing_month, billing_amount, bandwidth_mbps, status, remarks, created_by, updated_by, created_at, updated_at, collection_segment)
             VALUES (?,?,?,?,?,?,?,?,NOW(),NOW(),?)'
        )->execute([$custId, $monthNorm, $form['billing_amount'], $form['bandwidth_mbps'], $form['status'], $form['remarks'] ?: null, current_user_id(), current_user_id(), $form['collection_segment']]);

        log_activity('Monthly Record Created', 'Billing', $custId, 'Added billing for ' . format_month($monthNorm));
        flash_set('success', 'Monthly billing record saved successfully.');
        redirect(base_url('customers/view.php?id=' . $custId));
    } else {
        if ($custId) {
            $s = db()->prepare(
                'SELECT c.*, co.company_name, cat.category_name, z.zone_name FROM customers c
                 JOIN companies co ON co.id=c.company_id JOIN categories cat ON cat.id=c.category_id JOIN zones z ON z.id=c.zone_id
                 WHERE c.id=? AND c.deleted_at IS NULL'
            );
            $s->execute([$custId]);
            $preselect = $s->fetch();
        }
    }
}

$pageTitle = 'Manual Monthly Input';
$activeNav = 'billing_add';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="section-title">Manual Monthly Billing Input</div>
<div class="section-sub mb-3">Search for an existing customer, then record this month's billing and bandwidth.</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-body p-4">
        <?php if ($errors): ?>
          <div class="alert alert-warning small">
            <ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            <?php if ($duplicateOf): ?>
              <a class="btn btn-sm btn-warning mt-2" href="<?= e(base_url('billing/edit.php?id=' . $duplicateOf['id'])) ?>">Edit Existing Record Instead</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <form method="POST" id="billingForm">
          <?= csrf_field() ?>
          <div class="mb-3 position-relative">
            <label class="form-label required">Customer</label>
            <input type="text" id="customerSearch" class="form-control" placeholder="Type Customer ID or Name to search..." autocomplete="off"
                   value="<?= $preselect ? e($preselect['customer_id'] . ' - ' . $preselect['customer_name']) : '' ?>">
            <input type="hidden" name="customer_id" id="customerIdField" value="<?= e($form['customer_id']) ?>">
            <div id="customerResults" class="list-group position-absolute w-100 shadow-sm" style="z-index:10;max-height:260px;overflow:auto;"></div>
          </div>

          <div id="customerInfo" class="mb-3 <?= $preselect ? '' : 'd-none' ?>">
            <div class="stat-pill small d-flex flex-wrap gap-3" id="customerInfoBody">
              <?php if ($preselect): ?>
                <span><strong>ID:</strong> <?= e($preselect['customer_id']) ?></span>
                <span><strong>Company:</strong> <?= e($preselect['company_name']) ?></span>
                <span><strong>Category:</strong> <?= e($preselect['category_name']) ?></span>
                <span><strong>Zone:</strong> <?= e($preselect['zone_name']) ?></span>
                <span><strong>Status:</strong> <?= e($preselect['status']) ?></span>
              <?php endif; ?>
            </div>
          </div>

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
                  <option value="<?= e($seg['value']) ?>" <?= ($form['week_segment'] ?? '') === $seg['value'] ? 'selected' : '' ?>>
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
            </div>
          </div>
          <div class="mb-4">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" class="form-control" rows="2"><?= e($form['remarks']) ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Save Record</button>
          <a href="<?= e(base_url('billing/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </form>
      </div>
    </div>
  </div>
</div>

<?php $extraScripts = "<script>
(function(){
  var input = document.getElementById('customerSearch');
  var results = document.getElementById('customerResults');
  var hidden = document.getElementById('customerIdField');
  var infoBox = document.getElementById('customerInfo');
  var infoBody = document.getElementById('customerInfoBody');

  var search = ismDebounce(function(){
    var q = input.value.trim();
    if(q.length < 1){ results.innerHTML=''; return; }
    fetch('" . e(base_url('customers/search_ajax.php')) . "?q=' + encodeURIComponent(q))
      .then(function(r){ return r.json(); })
      .then(function(data){
        results.innerHTML = '';
        (data.results||[]).forEach(function(c){
          var a = document.createElement('a');
          a.href = '#'; a.className = 'list-group-item list-group-item-action bg-white';
          a.innerHTML = '<strong>'+c.customer_id+'</strong> — '+c.customer_name+' <span class=\"text-muted small\">('+c.company_name+' / '+c.category_name+' / '+c.zone_name+')</span>';
          a.addEventListener('click', function(e){
            e.preventDefault();
            input.value = c.customer_id + ' - ' + c.customer_name;
            hidden.value = c.id;
            infoBody.innerHTML = '<span><strong>ID:</strong> '+c.customer_id+'</span><span><strong>Company:</strong> '+c.company_name+'</span><span><strong>Category:</strong> '+c.category_name+'</span><span><strong>Zone:</strong> '+c.zone_name+'</span><span><strong>Status:</strong> '+c.status+'</span>';
            infoBox.classList.remove('d-none');
            results.innerHTML = '';
          });
          results.appendChild(a);
        });
      });
  }, 300);

  input.addEventListener('input', function(){ hidden.value=''; infoBox.classList.add('d-none'); search(); });
  document.addEventListener('click', function(e){ if(!results.contains(e.target) && e.target!==input) results.innerHTML=''; });
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
</script>";
require_once __DIR__ . '/../includes/footer.php'; ?>
