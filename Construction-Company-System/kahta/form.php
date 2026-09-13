<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
if (!canEditData($user)) { http_response_code(403); include __DIR__ . '/../403.php'; exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$kahta = ['kahta_type_id' => '', 'name' => '', 'phone' => '', 'notes' => '', 'is_active' => 1, 'code' => ''];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM kahta_accounts WHERE id = :id AND project_id = :pid');
    $stmt->execute([':id' => $id, ':pid' => $projectId]);
    $kahta = $stmt->fetch();
    if (!$kahta) { flash('danger', 'کهاته یافت نشد.'); redirect('list.php?project_id=' . $projectId); }
}
$pageTitle = $id ? 'ویرایش کهاته' : 'کهاته جدید';
$errors = [];
$types = $pdo->query('SELECT * FROM kahta_types ORDER BY id')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $typeId = (int)($_POST['kahta_type_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $typeRow = null;
    foreach ($types as $t) { if ((int)$t['id'] === $typeId) { $typeRow = $t; break; } }
    if (!$typeRow) { $errors[] = 'نوع کهاته را انتخاب کنید.'; }
    if ($name === '') { $errors[] = 'نام کهاته الزامی است.'; }

    if (empty($errors)) {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE kahta_accounts SET kahta_type_id=:tid, name=:name, phone=:phone, notes=:notes, is_active=:act WHERE id=:id AND project_id=:pid');
            $stmt->execute([':tid' => $typeId, ':name' => $name, ':phone' => $phone, ':notes' => $notes, ':act' => $isActive, ':id' => $id, ':pid' => $projectId]);
            auditLog('update', 'kahta_accounts', $id, "ویرایش کهاته: $name");
            flash('success', 'کهاته ویرایش شد.');
        } else {
            $code = generateKahtaCode($pdo, $projectId, $typeRow['code']);
            $stmt = $pdo->prepare('INSERT INTO kahta_accounts (project_id, kahta_type_id, code, name, phone, notes, is_active) VALUES (:pid,:tid,:code,:name,:phone,:notes,:act)');
            $stmt->execute([':pid' => $projectId, ':tid' => $typeId, ':code' => $code, ':name' => $name, ':phone' => $phone, ':notes' => $notes, ':act' => $isActive]);
            $newId = (int)$pdo->lastInsertId();
            auditLog('create', 'kahta_accounts', $newId, "ایجاد کهاته جدید: $name ($code)");
            flash('success', "کهاته جدید با کد $code ایجاد شد.");
        }
        redirect('list.php?project_id=' . $projectId);
    }
    $kahta = compact('typeId', 'name', 'phone', 'notes') + ['is_active' => $isActive, 'kahta_type_id' => $typeId];
}

include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:640px">
    <div class="card-header"><?= e($pageTitle) ?> <?= $id ? '<span class="badge bg-dark">' . e($kahta['code']) . '</span>' : '' ?></div>
    <div class="card-body">
        <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="mb-3">
                <label class="form-label required">نوع کهاته</label>
                <select name="kahta_type_id" class="form-select" required <?= $id ? 'disabled' : '' ?>>
                    <option value="">-- انتخاب کنید --</option>
                    <?php foreach ($types as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (int)$kahta['kahta_type_id'] === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['code']) ?> - <?= e($t['name_fa']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($id): ?><input type="hidden" name="kahta_type_id" value="<?= (int)$kahta['kahta_type_id'] ?>"><small class="text-muted">پس از ایجاد، نوع و کد کهاته قابل تغییر نیست.</small><?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label required">نام</label>
                <input type="text" name="name" class="form-control" required value="<?= e($kahta['name']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">تلفن</label>
                <input type="text" name="phone" class="form-control" value="<?= e($kahta['phone']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-control" rows="2"><?= e($kahta['notes']) ?></textarea>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="is_active" id="is_active" class="form-check-input" <?= $kahta['is_active'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">فعال</label>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> ذخیره</button>
            <a href="list.php?project_id=<?= $projectId ?>" class="btn btn-secondary">انصراف</a>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
