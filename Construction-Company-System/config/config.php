<?php
/**
 * تنظیمات عمومی برنامه
 * Application-wide configuration
 */

// --- اطلاعات اتصال به دیتابیس (این مقادیر را مطابق سرور خود تغییر دهید) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'construction_company_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// --- مسیر پایه برنامه (در صورت قرار گرفتن در پوشه فرعی تغییر دهید، مثلا /Construction-Company-System) ---
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
// حذف مسیرهای زیرپوشه‌ای ماژول‌ها (مثل /companies، /projects و ...) برای رسیدن به ریشه برنامه
$knownModules = ['companies','projects','users','kahta','receipts','expenses','revenue','advances','reports','audit','settings','export','customers','contracts','employees','attendance','leaves','files','machinery'];
$parts = explode('/', trim($scriptDir, '/'));
if (!empty($parts) && in_array(end($parts), $knownModules, true)) {
    array_pop($parts);
}
define('BASE_URL', '/' . implode('/', $parts));

define('APP_NAME', 'سیستم مدیریت مالی پروژه‌های ساختمانی');
define('APP_VERSION', '1.2.0');

// --- منطقه زمانی ---
date_default_timezone_set('Asia/Kabul');

// --- نشست (Session) ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- نمایش خطاها (در محیط تولید false کنید) ---
define('APP_DEBUG', true);
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
