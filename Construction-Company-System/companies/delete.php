<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireRole(['super_admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('list.php'); }
verifyCsrf();

$pdo = getDb();
$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT name FROM companies WHERE id = :id');
$stmt->execute([':id' => $id]);
$company = $stmt->fetch();

if ($company) {
    $del = $pdo->prepare('DELETE FROM companies WHERE id = :id');
    $del->execute([':id' => $id]);
    auditLog('delete', 'companies', $id, 'حذف شرکت: ' . $company['name']);
    flash('success', 'شرکت حذف شد.');
} else {
    flash('danger', 'شرکت یافت نشد.');
}
redirect('list.php');
