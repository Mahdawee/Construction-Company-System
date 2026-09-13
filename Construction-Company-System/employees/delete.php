<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
if (!canDeleteData($user)) { http_response_code(403); include __DIR__ . '/../403.php'; exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('list.php?project_id=' . $projectId); }
verifyCsrf();

$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM employees WHERE id = :id AND project_id = :pid');
$stmt->execute([':id' => $id, ':pid' => $projectId]);
$employee = $stmt->fetch();

if ($employee) {
    $pdo->prepare('DELETE FROM employees WHERE id = :id')->execute([':id' => $id]);
    deleteUploadedFile($employee['attachment_path']);
    auditLog('delete', 'employees', $id, 'حذف کارمند: ' . $employee['full_name']);
    flash('success', 'کارمند حذف شد.');
} else {
    flash('danger', 'کارمند یافت نشد.');
}
redirect('list.php?project_id=' . $projectId);
