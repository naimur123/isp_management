<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$errors = [];
$current = app_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $values = [
        'software_name' => post('software_name'),
        'organization_name' => post('organization_name'),
        'currency_code' => strtoupper(post('currency_code')),
        'currency_symbol' => post('currency_symbol'),
        'timezone' => post('timezone', 'Asia/Dhaka'),
        'date_format' => post('date_format', 'd-m-Y'),
        'month_format' => post('month_format', 'M-Y'),
        'customer_id_digits' => post('customer_id_digits', '5'),
        'default_pagination' => post('default_pagination', '25'),
        'excel_upload_limit_mb' => post('excel_upload_limit_mb', '10'),
        'session_timeout_minutes' => post('session_timeout_minutes', '60'),
    ];

    if ($values['software_name'] === '') $errors[] = 'Software Name is required.';
    if (!in_array((int) $values['customer_id_digits'], [4, 5, 6, 7, 8], true)) $errors[] = 'Customer ID Digit Length must be between 4 and 8.';
    if (!in_array((int) $values['default_pagination'], [10, 25, 50, 100], true)) $errors[] = 'Default Pagination must be 10, 25, 50 or 100.';
    if ((int) $values['excel_upload_limit_mb'] < 1 || (int) $values['excel_upload_limit_mb'] > 50) $errors[] = 'Excel Upload Limit must be between 1 and 50 MB.';
    if ((int) $values['session_timeout_minutes'] < 5) $errors[] = 'Session Timeout must be at least 5 minutes.';

    $logoPath = $current['logo_path'] ?? '';
    if (!empty($_FILES['logo']['name'])) {
        if ($_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['logo']['tmp_name']);
            finfo_close($finfo);
            $allowedMime = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'];
            if (!in_array($ext, $allowed, true) || !in_array($mime, $allowedMime, true)) {
                $errors[] = 'Logo must be a PNG, JPG, WEBP, or SVG image.';
            } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Logo file must be smaller than 2MB.';
            } else {
                $dir = APP_ROOT . '/uploads/logo';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $filename = 'logo_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir . '/' . $filename)) {
                    $logoPath = 'uploads/logo/' . $filename;
                } else {
                    $errors[] = 'Could not save the uploaded logo.';
                }
            }
        }
    }
    $values['logo_path'] = $logoPath;

    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($values as $k => $v) {
            $stmt->execute([$k, $v]);
        }
        log_activity('Settings Updated', 'Settings', null, 'System settings were updated.');
        flash_set('success', 'Settings saved successfully.');
        redirect(base_url('admin/settings.php'));
    }
    $current = array_merge($current, $values);
}

$pageTitle = 'System Settings';
$activeNav = 'admin_settings';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="section-title">System Settings</div>
<div class="section-sub mb-3">Branding, formats and system-wide defaults.</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card"><div class="card-body p-4">
      <?php if ($errors): ?><div class="alert alert-danger small"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <h6 class="fw-bold text-uppercase small text-muted mb-3">Branding</h6>
        <div class="row">
          <div class="col-md-6 mb-3"><label class="form-label required">Software Name</label><input type="text" name="software_name" class="form-control" required value="<?= e($current['software_name']) ?>"></div>
          <div class="col-md-6 mb-3"><label class="form-label">Organization Name</label><input type="text" name="organization_name" class="form-control" value="<?= e($current['organization_name']) ?>"></div>
        </div>
        <div class="mb-4">
          <label class="form-label">Logo</label>
          <?php if (!empty($current['logo_path'])): ?><div class="mb-2"><img src="<?= e(base_url($current['logo_path'])) ?>" style="height:44px;" alt="Logo"></div><?php endif; ?>
          <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/svg+xml,image/webp">
          <div class="form-text">PNG, JPG, WEBP or SVG. Maximum 2MB.</div>
        </div>

        <h6 class="fw-bold text-uppercase small text-muted mb-3">Regional Formats</h6>
        <div class="row">
          <div class="col-md-4 mb-3"><label class="form-label">Currency Code</label><input type="text" name="currency_code" class="form-control" value="<?= e($current['currency_code']) ?>"></div>
          <div class="col-md-4 mb-3"><label class="form-label">Currency Symbol</label><input type="text" name="currency_symbol" class="form-control" value="<?= e($current['currency_symbol']) ?>"></div>
          <div class="col-md-4 mb-3"><label class="form-label">Time Zone</label><input type="text" name="timezone" class="form-control" value="<?= e($current['timezone']) ?>">
            <div class="form-text">Recommended: Asia/Dhaka</div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3"><label class="form-label">Date Format</label>
            <select name="date_format" class="form-select">
              <option value="d-m-Y" <?= $current['date_format'] === 'd-m-Y' ? 'selected' : '' ?>>DD-MM-YYYY (29-08-2026)</option>
              <option value="d/m/Y" <?= $current['date_format'] === 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY (29/08/2026)</option>
              <option value="Y-m-d" <?= $current['date_format'] === 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD (2026-08-29)</option>
            </select>
          </div>
          <div class="col-md-6 mb-3"><label class="form-label">Month Format</label>
            <select name="month_format" class="form-select">
              <option value="M-Y" <?= $current['month_format'] === 'M-Y' ? 'selected' : '' ?>>MMM-YYYY (Aug-2026)</option>
              <option value="F Y" <?= $current['month_format'] === 'F Y' ? 'selected' : '' ?>>Month YYYY (August 2026)</option>
            </select>
          </div>
        </div>

        <h6 class="fw-bold text-uppercase small text-muted mb-3">System Defaults</h6>
        <div class="row">
          <div class="col-md-3 mb-3"><label class="form-label">Customer ID Digit Length</label>
            <select name="customer_id_digits" class="form-select"><?php foreach ([4,5,6,7,8] as $d): ?><option value="<?= $d ?>" <?= (int) $current['customer_id_digits'] === $d ? 'selected' : '' ?>><?= $d ?> digits</option><?php endforeach; ?></select>
          </div>
          <div class="col-md-3 mb-3"><label class="form-label">Default Pagination</label>
            <select name="default_pagination" class="form-select"><?php foreach ([10,25,50,100] as $p): ?><option value="<?= $p ?>" <?= (int) $current['default_pagination'] === $p ? 'selected' : '' ?>><?= $p ?> rows</option><?php endforeach; ?></select>
          </div>
          <div class="col-md-3 mb-3"><label class="form-label">Excel Upload Limit (MB)</label><input type="number" min="1" max="50" name="excel_upload_limit_mb" class="form-control" value="<?= e($current['excel_upload_limit_mb']) ?>"></div>
          <div class="col-md-3 mb-3"><label class="form-label">Session Timeout (min)</label><input type="number" min="5" name="session_timeout_minutes" class="form-control" value="<?= e($current['session_timeout_minutes']) ?>"></div>
        </div>

        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Save Settings</button>
      </form>
    </div></div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
