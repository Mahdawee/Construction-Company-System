<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireRole(['super_admin', 'company_manager']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('list.php'); }
verifyCsrf();

$pdo = getDb();
$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM projects WHERE id = :id');
$stmt->execute([':id' => $id]);
$project = $stmt->fetch();

if (!$project || ($user['role_key'] === 'company_manager' && (int)$project['company_id'] !== (int)$user['company_id'])) {
    flash('danger', 'پروژه یافت نشد یا دسترسی ندارید.');
    redirect('list.php');
}

$del = $pdo->prepare('DELETE FROM projects WHERE id = :id');
$del->execute([':id' => $id]);
auditLog('delete', 'projects', $id, 'حذف پروژه: ' . $project['name']);
flash('success', 'پروژه حذف شد.');
redirect('list.php');
