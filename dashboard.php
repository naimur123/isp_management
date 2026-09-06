<?php
require_once __DIR__ . '/config/config.php';
require_login();

/* -----------------------------------------------------------------
 * Filters
 * ------------------------------------------------------------- */
$month = get_param('month');     // YYYY-MM or ''
$year = get_param('year');       // YYYY or ''
$companyId = get_param('company');
$categoryId = get_param('category');
$zoneId = get_param('zone');
$status = get_param('status');

$pdo = db();

// Customer-attribute filters (company/category/zone/status) shared by both customer-master
// queries and monthly-record queries (joined through customers).
$custConds = ['c.deleted_at IS NULL'];
$custParams = [];
if ($companyId !== '') {
  $custConds[] = 'c.company_id = ?';
  $custParams[] = $companyId;
}
if ($categoryId !== '') {
  $custConds[] = 'c.category_id = ?';
  $custParams[] = $categoryId;
}
if ($zoneId !== '') {
  $custConds[] = 'c.zone_id = ?';
  $custParams[] = $zoneId;
}

$custWhereForCounts = $custConds;
$custParamsForCounts = $custParams;
if ($status !== '') {
  $custWhereForCounts[] = 'c.status = ?';
  $custParamsForCounts[] = $status;
}
$custWhereSql = implode(' AND ', $custWhereForCounts);


// Monthly-record filters: customer attributes + optional month/year + optional record status.
$mrConds = array_merge(['m.deleted_at IS NULL'], $custConds);
$mrParams = $custParams;
if ($status !== '') {
  $mrConds[] = 'm.status = ?';
  $mrParams[] = $status;
}
if ($month !== '') {
  $mrConds[] = 'm.billing_month = ?';
  $mrParams[] = $month . '-01';
} elseif ($year !== '') {
  $mrConds[] = 'YEAR(m.billing_month) = ?';
  $mrParams[] = $year;
}
$mrWhereSql = implode(' AND ', $mrConds);

// Trend queries (span multiple months) respect year + other filters but never a single month.
$trendConds = array_merge(['m.deleted_at IS NULL'], $custConds);
$trendParams = $custParams;
if ($status !== '') {
  $trendConds[] = 'm.status = ?';
  $trendParams[] = $status;
}
if ($year !== '') {
  $trendConds[] = 'YEAR(m.billing_month) = ?';
  $trendParams[] = $year;
}
$trendWhereSql = implode(' AND ', $trendConds);

$baseJoin = 'FROM monthly_records m JOIN customers c ON c.id = m.customer_id
             JOIN companies co ON co.id = c.company_id JOIN categories cat ON cat.id = c.category_id JOIN zones z ON z.id = c.zone_id';

/* -----------------------------------------------------------------
 * KPI cards
 * ------------------------------------------------------------- */
$activeCustomers = (int) run_scalar("SELECT COUNT(*) FROM customers c WHERE $custWhereSql AND c.status = 'Active'", $custParamsForCounts);
$inactiveCustomers = (int) run_scalar("SELECT COUNT(*) FROM customers c WHERE $custWhereSql AND c.status = 'Inactive'", $custParamsForCounts);
$totalCustomers = $activeCustomers + $inactiveCustomers;

$mrAgg = run_row("SELECT COALESCE(SUM(m.billing_amount),0) billing, COALESCE(SUM(m.bandwidth_mbps),0) bw, COUNT(DISTINCT m.customer_id) custs
                   $baseJoin WHERE $mrWhereSql", $mrParams);
$totalBilling = (float) $mrAgg['billing'];
$totalBw = (float) $mrAgg['bw'];
$billedCustomers = (int) $mrAgg['custs'];
$avgBillingPerActive = safe_divide($totalBilling, $activeCustomers ?: $billedCustomers);
$avgBwPerActive = safe_divide($totalBw, $activeCustomers ?: $billedCustomers);

/* -----------------------------------------------------------------
 * Highest / leaderboard callouts
 * ------------------------------------------------------------- */
$highestBillingCompany = run_row("SELECT co.company_name AS label, SUM(m.billing_amount) val $baseJoin WHERE $mrWhereSql GROUP BY co.id ORDER BY val DESC LIMIT 1", $mrParams);
$highestBillingZone = run_row("SELECT z.zone_name AS label, SUM(m.billing_amount) val $baseJoin WHERE $mrWhereSql GROUP BY z.id ORDER BY val DESC LIMIT 1", $mrParams);
$highestBillingCategory = run_row("SELECT cat.category_name AS label, SUM(m.billing_amount) val $baseJoin WHERE $mrWhereSql GROUP BY cat.id ORDER BY val DESC LIMIT 1", $mrParams);
$highestBandwidthZone = run_row("SELECT z.zone_name AS label, SUM(m.bandwidth_mbps) val $baseJoin WHERE $mrWhereSql GROUP BY z.id ORDER BY val DESC LIMIT 1", $mrParams);
$highestCustomerZone = run_row("SELECT z.zone_name AS label, COUNT(*) val FROM customers c JOIN zones z ON z.id=c.zone_id WHERE $custWhereSql GROUP BY z.id ORDER BY val DESC LIMIT 1", $custParamsForCounts);
$highestBillingCustomer = run_row("SELECT c.customer_id AS code, c.customer_name AS label, SUM(m.billing_amount) val $baseJoin WHERE $mrWhereSql GROUP BY c.id ORDER BY val DESC LIMIT 1", $mrParams);
$highestBandwidthCustomer = run_row("SELECT c.customer_id AS code, c.customer_name AS label, SUM(m.bandwidth_mbps) val $baseJoin WHERE $mrWhereSql GROUP BY c.id ORDER BY val DESC LIMIT 1", $mrParams);

$top10Billing = run_all("SELECT c.customer_id AS code, c.customer_name AS label, co.company_name, SUM(m.billing_amount) val $baseJoin WHERE $mrWhereSql GROUP BY c.id ORDER BY val DESC LIMIT 10", $mrParams);
$top10Bandwidth = run_all("SELECT c.customer_id AS code, c.customer_name AS label, co.company_name, SUM(m.bandwidth_mbps) val $baseJoin WHERE $mrWhereSql GROUP BY c.id ORDER BY val DESC LIMIT 10", $mrParams);
$top10Zones = run_all("SELECT z.zone_name AS label, SUM(m.billing_amount) val $baseJoin WHERE $mrWhereSql GROUP BY z.id ORDER BY val DESC LIMIT 10", $mrParams);

/* -----------------------------------------------------------------
 * Chart data
 * ------------------------------------------------------------- */
$companyCustDist = run_all("SELECT co.company_name label, COUNT(*) val FROM customers c JOIN companies co ON co.id=c.company_id WHERE $custWhereSql GROUP BY co.id ORDER BY val DESC", $custParamsForCounts);
$companyBilling = run_all("SELECT co.company_name label, SUM(m.billing_amount) val $baseJoin WHERE $mrWhereSql GROUP BY co.id ORDER BY val DESC", $mrParams);
$companyBw = run_all("SELECT co.company_name label, SUM(m.bandwidth_mbps) val $baseJoin WHERE $mrWhereSql GROUP BY co.id ORDER BY val DESC", $mrParams);

$categoryCustDist = run_all("SELECT cat.category_name label, COUNT(*) val FROM customers c JOIN categories cat ON cat.id=c.category_id WHERE $custWhereSql GROUP BY cat.id ORDER BY val DESC", $custParamsForCounts);
$categoryBilling = run_all("SELECT cat.category_name label, SUM(m.billing_amount) val $baseJoin WHERE $mrWhereSql GROUP BY cat.id ORDER BY val DESC", $mrParams);
$categoryBw = run_all("SELECT cat.category_name label, SUM(m.bandwidth_mbps) val $baseJoin WHERE $mrWhereSql GROUP BY cat.id ORDER BY val DESC", $mrParams);

$zoneBilling = run_all("SELECT z.zone_name label, SUM(m.billing_amount) val $baseJoin WHERE $mrWhereSql GROUP BY z.id ORDER BY val DESC", $mrParams);
$zoneCustCount = run_all("SELECT z.zone_name label, COUNT(*) val FROM customers c JOIN zones z ON z.id=c.zone_id WHERE $custWhereSql GROUP BY z.id ORDER BY val DESC", $custParamsForCounts);
$zoneBw = run_all("SELECT z.zone_name label, SUM(m.bandwidth_mbps) val $baseJoin WHERE $mrWhereSql GROUP BY z.id ORDER BY val DESC", $mrParams);

$monthlyBillingTrend = run_all("SELECT DATE_FORMAT(m.billing_month,'%Y-%m') ym, SUM(m.billing_amount) val $baseJoin WHERE $trendWhereSql GROUP BY ym ORDER BY ym", $trendParams);
$monthlyCustomerTrend = run_all("SELECT DATE_FORMAT(m.billing_month,'%Y-%m') ym, COUNT(DISTINCT m.customer_id) val $baseJoin WHERE $trendWhereSql GROUP BY ym ORDER BY ym", $trendParams);
$monthlyBwTrend = run_all("SELECT DATE_FORMAT(m.billing_month,'%Y-%m') ym, SUM(m.bandwidth_mbps) val $baseJoin WHERE $trendWhereSql GROUP BY ym ORDER BY ym", $trendParams);


/* For Month to Month Collection Report */
// $collectionWhere = ['c.deleted_at IS NULL', 'cr.deleted_at IS NULL'];
// $collectionParams = [];

// if (!empty($month)) {
//     // Standardize to YYYY-MM-01 format
//     $collectionWhere[] = 'cr.billing_month = ?';
//     $collectionParams[] = date('Y-m-01', strtotime($month));
// } elseif (!empty($year)) {
//     $collectionWhere[] = 'YEAR(cr.billing_month) = ?';
//     $collectionParams[] = (int) $year;
// }

// if (!empty($companyId)) {
//     $collectionWhere [] = 'c.company_id = ?';
//     $collectionParams[] = (int) $companyId;
// }

// if (!empty($categoryId)) {
//     $collectionWhere[] = 'c.category_id = ?';
//     $collectionParams[] = (int) $categoryId;
// }

// if (!empty($zoneId)) {
//     $collectionWhere[] = 'c.zone_id = ?';
//     $collectionParams[] = (int) $zoneId;
// }

// $topWhere = $collectionWhere;
// $topParams = $collectionParams;

// $topWhere[] = "cr.status = 'Paid'";
$dueWhereSql = $mrWhereSql. " AND  m.collection_status = 'Due'";

$sql = "SELECT m.*, c.customer_id AS cust_code, c.customer_name, co.company_name
        $baseJoin
        WHERE $dueWhereSql
        ORDER BY m.id DESC";
$collection_due_records = run_all($sql , $mrParams);
// if(!empty($collection_due_records)){
//   foreach($collection_due_records as $record){
//       if(!empty($record['collection_segments'])){
//         $week_segments = 
//       }
//   }
// }

/* Get Top 10 collection due records */
$sqlTop10 = "SELECT c.id AS customer_id_pk, c.customer_id AS cust_code, c.customer_name, 
                    co.company_name, cat.category_name, z.zone_name,
                    SUM(m.billing_amount) AS total_due,
                    COUNT(m.id) AS total_months
             $baseJoin
             Where $dueWhereSql
             GROUP BY c.customer_id
             ORDER BY total_due DESC
             LIMIT 10";

$top10_collection_due_records = run_all($sqlTop10, $mrParams);
/* End */

/* Week Segment Wise Collection Due */
$WeeklyCollectionWhereSql = $mrWhereSql;
$sqlWeeklyCollection = "SELECT *
    FROM (
        SELECT
            m.collection_segment,
            c.id AS customer_id_pk,
            c.customer_id AS cust_code,
            c.customer_name,
            co.company_name,
            cat.category_name,
            z.zone_name,
            m.billing_month,
            SUM(m.billing_amount) AS target_collection,
            SUM(m.collection_amount) AS already_collected,
            SUM(
                m.billing_amount - m.collection_amount
            ) AS total_due,
            ROW_NUMBER() OVER (
                PARTITION BY m.collection_segment
                ORDER BY SUM(
                    m.billing_amount - m.collection_amount
                ) DESC
            ) AS row_num

        $baseJoin
        WHERE $WeeklyCollectionWhereSql
        GROUP BY
            m.collection_segment,
            c.id,
            c.customer_id,
            c.customer_name,
            co.company_name,
            cat.category_name,
            m.billing_month,
            z.zone_name
    ) AS ranked

    WHERE row_num <= 5

    ORDER BY
        CAST(SUBSTRING_INDEX(collection_segment, '-', 1) AS UNSIGNED) ASC,
        total_due DESC";
$weeklyCollectionRecords = run_all($sqlWeeklyCollection, $mrParams);
$week_segments = [];

foreach ($weeklyCollectionRecords as $row) {
    $week_segments[$row['collection_segment']][] = $row;
}
/* End */

/* Bank wise Collection */
$bankWiseCollectionWhereSql = $mrWhereSql;
$bankWiseCollectionJoin = $baseJoin . " JOIN banks b ON b.id = m.bank_id";
$sqlBankWiseCollection = "SELECT
                            b.bank_name,
                            SUM(m.billing_amount) AS target_collection,
                            SUM(m.collection_amount) AS total_collection
                          $bankWiseCollectionJoin
                          WHERE
                            $bankWiseCollectionWhereSql
                          GROUP BY
                            b.bank_name
                          limit 5";

$bankWiseCollectionRecords = run_all($sqlBankWiseCollection, $mrParams);
/* End */

/* Total Paid Collection */
$paidWhereSql = $mrWhereSql. " AND  m.collection_status = 'Paid'";
$sqlTotalPaid = "SELECT SUM(m.billing_amount) AS paid_amount
             $baseJoin
             Where $paidWhereSql";

$total_paid_collection = run_row($sqlTotalPaid, $mrParams);

/* End */

$activeVsInactive = ['Active' => $activeCustomers, 'Inactive' => $inactiveCustomers];

$companies = list_companies();
$categories = list_categories();
$zones = list_zones();
$years = year_options();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>
<div class="section-title">ISP Management Dashboard</div>
<div class="section-sub mb-3">Live overview of customers, billing and bandwidth across your network.</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-2"><label class="form-label">Reporting Month</label><input type="month" name="month" class="form-control" value="<?= e($month) ?>"></div>
      <div class="col-md-2"><label class="form-label">Year</label><select name="year" class="form-select">
          <option value="">All Years</option><?php foreach ($years as $y): ?>
            <option value="<?= $y ?>" <?= (string) $year === (string) $y ? 'selected' : '' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-2"><label class="form-label">Company</label><select name="company" class="form-select">
          <option value="">All</option><?= options_html($companies, 'id', 'company_name', $companyId) ?>
        </select></div>
      <div class="col-md-2"><label class="form-label">Category</label><select name="category" class="form-select">
          <option value="">All</option><?= options_html($categories, 'id', 'category_name', $categoryId) ?>
        </select></div>
      <div class="col-md-2"><label class="form-label">Zone</label><select name="zone" class="form-select">
          <option value="">All</option><?= options_html($zones, 'id', 'zone_name', $zoneId) ?>
        </select></div>
      <div class="col-md-1"><label class="form-label">Status</label><select name="status" class="form-select">
          <option value="">All</option>
          <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
          <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
        </select></div>
      <div class="col-md-1 d-flex gap-1">
        <button class="btn btn-primary flex-fill" type="submit" title="Apply Filter"><i
            class="fa-solid fa-filter"></i></button>
        <a href="<?= e(base_url('dashboard.php')) ?>" class="btn btn-outline-secondary flex-fill" title="Reset"><i
            class="fa-solid fa-rotate-left"></i></a>
      </div>
    </form>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-xl-3 col-md-6">
    <div class="card kpi-card h-100">
      <div class="d-flex justify-content-between">
        <div>
          <div class="kpi-label">Active Customers</div>
          <div class="kpi-value"><?= number_format($activeCustomers) ?></div>
        </div>
        <div class="kpi-icon bg-icon-green"><i class="fa-solid fa-user-check"></i></div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6">
    <div class="card kpi-card h-100">
      <div class="d-flex justify-content-between">
        <div>
          <div class="kpi-label">Inactive Customers</div>
          <div class="kpi-value"><?= number_format($inactiveCustomers) ?></div>
        </div>
        <div class="kpi-icon bg-icon-red"><i class="fa-solid fa-user-xmark"></i></div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6">
    <div class="card kpi-card h-100">
      <div class="d-flex justify-content-between">
        <div>
          <div class="kpi-label">Total Customers</div>
          <div class="kpi-value"><?= number_format($totalCustomers) ?></div>
        </div>
        <div class="kpi-icon bg-icon-blue"><i class="fa-solid fa-users"></i></div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6">
    <div class="card kpi-card h-100">
      <div class="d-flex justify-content-between">
        <div>
          <div class="kpi-label">Total Billing</div>
          <div class="kpi-value" style="font-size:1.3rem;"><?= format_currency($totalBilling) ?></div>
        </div>
        <div class="kpi-icon bg-icon-amber"><i class="fa-solid fa-sack-dollar"></i></div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6">
    <div class="card kpi-card h-100">
      <div class="d-flex justify-content-between">
        <div>
          <div class="kpi-label">Total BW Sold</div>
          <div class="kpi-value" style="font-size:1.3rem;"><?= format_bandwidth($totalBw) ?></div>
        </div>
        <div class="kpi-icon bg-icon-purple"><i class="fa-solid fa-gauge"></i></div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6">
    <div class="card kpi-card h-100">
      <div class="d-flex justify-content-between">
        <div>
          <div class="kpi-label">Avg Billing / Active Customer</div>
          <div class="kpi-value" style="font-size:1.15rem;"><?= format_currency($avgBillingPerActive) ?></div>
        </div>
        <div class="kpi-icon bg-icon-teal"><i class="fa-solid fa-calculator"></i></div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6">
    <div class="card kpi-card h-100">
      <div class="d-flex justify-content-between">
        <div>
          <div class="kpi-label">Avg BW / Active Customer</div>
          <div class="kpi-value" style="font-size:1.15rem;"><?= format_bandwidth($avgBwPerActive) ?></div>
        </div>
        <div class="kpi-icon bg-icon-blue"><i class="fa-solid fa-wave-square"></i></div>
      </div>
    </div>
  </div>
  <!-- Total Paid Collection -->
  <?php 
    $paid_params = array_merge($_GET, ['status' => 'Paid']); 
  ?>
  <div class="col-xl-3 col-md-6">
    <a href="<?= e(base_url('billing/collection_list.php?' . http_build_query($paid_params))) ?>" class="text-decoration-none text-reset">
      <div class="card kpi-card h-100 kpi-card-hover">
        <div class="d-flex justify-content-between">
          <div>
            <div class="kpi-label">Total Paid Collection</div>
            <div class="kpi-value" style="font-size:1.3rem;"><?= format_currency($total_paid_collection['paid_amount']) ?></div>
          </div>
          <div class="kpi-icon bg-icon-green"><i class="fa-solid fa-check"></i></div>
        </div>
      </div>
    </a>
  </div>

<!-- Weekly Targeted Collection Reports -->
<div class="row g-3 mb-1">
  <div class="col-12">
    <div class="fw-bold small text-uppercase text-muted">Weekly Targeted Collection Reports</div>
  </div>
  <?php $params = array_merge($_GET); ?>
  <!-- Total Due -->
  <?php foreach ($week_segments as $segment => $customers): ?>
    <div class="col-lg-6 mb-2">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>
                    Week <?= e($segment) ?>
                </strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0">
                        <thead>
                            <tr>
                                <th class="text-center">Customer</th>
                                <th>Target Collection</th>
                                <th>Already Collected</th>
                                <th>Due Collection</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td class="text-center">
                                        <?= e($customer['customer_name']) ?>
                                        </span><br><span class="text-muted small"><?= e($customer['company_name']) ?>
                                        </span><br><span class="text-muted small"><?= e(format_month($customer['billing_month'])) ?>
                                    </td>

                                    <td class="text-center">
                                        <?= number_format($customer['target_collection']) ?>
                                    </td>

                                    <td class="text-center">
                                        <?= number_format($customer['already_collected']) ?>
                                    </td>

                                    <td class="text-center">
                                        <strong>
                                            <?= number_format($customer['total_due']) ?>
                                        </strong>
                                    </td>
                                </tr>

                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
  <?php endforeach; ?>
</div>
<!-- End -->

<!-- Bank Wise Collection Reports -->
 <div class="row g-3 mb-1">
  <div class="col-12">
    <div class="fw-bold small text-uppercase text-muted">Bank Wise Collection Reports</div>
  </div>
  <!-- Bank Collection Total -->
    <div class="col-lg-6 mb-2">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-center align-items-center">
        <span>Top 5 Bank Collection</span>
      </div>
      <div class="table-responsive">
       <table class="table table-bordered table-sm mb-0">
            <thead>
                <tr>
                    <th>Bank Name</th>
                    <th>Target</th>
                    <th>Collected</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bankWiseCollectionRecords as $bank_collection): ?>
                    <tr>
                        <td>
                            <?= e($bank_collection['bank_name']) ?>
                        </td>
                        <td>
                            <?= number_format($bank_collection['target_collection']) ?>
                        </td>
                        <td>
                            <?= number_format($bank_collection['total_collection']) ?>
                        </td>
                    </tr>

                <?php endforeach; ?>
            </tbody>
        </table>
      </div>
    </div>
  </div>
  <!-- End -->
</div>

<div class="row g-3 mb-1">
  <div class="col-12">
    <div class="fw-bold small text-uppercase text-muted">Management Highlights</div>
  </div>
  <?php
  $callouts = [
    ['Highest Billing Company', $highestBillingCompany, 'currency', 'fa-building', 'bg-icon-blue'],
    ['Highest Billing Zone', $highestBillingZone, 'currency', 'fa-map-location-dot', 'bg-icon-green'],
    ['Highest Customer Zone', $highestCustomerZone, 'number', 'fa-users', 'bg-icon-amber'],
    ['Highest Bandwidth Zone', $highestBandwidthZone, 'bw', 'fa-gauge-high', 'bg-icon-purple'],
    ['Highest Billing Category', $highestBillingCategory, 'currency', 'fa-layer-group', 'bg-icon-teal'],
    ['Highest Billing Customer', $highestBillingCustomer, 'currency', 'fa-crown', 'bg-icon-red'],
    ['Highest Bandwidth Customer', $highestBandwidthCustomer, 'bw', 'fa-signal', 'bg-icon-blue'],
  ];
  foreach ($callouts as [$label, $row, $type, $icon, $bg]):
    $val = $row ? ($type === 'currency' ? format_currency($row['val']) : ($type === 'bw' ? format_bandwidth($row['val']) : number_format($row['val']))) : '-';
  ?>
    <div class="col-xl-3 col-md-6">
      <div class="card kpi-card h-100">
        <div class="d-flex justify-content-between">
          <div>
            <div class="kpi-label"><?= e($label) ?></div>
            <div class="fw-bold" style="font-size:1rem;"><?= $row ? e($row['label']) : '-' ?><?= isset($row['code']) ? ' <span class="text-muted small">(' . e($row['code']) . ')</span>' : '' ?></div>
            <div class="text-primary fw-semibold small"><?= $val ?></div>
          </div>
          <div class="kpi-icon <?= $bg ?>"><i class="fa-solid <?= $icon ?>"></i></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Company-wise Customer Distribution</div>
      <div class="card-body">
        <div class="chart-box-sm"><canvas id="chCompanyCust"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Company-wise Billing</div>
      <div class="card-body">
        <div class="chart-box-sm"><canvas id="chCompanyBilling"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Company-wise Bandwidth</div>
      <div class="card-body">
        <div class="chart-box-sm"><canvas id="chCompanyBw"></canvas></div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Category-wise Customer Distribution</div>
      <div class="card-body">
        <div class="chart-box-sm"><canvas id="chCategoryCust"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Category-wise Billing</div>
      <div class="card-body">
        <div class="chart-box-sm"><canvas id="chCategoryBilling"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Category-wise Bandwidth</div>
      <div class="card-body">
        <div class="chart-box-sm"><canvas id="chCategoryBw"></canvas></div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">Zone-wise Billing</div>
      <div class="card-body">
        <div class="chart-box"><canvas id="chZoneBilling"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-3">
    <div class="card">
      <div class="card-header">Zone-wise Customer Count</div>
      <div class="card-body">
        <div class="chart-box"><canvas id="chZoneCust"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-3">
    <div class="card">
      <div class="card-header">Zone-wise Bandwidth</div>
      <div class="card-body">
        <div class="chart-box"><canvas id="chZoneBw"></canvas></div>
      </div>
    </div>
  </div>

  <div class="col-lg-12">
    <div class="card">
      <div class="card-header">Monthly Billing Trend</div>
      <div class="card-body">
        <div class="chart-box"><canvas id="chMonthlyBilling"></canvas></div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">Monthly Customer Trend</div>
      <div class="card-body">
        <div class="chart-box"><canvas id="chMonthlyCust"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">Monthly Bandwidth Trend</div>
      <div class="card-body">
        <div class="chart-box"><canvas id="chMonthlyBw"></canvas></div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Active vs Inactive Customers</div>
      <div class="card-body">
        <div class="chart-box-sm"><canvas id="chActiveInactive"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">Top 10 Customers by Billing</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <tbody>
            <?php if (!$top10Billing): ?><tr>
                <td class="text-muted small p-3">No data.</td>
              </tr><?php endif; ?>
            <?php foreach ($top10Billing as $i => $r): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><?= e($r['code']) ?><br><span class="text-muted small"><?= e($r['label']) ?></span></td>
                <td class="text-end fw-semibold"><?= format_currency($r['val']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">Top 10 Customers by Bandwidth</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <tbody>
            <?php if (!$top10Bandwidth): ?><tr>
                <td class="text-muted small p-3">No data.</td>
              </tr><?php endif; ?>
            <?php foreach ($top10Bandwidth as $i => $r): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><?= e($r['code']) ?><br><span class="text-muted small"><?= e($r['label']) ?></span></td>
                <td class="text-end fw-semibold"><?= format_bandwidth($r['val']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
$labels = fn($rows) => array_map(fn($r) => $r['label'], $rows);
$vals = fn($rows) => array_map(fn($r) => (float) $r['val'], $rows);
$trendLabels = array_map('format_month', array_map(fn($r) => $r['ym'] . '-01', $monthlyBillingTrend));

$chartJs = "document.addEventListener('DOMContentLoaded', function(){
  if (typeof Chart === 'undefined') {
    console.error('Chart.js is not loaded.');
    return;
  }
  var C = window.ISM_COLORS || ['#2563eb', '#16a34a', '#f59e0b', '#8b5cf6', '#ef4444', '#14b8a6'];
  
  function pie(id, labels, data){ 
    var el = document.getElementById(id);
    if(el) new Chart(el, {type:'doughnut', data:{labels:labels, datasets:[{data:data, backgroundColor:C}]}, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:10}}}}}}); 
  }
  function bar(id, labels, data, color, horizontal){ 
    var el = document.getElementById(id);
    if(el) new Chart(el, {type:'bar', data:{labels:labels, datasets:[{data:data, backgroundColor:color||'#2563eb', borderRadius:4}]}, options:{indexAxis:horizontal?'y':'x', responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}}}}); 
  }
  function line(id, labels, datasets){ 
    var el = document.getElementById(id);
    if(el) new Chart(el, {type:'line', data:{labels:labels, datasets:datasets}, options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false}}}); 
  }

  pie('chCompanyCust', " . json_encode($labels($companyCustDist)) . ", " . json_encode($vals($companyCustDist)) . ");
  bar('chCompanyBilling', " . json_encode($labels($companyBilling)) . ", " . json_encode($vals($companyBilling)) . ", '#2563eb');
  bar('chCompanyBw', " . json_encode($labels($companyBw)) . ", " . json_encode($vals($companyBw)) . ", '#16a34a');

  pie('chCategoryCust', " . json_encode($labels($categoryCustDist)) . ", " . json_encode($vals($categoryCustDist)) . ");
  bar('chCategoryBilling', " . json_encode($labels($categoryBilling)) . ", " . json_encode($vals($categoryBilling)) . ", '#f59e0b');
  bar('chCategoryBw', " . json_encode($labels($categoryBw)) . ", " . json_encode($vals($categoryBw)) . ", '#8b5cf6');

  bar('chZoneBilling', " . json_encode($labels($zoneBilling)) . ", " . json_encode($vals($zoneBilling)) . ", '#2563eb', true);
  bar('chZoneCust', " . json_encode($labels($zoneCustCount)) . ", " . json_encode($vals($zoneCustCount)) . ", '#16a34a', true);
  bar('chZoneBw', " . json_encode($labels($zoneBw)) . ", " . json_encode($vals($zoneBw)) . ", '#14b8a6', true);

  line('chMonthlyBilling', " . json_encode($trendLabels) . ", [{label:'Billing (BDT)', data:" . json_encode($vals($monthlyBillingTrend)) . ", borderColor:'#2563eb', backgroundColor:'rgba(37,99,235,.1)', tension:.3, fill:true}]);
  line('chMonthlyCust', " . json_encode($trendLabels) . ", [{label:'Customers', data:" . json_encode($vals($monthlyCustomerTrend)) . ", borderColor:'#16a34a', backgroundColor:'rgba(22,163,74,.1)', tension:.3, fill:true}]);
  line('chMonthlyBw', " . json_encode($trendLabels) . ", [{label:'Bandwidth (Mbps)', data:" . json_encode($vals($monthlyBwTrend)) . ", borderColor:'#8b5cf6', backgroundColor:'rgba(139,92,246,.1)', tension:.3, fill:true}]);

  pie('chActiveInactive', ['Active','Inactive'], [" . (int) $activeCustomers . "," . (int) $inactiveCustomers . "]);
});";
$extraScripts = '<script>' . $chartJs . '</script>';
require_once __DIR__ . '/includes/footer.php';
?>
