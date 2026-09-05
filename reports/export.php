<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_login();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$type = get_param('type');
$format = get_param('format', 'xlsx');
$title = 'Report';
$headers = [];
$rows = [];

switch ($type) {

    case 'customer_list': {
        $title = 'Customer List';
        $where = ['c.deleted_at IS NULL'];
        $params = [];
        $q = get_param('q'); $companyId = get_param('company'); $categoryId = get_param('category'); $zoneId = get_param('zone'); $status = get_param('status');
        if ($q !== '') { $where[] = '(c.customer_id LIKE ? OR c.customer_name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($companyId !== '') { $where[] = 'c.company_id = ?'; $params[] = $companyId; }
        if ($categoryId !== '') { $where[] = 'c.category_id = ?'; $params[] = $categoryId; }
        if ($zoneId !== '') { $where[] = 'c.zone_id = ?'; $params[] = $zoneId; }
        if ($status !== '') { $where[] = 'c.status = ?'; $params[] = $status; }
        $whereSql = implode(' AND ', $where);
        $data = run_all("SELECT c.customer_id, c.customer_name, co.company_name, cat.category_name, z.zone_name, c.status, c.created_at, u.full_name created_by
                          FROM customers c JOIN companies co ON co.id=c.company_id JOIN categories cat ON cat.id=c.category_id JOIN zones z ON z.id=c.zone_id
                          LEFT JOIN users u ON u.id=c.created_by WHERE $whereSql ORDER BY c.id DESC", $params);
        $headers = ['Customer ID','Customer Name','Company','Category','Area/Zone','Status','Created Date','Created By'];
        foreach ($data as $r) $rows[] = [$r['customer_id'], $r['customer_name'], $r['company_name'], $r['category_name'], $r['zone_name'], $r['status'], format_date($r['created_at']), $r['created_by'] ?? ''];
        break;
    }

    case 'monthly_records': {
        $title = 'Monthly Billing Records';
        $where = ['m.deleted_at IS NULL'];
        $params = [];
        $q = get_param('q'); $companyId = get_param('company'); $categoryId = get_param('category'); $zoneId = get_param('zone');
        $month = get_param('month'); $year = get_param('year'); $status = get_param('status');
        if ($q !== '') { $where[] = '(c.customer_id LIKE ? OR c.customer_name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($companyId !== '') { $where[] = 'c.company_id = ?'; $params[] = $companyId; }
        if ($categoryId !== '') { $where[] = 'c.category_id = ?'; $params[] = $categoryId; }
        if ($zoneId !== '') { $where[] = 'c.zone_id = ?'; $params[] = $zoneId; }
        if ($month !== '') { $where[] = 'm.billing_month = ?'; $params[] = $month . '-01'; }
        elseif ($year !== '') { $where[] = 'YEAR(m.billing_month) = ?'; $params[] = $year; }
        if ($status !== '') { $where[] = 'm.status = ?'; $params[] = $status; }
        $whereSql = implode(' AND ', $where);
        $data = run_all("SELECT m.billing_month, c.customer_id, c.customer_name, co.company_name, cat.category_name, z.zone_name, m.billing_amount, m.bandwidth_mbps, m.status, m.created_at, u.full_name created_by
                          FROM monthly_records m JOIN customers c ON c.id=m.customer_id JOIN companies co ON co.id=c.company_id JOIN categories cat ON cat.id=c.category_id JOIN zones z ON z.id=c.zone_id
                          LEFT JOIN users u ON u.id=m.created_by WHERE $whereSql ORDER BY m.id DESC", $params);
        $headers = ['Billing Month','Customer ID','Customer Name','Company','Category','Zone','Monthly Billing (BDT)','BW Sold (Mbps)','Status','Entry Date','Created By'];
        foreach ($data as $r) $rows[] = [format_month($r['billing_month']), $r['customer_id'], $r['customer_name'], $r['company_name'], $r['category_name'], $r['zone_name'], (float) $r['billing_amount'], (float) $r['bandwidth_mbps'], $r['status'], format_date($r['created_at']), $r['created_by'] ?? ''];
        break;
    }

    case 'central': {
        $title = 'Central Report';
        $companyId = get_param('company'); $categoryId = get_param('category'); $zoneId = get_param('zone'); $status = get_param('status');
        $month = get_param('month'); $year = get_param('year');
        $custConds = ['c.deleted_at IS NULL']; $custParams = [];
        if ($companyId !== '') { $custConds[] = 'c.company_id = ?'; $custParams[] = $companyId; }
        if ($categoryId !== '') { $custConds[] = 'c.category_id = ?'; $custParams[] = $categoryId; }
        if ($zoneId !== '') { $custConds[] = 'c.zone_id = ?'; $custParams[] = $zoneId; }
        if ($status !== '') { $custConds[] = 'c.status = ?'; $custParams[] = $status; }
        $custWhereSql = implode(' AND ', $custConds);
        $mrExtra = ''; $mrExtraParams = [];
        if ($month !== '') { $mrExtra = ' AND m.billing_month = ?'; $mrExtraParams[] = $month . '-01'; }
        elseif ($year !== '') { $mrExtra = ' AND YEAR(m.billing_month) = ?'; $mrExtraParams[] = $year; }
        if ($status !== '') { $mrExtra .= ' AND m.status = ?'; $mrExtraParams[] = $status; }
        $data = run_all("SELECT z.zone_name, co.company_name, cat.category_name,
                SUM(CASE WHEN c.status='Active' THEN 1 ELSE 0 END) active_customers, COUNT(DISTINCT c.id) total_customers,
                COALESCE(SUM(m.billing_amount),0) billing, COALESCE(SUM(m.bandwidth_mbps),0) bw
             FROM customers c JOIN companies co ON co.id=c.company_id JOIN categories cat ON cat.id=c.category_id JOIN zones z ON z.id=c.zone_id
             LEFT JOIN monthly_records m ON m.customer_id=c.id AND m.deleted_at IS NULL $mrExtra
             WHERE $custWhereSql GROUP BY z.id, co.id, cat.id HAVING total_customers > 0 ORDER BY z.zone_name, co.company_name, cat.category_name",
             array_merge($custParams, $mrExtraParams));
        $headers = ['Area/Zone','Company','Category','Active Customers','Total Customers','Billing (BDT)','BW Sold (Mbps)','Avg Billing (BDT)'];
        foreach ($data as $r) $rows[] = [$r['zone_name'], $r['company_name'], $r['category_name'], (int) $r['active_customers'], (int) $r['total_customers'], (float) $r['billing'], (float) $r['bw'], round(safe_divide($r['billing'], $r['active_customers']), 2)];
        break;
    }

    case 'company': {
        $title = 'Company Report';
        $month = get_param('month'); $year = get_param('year'); $status = get_param('status');
        $mrExtra = ''; $mrExtraParams = [];
        if ($month !== '') { $mrExtra = ' AND m.billing_month = ?'; $mrExtraParams[] = $month . '-01'; }
        elseif ($year !== '') { $mrExtra = ' AND YEAR(m.billing_month) = ?'; $mrExtraParams[] = $year; }
        if ($status !== '') { $mrExtra .= ' AND m.status = ?'; $mrExtraParams[] = $status; }
        $custExtra = ''; $custExtraParams = [];
        if ($status !== '') { $custExtra = ' AND c.status = ?'; $custExtraParams[] = $status; }
        $data = run_all("SELECT co.company_name, SUM(CASE WHEN c.status='Active' THEN 1 ELSE 0 END) active_customers, SUM(CASE WHEN c.status='Inactive' THEN 1 ELSE 0 END) inactive_customers,
                COUNT(DISTINCT c.id) total_customers, COALESCE(SUM(m.billing_amount),0) billing, COALESCE(SUM(m.bandwidth_mbps),0) bw
             FROM companies co LEFT JOIN customers c ON c.company_id=co.id AND c.deleted_at IS NULL $custExtra
             LEFT JOIN monthly_records m ON m.customer_id=c.id AND m.deleted_at IS NULL $mrExtra
             GROUP BY co.id ORDER BY co.company_name", array_merge($custExtraParams, $mrExtraParams));
        $headers = ['Company','Active Customers','Inactive Customers','Total Customers','Billing (BDT)','BW Sold (Mbps)','Avg Billing (BDT)'];
        foreach ($data as $r) $rows[] = [$r['company_name'], (int) $r['active_customers'], (int) $r['inactive_customers'], (int) $r['total_customers'], (float) $r['billing'], (float) $r['bw'], round(safe_divide($r['billing'], $r['active_customers']), 2)];
        break;
    }

    case 'category': {
        $title = 'Category Report';
        $month = get_param('month'); $year = get_param('year'); $status = get_param('status');
        $mrExtra = ''; $mrExtraParams = [];
        if ($month !== '') { $mrExtra = ' AND m.billing_month = ?'; $mrExtraParams[] = $month . '-01'; }
        elseif ($year !== '') { $mrExtra = ' AND YEAR(m.billing_month) = ?'; $mrExtraParams[] = $year; }
        if ($status !== '') { $mrExtra .= ' AND m.status = ?'; $mrExtraParams[] = $status; }
        $custExtra = ''; $custExtraParams = [];
        if ($status !== '') { $custExtra = ' AND c.status = ?'; $custExtraParams[] = $status; }
        $data = run_all("SELECT cat.category_name, SUM(CASE WHEN c.status='Active' THEN 1 ELSE 0 END) active_customers, COUNT(DISTINCT c.id) total_customers,
                COALESCE(SUM(m.billing_amount),0) billing, COALESCE(SUM(m.bandwidth_mbps),0) bw
             FROM categories cat LEFT JOIN customers c ON c.category_id=cat.id AND c.deleted_at IS NULL $custExtra
             LEFT JOIN monthly_records m ON m.customer_id=c.id AND m.deleted_at IS NULL $mrExtra
             GROUP BY cat.id ORDER BY cat.category_name", array_merge($custExtraParams, $mrExtraParams));
        $headers = ['Category','Active Customers','Total Customers','Billing (BDT)','BW Sold (Mbps)','Avg Billing (BDT)'];
        foreach ($data as $r) $rows[] = [$r['category_name'], (int) $r['active_customers'], (int) $r['total_customers'], (float) $r['billing'], (float) $r['bw'], round(safe_divide($r['billing'], $r['active_customers']), 2)];
        break;
    }

    case 'zone': {
        $title = 'Zone Report';
        $month = get_param('month'); $year = get_param('year'); $status = get_param('status');
        $mrExtra = ''; $mrExtraParams = [];
        if ($month !== '') { $mrExtra = ' AND m.billing_month = ?'; $mrExtraParams[] = $month . '-01'; }
        elseif ($year !== '') { $mrExtra = ' AND YEAR(m.billing_month) = ?'; $mrExtraParams[] = $year; }
        if ($status !== '') { $mrExtra .= ' AND m.status = ?'; $mrExtraParams[] = $status; }
        $custExtra = ''; $custExtraParams = [];
        if ($status !== '') { $custExtra = ' AND c.status = ?'; $custExtraParams[] = $status; }
        $data = run_all("SELECT z.zone_name, SUM(CASE WHEN c.status='Active' THEN 1 ELSE 0 END) active_customers, SUM(CASE WHEN c.status='Inactive' THEN 1 ELSE 0 END) inactive_customers,
                COUNT(DISTINCT c.id) total_customers, COALESCE(SUM(m.billing_amount),0) billing, COALESCE(SUM(m.bandwidth_mbps),0) bw
             FROM zones z LEFT JOIN customers c ON c.zone_id=z.id AND c.deleted_at IS NULL $custExtra
             LEFT JOIN monthly_records m ON m.customer_id=c.id AND m.deleted_at IS NULL $mrExtra
             GROUP BY z.id ORDER BY z.zone_name", array_merge($custExtraParams, $mrExtraParams));
        $headers = ['Area/Zone','Active Customers','Inactive Customers','Total Customers','Billing (BDT)','BW Sold (Mbps)','Avg Billing (BDT)'];
        foreach ($data as $r) $rows[] = [$r['zone_name'], (int) $r['active_customers'], (int) $r['inactive_customers'], (int) $r['total_customers'], (float) $r['billing'], (float) $r['bw'], round(safe_divide($r['billing'], $r['active_customers']), 2)];
        break;
    }

    case 'zone_customers': {
        $zoneId = get_param('zone'); $zoneName = get_param('zone_name'); $companyId = get_param('company');
        $zone = $zoneId !== '' ? run_row('SELECT * FROM zones WHERE id=?', [$zoneId]) : run_row('SELECT * FROM zones WHERE zone_name=?', [$zoneName]);
        $title = 'Zone Customer Report — ' . ($zone['zone_name'] ?? '');
        $where = ['c.deleted_at IS NULL', 'c.zone_id = ?']; $params = [$zone['id'] ?? 0];
        if ($companyId !== '') { $where[] = 'c.company_id = ?'; $params[] = $companyId; }
        $data = run_all("SELECT c.customer_id, c.customer_name, co.company_name, cat.category_name, c.status,
                COALESCE((SELECT SUM(billing_amount) FROM monthly_records m WHERE m.customer_id=c.id AND m.deleted_at IS NULL),0) total_billing
             FROM customers c JOIN companies co ON co.id=c.company_id JOIN categories cat ON cat.id=c.category_id
             WHERE " . implode(' AND ', $where) . ' ORDER BY c.customer_name', $params);
        $headers = ['Customer ID','Customer Name','Company','Category','Total Billing (BDT)','Status'];
        foreach ($data as $r) $rows[] = [$r['customer_id'], $r['customer_name'], $r['company_name'], $r['category_name'], (float) $r['total_billing'], $r['status']];
        break;
    }

    case 'monthly': {
        $title = 'Monthly Report';
        $year = get_param('year'); $companyId = get_param('company'); $categoryId = get_param('category'); $zoneId = get_param('zone');
        $conds = ['m.deleted_at IS NULL', 'c.deleted_at IS NULL']; $params = [];
        if ($companyId !== '') { $conds[] = 'c.company_id = ?'; $params[] = $companyId; }
        if ($categoryId !== '') { $conds[] = 'c.category_id = ?'; $params[] = $categoryId; }
        if ($zoneId !== '') { $conds[] = 'c.zone_id = ?'; $params[] = $zoneId; }
        if ($year !== '') { $conds[] = 'YEAR(m.billing_month) = ?'; $params[] = $year; }
        $data = run_all("SELECT DATE_FORMAT(m.billing_month,'%Y-%m') ym, m.billing_month, SUM(CASE WHEN m.status='Active' THEN 1 ELSE 0 END) active_records,
                COUNT(DISTINCT m.customer_id) customers, SUM(m.billing_amount) billing, SUM(m.bandwidth_mbps) bw
             FROM monthly_records m JOIN customers c ON c.id=m.customer_id WHERE " . implode(' AND ', $conds) . ' GROUP BY ym ORDER BY ym', $params);
        $headers = ['Month','Active Records','Customers','Total Billing (BDT)','BW Sold (Mbps)','Avg Billing (BDT)','Avg BW (Mbps)'];
        foreach ($data as $r) $rows[] = [format_month($r['billing_month']), (int) $r['active_records'], (int) $r['customers'], (float) $r['billing'], (float) $r['bw'], round(safe_divide($r['billing'], $r['customers']), 2), round(safe_divide($r['bw'], $r['customers']), 2)];
        break;
    }

    default:
        flash_set('danger', 'Unknown export type.');
        redirect(base_url('dashboard.php'));
}

/* -------------------------------------------------------------- */
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment;filename="' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $title) . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers, ',', '"', '\\');
    foreach ($rows as $r) fputcsv($out, $r, ',', '"', '\\');
    fclose($out);
    exit;
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(substr($title, 0, 31));

$softwareName = setting('software_name');
$sheet->setCellValue('A1', $softwareName . ' — ' . $title);
$sheet->mergeCells('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

$filterBits = [];
foreach (['month','year','company','category','zone','status','q'] as $k) {
    if (get_param($k) !== '') $filterBits[] = ucfirst($k) . ': ' . get_param($k);
}
$sheet->setCellValue('A2', 'Generated: ' . format_date(date('Y-m-d')) . ' ' . format_time(date('Y-m-d H:i:s')) . '  |  By: ' . (current_user()['full_name'] ?? '') . ($filterBits ? '  |  Filters: ' . implode(', ', $filterBits) : ''));
$sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9);

$headerRow = 4;
$col = 1;
foreach ($headers as $h) {
    $sheet->setCellValue([$col, $headerRow], $h);
    $col++;
}
$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
$sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1D4ED8');
$sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$headerRow}");
$sheet->freezePane('A' . ($headerRow + 1));

$r = $headerRow + 1;
foreach ($rows as $rowData) {
    $col = 1;
    foreach ($rowData as $val) {
        $sheet->setCellValue([$col, $r], $val);
        $col++;
    }
    $r++;
}

foreach (range(1, count($headers)) as $c) {
    $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $title) . '.xlsx"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
