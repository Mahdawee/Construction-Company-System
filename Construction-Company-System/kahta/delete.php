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
$stmt = $pdo->prepare('SELECT * FROM kahta_accounts WHERE id = :id AND project_id = :pid');
$stmt->execute([':id' => $id, ':pid' => $projectId]);
$kahta = $stmt->fetch();

if ($kahta) {
    $del = $pdo->prepare('DELETE FROM kahta_accounts WHERE id = :id');
    $del->execute([':id' => $id]);
    auditLog('delete', 'kahta_accounts', $id, 'حذف کهاته: ' . $kahta['name']);
    flash('success', 'کهاته حذف شد.');
} else {
    flash('danger', 'کهاته یافت نشد.');
}
redirect('list.php?project_id=' . $projectId);
