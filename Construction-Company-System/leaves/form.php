<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
if (!canEditData($user)) { http_response_code(403); include __DIR__ . '/../403.php'; exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$l = [
    'employee_id' => (int)($_GET['employee_id'] ?? 0), 'leave_type' => 'annual', 'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d'), 'reason' => '', 'status' => 'pending', 'notes' => '',
];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM employee_leaves WHERE id = :id AND project_id = :pid');
    $stmt->execute([':id' => $id, ':pid' => $projectId]);
    $l = $stmt->fetch();
    if (!$l) { flash('danger', 'رکورد مرخصی یافت نشد.'); redirect('list.php?project_id=' . $projectId); }
}
$pageTitle = $id ? 'ویرایش مرخصی' : 'ثبت مرخصی جدید';
$errors = [];
$empStmt = $pdo->prepare("SELECT id, full_name FROM employees WHERE project_id = :pid AND status = 'active' ORDER BY full_name");
$empStmt->execute([':pid' => $projectId]);
$employees = $empStmt->fetchAll();
// مرخصیِ کارمندی که بعداً ختم شده باید همچنان کارمند خودش را نشان دهد؛
// وگرنه با ذخیره، یا خطای «انتخاب کارمند الزامی است» می‌گیرد یا بی‌صدا به
// کارمند دیگری منتقل می‌شود.
$employees = includeLinkedOptions($pdo, $employees, $l['employee_id'] ?? null,
    'SELECT id, full_name FROM employees WHERE id = ?', [], 'full_name');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $leaveType = in_array($_POST['leave_type'] ?? '', ['annual', 'sick', 'unpaid', 'other'], true) ? $_POST['leave_type'] : 'annual';
    $startDate = sanitizeDate($_POST['start_date'] ?? null);
    $endDate = sanitizeDate($_POST['end_date'] ?? null);
    $reason = trim($_POST['reason'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_POST['status'] : 'pending';
    $notes = trim($_POST['notes'] ?? '');

    if (!$employeeId) { $errors[] = 'انتخاب کارمند الزامی است.'; }
    if (!$startDate || !$endDate) { $errors[] = 'تاریخ شروع و ختم مرخصی الزامی و باید معتبر باشد.'; }
    if ($startDate && $endDate && $startDate > $endDate) { $errors[] = 'تاریخ شروع نمی‌تواند بعد از تاریخ ختم باشد.'; }

    if (empty($errors)) {
        $daysCount = (int)((strtotime($endDate) - strtotime($startDate)) / 86400) + 1;
        $paramsArr = [':eid' => $employeeId, ':lt' => $leaveType, ':sd' => $startDate, ':ed' => $endDate, ':days' => $daysCount, ':reason' => $reason, ':st' => $status, ':notes' => $notes];
        if ($id) {
            $paramsArr[':id'] = $id; $paramsArr[':pid'] = $projectId;
            $stmt = $pdo->prepare('UPDATE employee_leaves SET employee_id=:eid, leave_type=:lt, start_date=:sd, end_date=:ed, days_count=:days, reason=:reason, status=:st, notes=:notes WHERE id=:id AND project_id=:pid');
            $stmt->execute($paramsArr);
            auditLog('update', 'employee_leaves', $id, 'ویرایش مرخصی کارمند');
            flash('success', 'رکورد مرخصی ویرایش شد.');
        } else {
            $paramsArr[':pid'] = $projectId; $paramsArr[':cb'] = $user['id'];
            $stmt = $pdo->prepare('INSERT INTO employee_leaves (employee_id, project_id, leave_type, start_date, end_date, days_count, reason, status, notes, created_by) VALUES (:eid,:pid,:lt,:sd,:ed,:days,:reason,:st,:notes,:cb)');
            $stmt->execute($paramsArr);
            $newId = (int)$pdo->lastInsertId();
            auditLog('create', 'employee_leaves', $newId, 'ثبت مرخصی جدید کارمند');
            flash('success', 'مرخصی جدید ثبت شد.');
        }
        redirect('list.php?project_id=' . $projectId);
    }
    $l = compact('employeeId', 'leaveType', 'startDate', 'endDate', 'reason', 'status', 'notes');
    $l['employee_id'] = $employeeId; $l['leave_type'] = $leaveType; $l['start_date'] = $startDate; $l['end_date'] = $endDate;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:640px">
    <div class="card-header"><?= e($pageTitle) ?></div>
    <div class="card-body">
        <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="mb-3">
                <label class="form-label required">کارمند</label>
                <select name="employee_id" class="form-select" required>
                    <option value="">-- انتخاب کنید --</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= (int)$emp['id'] ?>" <?= (int)$l['employee_id'] === (int)$emp['id'] ? 'selected' : '' ?>><?= e($emp['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required">نوع مرخصی</label>
                    <select name="leave_type" class="form-select" required>
                        <option value="annual" <?= $l['leave_type']==='annual'?'selected':'' ?>>رخصتی سالانه</option>
                        <option value="sick" <?= $l['leave_type']==='sick'?'selected':'' ?>>مریضی</option>
                        <option value="unpaid" <?= $l['leave_type']==='unpaid'?'selected':'' ?>>بدون معاش</option>
                        <option value="other" <?= $l['leave_type']==='other'?'selected':'' ?>>سایر</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">وضعیت</label>
                    <select name="status" class="form-select">
                        <option value="pending" <?= $l['status']==='pending'?'selected':'' ?>>در انتظار</option>
                        <option value="approved" <?= $l['status']==='approved'?'selected':'' ?>>تایید شده</option>
                        <option value="rejected" <?= $l['status']==='rejected'?'selected':'' ?>>رد شده</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required">از تاریخ</label>
                    <input type="date" name="start_date" class="form-control" required value="<?= e($l['start_date']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required">تا تاریخ</label>
                    <input type="date" name="end_date" class="form-control" required value="<?= e($l['end_date']) ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">دلیل مرخصی</label>
                <input type="text" name="reason" class="form-control" value="<?= e($l['reason']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-control" rows="2"><?= e($l['notes']) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> ذخیره</button>
            <a href="list.php?project_id=<?= $projectId ?>" class="btn btn-secondary">انصراف</a>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
