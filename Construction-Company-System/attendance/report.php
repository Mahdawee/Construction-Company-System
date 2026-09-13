<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
$pageTitle = 'راپور ماهانه حاضری کارمندان';

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) { $month = date('Y-m'); }
$monthStart = $month . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

$stmt = $pdo->prepare("
    SELECT e.id, e.full_name, e.position,
        SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) AS present_days,
        SUM(CASE WHEN a.status='absent' THEN 1 ELSE 0 END) AS absent_days,
        SUM(CASE WHEN a.status='leave' THEN 1 ELSE 0 END) AS leave_days,
        SUM(CASE WHEN a.status='holiday' THEN 1 ELSE 0 END) AS holiday_days,
        COUNT(a.id) AS total_marked
    FROM employees e
    LEFT JOIN employee_attendance a ON a.employee_id = e.id AND a.attendance_date BETWEEN :start AND :end
    WHERE e.project_id = :pid
    GROUP BY e.id ORDER BY e.full_name
");
$stmt->execute([':start' => $monthStart, ':end' => $monthEnd, ':pid' => $projectId]);
$rows = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="d-flex gap-2 align-items-end">
            <input type="hidden" name="project_id" value="<?= $projectId ?>">
            <div>
                <label class="form-label mb-1">ماه گزارش</label>
                <input type="month" name="month" class="form-control form-control-sm" value="<?= e($month) ?>" onchange="this.form.submit()">
            </div>
            <a href="daily.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-calendar-check"></i> ثبت حاضری روزانه</a>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-header">خلاصه حاضری از <?= e($monthStart) ?> تا <?= e($monthEnd) ?></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>نام کارمند</th><th>سمت</th><th>حاضر</th><th>غایب</th><th>مرخصی</th><th>رخصتی</th><th>مجموع ثبت‌شده</th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8" class="text-center text-muted py-3">کارمندی یافت نشد</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $i => $r): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($r['full_name']) ?></td>
                    <td><?= e($r['position']) ?></td>
                    <td><span class="badge bg-success"><?= (int)$r['present_days'] ?></span></td>
                    <td><span class="badge bg-danger"><?= (int)$r['absent_days'] ?></span></td>
                    <td><span class="badge bg-warning text-dark"><?= (int)$r['leave_days'] ?></span></td>
                    <td><span class="badge bg-secondary"><?= (int)$r['holiday_days'] ?></span></td>
                    <td><?= (int)$r['total_marked'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
