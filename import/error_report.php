<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/import_helpers.php';
require_admin();

$stage = get_param('stage');
$rows = [];

if ($stage === 'preview') {
    $token = get_param('token');
    $ext = get_param('ext');
    if (!$token || !preg_match('/^[a-f0-9]{32}$/', $token) || !in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
        flash_set('danger', 'Invalid import session.');
        redirect(base_url('import/upload.php'));
    }
    $path = import_upload_dir() . '/' . $token . '.' . $ext;
    if (!is_file($path)) {
        flash_set('danger', 'The uploaded file could not be found.');
        redirect(base_url('import/upload.php'));
    }
    $parsed = parse_import_file($path);
    foreach ($parsed['rows'] as $r) {
        if ($r['row_status'] === 'error') {
            $rows[] = [$r['excel_row'], $r['customer_id_raw'], $r['customer_name'], 'Validation Failed', implode(' ', $r['errors'])];
        }
    }
} elseif ($stage === 'final') {
    $importId = (int) get_param('import_id');
    $stmt = db()->prepare('SELECT * FROM import_errors WHERE import_id = ? ORDER BY excel_row');
    $stmt->execute([$importId]);
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = [$r['excel_row'], $r['customer_id'], $r['customer_name'], $r['error_type'], $r['error_description']];
    }
} else {
    redirect(base_url('import/upload.php'));
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment;filename="import_error_report.csv"');
$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
fputcsv($out, ['Excel Row', 'Customer ID', 'Customer Name', 'Error Type', 'Error Description'], ',', '"', '\\');
foreach ($rows as $r) {
    fputcsv($out, $r, ',', '"', '\\');
}
fclose($out);
exit;
