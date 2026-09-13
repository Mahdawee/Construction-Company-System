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
$stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = :id AND project_id = :pid');
$stmt->execute([':id' => $id, ':pid' => $projectId]);
$contract = $stmt->fetch();

if ($contract) {
    $pdo->prepare('DELETE FROM contracts WHERE id = :id')->execute([':id' => $id]);
    deleteUploadedFile($contract['attachment_path']);
    auditLog('delete', 'contracts', $id, 'حذف قرارداد: ' . $contract['title']);
    flash('success', 'قرارداد حذف شد.');
} else {
    flash('danger', 'قرارداد یافت نشد.');
}
redirect('list.php?project_id=' . $projectId);
