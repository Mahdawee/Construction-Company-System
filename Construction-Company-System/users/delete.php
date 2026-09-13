<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireRole(['super_admin', 'company_manager']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('list.php'); }
verifyCsrf();

$pdo = getDb();
$id = (int)($_POST['id'] ?? 0);

if ($id === (int)$user['id']) {
    flash('danger', 'شما نمی‌توانید حساب کاربری خودتان را حذف کنید.');
    redirect('list.php');
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute([':id' => $id]);
$target = $stmt->fetch();

if (!$target || ($user['role_key'] === 'company_manager' && (int)$target['company_id'] !== (int)$user['company_id'])) {
    flash('danger', 'کاربر یافت نشد یا دسترسی ندارید.');
    redirect('list.php');
}

$del = $pdo->prepare('DELETE FROM users WHERE id = :id');
$del->execute([':id' => $id]);
auditLog('delete', 'users', $id, 'حذف کاربر: ' . $target['username']);
flash('success', 'کاربر حذف شد.');
redirect('list.php');
