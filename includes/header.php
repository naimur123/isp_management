<?php
/**
 * Shared page header + sidebar + topbar.
 * Expects (optional): $pageTitle, $activeNav, $breadcrumbs (array of ['label'=>,'url'=>])
 */
require_login();
$__user = current_user();
$pageTitle = $pageTitle ?? 'Dashboard';
$activeNav = $activeNav ?? '';
$softwareName = setting('software_name', 'ISP Management System');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> · <?= e($softwareName) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.11/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
</head>
<body>
<div class="ism-wrapper">

  <!-- Sidebar -->
  <aside class="ism-sidebar" id="ismSidebar">
    <div class="brand">
      <div class="logo-badge"><i class="fa-solid fa-tower-broadcast"></i></div>
      <div class="brand-text"><?= e($softwareName) ?><small><?= e(setting('organization_name', '')) ?></small></div>
    </div>
    <nav class="nav flex-column pb-4">
      <a class="nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>" href="<?= e(base_url('dashboard.php')) ?>">
        <i class="fa-solid fa-gauge-high"></i> Dashboard
      </a>

      <div class="ism-nav-section-title">Customer Management</div>
      <?php if (is_admin()): ?>
      <a class="nav-link <?= $activeNav === 'customer_add' ? 'active' : '' ?>" href="<?= e(base_url('customers/add.php')) ?>">
        <i class="fa-solid fa-user-plus"></i> Add New Customer
      </a>
      <?php endif; ?>
      <a class="nav-link <?= $activeNav === 'customer_list' ? 'active' : '' ?>" href="<?= e(base_url('customers/index.php')) ?>">
        <i class="fa-solid fa-users"></i> Customer List
      </a>

      <div class="ism-nav-section-title">Billing &amp; Input</div>
      <?php if (is_admin()): ?>
      <a class="nav-link <?= $activeNav === 'billing_add' ? 'active' : '' ?>" href="<?= e(base_url('billing/add.php')) ?>">
        <i class="fa-solid fa-file-circle-plus"></i> Manual Monthly Input
      </a>
      <?php endif; ?>
      <a class="nav-link <?= $activeNav === 'billing_list' ? 'active' : '' ?>" href="<?= e(base_url('billing/index.php')) ?>">
        <i class="fa-solid fa-receipt"></i> All Monthly Records
      </a>

      <!-- Newly added Collection Input -->
      <a class="nav-link <?= $activeNav === 'collection_list' ? 'active' : '' ?>" href="<?= e(base_url('billing/collection_list.php')) ?>">
        <i class="fa-solid fa-receipt"></i> Collection List
      </a>
      <!-- End -->

      <?php if (is_admin()): ?>
      <a class="nav-link <?= $activeNav === 'import_upload' ? 'active' : '' ?>" href="<?= e(base_url('import/upload.php')) ?>">
        <i class="fa-solid fa-file-excel"></i> Excel Bulk Input
      </a>
      <a class="nav-link <?= $activeNav === 'import_history' ? 'active' : '' ?>" href="<?= e(base_url('import/history.php')) ?>">
        <i class="fa-solid fa-clock-rotate-left"></i> Import History
      </a>
      <?php endif; ?>

      <div class="ism-nav-section-title">Reports</div>
      <a class="nav-link <?= $activeNav === 'report_central' ? 'active' : '' ?>" href="<?= e(base_url('reports/central.php')) ?>">
        <i class="fa-solid fa-table-cells"></i> Central Report
      </a>
      <a class="nav-link <?= $activeNav === 'report_company' ? 'active' : '' ?>" href="<?= e(base_url('reports/company.php')) ?>">
        <i class="fa-solid fa-building"></i> Company Report
      </a>
      <a class="nav-link <?= $activeNav === 'report_category' ? 'active' : '' ?>" href="<?= e(base_url('reports/category.php')) ?>">
        <i class="fa-solid fa-layer-group"></i> Category Report
      </a>
      <a class="nav-link <?= $activeNav === 'report_zone' ? 'active' : '' ?>" href="<?= e(base_url('reports/zone.php')) ?>">
        <i class="fa-solid fa-map-location-dot"></i> Zone Report
      </a>
      <a class="nav-link <?= $activeNav === 'report_monthly' ? 'active' : '' ?>" href="<?= e(base_url('reports/monthly.php')) ?>">
        <i class="fa-solid fa-calendar-days"></i> Monthly Report
      </a>
      <a class="nav-link <?= $activeNav === 'report_customer' ? 'active' : '' ?>" href="<?= e(base_url('reports/customer.php')) ?>">
        <i class="fa-solid fa-id-card"></i> Customer Report
      </a>

      <?php if (is_admin()): ?>
      <div class="ism-nav-section-title">Administration</div>
      <a class="nav-link <?= $activeNav === 'admin_users' ? 'active' : '' ?>" href="<?= e(base_url('admin/users.php')) ?>">
        <i class="fa-solid fa-user-shield"></i> User Management
      </a>
      <a class="nav-link <?= $activeNav === 'admin_companies' ? 'active' : '' ?>" href="<?= e(base_url('admin/companies.php')) ?>">
        <i class="fa-solid fa-building-columns"></i> Companies
      </a>
      <a class="nav-link <?= $activeNav === 'admin_categories' ? 'active' : '' ?>" href="<?= e(base_url('admin/categories.php')) ?>">
        <i class="fa-solid fa-tags"></i> Categories
      </a>
      <a class="nav-link <?= $activeNav === 'admin_zones' ? 'active' : '' ?>" href="<?= e(base_url('admin/zones.php')) ?>">
        <i class="fa-solid fa-map-pin"></i> Zones
      </a>
      <a class="nav-link <?= $activeNav === 'banks' ? 'active' : '' ?>" href="<?= e(base_url('admin/banks.php')) ?>">
        <i class="fa-solid fa-building"></i> Banks
      </a>
      <a class="nav-link <?= $activeNav === 'admin_activity' ? 'active' : '' ?>" href="<?= e(base_url('admin/activity_log.php')) ?>">
        <i class="fa-solid fa-list-check"></i> Activity Log
      </a>
      <a class="nav-link <?= $activeNav === 'admin_settings' ? 'active' : '' ?>" href="<?= e(base_url('admin/settings.php')) ?>">
        <i class="fa-solid fa-gears"></i> System Settings
      </a>
      <?php endif; ?>

      <div class="ism-nav-section-title">My Account</div>
      <a class="nav-link <?= $activeNav === 'profile' ? 'active' : '' ?>" href="<?= e(base_url('account/profile.php')) ?>">
        <i class="fa-solid fa-id-badge"></i> Profile
      </a>
      <a class="nav-link <?= $activeNav === 'change_password' ? 'active' : '' ?>" href="<?= e(base_url('account/change_password.php')) ?>">
        <i class="fa-solid fa-key"></i> Change Password
      </a>
      <a class="nav-link" href="<?= e(base_url('logout.php')) ?>">
        <i class="fa-solid fa-right-from-bracket"></i> Logout
      </a>
    </nav>
  </aside>
  <div class="d-none" id="sidebarBackdrop" style="position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1035;"></div>

  <div class="ism-main">
    <!-- Topbar -->
    <div class="ism-topbar no-print">
      <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle" type="button">
        <i class="fa-solid fa-bars"></i>
      </button>

      <form class="flex-grow-1" style="max-width:420px;" action="<?= e(base_url('reports/customer.php')) ?>" method="get">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
          <input type="text" class="form-control" name="q" placeholder="Search Customer ID or Name..." value="<?= e($_GET['q'] ?? '') ?>">
        </div>
      </form>

      <div class="ms-auto d-flex align-items-center gap-3">
        <span class="badge rounded-pill <?= is_admin() ? 'bg-primary-subtle text-primary-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?> border">
          <i class="fa-solid <?= is_admin() ? 'fa-user-shield' : 'fa-eye' ?>"></i>
          <?= is_admin() ? 'Full Access' : 'View Only' ?>
        </span>
        <div class="dropdown">
          <a class="d-flex align-items-center gap-2 text-decoration-none text-dark cursor-pointer" data-bs-toggle="dropdown">
            <span class="avatar-circle"><?= e(strtoupper(substr($__user['full_name'] ?? 'U', 0, 1))) ?></span>
            <span class="d-none d-md-inline small fw-semibold"><?= e($__user['full_name'] ?? '') ?></span>
            <i class="fa-solid fa-chevron-down small text-muted"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li><a class="dropdown-item" href="<?= e(base_url('account/profile.php')) ?>"><i class="fa-solid fa-id-badge me-2"></i>Profile</a></li>
            <li><a class="dropdown-item" href="<?= e(base_url('account/change_password.php')) ?>"><i class="fa-solid fa-key me-2"></i>Change Password</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= e(base_url('logout.php')) ?>"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
          </ul>
        </div>
      </div>
    </div>

    <div class="ism-content">
      <?php $flashes = flash_get(); if ($flashes): ?>
        <?php foreach ($flashes as $f): ?>
          <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
            <?= $f['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
