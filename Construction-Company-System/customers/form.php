<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
if (!canEditData($user)) { http_response_code(403); include __DIR__ . '/../403.php'; exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$c = ['customer_type' => 'individual', 'name' => '', 'contact_person' => '', 'phone' => '', 'email' => '',
      'address' => '', 'id_number' => '', 'notes' => '', 'is_active' => 1, 'attachment_path' => null, 'attachment_name' => null];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id AND project_id = :pid');
    $stmt->execute([':id' => $id, ':pid' => $projectId]);
    $c = $stmt->fetch();
    if (!$c) { flash('danger', 'مشتری یافت نشد.'); redirect('list.php?project_id=' . $projectId); }
}
$pageTitle = $id ? 'ویرایش مشتری' : 'مشتری جدید';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $customerType = in_array($_POST['customer_type'] ?? '', ['individual', 'company'], true) ? $_POST['customer_type'] : 'individual';
    $name = trim($_POST['name'] ?? '');
    $contactPerson = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $idNumber = trim($_POST['id_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') { $errors[] = 'نام مشتری الزامی است.'; }

    $newAttachment = null;
    if (empty($errors)) {
        try {
            $newAttachment = handleFileUpload('attachment', 'customers', $projectId);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        $attachmentPath = $c['attachment_path'] ?? null;
        $attachmentName = $c['attachment_name'] ?? null;
        if ($newAttachment) {
            deleteUploadedFile($attachmentPath);
            $attachmentPath = $newAttachment['path'];
            $attachmentName = $newAttachment['name'];
        } elseif (!empty($_POST['remove_attachment'])) {
            deleteUploadedFile($attachmentPath);
            $attachmentPath = null; $attachmentName = null;
        }

        if ($id) {
            $stmt = $pdo->prepare('UPDATE customers SET customer_type=:ct, name=:name, contact_person=:cp, phone=:phone, email=:email, address=:addr, id_number=:idn, notes=:notes, is_active=:act, attachment_path=:ap, attachment_name=:an WHERE id=:id AND project_id=:pid');
            $stmt->execute([':ct'=>$customerType, ':name'=>$name, ':cp'=>$contactPerson, ':phone'=>$phone, ':email'=>$email, ':addr'=>$address, ':idn'=>$idNumber, ':notes'=>$notes, ':act'=>$isActive, ':ap'=>$attachmentPath, ':an'=>$attachmentName, ':id'=>$id, ':pid'=>$projectId]);
            auditLog('update', 'customers', $id, "ویرایش مشتری: $name");
            flash('success', 'مشتری ویرایش شد.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO customers (project_id, customer_type, name, contact_person, phone, email, address, id_number, notes, is_active, attachment_path, attachment_name, created_by) VALUES (:pid,:ct,:name,:cp,:phone,:email,:addr,:idn,:notes,:act,:ap,:an,:cb)');
            $stmt->execute([':pid'=>$projectId, ':ct'=>$customerType, ':name'=>$name, ':cp'=>$contactPerson, ':phone'=>$phone, ':email'=>$email, ':addr'=>$address, ':idn'=>$idNumber, ':notes'=>$notes, ':act'=>$isActive, ':ap'=>$attachmentPath, ':an'=>$attachmentName, ':cb'=>$user['id']]);
            $newId = (int)$pdo->lastInsertId();
            auditLog('create', 'customers', $newId, "ایجاد مشتری جدید: $name");
            flash('success', 'مشتری جدید ثبت شد.');
        }
        redirect('list.php?project_id=' . $projectId);
    }
    $c = compact('customerType','name','contactPerson','phone','email','address','idNumber','notes') + ['is_active' => $isActive];
    $c['customer_type']=$customerType; $c['contact_person']=$contactPerson; $c['id_number']=$idNumber;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:700px">
    <div class="card-header"><?= e($pageTitle) ?></div>
    <div class="card-body">
        <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required">نوع مشتری</label>
                    <select name="customer_type" class="form-select" required>
                        <option value="individual" <?= $c['customer_type']==='individual'?'selected':'' ?>>شخص حقیقی</option>
                        <option value="company" <?= $c['customer_type']==='company'?'selected':'' ?>>شرکت/نهاد</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required">نام مشتری</label>
                    <input type="text" name="name" class="form-control" required value="<?= e($c['name']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">شخص رابط</label>
                    <input type="text" name="contact_person" class="form-control" value="<?= e($c['contact_person']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">شماره تذکره / جواز کاری</label>
                    <input type="text" name="id_number" class="form-control" value="<?= e($c['id_number']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">تلفن</label>
                    <input type="text" name="phone" class="form-control" value="<?= e($c['phone']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">ایمیل</label>
                    <input type="email" name="email" class="form-control" value="<?= e($c['email']) ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">آدرس</label>
                <input type="text" name="address" class="form-control" value="<?= e($c['address']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-control" rows="2"><?= e($c['notes']) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">فایل ضمیمه (تصویر تذکره/جواز و غیره)</label>
                <?php if (!empty($c['attachment_path'])): ?>
                <div class="mb-2">
                    <a href="<?= BASE_URL ?>/files/download.php?module=customers&id=<?= (int)$id ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-paperclip"></i> <?= e($c['attachment_name']) ?></a>
                    <div class="form-check d-inline-block ms-2">
                        <input type="checkbox" name="remove_attachment" value="1" class="form-check-input" id="rmatt">
                        <label class="form-check-label" for="rmatt">حذف فایل فعلی</label>
                    </div>
                </div>
                <?php endif; ?>
                <input type="file" name="attachment" class="form-control">
                <small class="text-muted">فرمت‌های مجاز: PDF, JPG, PNG, GIF, DOC, DOCX - حداکثر ۸ مگابایت</small>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="is_active" id="is_active" class="form-check-input" <?= $c['is_active'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">فعال</label>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> ذخیره</button>
            <a href="list.php?project_id=<?= $projectId ?>" class="btn btn-secondary">انصراف</a>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
