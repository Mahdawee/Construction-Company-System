<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
$pageTitle = 'حاضری روزانه کارمندان';

$date = sanitizeDate($_GET['date'] ?? null) ?: date('Y-m-d');
$statusLabels = ['present' => 'حاضر', 'absent' => 'غایب', 'leave' => 'مرخصی', 'holiday' => 'رخصتی'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!canEditData($user)) { http_response_code(403); include __DIR__ . '/../403.php'; exit; }
    verifyCsrf();
    $postDate = sanitizeDate($_POST['date'] ?? null) ?: $date;
    $rows = $_POST['att'] ?? [];
    $stmt = $pdo->prepare("
        INSERT INTO employee_attendance (employee_id, project_id, attendance_date, status, notes, created_by)
        VALUES (:eid, :pid, :dt, :st, :notes, :cb)
        ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes)
    ");
    $count = 0;
    foreach ($rows as $employeeId => $data) {
        $employeeId = (int)$employeeId;
        $status = in_array($data['status'] ?? '', ['present', 'absent', 'leave', 'holiday'], true) ? $data['status'] : 'present';
        $notes = trim($data['notes'] ?? '');
        $stmt->execute([':eid' => $employeeId, ':pid' => $projectId, ':dt' => $postDate, ':st' => $status, ':notes' => $notes, ':cb' => $user['id']]);
        $count++;
    }
    auditLog('create', 'employee_attendance', null, "ثبت/به‌روزرسانی حاضری روزانه برای تاریخ $postDate ($count کارمند)");
    flash('success', "حاضری روزانه برای تاریخ $postDate برای $count کارمند ذخیره شد.");
    redirect('daily.php?project_id=' . $projectId . '&date=' . $postDate);
}

$stmt = $pdo->prepare("SELECT id, full_name, position FROM employees WHERE project_id = :pid AND status = 'active' ORDER BY full_name");
$stmt->execute([':pid' => $projectId]);
$employees = $stmt->fetchAll();

$existing = [];
if (!empty($employees)) {
    $stmt = $pdo->prepare("SELECT employee_id, status, notes FROM employee_attendance WHERE project_id = :pid AND attendance_date = :dt");
    $stmt->execute([':pid' => $projectId, ':dt' => $date]);
    foreach ($stmt->fetchAll() as $r) { $existing[$r['employee_id']] = $r; }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="d-flex gap-2 align-items-end">
            <input type="hidden" name="project_id" value="<?= $projectId ?>">
            <div>
                <label class="form-label mb-1">تاریخ حاضری</label>
                <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>" onchange="this.form.submit()">
            </div>
            <a href="report.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-chart-simple"></i> راپور ماهانه حاضری</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">فهرست حاضری برای تاریخ <?= e($date) ?></div>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="date" value="<?= e($date) ?>">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>#</th><th>نام کارمند</th><th>سمت</th><th>وضعیت حاضری</th><th>ملاحظات</th></tr></thead>
                <tbody>
                <?php if (empty($employees)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">کارمند فعالی برای این پروژه ثبت نشده است</td></tr>
                <?php endif; ?>
                <?php foreach ($employees as $i => $emp): $cur = $existing[$emp['id']] ?? ['status' => 'present', 'notes' => '']; ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($emp['full_name']) ?></td>
                        <td><?= e($emp['position']) ?></td>
                        <td>
                            <select name="att[<?= (int)$emp['id'] ?>][status]" class="form-select form-select-sm" <?= canEditData($user) ? '' : 'disabled' ?>>
                                <?php foreach ($statusLabels as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $cur['status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="text" name="att[<?= (int)$emp['id'] ?>][notes]" class="form-control form-control-sm" value="<?= e($cur['notes']) ?>" <?= canEditData($user) ? '' : 'disabled' ?>></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (canEditData($user) && !empty($employees)): ?>
        <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> ذخیره حاضری این روز</button>
        </div>
        <?php endif; ?>
    </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
