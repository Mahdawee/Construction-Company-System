<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
$pageTitle = 'مرخصی‌های کارمندان';

$employeeId = (int)($_GET['employee_id'] ?? 0);
$sql = "SELECT l.*, e.full_name FROM employee_leaves l JOIN employees e ON e.id = l.employee_id WHERE l.project_id = :pid";
$params = [':pid' => $projectId];
if ($employeeId) { $sql .= ' AND l.employee_id = :eid'; $params[':eid'] = $employeeId; }
$sql .= ' ORDER BY l.start_date DESC, l.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leaves = $stmt->fetchAll();

$typeLabels = ['annual' => 'رخصتی سالانه', 'sick' => 'مریضی', 'unpaid' => 'بدون معاش', 'other' => 'سایر'];
$statusLabels = ['pending' => ['در انتظار', 'secondary'], 'approved' => ['تایید شده', 'success'], 'rejected' => ['رد شده', 'danger']];

$empName = '';
if ($employeeId) {
    $s = $pdo->prepare('SELECT full_name FROM employees WHERE id = :id AND project_id = :pid');
    $s->execute([':id' => $employeeId, ':pid' => $projectId]);
    $empName = $s->fetchColumn() ?: '';
}

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?= $employeeId ? 'مرخصی‌های: ' . e($empName) : 'همه مرخصی‌ها' ?></span>
        <div class="d-flex gap-2">
            <?php if ($employeeId): ?><a href="list.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-outline-secondary">نمایش همه</a><?php endif; ?>
            <?php if (canEditData($user)): ?>
            <a href="form.php?project_id=<?= $projectId ?><?= $employeeId ? '&employee_id=' . $employeeId : '' ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> ثبت مرخصی جدید</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>کارمند</th><th>نوع مرخصی</th><th>از تاریخ</th><th>تا تاریخ</th><th>تعداد روز</th><th>دلیل</th><th>وضعیت</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php if (empty($leaves)): ?>
                <tr><td colspan="9" class="text-center text-muted py-3">مرخصی‌ای ثبت نشده است</td></tr>
            <?php endif; ?>
            <?php foreach ($leaves as $l): [$label, $color] = $statusLabels[$l['status']] ?? ['-', 'secondary']; ?>
                <tr>
                    <td><?= (int)$l['id'] ?></td>
                    <td><?= e($l['full_name']) ?></td>
                    <td><?= e($typeLabels[$l['leave_type']] ?? $l['leave_type']) ?></td>
                    <td><?= e($l['start_date']) ?></td>
                    <td><?= e($l['end_date']) ?></td>
                    <td><?= (int)$l['days_count'] ?></td>
                    <td><?= e(mb_strimwidth($l['reason'] ?? '', 0, 30, '...')) ?></td>
                    <td><span class="badge bg-<?= $color ?>"><?= $label ?></span></td>
                    <td>
                        <?php if (canEditData($user)): ?>
                        <a href="form.php?id=<?= (int)$l['id'] ?>&project_id=<?= $projectId ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (canDeleteData($user)): ?>
                        <form action="delete.php" method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="آیا از حذف این مرخصی مطمئن هستید؟"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
