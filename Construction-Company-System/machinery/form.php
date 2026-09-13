<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
if (!canEditData($user)) { http_response_code(403); include __DIR__ . '/../403.php'; exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$m = [
    'name' => '', 'machine_type' => '', 'plate_or_serial_no' => '', 'model' => '',
    'ownership_type' => 'owned', 'owner_name' => '', 'daily_rate' => '', 'currency_id' => 1,
    'status' => 'active', 'notes' => '', 'attachment_path' => null, 'attachment_name' => null,
];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM machinery WHERE id = :id AND project_id = :pid');
    $stmt->execute([':id' => $id, ':pid' => $projectId]);
    $m = $stmt->fetch();
    if (!$m) { flash('danger', 'ماشین یافت نشد.'); redirect('list.php?project_id=' . $projectId); }
}
$pageTitle = $id ? 'ویرایش ماشین' : 'ماشین جدید';
$errors = [];
$currencies = $pdo->query('SELECT * FROM currencies ORDER BY id')->fetchAll();
$typeOptions = ['کامیون', 'لودر', 'بلدوزر', 'گریدر', 'جرثقیل', 'حفار (اکسکاویتور)', 'جنراتور', 'میکسر بتون', 'سایر'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $machineType = trim($_POST['machine_type'] ?? '');
    $plateNo = trim($_POST['plate_or_serial_no'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $ownershipType = in_array($_POST['ownership_type'] ?? '', ['owned', 'rented'], true) ? $_POST['ownership_type'] : 'owned';
    $ownerName = trim($_POST['owner_name'] ?? '');
    $dailyRateRaw = trim($_POST['daily_rate'] ?? '');
    $dailyRate = $dailyRateRaw === '' ? null : (float)str_replace(',', '', $dailyRateRaw);
    $currencyId = (int)($_POST['currency_id'] ?? 1);
    $status = in_array($_POST['status'] ?? '', ['active', 'maintenance', 'inactive'], true) ? $_POST['status'] : 'active';
    $notes = trim($_POST['notes'] ?? '');

    if ($name === '') { $errors[] = 'نام ماشین الزامی است.'; }

    $newAttachment = null;
    if (empty($errors)) {
        try {
            $newAttachment = handleFileUpload('attachment', 'machinery', $projectId);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        $attachmentPath = $m['attachment_path'] ?? null;
        $attachmentName = $m['attachment_name'] ?? null;
        if ($newAttachment) {
            deleteUploadedFile($attachmentPath);
            $attachmentPath = $newAttachment['path'];
            $attachmentName = $newAttachment['name'];
        } elseif (!empty($_POST['remove_attachment'])) {
            deleteUploadedFile($attachmentPath);
            $attachmentPath = null; $attachmentName = null;
        }

        $paramsArr = [
            ':name' => $name, ':mt' => $machineType, ':plate' => $plateNo, ':model' => $model,
            ':ot' => $ownershipType, ':on' => $ownerName, ':dr' => $dailyRate, ':cur' => $currencyId,
            ':st' => $status, ':notes' => $notes, ':ap' => $attachmentPath, ':an' => $attachmentName,
        ];
        if ($id) {
            $paramsArr[':id'] = $id; $paramsArr[':pid'] = $projectId;
            $stmt = $pdo->prepare('UPDATE machinery SET name=:name, machine_type=:mt, plate_or_serial_no=:plate, model=:model, ownership_type=:ot, owner_name=:on, daily_rate=:dr, currency_id=:cur, status=:st, notes=:notes, attachment_path=:ap, attachment_name=:an WHERE id=:id AND project_id=:pid');
            $stmt->execute($paramsArr);
            auditLog('update', 'machinery', $id, "ویرایش ماشین: $name");
            flash('success', 'مشخصات ماشین ویرایش شد.');
        } else {
            $paramsArr[':pid'] = $projectId; $paramsArr[':cb'] = $user['id'];
            $stmt = $pdo->prepare('INSERT INTO machinery (project_id, name, machine_type, plate_or_serial_no, model, ownership_type, owner_name, daily_rate, currency_id, status, notes, attachment_path, attachment_name, created_by) VALUES (:pid,:name,:mt,:plate,:model,:ot,:on,:dr,:cur,:st,:notes,:ap,:an,:cb)');
            $stmt->execute($paramsArr);
            $newId = (int)$pdo->lastInsertId();
            auditLog('create', 'machinery', $newId, "ثبت ماشین جدید: $name");
            flash('success', 'ماشین جدید ثبت شد.');
        }
        redirect('list.php?project_id=' . $projectId);
    }
    $m = compact('name','machineType','plateNo','model','ownershipType','ownerName','currencyId','status','notes');
    $m['machine_type']=$machineType; $m['plate_or_serial_no']=$plateNo; $m['ownership_type']=$ownershipType;
    $m['owner_name']=$ownerName; $m['daily_rate']=$dailyRate; $m['currency_id']=$currencyId;
}

include __DIR__ . '/../includes/header.php';
?>
<datalist id="machineTypeList">
    <?php foreach ($typeOptions as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?>
</datalist>
<div class="card" style="max-width:760px">
    <div class="card-header"><?= e($pageTitle) ?></div>
    <div class="card-body">
        <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required">نام ماشین</label>
                    <input type="text" name="name" class="form-control" required value="<?= e($m['name']) ?>" placeholder="مثلاً: کامیون شماره ۱">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">نوع ماشین</label>
                    <input type="text" name="machine_type" class="form-control" list="machineTypeList" value="<?= e($m['machine_type']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">شماره پلیت / سریال</label>
                    <input type="text" name="plate_or_serial_no" class="form-control" value="<?= e($m['plate_or_serial_no']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">مودل</label>
                    <input type="text" name="model" class="form-control" value="<?= e($m['model']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required">مالکیت</label>
                    <select name="ownership_type" class="form-select" required>
                        <option value="owned" <?= $m['ownership_type']==='owned'?'selected':'' ?>>ملکیت شرکت</option>
                        <option value="rented" <?= $m['ownership_type']==='rented'?'selected':'' ?>>کرایی (اجاره‌ای)</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">مالک / کرایه‌دهنده</label>
                    <input type="text" name="owner_name" class="form-control" value="<?= e($m['owner_name']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">نرخ کرایه روزانه</label>
                    <input type="number" step="0.01" name="daily_rate" class="form-control" value="<?= e($m['daily_rate'] === null ? '' : (string)$m['daily_rate']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">واحد پولی</label>
                    <select name="currency_id" class="form-select">
                        <?php foreach ($currencies as $cur): ?>
                        <option value="<?= (int)$cur['id'] ?>" <?= (int)$m['currency_id'] === (int)$cur['id'] ? 'selected' : '' ?>><?= e($cur['name_fa']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">وضعیت</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= $m['status']==='active'?'selected':'' ?>>فعال</option>
                        <option value="maintenance" <?= $m['status']==='maintenance'?'selected':'' ?>>تعمیراتی</option>
                        <option value="inactive" <?= $m['status']==='inactive'?'selected':'' ?>>غیرفعال</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-control" rows="2"><?= e($m['notes']) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">فایل ضمیمه (جواز سیر، سند مالکیت/کرایه و غیره)</label>
                <?php if (!empty($m['attachment_path'])): ?>
                <div class="mb-2">
                    <a href="<?= BASE_URL ?>/files/download.php?module=machinery&id=<?= (int)$id ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-paperclip"></i> <?= e($m['attachment_name']) ?></a>
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
