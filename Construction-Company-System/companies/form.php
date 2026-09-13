<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireRole(['super_admin']);
$pdo = getDb();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$company = ['name' => '', 'address' => '', 'phone' => '', 'is_active' => 1];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $company = $stmt->fetch();
    if (!$company) { flash('danger', 'شرکت یافت نشد.'); redirect('list.php'); }
}
$pageTitle = $id ? 'ویرایش شرکت' : 'شرکت جدید';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') { $errors[] = 'نام شرکت الزامی است.'; }

    if (empty($errors)) {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE companies SET name=:name, address=:address, phone=:phone, is_active=:active WHERE id=:id');
            $stmt->execute([':name' => $name, ':address' => $address, ':phone' => $phone, ':active' => $isActive, ':id' => $id]);
            auditLog('update', 'companies', $id, "ویرایش شرکت: $name");
            flash('success', 'شرکت با موفقیت ویرایش شد.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO companies (name, address, phone, is_active) VALUES (:name,:address,:phone,:active)');
            $stmt->execute([':name' => $name, ':address' => $address, ':phone' => $phone, ':active' => $isActive]);
            $newId = (int)$pdo->lastInsertId();
            auditLog('create', 'companies', $newId, "ایجاد شرکت جدید: $name");
            flash('success', 'شرکت جدید با موفقیت ایجاد شد.');
        }
        redirect('list.php');
    }
    $company = compact('name', 'address', 'phone') + ['is_active' => $isActive];
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
                <label class="form-label required">نام شرکت</label>
                <input type="text" name="name" class="form-control" required value="<?= e($company['name']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">آدرس</label>
                <input type="text" name="address" class="form-control" value="<?= e($company['address']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">تلفن</label>
                <input type="text" name="phone" class="form-control" value="<?= e($company['phone']) ?>">
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="is_active" id="is_active" class="form-check-input" <?= $company['is_active'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">فعال</label>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> ذخیره</button>
            <a href="list.php" class="btn btn-secondary">انصراف</a>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
