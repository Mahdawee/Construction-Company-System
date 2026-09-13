<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireRole(['super_admin', 'company_manager']);
$pdo = getDb();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$project = [
    'company_id' => $user['company_id'] ?? '', 'sarafi_company_id' => '', 'name' => '', 'code' => '',
    'location' => '', 'start_date' => '', 'end_date' => '', 'status' => 'active', 'description' => '',
];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $project = $stmt->fetch();
    if (!$project || ($user['role_key'] === 'company_manager' && (int)$project['company_id'] !== (int)$user['company_id'])) {
        flash('danger', 'پروژه یافت نشد یا دسترسی ندارید.');
        redirect('list.php');
    }
}
$pageTitle = $id ? 'ویرایش پروژه' : 'پروژه جدید';
$errors = [];

// شرکت‌های قابل انتخاب
if ($user['role_key'] === 'super_admin') {
    $companies = $pdo->query('SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT id, name FROM companies WHERE id = :id');
    $stmt->execute([':id' => $user['company_id']]);
    $companies = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $companyId = $user['role_key'] === 'super_admin' ? (int)($_POST['company_id'] ?? 0) : (int)$user['company_id'];
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $startDate = sanitizeDate($_POST['start_date'] ?? null);
    $endDate = sanitizeDate($_POST['end_date'] ?? null);
    $status = in_array($_POST['status'] ?? '', ['active', 'completed', 'suspended'], true) ? $_POST['status'] : 'active';
    $description = trim($_POST['description'] ?? '');
    $sarafiId = (int)($_POST['sarafi_company_id'] ?? 0) ?: null;

    if ($name === '') { $errors[] = 'نام پروژه الزامی است.'; }
    if (!$companyId) { $errors[] = 'انتخاب شرکت الزامی است.'; }

    if (empty($errors)) {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE projects SET company_id=:cid, sarafi_company_id=:sid, name=:name, code=:code, location=:loc, start_date=:sd, end_date=:ed, status=:st, description=:desc WHERE id=:id');
            $stmt->execute([':cid' => $companyId, ':sid' => $sarafiId, ':name' => $name, ':code' => $code, ':loc' => $location, ':sd' => $startDate, ':ed' => $endDate, ':st' => $status, ':desc' => $description, ':id' => $id]);
            auditLog('update', 'projects', $id, "ویرایش پروژه: $name");
            flash('success', 'پروژه ویرایش شد.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO projects (company_id, sarafi_company_id, name, code, location, start_date, end_date, status, description) VALUES (:cid,:sid,:name,:code,:loc,:sd,:ed,:st,:desc)');
            $stmt->execute([':cid' => $companyId, ':sid' => $sarafiId, ':name' => $name, ':code' => $code, ':loc' => $location, ':sd' => $startDate, ':ed' => $endDate, ':st' => $status, ':desc' => $description]);
            $newId = (int)$pdo->lastInsertId();
            auditLog('create', 'projects', $newId, "ایجاد پروژه جدید: $name");
            flash('success', 'پروژه جدید ایجاد شد.');
        }
        redirect('list.php');
    }
    $project = compact('companyId', 'sarafiId', 'name', 'code', 'location', 'startDate', 'endDate', 'status', 'description');
    $project['company_id'] = $companyId; $project['sarafi_company_id'] = $sarafiId;
    $project['start_date'] = $startDate; $project['end_date'] = $endDate;
}

$sarafiCompanies = $pdo->query('SELECT id, name, company_id FROM sarafi_companies ORDER BY name')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:720px">
    <div class="card-header"><?= e($pageTitle) ?></div>
    <div class="card-body">
        <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required">شرکت</label>
                    <?php if ($user['role_key'] === 'super_admin'): ?>
                    <select name="company_id" class="form-select" required>
                        <?php foreach ($companies as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)$project['company_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="text" class="form-control" value="<?= e($companies[0]['name'] ?? '') ?>" disabled>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">شرکت صرافی طرف مقابل</label>
                    <select name="sarafi_company_id" class="form-select">
                        <option value="">-- انتخاب نشده --</option>
                        <?php foreach ($sarafiCompanies as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= (int)$project['sarafi_company_id'] === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label required">نام پروژه</label>
                    <input type="text" name="name" class="form-control" required value="<?= e($project['name']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">کد پروژه</label>
                    <input type="text" name="code" class="form-control" value="<?= e($project['code']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">موقعیت</label>
                    <input type="text" name="location" class="form-control" value="<?= e($project['location']) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">تاریخ آغاز</label>
                    <input type="date" name="start_date" class="form-control" value="<?= e($project['start_date']) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">تاریخ ختم</label>
                    <input type="date" name="end_date" class="form-control" value="<?= e($project['end_date']) ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">وضعیت</label>
                <select name="status" class="form-select">
                    <option value="active" <?= $project['status'] === 'active' ? 'selected' : '' ?>>فعال</option>
                    <option value="completed" <?= $project['status'] === 'completed' ? 'selected' : '' ?>>تکمیل‌شده</option>
                    <option value="suspended" <?= $project['status'] === 'suspended' ? 'selected' : '' ?>>متوقف‌شده</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">توضیحات</label>
                <textarea name="description" class="form-control" rows="3"><?= e($project['description']) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> ذخیره</button>
            <a href="list.php" class="btn btn-secondary">انصراف</a>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
