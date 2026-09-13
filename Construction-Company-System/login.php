<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (currentUser()) {
    redirect(BASE_URL . '/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $pdo = getDb();
    $stmt = $pdo->prepare('SELECT u.*, r.role_key, r.name_fa AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        $error = 'نام کاربری یا رمز عبور نادرست است.';
        auditLog('login_failed', 'auth', null, 'تلاش ناموفق ورود برای یوزرنیم: ' . $username);
    } elseif (!$row['is_active']) {
        $error = 'حساب کاربری شما غیرفعال شده است. با مدیر سیستم تماس بگیرید.';
    } else {
        $_SESSION['user'] = [
            'id' => (int)$row['id'],
            'username' => $row['username'],
            'full_name' => $row['full_name'],
            'role_id' => (int)$row['role_id'],
            'role_key' => $row['role_key'],
            'role_name' => $row['role_name'],
            'company_id' => $row['company_id'] !== null ? (int)$row['company_id'] : null,
        ];
        unset($_SESSION['current_project_id']);
        $upd = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
        $upd->execute([':id' => $row['id']]);
        auditLog('login', 'auth', (int)$row['id'], 'ورود موفق کاربر ' . $row['username']);
        redirect(BASE_URL . '/index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ورود به سیستم | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<style>
body { background: linear-gradient(135deg,#0b3d59,#10557a); min-height:100vh; display:flex; align-items:center; justify-content:center; }
.login-card { max-width: 400px; width: 100%; border-radius: 14px; }
</style>
</head>
<body>
<div class="card login-card p-2">
    <div class="card-body">
        <div class="text-center mb-3">
            <i class="fa-solid fa-building text-primary" style="font-size:42px"></i>
            <h5 class="mt-2 mb-0"><?= e(APP_NAME) ?></h5>
            <small class="text-muted">ورود به سیستم</small>
        </div>
        <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="mb-3">
                <label class="form-label">نام کاربری</label>
                <input type="text" name="username" class="form-control" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">رمز عبور</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-right-to-bracket"></i> ورود</button>
        </form>
    </div>
</div>
</body>
</html>
