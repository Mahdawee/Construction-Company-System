<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$pageTitle = 'مشخصات من';
$errors = [];

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute([':id' => $user['id']]);
$me = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');

    if ($fullName === '') { $errors[] = 'نام کامل الزامی است.'; }

    if ($newPassword !== '') {
        if (!password_verify($currentPassword, $me['password_hash'])) {
            $errors[] = 'رمز عبور فعلی نادرست است.';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'رمز عبور جدید باید حداقل ۶ کاراکتر باشد.';
        }
    }

    if (empty($errors)) {
        if ($newPassword !== '') {
            $stmt = $pdo->prepare('UPDATE users SET full_name=:fn, email=:em, phone=:ph, password_hash=:pw WHERE id=:id');
            $stmt->execute([':fn' => $fullName, ':em' => $email, ':ph' => $phone, ':pw' => password_hash($newPassword, PASSWORD_BCRYPT), ':id' => $user['id']]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET full_name=:fn, email=:em, phone=:ph WHERE id=:id');
            $stmt->execute([':fn' => $fullName, ':em' => $email, ':ph' => $phone, ':id' => $user['id']]);
        }
        $_SESSION['user']['full_name'] = $fullName;
        auditLog('update', 'users', $user['id'], 'ویرایش مشخصات شخصی');
        flash('success', 'مشخصات با موفقیت ذخیره شد.');
        redirect(BASE_URL . '/profile.php');
    }
    $me['full_name'] = $fullName; $me['email'] = $email; $me['phone'] = $phone;
}

include __DIR__ . '/includes/header.php';
?>
<div class="card" style="max-width:600px">
    <div class="card-header">مشخصات من</div>
    <div class="card-body">
        <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="mb-3">
                <label class="form-label">یوزرنیم</label>
                <input type="text" class="form-control" value="<?= e($me['username']) ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label required">نام کامل</label>
                <input type="text" name="full_name" class="form-control" required value="<?= e($me['full_name']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">ایمیل</label>
                <input type="email" name="email" class="form-control" value="<?= e($me['email']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">تلفن</label>
                <input type="text" name="phone" class="form-control" value="<?= e($me['phone']) ?>">
            </div>
            <hr>
            <p class="text-muted">برای تغییر رمز عبور، فیلدهای زیر را تکمیل کنید:</p>
            <div class="mb-3">
                <label class="form-label">رمز عبور فعلی</label>
                <input type="password" name="current_password" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">رمز عبور جدید</label>
                <input type="password" name="new_password" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> ذخیره</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
