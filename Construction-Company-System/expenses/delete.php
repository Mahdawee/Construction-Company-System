<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
if (!canDeleteData($user)) { http_response_code(403); include __DIR__ . '/../403.php'; exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('list.php?project_id=' . $projectId); }
verifyCsrf();

$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT id FROM expenses WHERE id = :id AND project_id = :pid');
$stmt->execute([':id' => $id, ':pid' => $projectId]);
if ($stmt->fetch()) {
    $pdo->prepare('DELETE FROM expenses WHERE id = :id')->execute([':id' => $id]);
    auditLog('delete', 'expenses', $id, 'حذف مصرف');
    flash('success', 'مصرف حذف شد.');
} else {
    flash('danger', 'مصرف یافت نشد.');
}
redirect('list.php?project_id=' . $projectId);
