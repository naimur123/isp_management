<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('customers/index.php'));
}
csrf_require();

$id = (int) post('id');
$stmt = db()->prepare('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$id]);
$customer = $stmt->fetch();

if ($customer) {
    db()->prepare('UPDATE customers SET deleted_at = NOW(), updated_by = ? WHERE id = ?')->execute([current_user_id(), $id]);
    log_activity('Customer Deleted', 'Customers', $customer['customer_id'], 'Soft-deleted customer ' . $customer['customer_id'] . ' (' . $customer['customer_name'] . ')');
    flash_set('success', 'Customer "' . e($customer['customer_name']) . '" (' . e($customer['customer_id']) . ') has been deleted. Historical records are preserved for audit purposes.');
} else {
    flash_set('danger', 'Customer not found.');
}

redirect(base_url('customers/index.php'));
