<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireRole(['super_admin', 'company_manager']);
$pdo = getDb();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$editUser = ['username' => '', 'full_name' => '', 'email' => '', 'phone' => '', 'role_id' => '', 'company_id' => $user['company_id'] ?? '', 'is_active' => 1];
$assignedProjects = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $editUser = $stmt->fetch();
    if (!$editUser || ($user['role_key'] === 'company_manager' && (int)$editUser['company_id'] !== (int)$user['company_id'] && (int)$editUser['id'] !== (int)$user['id'])) {
        flash('danger', 'کاربر یافت نشد یا دسترسی ندارید.');
        redirect('list.php');
    }
    $stmt2 = $pdo->prepare('SELECT project_id FROM user_projects WHERE user_id = :uid');
    $stmt2->execute([':uid' => $id]);
    $assignedProjects = array_map(static fn($r) => (int)$r['project_id'], $stmt2->fetchAll());
}
$pageTitle = $id ? 'ویرایش کاربر' : 'کاربر جدید';
$errors = [];

// نقش‌های مجاز برای انتساب
if ($user['role_key'] === 'super_admin') {
    $availableRoles = $pdo->query('SELECT * FROM roles ORDER BY level')->fetchAll();
    $companies = $pdo->query('SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name')->fetchAll();
    $projects = $pdo->query('SELECT p.id, p.name, c.name AS company_name FROM projects p JOIN companies c ON c.id = p.company_id ORDER BY c.name, p.name')->fetchAll();
} else {
    $availableRoles = $pdo->query("SELECT * FROM roles WHERE role_key IN ('project_manager','accountant','viewer') ORDER BY level")->fetchAll();
    $stmtC = $pdo->prepare('SELECT id, name FROM companies WHERE id = :id');
    $stmtC->execute([':id' => $user['company_id']]);
    $companies = $stmtC->fetchAll();
    $stmtP = $pdo->prepare('SELECT p.id, p.name, c.name AS company_name FROM projects p JOIN companies c ON c.id = p.company_id WHERE p.company_id = :cid ORDER BY p.name');
    $stmtP->execute([':cid' => $user['company_id']]);
    $projects = $stmtP->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $roleId = (int)($_POST['role_id'] ?? 0);
    $companyId = $user['role_key'] === 'super_admin' ? ((int)($_POST['company_id'] ?? 0) ?: null) : (int)$user['company_id'];
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $selectedProjects = array_map('intval', $_POST['projects'] ?? []);

    $allowedRoleIds = array_map(static fn($r) => (int)$r['id'], $availableRoles);
    if ($username === '') { $errors[] = 'یوزرنیم الزامی است.'; }
    if ($fullName === '') { $errors[] = 'نام کامل الزامی است.'; }
    if (!in_array($roleId, $allowedRoleIds, true)) { $errors[] = 'نقش انتخاب‌شده معتبر نیست.'; }
    if (!$id && $password === '') { $errors[] = 'رمز عبور برای کاربر جدید الزامی است.'; }
    if ($password !== '' && strlen($password) < 6) { $errors[] = 'رمز عبور باید حداقل ۶ کاراکتر باشد.'; }

    if (empty($errors)) {
        $dup = $pdo->prepare('SELECT id FROM users WHERE username = :u AND id != :id');
        $dup->execute([':u' => $username, ':id' => $id ?: 0]);
        if ($dup->fetch()) { $errors[] = 'این یوزرنیم قبلاً استفاده شده است.'; }
    }

    if (empty($errors)) {
        if ($id) {
            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE users SET username=:u, full_name=:fn, email=:em, phone=:ph, role_id=:rid, company_id=:cid, is_active=:act, password_hash=:pw WHERE id=:id');
                $stmt->execute([':u' => $username, ':fn' => $fullName, ':em' => $email, ':ph' => $phone, ':rid' => $roleId, ':cid' => $companyId, ':act' => $isActive, ':pw' => password_hash($password, PASSWORD_BCRYPT), ':id' => $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET username=:u, full_name=:fn, email=:em, phone=:ph, role_id=:rid, company_id=:cid, is_active=:act WHERE id=:id');
                $stmt->execute([':u' => $username, ':fn' => $fullName, ':em' => $email, ':ph' => $phone, ':rid' => $roleId, ':cid' => $companyId, ':act' => $isActive, ':id' => $id]);
            }
            $userId = $id;
            auditLog('update', 'users', $userId, "ویرایش کاربر: $username");
            flash('success', 'کاربر ویرایش شد.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, full_name, email, phone, role_id, company_id, is_active) VALUES (:u,:pw,:fn,:em,:ph,:rid,:cid,:act)');
            $stmt->execute([':u' => $username, ':pw' => password_hash($password, PASSWORD_BCRYPT), ':fn' => $fullName, ':em' => $email, ':ph' => $phone, ':rid' => $roleId, ':cid' => $companyId, ':act' => $isActive]);
            $userId = (int)$pdo->lastInsertId();
            auditLog('create', 'users', $userId, "ایجاد کاربر جدید: $username");
            flash('success', 'کاربر جدید ایجاد شد.');
        }

        // به‌روزرسانی انتساب پروژه‌ها
        $pdo->prepare('DELETE FROM user_projects WHERE user_id = :uid')->execute([':uid' => $userId]);
        if (!empty($selectedProjects)) {
            $insProj = $pdo->prepare('INSERT IGNORE INTO user_projects (user_id, project_id) VALUES (:uid, :pid)');
            foreach ($selectedProjects as $pid) {
                $insProj->execute([':uid' => $userId, ':pid' => $pid]);
            }
        }
        redirect('list.php');
    }
    $editUser = compact('username', 'fullName', 'email', 'phone', 'roleId', 'companyId', 'isActive');
    $editUser['full_name'] = $fullName; $editUser['role_id'] = $roleId; $editUser['company_id'] = $companyId; $editUser['is_active'] = $isActive;
    $assignedProjects = $selectedProjects;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:760px">
    <div class="card-header"><?= e($pageTitle) ?></div>
    <div class="card-body">
        <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required">نام کامل</label>
                    <input type="text" name="full_name" class="form-control" required value="<?= e($editUser['full_name']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required">یوزرنیم</label>
                    <input type="text" name="username" class="form-control" required value="<?= e($editUser['username']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">ایمیل</label>
                    <input type="email" name="email" class="form-control" value="<?= e($editUser['email']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">تلفن</label>
                    <input type="text" name="phone" class="form-control" value="<?= e($editUser['phone']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label <?= $id ? '' : 'required' ?>">رمز عبور <?= $id ? '(در صورت خالی‌بودن تغییر نمی‌کند)' : '' ?></label>
                    <input type="password" name="password" class="form-control" <?= $id ? '' : 'required' ?>>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required">نقش</label>
                    <select name="role_id" class="form-select" required>
                        <option value="">-- انتخاب کنید --</option>
                        <?php foreach ($availableRoles as $r): ?>
                        <option value="<?= (int)$r['id'] ?>" <?= (int)$editUser['role_id'] === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name_fa']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php if ($user['role_key'] === 'super_admin'): ?>
            <div class="mb-3">
                <label class="form-label">شرکت (برای مدیر شرکت و سطوح پایین‌تر)</label>
                <select name="company_id" class="form-select">
                    <option value="">-- بدون شرکت (فقط سوپر ادمین) --</option>
                    <?php foreach ($companies as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)$editUser['company_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <input type="hidden" name="company_id" value="<?= (int)$user['company_id'] ?>">
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label">دسترسی به پروژه‌ها (برای مدیر پروژه، محاسب و ناظر)</label>
                <div class="border rounded p-2" style="max-height:220px; overflow-y:auto">
                    <?php if (empty($projects)): ?>
                        <span class="text-muted">پروژه‌ای یافت نشد</span>
                    <?php endif; ?>
                    <?php foreach ($projects as $p): ?>
                    <div class="form-check">
                        <input type="checkbox" name="projects[]" value="<?= (int)$p['id'] ?>" class="form-check-input" id="proj<?= (int)$p['id'] ?>"
                            <?= in_array((int)$p['id'], $assignedProjects, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="proj<?= (int)$p['id'] ?>"><?= e($p['company_name']) ?> - <?= e($p['name']) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="is_active" id="is_active" class="form-check-input" <?= $editUser['is_active'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">فعال</label>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> ذخیره</button>
            <a href="list.php" class="btn btn-secondary">انصراف</a>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
