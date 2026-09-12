<?php
/**
 * General purpose helper functions.
 */

/** Cache of settings loaded from the `settings` table. */
function app_settings(): array
{
    static $settings = null;

    if ($settings === null) {
        $settings = [];
        try {
            $stmt = db()->query('SELECT setting_key, setting_value FROM settings');
            foreach ($stmt->fetchAll() as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            // Table may not exist yet (fresh install before import) - ignore.
        }
        $defaults = [
            'software_name'          => 'ISP Management System',
            'organization_name'      => 'Your ISP Organization',
            'logo_path'              => '',
            'currency_code'          => 'BDT',
            'currency_symbol'        => '৳',
            'timezone'               => 'Asia/Dhaka',
            'date_format'            => 'd-m-Y',
            'month_format'           => 'M-Y',
            'customer_id_digits'     => '5',
            'default_pagination'     => '25',
            'excel_upload_limit_mb'  => '10',
            'session_timeout_minutes'=> '60',
        ];
        $settings = array_merge($defaults, $settings);
    }

    return $settings;
}

function setting(string $key, $default = null)
{
    $settings = app_settings();
    return $settings[$key] ?? $default;
}

/** Reset the in-process settings cache (call after saving Settings page). */
function app_settings_flush(): void
{
    app_settings_reset();
}
function app_settings_reset(): void
{
    // Re-invoke app_settings() with a static reset via reflection-free trick:
    // simplest approach - use a global flag checked by app_settings().
    $GLOBALS['__settings_dirty'] = true;
}

/* ---------------------------------------------------------------------
 * Formatting helpers
 * ------------------------------------------------------------------- */

function format_date(?string $date, string $fallback = '-'): string
{
    if (empty($date) || $date === '0000-00-00') {
        return $fallback;
    }
    $ts = is_numeric($date) ? (int) $date : strtotime($date);
    if (!$ts) {
        return $fallback;
    }
    return date(setting('date_format', 'd-m-Y'), $ts);
}

function format_datetime(?string $datetime, string $fallback = '-'): string
{
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return $fallback;
    }
    $ts = strtotime($datetime);
    if (!$ts) {
        return $fallback;
    }
    return date(setting('date_format', 'd-m-Y') . ' h:i A', $ts);
}

function format_time(?string $datetime, string $fallback = '-'): string
{
    if (empty($datetime)) {
        return $fallback;
    }
    $ts = strtotime($datetime);
    if (!$ts) {
        return $fallback;
    }
    return date('h:i A', $ts);
}

function format_month(?string $date, string $fallback = '-'): string
{
    if (empty($date)) {
        return $fallback;
    }
    $ts = strtotime($date);
    if (!$ts) {
        return $fallback;
    }
    return date(setting('month_format', 'M-Y'), $ts);
}

function format_currency($amount, bool $withSymbol = true): string
{
    $amount = (float) $amount;
    $formatted = number_format($amount, 2);
    return $withSymbol ? setting('currency_symbol', '৳') . $formatted : $formatted;
}

function format_bandwidth($mbps): string
{
    $mbps = (float) $mbps;
    $out = number_format($mbps, $mbps == floor($mbps) ? 0 : 2) . ' Mbps';
    if ($mbps >= 1000) {
        $out .= ' (' . number_format($mbps / 1000, 2) . ' Gbps)';
    }
    return $out;
}

function format_percent($value, int $decimals = 1): string
{
    return number_format((float) $value, $decimals) . '%';
}

function safe_percent($numerator, $denominator, int $decimals = 1): float
{
    $denominator = (float) $denominator;
    if ($denominator == 0.0) {
        return 0.0;
    }
    return round(((float) $numerator / $denominator) * 100, $decimals);
}

function safe_divide($numerator, $denominator): float
{
    $denominator = (float) $denominator;
    if ($denominator == 0.0) {
        return 0.0;
    }
    return (float) $numerator / $denominator;
}

/* ---------------------------------------------------------------------
 * Input / output safety helpers
 * ------------------------------------------------------------------- */

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function post(string $key, $default = '')
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function get_param(string $key, $default = '')
{
    return isset($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
}

function int_or_null($value): ?int
{
    return ($value === '' || $value === null) ? null : (int) $value;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Queue a one-time flash message. $message is rendered unescaped by the
 * layout, so ALWAYS pass either a plain literal string or a string built
 * from e()-escaped fragments - never raw, unescaped user input.
 */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ---------------------------------------------------------------------
 * Pagination
 * ------------------------------------------------------------------- */

function current_page(): int
{
    $page = (int) get_param('page', 1);
    return $page > 0 ? $page : 1;
}

function page_size(): int
{
    $allowed = [10, 25, 50, 100];
    $size = (int) get_param('per_page', (int) setting('default_pagination', 25));
    return in_array($size, $allowed, true) ? $size : (int) setting('default_pagination', 25);
}

function build_query(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        }
    }
    return http_build_query($params);
}

function render_pagination(int $total, int $page, int $perPage): string
{
    $totalPages = (int) ceil($total / max($perPage, 1));
    if ($totalPages <= 1) {
        return '';
    }
    $html = '<nav aria-label="Page navigation"><ul class="pagination pagination-sm mb-0 flex-wrap">';
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);

    $mk = function ($p, $label = null, $disabled = false, $active = false) {
        $label = $label ?? (string) $p;
        $cls = 'page-item' . ($disabled ? ' disabled' : '') . ($active ? ' active' : '');
        $href = e('?' . build_query(['page' => $p]));
        return "<li class=\"$cls\"><a class=\"page-link\" href=\"$href\">$label</a></li>";
    };

    $html .= $mk(max(1, $page - 1), '&laquo;', $page <= 1);
    if ($start > 1) {
        $html .= $mk(1);
        if ($start > 2) $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
    }
    for ($p = $start; $p <= $end; $p++) {
        $html .= $mk($p, null, false, $p === $page);
    }
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        $html .= $mk($totalPages);
    }
    $html .= $mk(min($totalPages, $page + 1), '&raquo;', $page >= $totalPages);
    $html .= '</ul></nav>';

    return $html;
}

/* ---------------------------------------------------------------------
 * Customer ID generation
 * ------------------------------------------------------------------- */

/**
 * Atomically generate the next Customer ID for a company.
 * Must be called inside (or will start) a DB transaction with row locking
 * so two concurrent requests never receive the same ID.
 */
function generate_customer_id(int $companyId): string
{
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT customer_prefix, last_customer_sequence
         FROM companies
         WHERE id = ?
         FOR UPDATE'
    );

    $stmt->execute([$companyId]);

    $company = $stmt->fetch();

    if (!$company) {
        throw new RuntimeException('Invalid company selected.');
    }

    $prefix = $company['customer_prefix'];
    $lastSequence = (int) $company['last_customer_sequence'];
    $digits = (int) setting('customer_id_digits', 5);

    /*
     * Finding the highest customer sequence already used.
     *
     * Example:
     * TCL-00001
     * TCL-00002
     * TCL-00015
     */
    $stmt = $pdo->prepare(
        "SELECT MAX(
            CAST(
                SUBSTRING(customer_id, LENGTH(?) + 2)
                AS UNSIGNED
            )
        )
        FROM customers
        WHERE customer_id LIKE CONCAT(?, '-%')"
    );

    $stmt->execute([$prefix, $prefix]);

    $maxExisting = (int) ($stmt->fetchColumn() ?? 0);

    $next = max($lastSequence, $maxExisting) + 1;

    // Save the new sequence number.
    $update = $pdo->prepare(
        'UPDATE companies
         SET last_customer_sequence = ?
         WHERE id = ?'
    );

    $update->execute([$next, $companyId]);

    return $prefix . '-' . str_pad(
        (string) $next,
        $digits,
        '0',
        STR_PAD_LEFT
    );
}

/* ---------------------------------------------------------------------
 * Month helpers
 * ------------------------------------------------------------------- */

/** Normalize a billing-month input (many formats) into Y-m-01. */
function normalize_billing_month($value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    // Excel serial date number
    if (is_numeric($value)) {
        try {
            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value);
            return $dt->format('Y-m-01');
        } catch (Throwable $e) {
            return null;
        }
    }

    $value = trim((string) $value);
    $formats = ['M-Y', 'M-y', 'F-Y', 'Y-m-d', 'Y-m', 'd-m-Y', 'm/Y', 'M Y', 'F Y'];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $value);
        if ($dt !== false) {
            return $dt->format('Y-m-01');
        }
    }
    $ts = strtotime($value);
    if ($ts !== false) {
        return date('Y-m-01', $ts);
    }
    return null;
}

function month_options(int $back = 24, int $forward = 3): array
{
    $months = [];
    $start = new DateTime('first day of this month');
    $start->modify("-$back months");
    for ($i = 0; $i <= $back + $forward; $i++) {
        $months[] = $start->format('Y-m-01');
        $start->modify('+1 month');
    }
    return array_reverse($months);
}

function year_options(): array
{
    $pdo = db();
    $years = [];
    try {
        $stmt = $pdo->query('SELECT DISTINCT YEAR(billing_month) y FROM monthly_records WHERE deleted_at IS NULL ORDER BY y DESC');
        $years = array_column($stmt->fetchAll(), 'y');
    } catch (Throwable $e) {
    }
    $currentYear = (int) date('Y');
    if (!in_array($currentYear, $years, true)) {
        array_unshift($years, $currentYear);
    }
    rsort($years);
    return $years;
}

/* ---------------------------------------------------------------------
 * Small query-runner helpers used throughout dashboard/reports.
 * ------------------------------------------------------------------- */

function run_scalar(string $sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    return $row ? $row[0] : 0;
}

function run_row(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function run_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/* ---------------------------------------------------------------------
 * Master data lookups (companies / categories / zones)
 * ------------------------------------------------------------------- */

function list_companies(bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM companies' . ($activeOnly ? " WHERE status = 'active'" : '') . ' ORDER BY company_name';
    return db()->query($sql)->fetchAll();
}

function list_categories(bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM categories' . ($activeOnly ? " WHERE status = 'active'" : '') . ' ORDER BY category_name';
    return db()->query($sql)->fetchAll();
}

function list_zones(bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM zones' . ($activeOnly ? " WHERE status = 'active'" : '') . ' ORDER BY zone_name';
    return db()->query($sql)->fetchAll();
}

/* Bank List */
function list_banks(bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM banks' . ($activeOnly ? " WHERE status = 'active'" : '') . ' ORDER BY bank_name';
    return db()->query($sql)->fetchAll();
}

/**
 * Build a <select> option list, keeping a currently-assigned-but-inactive
 * record visible (masters are deactivated, never hard deleted).
 */
function options_html(array $rows, string $valueKey, string $labelKey, $selected = null, ?array $currentRow = null): string
{
    $html = '';
    $ids = array_column($rows, $valueKey);
    if ($currentRow && !in_array($currentRow[$valueKey], $ids, true)) {
        $rows[] = $currentRow;
    }
    foreach ($rows as $row) {
        $sel = ((string) $row[$valueKey] === (string) $selected) ? 'selected' : '';
        $inactiveTag = (isset($row['status']) && $row['status'] !== 'active') ? ' (Inactive)' : '';
        $html .= '<option value="' . e((string) $row[$valueKey]) . '" ' . $sel . '>' . e($row[$labelKey] . $inactiveTag) . '</option>';
    }
    return $html;
}

/* ---------------------------------------------------------------------
 * Misc
 * ------------------------------------------------------------------- */

function status_badge(string $status): string
{
    $status = ucfirst(strtolower(trim($status)));
    
    // Check for both 'Active' and 'Paid'
    $cls = '';
    if(in_array($status, ['Active', 'Paid'])){
       $cls = 'bg-success-subtle text-success-emphasis border-success-subtle'; 
    }else if(in_array($status, ['Inactive', 'Due'])){
        $cls = 'bg-danger-subtle text-danger-emphasis border-danger-subtle';
    }else if(in_array($status, ['Hold', 'Partial'])){
        $cls = 'bg-warning text-warning-emphasis border-warning-subtle';
    }
        
    return '<span class="badge rounded-pill ' . $cls . ' border">' . e($status) . '</span>';
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function base_url(string $path = ''): string
{
    $base = defined('BASE_URL') ? BASE_URL : '';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function is_ajax_request(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/* Month segmentation by week */
function get_month_week_segments(string $billingMonth)
{
    if (empty($billingMonth)) {
        $lastDay = 30;
    } else {
        // Parse year and month to find the exact last day of that specific month
        $dateParts = explode('-', $billingMonth);
        $year = (int) ($dateParts[0] ?? date('Y'));
        $month = (int) ($dateParts[1] ?? date('n'));
        $lastDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    }

    return [
        ['value' => '1-7',   'label' => '1 to 7'],
        ['value' => '8-14',  'label' => '8 to 14'],
        ['value' => '15-22', 'label' => '15 to 22'],
        ['value' => "23-{$lastDay}", 'label' => "23 to {$lastDay}"],
    ];
}
