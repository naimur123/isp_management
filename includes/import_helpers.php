<?php
/**
 * Excel / CSV bulk import parsing & validation.
 *
 * Never inserts anything into the database - parse_import_file() is a pure
 * read-only pass so it can be safely reused for both the Preview screen and
 * the pre-confirmation error-report download. process.php is the only
 * place that actually writes to the database, and only after the
 * administrator clicks "Confirm Import".
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

const IMPORT_TEMPLATE_HEADERS = [
    'Customer ID', 'Customer Name', 'Company', 'Category', 'Area/Zone',
    'Billing Month', 'Monthly Billing Amount', 'BW Sold Mbps', 'Status', 'Remarks',
];

function import_upload_dir(): string
{
    $dir = APP_ROOT . '/uploads/excel';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function find_company_by_text(array $companies, string $text): ?array
{
    $text = trim($text);
    if ($text === '') return null;
    foreach ($companies as $c) {
        if (strcasecmp($c['company_code'], $text) === 0 || strcasecmp($c['company_name'], $text) === 0 || strcasecmp($c['customer_prefix'], $text) === 0) {
            return $c;
        }
    }
    return null;
}

function find_by_name(array $rows, string $nameKey, string $text): ?array
{
    $text = trim($text);
    if ($text === '') return null;
    foreach ($rows as $r) {
        if (strcasecmp($r[$nameKey], $text) === 0) {
            return $r;
        }
    }
    return null;
}

/**
 * Parse an uploaded spreadsheet and validate every row, WITHOUT writing
 * anything to the database.
 */
function parse_import_file(string $filePath): array
{
    $companies = list_companies();
    $categories = list_categories();
    $zones = list_zones();

    $reader = IOFactory::createReaderForFile($filePath);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestDataRow();

    $rows = [];
    $seenInFile = []; // "customerkey|month" => excel_row

    // Detect header row (row 1) and skip it if it matches expected labels.
    $startRow = 2;
    $firstCell = trim((string) $sheet->getCell('A1')->getValue());
    if ($firstCell === '' || is_numeric($firstCell)) {
        $startRow = 1; // no header present
    }

    for ($rowNum = $startRow; $rowNum <= $highestRow; $rowNum++) {
        $vals = [
            'A' => $sheet->getCell("A$rowNum")->getValue(),
            'B' => $sheet->getCell("B$rowNum")->getValue(),
            'C' => $sheet->getCell("C$rowNum")->getValue(),
            'D' => $sheet->getCell("D$rowNum")->getValue(),
            'E' => $sheet->getCell("E$rowNum")->getValue(),
            'F' => $sheet->getCell("F$rowNum")->getValue(),
            'G' => $sheet->getCell("G$rowNum")->getValue(),
            'H' => $sheet->getCell("H$rowNum")->getValue(),
            'I' => $sheet->getCell("I$rowNum")->getValue(),
            'J' => $sheet->getCell("J$rowNum")->getValue(),
        ];

        // Skip completely blank rows.
        if (implode('', array_map('strval', $vals)) === '') {
            continue;
        }

        $customerIdRaw = trim((string) $vals['A']);
        $customerName = trim((string) $vals['B']);
        $companyRaw = trim((string) $vals['C']);
        $categoryRaw = trim((string) $vals['D']);
        $zoneRaw = trim((string) $vals['E']);
        $monthRaw = $vals['F'];
        $billingRaw = $vals['G'];
        $bwRaw = $vals['H'];
        $statusRaw = trim((string) $vals['I']);
        $remarks = trim((string) $vals['J']);

        $badges = [];
        $errors = [];

        $resolvedCustomer = null;
        if ($customerIdRaw !== '') {
            $stmt = db()->prepare('SELECT * FROM customers WHERE customer_id = ? AND deleted_at IS NULL');
            $stmt->execute([$customerIdRaw]);
            $resolvedCustomer = $stmt->fetch() ?: null;
            if (!$resolvedCustomer) {
                $errors[] = "Customer ID \"$customerIdRaw\" was not found in the system.";
            }
        }

        $company = null;
        $category = find_by_name($categories, 'category_name', $categoryRaw);
        $zone = find_by_name($zones, 'zone_name', $zoneRaw);

        $isNewCustomer = ($customerIdRaw === '' && !$resolvedCustomer);

        if ($isNewCustomer) {
            $badges[] = 'Auto Generate ID';
            if ($customerName === '') $errors[] = 'Customer Name is required for a new customer.';
            $company = find_company_by_text($companies, $companyRaw);
            if ($companyRaw === '') { $errors[] = 'Company is required for a new customer.'; }
            elseif (!$company) { $errors[] = "Company \"$companyRaw\" was not recognized."; $badges[] = 'Invalid Company'; }
            if ($categoryRaw === '') { $errors[] = 'Category is required for a new customer.'; }
            elseif (!$category) { $errors[] = "Category \"$categoryRaw\" was not recognized."; $badges[] = 'Invalid Category'; }
            if ($zoneRaw === '') { $errors[] = 'Area/Zone is required for a new customer.'; }
            elseif (!$zone) { $errors[] = "Zone \"$zoneRaw\" was not recognized."; $badges[] = 'Invalid Zone'; }

            // Possible-duplicate-customer heuristic.
            if ($customerName !== '' && $company) {
                $stmt = db()->prepare('SELECT customer_id FROM customers WHERE deleted_at IS NULL AND customer_name = ? AND company_id = ? LIMIT 1');
                $stmt->execute([$customerName, $company['id']]);
                $possible = $stmt->fetch();
                if ($possible) {
                    $badges[] = 'Possible Existing Customer (' . $possible['customer_id'] . ')';
                }
            }
        } elseif ($resolvedCustomer) {
            $company = ['id' => $resolvedCustomer['company_id']];
        }

        $monthNorm = normalize_billing_month($monthRaw);
        if ($monthRaw === null || $monthRaw === '') {
            $errors[] = 'Billing Month is required.';
        } elseif (!$monthNorm) {
            $errors[] = "Billing Month \"$monthRaw\" could not be understood.";
            $badges[] = 'Invalid Month';
        }

        $billingNorm = null;
        if ($billingRaw === null || $billingRaw === '') {
            $errors[] = 'Monthly Billing Amount is required.';
        } elseif (!is_numeric($billingRaw) || (float) $billingRaw < 0) {
            $errors[] = 'Monthly Billing Amount must be a number >= 0.';
            $badges[] = 'Invalid Billing';
        } else {
            $billingNorm = (float) $billingRaw;
        }

        $bwNorm = null;
        if ($bwRaw === null || $bwRaw === '') {
            $errors[] = 'BW Sold is required.';
        } elseif (!is_numeric($bwRaw) || (float) $bwRaw < 0) {
            $errors[] = 'BW Sold (Mbps) must be a number >= 0.';
            $badges[] = 'Invalid Bandwidth';
        } else {
            $bwNorm = (float) $bwRaw;
        }

        $statusNorm = 'Active';
        if ($statusRaw !== '') {
            if (strcasecmp($statusRaw, 'active') === 0) $statusNorm = 'Active';
            elseif (strcasecmp($statusRaw, 'inactive') === 0) $statusNorm = 'Inactive';
            elseif (strcasecmp($statusRaw, 'hold') === 0) $statusNorm = 'Hold';
            else { $errors[] = "Status \"$statusRaw\" must be Active or Inactive or Hold."; }
        }

        // Duplicate detection (within this file).
        $dupKey = ($resolvedCustomer ? 'C' . $resolvedCustomer['id'] : 'N' . strtolower($customerName . '|' . $companyRaw)) . '|' . $monthNorm;
        $isDuplicateInFile = isset($seenInFile[$dupKey]);
        if ($isDuplicateInFile) {
            $badges[] = 'Duplicate (row ' . $seenInFile[$dupKey] . ')';
        } else {
            $seenInFile[$dupKey] = $rowNum;
        }

        // Duplicate detection (already in database) - only meaningful for existing customers.
        $isDuplicateInDb = false;
        if ($resolvedCustomer && $monthNorm) {
            $stmt = db()->prepare('SELECT id FROM monthly_records WHERE customer_id = ? AND billing_month = ? AND deleted_at IS NULL');
            $stmt->execute([$resolvedCustomer['id'], $monthNorm]);
            $isDuplicateInDb = (bool) $stmt->fetch();
            if ($isDuplicateInDb) $badges[] = 'Duplicate Monthly Record';
        }

        if (!$errors) {
            array_unshift($badges, 'Valid');
        }

        $status = $errors ? 'error' : (($isDuplicateInDb || $isDuplicateInFile) ? 'duplicate' : 'valid');

        $rows[] = [
            'excel_row' => $rowNum,
            'customer_id_raw' => $customerIdRaw,
            'customer_name' => $customerName ?: ($resolvedCustomer['customer_name'] ?? ''),
            'company_raw' => $companyRaw,
            'category_raw' => $categoryRaw,
            'zone_raw' => $zoneRaw,
            'billing_month_raw' => $monthRaw,
            'billing_month_norm' => $monthNorm,
            'billing_raw' => $billingRaw,
            'billing_norm' => $billingNorm,
            'bw_raw' => $bwRaw,
            'bw_norm' => $bwNorm,
            'status_raw' => $statusRaw,
            'status_norm' => $statusNorm,
            'remarks' => $remarks,
            'resolved_customer' => $resolvedCustomer,
            'resolved_company_id' => $company['id'] ?? null,
            'resolved_category_id' => $category['id'] ?? null,
            'resolved_zone_id' => $zone['id'] ?? null,
            'is_new_customer' => $isNewCustomer,
            'is_duplicate_in_file' => $isDuplicateInFile,
            'is_duplicate_in_db' => $isDuplicateInDb,
            'badges' => $badges,
            'errors' => $errors,
            'row_status' => $status,
        ];
    }

    $summary = ['total' => count($rows), 'valid' => 0, 'duplicate' => 0, 'error' => 0];
    foreach ($rows as $r) {
        $summary[$r['row_status']]++;
    }

    return ['rows' => $rows, 'summary' => $summary];
}

/**
 * Actually write the parsed rows to the database. Must only be called
 * after the administrator has confirmed the import.
 */
function process_import_rows(array $parsed, string $duplicateMode): array
{
    $pdo = db();
    $imported = 0; $updated = 0; $skipped = 0; $failed = 0;
    $errorLog = [];
    foreach ($parsed['rows'] as $row) {
        if ($row['row_status'] === 'error') {
            $failed++;
            $errorLog[] = [
                'excel_row' => $row['excel_row'],
                'customer_id' => $row['customer_id_raw'] ?: null,
                'customer_name' => $row['customer_name'] ?: null,
                'error_type' => 'Validation Failed',
                'error_description' => implode(' ', $row['errors']),
            ];
            continue;
        }

        if ($row['is_duplicate_in_file']) {
            // Only the first occurrence in the file is processed; later ones are skipped safely.
            $skipped++;
            continue;
        }

        try {
            
            $pdo->beginTransaction();

            $customerDbId = null;

            // Define allowed enum values mapping
            $statusNorm = $row['status_norm'];

            if ($row['resolved_customer']) {
                $customerDbId = (int) $row['resolved_customer']['id'];
            } else {
                $customerId = generate_customer_id((int) $row['resolved_company_id']);
                $stmt = $pdo->prepare(
                    'INSERT INTO customers (customer_id, customer_name, company_id, category_id, zone_id, status, remarks, created_by, updated_by, created_at, updated_at)
                     VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())'
                );
                $stmt->execute([
                    $customerId, $row['customer_name'], $row['resolved_company_id'], $row['resolved_category_id'], $row['resolved_zone_id'],
                    'Active', $row['remarks'] ?: null, current_user_id(), current_user_id(),
                ]);
                $customerDbId = (int) $pdo->lastInsertId();
            }

            if ($row['is_duplicate_in_db']) {
                if ($duplicateMode === 'update') {
                    $pdo->prepare(
                        'UPDATE monthly_records SET billing_amount=?, bandwidth_mbps=?, status=?, remarks=?, updated_by=?, updated_at=NOW()
                         WHERE customer_id=? AND billing_month=? AND deleted_at IS NULL'
                    )->execute([$row['billing_norm'], $row['bw_norm'], $statusNorm, $row['remarks'] ?: null, current_user_id(), $customerDbId, $row['billing_month_norm']]);
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                $pdo->prepare(
                    'INSERT INTO monthly_records (customer_id, billing_month, billing_amount, bandwidth_mbps, status, remarks, created_by, updated_by, created_at, updated_at)
                     VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())'
                )->execute([$customerDbId, $row['billing_month_norm'], $row['billing_norm'], $row['bw_norm'], $statusNorm, $row['remarks'] ?: null, current_user_id(), current_user_id()]);
                $imported++;
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            $failed++;
            $errorLog[] = [
                'excel_row' => $row['excel_row'],
                'customer_id' => $row['customer_id_raw'] ?: null,
                'customer_name' => $row['customer_name'] ?: null,
                'error_type' => 'Database Error',
                'error_description' => 'Could not save this row: ' . $e->getMessage(),
            ];
        }
    }

    return compact('imported', 'updated', 'skipped', 'failed', 'errorLog');
}
