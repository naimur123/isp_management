<?php
/**
 * Application bootstrap.
 * Every page starts with:  require_once __DIR__ . '/../config/config.php';
 * (adjust the relative path depth for files in subfolders)
 */

// ---------------------------------------------------------------------
// Basic environment
// ---------------------------------------------------------------------
define('APP_DEBUG', true); // Set to true only while troubleshooting on a dev server.
define('APP_ROOT', dirname(__DIR__));

error_reporting(APP_DEBUG ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

date_default_timezone_set('Asia/Dhaka');

// ---------------------------------------------------------------------
// BASE_URL auto-detection (works whether installed in a root domain,
// subdomain, or a sub-folder of public_html)
// ---------------------------------------------------------------------
if (!defined('BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    // Strip a trailing known sub-directory (e.g. /customers, /admin, /reports, /billing, /import, /account, /errors)
    $knownSub = ['/customers', '/billing', '/import', '/reports', '/admin', '/account', '/errors'];
    foreach ($knownSub as $sub) {
        if (substr($scriptDir, -strlen($sub)) === $sub) {
            $scriptDir = substr($scriptDir, 0, -strlen($sub));
            break;
        }
    }
    define('BASE_URL', $scheme . '://' . $host . $scriptDir);
}

// ---------------------------------------------------------------------
// Secure session configuration
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('ISPMGMT_SESSID');
    session_start();
}

// ---------------------------------------------------------------------
// Core includes
// ---------------------------------------------------------------------
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/audit.php';

// Load application-wide settings (currency, timezone, formats, etc.)
$settingsTz = setting('timezone', 'Asia/Dhaka');
if ($settingsTz) {
    date_default_timezone_set($settingsTz);
}
