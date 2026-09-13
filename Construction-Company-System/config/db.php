<?php
/**
 * اتصال به پایگاه داده با استفاده از PDO
 */
require_once __DIR__ . '/config.php';

function getDb(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES    => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            if (defined('APP_DEBUG') && APP_DEBUG) {
                die('خطا در اتصال به پایگاه داده: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
            }
            die('خطا در اتصال به پایگاه داده. لطفاً تنظیمات config/config.php را بررسی کنید.');
        }
    }
    return $pdo;
}
