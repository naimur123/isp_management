<?php
require_once __DIR__ . '/../config/config.php';
http_response_code(404);
$pageTitle = '404 Not Found';
$activeNav = '';
if (is_logged_in() && !empty(current_user())) {
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="d-flex flex-column align-items-center justify-content-center text-center py-5">
      <i class="fa-solid fa-magnifying-glass text-muted" style="font-size:3.5rem;"></i>
      <h2 class="fw-bold mt-3">404 — Page Not Found</h2>
      <p class="text-muted">The page you are looking for does not exist or may have been moved.</p>
      <a href="<?= e(base_url('dashboard.php')) ?>" class="btn btn-primary mt-2"><i class="fa-solid fa-house me-1"></i>Back to Dashboard</a>
    </div>
    <?php require_once __DIR__ . '/../includes/footer.php';
} else {
    redirect(base_url('login.php'));
}
