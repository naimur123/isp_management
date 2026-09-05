<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/import_helpers.php';
require_admin();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Import Template');

$col = 'A';
foreach (IMPORT_TEMPLATE_HEADERS as $header) {
    $sheet->setCellValue($col . '1', $header);
    $col++;
}
$sheet->getStyle('A1:J1')->getFont()->setBold(true);
$sheet->getStyle('A1:J1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('DBEAFE');
foreach (range('A', 'J') as $c) {
    $sheet->getColumnDimension($c)->setWidth(20);
}

// Two example rows to guide the administrator.
$sheet->fromArray(['', 'ABC International Ltd', 'TCL', 'Corporate', 'Gulshan', 'Aug-2026', 25000, 100, 'Active', ''], null, 'A2');
$sheet->fromArray(['TCL-00001', '', '', '', '', 'Sep-2026', 26000, 120, 'Active', 'Bandwidth upgraded'], null, 'A3');
$sheet->getStyle('A2:J3')->getFont()->setItalic(true)->getColor()->setRGB('9CA3AF');

$sheet->freezePane('A2');
foreach (range('A', 'J') as $c) {
    $sheet->getColumnDimension($c)->setAutoSize(false);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="ISP_Bulk_Import_Template.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
