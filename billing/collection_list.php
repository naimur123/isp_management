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

$where = ['cr.status != "Hold" AND cr.deleted_at IS NULL AND c.deleted_at IS NULL'];
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

// Calculate total count and grand total sum for amounts matching the active filters
$summaryStmt = db()->prepare("SELECT COUNT(*) AS total_count, SUM(cr.billing_amount) AS total_billing, SUM(cr.collection_amount) AS total_collection FROM monthly_records cr LEFT JOIN customers c ON c.id = cr.customer_id WHERE $whereSql");
$summaryStmt->execute($params);
$summaryData = $summaryStmt->fetch();

$total = (int) ($summaryData['total_count'] ?? 0);
$grandTotalBilling = (float) ($summaryData['total_billing'] ?? 0);
$grandTotalCollection = (float) ($summaryData['total_collection'] ?? 0);

// Handle pagination limits (25, 50, 100, 250, 500, 1000)
$perPageReq = (int) get_param('per_page');
$allowedPerPage = [25, 50, 100, 250, 500, 1000];
$perPage = in_array($perPageReq, $allowedPerPage, true) ? $perPageReq : 25;

$page = current_page();
$offset = ($page - 1) * $perPage;

$sql = "SELECT cr.*, c.customer_id AS cust_code, c.customer_name, co.company_name, u.full_name AS created_by_name
        FROM monthly_records cr
        LEFT JOIN customers c ON c.id = cr.customer_id
        LEFT JOIN companies co ON co.id = c.company_id
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
  <div class="d-flex gap-2 flex-wrap align-items-center">
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

<div class="row mb-3 mr-2">
   <div class="col-md-2">
      <div class="btn-group position-relative">
         <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fa-solid fa-download me-1"></i>Export
         </button>
         <!-- Removed dropdown-menu-end so it aligns to the left edge -->
         <ul class="dropdown-menu shadow" style="z-index: 1050;">
            <li>
              <a class="dropdown-item" href="<?= e(base_url('reports/export.php?type=collection_list&format=xlsx&' . build_query())) ?>"><i class="fa-solid fa-file-excel me-2 text-success"></i>Excel
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="<?= e(base_url('reports/export.php?type=collection_list&format=pdf&' . build_query())) ?>"><i class="fa-solid fa-file-pdf me-2 text-danger"></i>PDF
              </a>
            </li>
             <!-- <li><hr class="dropdown-divider"></li>
            <li><button type="button" class="dropdown-item" onclick="printCollectionTable();"><i class="fa-solid fa-print me-2"></i>Print</button></li> -->
         </ul>
      </div>
   </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table id="collection-table" class="table table-hover mb-0 align-middle">
      <thead>
        <tr>
          <th>SL</th>
          <th>Collection Month</th>
          <th>Customer ID</th>
          <th>Customer Name</th>
          <th>Company</th>
          <th>Monthly Billing</th>
          <th>Collection Paid</th>
          <th>Status</th>
          <th>Created By</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="10">
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
            <td><?= format_currency($r['collection_amount']) ?></td>
            <td>
              <!-- Toggle Button -->
               <?php 
                 $onclickAction = 'onclick="toggleInlineForm(this)"';
                 if($r['collection_status'] === 'Paid' && !empty($r['collection_amount']) && (float)($r['collection_amount'] != 0) && (float)($r['collection_amount']) == $r['billing_amount'] && !empty($r['bank_id'])){
                    $onclickAction = "";
                 } 
                 $collection_status = (!empty($r['collection_amount']) && (float)($r['collection_amount'] != 0) && (float)($r['collection_amount']) < $r['billing_amount']) ? status_badge('Partial') : status_badge($r['collection_status']);
               ?>
              <button type="button" 
                      class="btn btn-sm p-0 border-0 bg-transparent" 
                      <?php echo $onclickAction; ?>
                      title="Click to enter payment details">
                <?= $collection_status ?>
              </button>
            
              <!-- Inline Form Container -->
              <div class="inline-status-form mt-2 d-none p-2 border rounded bg-light" style="min-width: 200px;">
                <form action="<?= e(base_url('billing/toggle_status.php')) ?>" method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <input type="hidden" name="current_status" value="Paid">
            
                  <!-- Collection Amount -->
                  <?php if(empty($r['collection_amount']) || (float)$r['collection_amount'] == 0 || (float)($r['collection_amount']) < $r['billing_amount']): ?>
                  <div class="mb-2">
                    <label class="form-label form-label-sm fw-bold mb-1">Collection Amount</label>
                    <input type="number" step="any" name="collection_amount" value="<?= (!empty($r['billing_amount'])) ? $r['billing_amount'] - (float)($r['collection_amount']) : 0 ?>" class="form-control form-control-sm">
                  </div>
                  <?php endif; ?>
            
                  <!-- Bank Name Select -->
                  <?php if(empty($r['bank_id'])): ?>
                  <div class="mb-2">
                    <label class="form-label form-label-sm fw-bold mb-1">Bank Name</label>
                    <select name="bank_id" class="form-select form-select-sm" required>
                      <option value="" selected disabled>Select Bank</option>
                      <?php $banks = list_banks(true); 
                      foreach ($banks as $bank): ?>
                        <option value="<?= (int) $bank['id'] ?>"><?= e($bank['bank_name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <?php endif; ?>
            
                  <!-- Actions -->
                  <div class="d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Submit</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleInlineForm(this)">Cancel</button>
                  </div>
                </form>
              </div>
            </td>
            <td><?= e($r['created_by_name'] ?? '-') ?></td>
            <td class="text-end">
              <?php if (is_admin()): ?>
                <a href="<?= e(base_url('billing/edit.php?id=' . $r['id'])) ?>" class="btn btn-sm btn-outline-primary"
                  title="Edit"><i class="fa-solid fa-pen"></i></a>
              <?php else: ?>
                <a href="<?= e(base_url('customers/view.php?id=' . $r['customer_id'])) ?>"
                  class="btn btn-sm btn-outline-secondary" title="View"><i class="fa-solid fa-eye"></i></a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <?php if ($rows): ?>
      <tfoot class="table-light fw-bold">
        <tr>
          <td colspan="5" class="text-end">Grand Total:</td>
          <td><?= format_currency($grandTotalBilling) ?></td>
          <td><?= format_currency($grandTotalCollection) ?></td>
          <td colspan="3"></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
  <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2 bg-white">
    <form method="get" class="d-flex align-items-center gap-2 small">
      <?php foreach (['q', 'company', 'category', 'zone', 'month', 'year', 'status'] as $k): ?>
        <input type="hidden" name="<?= $k ?>" value="<?= e($_GET[$k] ?? '') ?>">
      <?php endforeach; ?>
      <span class="text-muted">Rows:</span>
      <select name="per_page" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
        <?php foreach ([25, 50, 100, 250, 500, 1000] as $ps): ?>
          <option value="<?= $ps ?>" <?= $perPage === $ps ? 'selected' : '' ?>><?= ($ps == 1000) ? 'All' : $ps ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <?= render_pagination($total, $page, $perPage) ?>
  </div>
</div>
<script>
  function toggleInlineForm(element) {
    const td = element.closest('td');
    const formContainer = td && td.querySelector('.inline-status-form');

    if (formContainer) {
      formContainer.classList.toggle('d-none');
    }
  }

  function printCollectionTable() {
    const table = document.getElementById('collection-table');
    if (!table) return;

    const clone = table.cloneNode(true);
    const actionHeader = clone.querySelector('thead th:last-child');
    const actionCells = clone.querySelectorAll('tbody td:last-child, tfoot td:last-child');
    if (actionHeader) actionHeader.remove();
    actionCells.forEach((cell) => cell.remove());

    clone.querySelectorAll('.inline-status-form, .inline-status-form *').forEach((node) => node.remove());
    clone.querySelectorAll('input[type="hidden"], form, button, a').forEach((node) => {
      if (node.closest('.inline-status-form')) {
        node.remove();
      }
    });

    const printWindow = window.open('', '_blank', 'width=1200,height=800');
    if (!printWindow) {
      alert('Pop-up blocked. Please allow pop-ups to print the collection list.');
      return;
    }

    const html = `
      <!doctype html>
      <html>
      <head>
        <meta charset="utf-8">
        <title>Collection List</title>
        <style>
          body { font-family: Arial, sans-serif; padding: 24px; color: #111; }
          h2 { margin-bottom: 12px; }
          table { width: 100%; border-collapse: collapse; margin-top: 12px; }
          th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; vertical-align: top; }
          th { background: #f3f4f6; }
          .section-sub { font-size: 12px; color: #4b5563; margin-bottom: 16px; }
          @media print {
            body { padding: 0; }
            button, .no-print { display: none !important; }
          }
        </style>
      </head>
      <body>
        <h2>Month to Month Collection List</h2>
        <div class="section-sub">Generated on ${new Date().toLocaleString()}</div>
        ${clone.outerHTML}
      </body>
      </html>
    `;

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
      printWindow.print();
      setTimeout(() => printWindow.close(), 1000);
    }, 500);
  }
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>