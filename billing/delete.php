<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('billing/index.php'));
}
csrf_require();

$id = (int) post('id');
$stmt = db()->prepare('SELECT m.*, c.customer_id AS cust_code FROM monthly_records m JOIN customers c ON c.id=m.customer_id WHERE m.id = ? AND m.deleted_at IS NULL');
$stmt->execute([$id]);
$record = $stmt->fetch();

if ($record) {
    db()->prepare('UPDATE monthly_records SET deleted_at = NOW(), deleted_marker = id, updated_by = ? WHERE id = ?')->execute([current_user_id(), $id]);
    log_activity('Monthly Record Deleted', 'Billing', $record['cust_code'], 'Deleted billing record for ' . format_month($record['billing_month']));
    flash_set('success', 'Monthly billing record deleted successfully.');
} else {
    flash_set('danger', 'Record not found.');
}

redirect(base_url('billing/index.php'));
