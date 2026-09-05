<?php
require_once __DIR__ . '/../config/config.php';
http_response_code(403);
if (is_logged_in()) {
    log_activity('Unauthorized Access Attempt', 'Security', null, ($_SERVER['REQUEST_URI'] ?? ''));
}
$pageTitle = '403 Unauthorized';
$activeNav = '';
if (is_logged_in() && !empty(current_user())) {
    require_once __DIR__ . '/../includes/header.php';
} else {
    redirect(base_url('login.php'));
}
?>
<div class="d-flex flex-column align-items-center justify-content-center text-center py-5">
  <i class="fa-solid fa-lock text-danger" style="font-size:3.5rem;"></i>
  <h2 class="fw-bold mt-3">403 — Unauthorized Access</h2>
  <p class="text-muted">You do not have permission to view this page. This action requires Full Access / Administrator privileges.</p>
  <a href="<?= e(base_url('dashboard.php')) ?>" class="btn btn-primary mt-2"><i class="fa-solid fa-house me-1"></i>Back to Dashboard</a>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
