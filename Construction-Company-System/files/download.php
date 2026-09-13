<?php
/**
 * دروازه امن دانلود/مشاهده فایل‌های ضمیمه (مشتریان، قراردادها، کارمندان)
 * دسترسی فقط پس از احراز هویت و بررسی دسترسی کاربر به پروژه مربوطه امکان‌پذیر است.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();

$allowedModules = ['customers' => 'customers', 'contracts' => 'contracts', 'employees' => 'employees', 'machinery' => 'machinery'];
$module = $_GET['module'] ?? '';
$id = (int)($_GET['id'] ?? 0);

$table = $allowedModules[$module] ?? null;
if (!$table || !$id) {
    http_response_code(404);
    die('درخواست نامعتبر است.');
}

$stmt = $pdo->prepare("SELECT project_id, attachment_path, attachment_name FROM `$table` WHERE id = :id");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();

if (!$row || !$row['attachment_path'] || !hasProjectAccess($pdo, $user, (int)$row['project_id'])) {
    http_response_code(404);
    die('فایل یافت نشد یا شما دسترسی لازم را ندارید.');
}

$fullPath = __DIR__ . '/../uploads/' . $row['attachment_path'];
if (!is_file($fullPath)) {
    http_response_code(404);
    die('فایل روی سرور یافت نشد.');
}

$displayName = $row['attachment_name'] ?: basename($fullPath);
$mime = function_exists('mime_content_type') ? (mime_content_type($fullPath) ?: 'application/octet-stream') : 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . rawurlencode($displayName) . '"');
header('Content-Length: ' . filesize($fullPath));
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
exit;
