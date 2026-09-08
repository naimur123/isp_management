<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$id = (int)($_POST['id'] ?? 0);
$sql = 'SELECT id, billing_month, collection_status, collection_segment, collection_amount, bank_id FROM monthly_records WHERE id = ?';
$record = run_row($sql, [$id]); 
$current_status = $_POST['current_status'];
$new_status = $current_status;
$collection_amount = (!empty($_POST['collection_amount']) && ( empty($record['collection_amount']) || (float)($record['collection_amount'] == 0))) ? $_POST['collection_amount'] : $record['collection_amount'];
$bank_id = (!empty($_POST['bank_id']) && empty($record['bank_id'])) ? $_POST['bank_id'] : $record['bank_id'];

if ($new_status === 'Paid' && ( empty($record['collection_amount']) || (float)($record['collection_amount'] == 0 || empty($record['bank_id'])))) {
    //current record details
    $sql = 'SELECT id, billing_month, collection_segment FROM monthly_records WHERE id = ?';
    $record = run_row($sql, [$id]); 
    $today = date('Y-m-d');
    $billing_month = $record['billing_month'];
    $segment = $record['collection_segment'];

    list($start_day, $end_day) = explode('-', $segment);

    $year_month = date('Y-m', strtotime($billing_month));
    
    $segment_start = date('Y-m-d', strtotime("$year_month-" . str_pad($start_day, 2, '0', STR_PAD_LEFT)));
    $segment_end   = date('Y-m-d', strtotime("$year_month-" . str_pad($end_day, 2, '0', STR_PAD_LEFT)));

    if ($today < $segment_start || $today > $segment_end) {
        $paid_date = $segment_start; 
    } else {
        $paid_date = $today;
    }

    $updateSql = "UPDATE monthly_records SET collection_status = ?, collected_date = ?, actual_collected_date = ?, collected_by = ?, collection_amount = ?, bank_id = ? WHERE id = ?";
    run_scalar($updateSql, [$new_status, $paid_date, $today, current_user_id(), $collection_amount, $bank_id, $id]);
    log_activity('Collection Status Updated', 'Billing', $id, 'Marked as '. $new_status .'on ' . $paid_date);
    flash_set('success', 'Collection Status Updated.');
} else {
    flash_set('danger', 'Collection Status Updat Failed.');
}

// Redirect back to billing table view
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;