<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
if (!canEditData($user)) { http_response_code(403); include __DIR__ . '/../403.php'; exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$emp = [
    'full_name' => '', 'father_name' => '', 'tazkira_no' => '', 'position' => '', 'department' => '',
    'phone' => '', 'address' => '', 'hire_date' => date('Y-m-d'), 'termination_date' => '', 'employment_type' => 'contract',
    'salary_amount' => '', 'salary_currency_id' => 1, 'status' => 'active', 'kahta_account_id' => '', 'notes' => '',
    'attachment_path' => null, 'attachment_name' => null,
];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM employees WHERE id = :id AND project_id = :pid');
    $stmt->execute([':id' => $id, ':pid' => $projectId]);
    $emp = $stmt->fetch();
    if (!$emp) { flash('danger', 'کارمند یافت نشد.'); redirect('list.php?project_id=' . $projectId); }
}
$pageTitle = $id ? 'ویرایش کارمند' : 'کارمند جدید';
$errors = [];
$currencies = $pdo->query('SELECT * FROM currencies ORDER BY id')->fetchAll();
$kahtaStmt = $pdo->prepare("SELECT k.id, k.code, k.name FROM kahta_accounts k JOIN kahta_types t ON t.id=k.kahta_type_id WHERE k.project_id = :pid AND t.code = 'S' AND k.is_active = 1 ORDER BY k.code");
$kahtaStmt->execute([':pid' => $projectId]);
$kahtas = $kahtaStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $fullName = trim($_POST['full_name'] ?? '');
    $fatherName = trim($_POST['father_name'] ?? '');
    $tazkiraNo = trim($_POST['tazkira_no'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $hireDate = sanitizeDate($_POST['hire_date'] ?? null);
    $terminationDate = sanitizeDate($_POST['termination_date'] ?? null);
    $employmentType = in_array($_POST['employment_type'] ?? '', ['permanent', 'contract', 'daily_wage'], true) ? $_POST['employment_type'] : 'contract';
    $salaryAmount = (float)str_replace(',', '', $_POST['salary_amount'] ?? 0);
    $salaryCurrencyId = (int)($_POST['salary_currency_id'] ?? 1);
    $status = in_array($_POST['status'] ?? '', ['active', 'terminated'], true) ? $_POST['status'] : 'active';
    $kahtaId = (int)($_POST['kahta_account_id'] ?? 0) ?: null;
    $notes = trim($_POST['notes'] ?? '');

    if ($fullName === '') { $errors[] = 'نام کامل کارمند الزامی است.'; }

    $newAttachment = null;
    if (empty($errors)) {
        try {
            $newAttachment = handleFileUpload('attachment', 'employees', $projectId);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        $attachmentPath = $emp['attachment_path'] ?? null;
        $attachmentName = $emp['attachment_name'] ?? null;
        if ($newAttachment) {
            deleteUploadedFile($attachmentPath);
            $attachmentPath = $newAttachment['path'];
            $attachmentName = $newAttachment['name'];
        } elseif (!empty($_POST['remove_attachment'])) {
            deleteUploadedFile($attachmentPath);
            $attachmentPath = null; $attachmentName = null;
        }

        $paramsArr = [
            ':fn'=>$fullName, ':father'=>$fatherName, ':tz'=>$tazkiraNo, ':pos'=>$position, ':dept'=>$department,
            ':phone'=>$phone, ':addr'=>$address, ':hd'=>$hireDate, ':td'=>$terminationDate, ':et'=>$employmentType,
            ':sal'=>$salaryAmount, ':cur'=>$salaryCurrencyId, ':st'=>$status, ':kahta'=>$kahtaId, ':notes'=>$notes,
            ':ap'=>$attachmentPath, ':an'=>$attachmentName,
        ];
        if ($id) {
            $paramsArr[':id'] = $id; $paramsArr[':pid'] = $projectId;
            $stmt = $pdo->prepare('UPDATE employees SET full_name=:fn, father_name=:father, tazkira_no=:tz, position=:pos, department=:dept, phone=:phone, address=:addr, hire_date=:hd, termination_date=:td, employment_type=:et, salary_amount=:sal, salary_currency_id=:cur, status=:st, kahta_account_id=:kahta, notes=:notes, attachment_path=:ap, attachment_name=:an WHERE id=:id AND project_id=:pid');
            $stmt->execute($paramsArr);
            auditLog('update', 'employees', $id, "ویرایش کارمند: $fullName");
            flash('success', 'اطلاعات کارمند ویرایش شد.');
        } else {
            $paramsArr[':pid'] = $projectId; $paramsArr[':cb'] = $user['id'];
            $stmt = $pdo->prepare('INSERT INTO employees (project_id, full_name, father_name, tazkira_no, position, department, phone, address, hire_date, termination_date, employment_type, salary_amount, salary_currency_id, status, kahta_account_id, notes, attachment_path, attachment_name, created_by) VALUES (:pid,:fn,:father,:tz,:pos,:dept,:phone,:addr,:hd,:td,:et,:sal,:cur,:st,:kahta,:notes,:ap,:an,:cb)');
            $stmt->execute($paramsArr);
            $newId = (int)$pdo->lastInsertId();
            auditLog('create', 'employees', $newId, "ثبت کارمند جدید: $fullName");
            flash('success', 'کارمند جدید ثبت شد.');
        }
        redirect('list.php?project_id=' . $projectId);
    }
    $emp = compact('fullName','fatherName','tazkiraNo','position','department','phone','address','hireDate','terminationDate','employmentType','salaryAmount','salaryCurrencyId','status','kahtaId','notes');
    $emp['full_name']=$fullName; $emp['father_name']=$fatherName; $emp['tazkira_no']=$tazkiraNo; $emp['hire_date']=$hireDate;
    $emp['termination_date']=$terminationDate; $emp['employment_type']=$employmentType; $emp['salary_amount']=$salaryAmount;
    $emp['salary_currency_id']=$salaryCurrencyId; $emp['kahta_account_id']=$kahtaId;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:860px">
    <div class="card-header"><?= e($pageTitle) ?></div>
    <div class="card-body">
        <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required">نام کامل</label>
                    <input type="text" name="full_name" class="form-control" required value="<?= e($emp['full_name']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">نام پدر</label>
                    <input type="text" name="father_name" class="form-control" value="<?= e($emp['father_name']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">شماره تذکره</label>
                    <input type="text" name="tazkira_no" class="form-control" value="<?= e($emp['tazkira_no']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">سمت / وظیفه</label>
                    <input type="text" name="position" class="form-control" value="<?= e($emp['position']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">بخش</label>
                    <input type="text" name="department" class="form-control" value="<?= e($emp['department']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">تلفن</label>
                    <input type="text" name="phone" class="form-control" value="<?= e($emp['phone']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">آدرس</label>
                    <input type="text" name="address" class="form-control" value="<?= e($emp['address']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">تاریخ استخدام</label>
                    <input type="date" name="hire_date" class="form-control" value="<?= e($emp['hire_date']) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">تاریخ ختم کار</label>
                    <input type="date" name="termination_date" class="form-control" value="<?= e($emp['termination_date']) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">نوع استخدام</label>
                    <select name="employment_type" class="form-select">
                        <option value="permanent" <?= $emp['employment_type']==='permanent'?'selected':'' ?>>دایمی</option>
                        <option value="contract" <?= $emp['employment_type']==='contract'?'selected':'' ?>>قراردادی</option>
                        <option value="daily_wage" <?= $emp['employment_type']==='daily_wage'?'selected':'' ?>>روزمزد</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">وضعیت</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= $emp['status']==='active'?'selected':'' ?>>فعال</option>
                        <option value="terminated" <?= $emp['status']==='terminated'?'selected':'' ?>>برکنارشده</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">مبلغ معاش</label>
                    <input type="number" step="0.01" name="salary_amount" class="form-control" value="<?= e((string)$emp['salary_amount']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">واحد پولی معاش</label>
                    <select name="salary_currency_id" class="form-select">
                        <?php foreach ($currencies as $cur): ?>
                        <option value="<?= (int)$cur['id'] ?>" <?= (int)$emp['salary_currency_id'] === (int)$cur['id'] ? 'selected' : '' ?>><?= e($cur['name_fa']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">لینک به کهاته پرسونل (S)</label>
                    <select name="kahta_account_id" class="form-select">
                        <option value="">-- بدون لینک --</option>
                        <?php foreach ($kahtas as $k): ?>
                        <option value="<?= (int)$k['id'] ?>" <?= (int)$emp['kahta_account_id'] === (int)$k['id'] ? 'selected' : '' ?>><?= e($k['code']) ?> - <?= e($k['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">برای پیگیری مصارف/پیش‌پرداخت‌های این کارمند در بخش کهاته‌ها</small>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-control" rows="2"><?= e($emp['notes']) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">فایل ضمیمه (تصویر تذکره، CV و غیره)</label>
                <?php if (!empty($emp['attachment_path'])): ?>
                <div class="mb-2">
                    <a href="<?= BASE_URL ?>/files/download.php?module=employees&id=<?= (int)$id ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-paperclip"></i> <?= e($emp['attachment_name']) ?></a>
                    <div class="form-check d-inline-block ms-2">
                        <input type="checkbox" name="remove_attachment" value="1" class="form-check-input" id="rmatt">
                        <label class="form-check-label" for="rmatt">حذف فایل فعلی</label>
                    </div>
                </div>
                <?php endif; ?>
                <input type="file" name="attachment" class="form-control">
                <small class="text-muted">فرمت‌های مجاز: PDF, JPG, PNG, GIF, DOC, DOCX - حداکثر ۸ مگابایت</small>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> ذخیره</button>
            <a href="list.php?project_id=<?= $projectId ?>" class="btn btn-secondary">انصراف</a>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
