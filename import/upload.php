<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/import_helpers.php';
require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    if (empty($_FILES['excel_file']) || $_FILES['excel_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please choose a file to upload.';
    } elseif ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'The file could not be uploaded. Please try again.';
    } else {
        $file = $_FILES['excel_file'];
        $limitBytes = (int) setting('excel_upload_limit_mb', 10) * 1024 * 1024;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExt = ['xlsx', 'xls', 'csv'];
        $allowedMime = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/msexcel',
            'text/csv',
            'text/plain',
            'application/csv',
            'application/octet-stream',
            'application/zip',
        ];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($ext, $allowedExt, true)) {
            $errors[] = 'Unsupported file type. Please upload a .xlsx, .xls, or .csv file.';
        } elseif ($file['size'] > $limitBytes) {
            $errors[] = 'File is too large. Maximum allowed size is ' . setting('excel_upload_limit_mb', 10) . ' MB.';
        } elseif (!in_array($mime, $allowedMime, true)) {
            $errors[] = 'The uploaded file does not appear to be a valid spreadsheet.';
        } else {
            $token = bin2hex(random_bytes(16));
            $dest = import_upload_dir() . '/' . $token . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $_SESSION['import_' . $token] = ['original_name' => $file['name']];
                log_activity('Excel Uploaded', 'Import', $token, 'Uploaded file ' . $file['name']);
                redirect(base_url('import/preview.php?token=' . $token . '&ext=' . $ext));
            } else {
                $errors[] = 'Could not save the uploaded file on the server.';
            }
        }
    }
}

$pageTitle = 'Excel Bulk Input';
$activeNav = 'import_upload';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="section-title">Excel Bulk Input</div>
<div class="section-sub mb-3">Upload hundreds of customer &amp; billing records at once. Nothing is saved until you review and confirm the import.</div>

<div class="row">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-body p-4">
        <?php if ($errors): ?>
          <div class="alert alert-danger small"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label required">Spreadsheet File</label>
            <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv" required>
            <div class="form-text">Supported formats: .xlsx, .xls, .csv &middot; Maximum size: <?= e(setting('excel_upload_limit_mb', 10)) ?> MB</div>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload me-1"></i>Upload &amp; Preview</button>
          <a href="<?= e(base_url('import/template.php')) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-download me-1"></i>Download Excel Template</a>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card bg-light border-0">
      <div class="card-body">
        <h6 class="fw-bold"><i class="fa-solid fa-circle-info text-primary me-1"></i>How this works</h6>
        <ol class="small text-muted mb-0 ps-3">
          <li>Upload your spreadsheet.</li>
          <li>Review the validation preview — every row is checked before anything is saved.</li>
          <li>Choose how to handle duplicate monthly records.</li>
          <li>Confirm the import. Only valid rows are written to the database.</li>
        </ol>
        <hr>
        <p class="small text-muted mb-0">Leave <strong>Customer ID</strong> blank for a brand-new customer — the system generates the ID automatically. Provide an existing <strong>Customer ID</strong> to add that customer's monthly billing.</p>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
