<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireRole(['super_admin', 'company_manager']);
$pdo = getDb();
$pageTitle = 'شرکت‌های صرافی';

if ($user['role_key'] === 'super_admin') {
    $companies = $pdo->query('SELECT id, name FROM companies ORDER BY name')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT id, name FROM companies WHERE id = :id');
    $stmt->execute([':id' => $user['company_id']]);
    $companies = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $companyId = $user['role_key'] === 'super_admin' ? (int)($_POST['company_id'] ?? 0) : (int)$user['company_id'];
        $contact = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($name !== '' && $companyId) {
            $stmt = $pdo->prepare('INSERT INTO sarafi_companies (company_id, name, contact_person, phone) VALUES (:cid,:n,:c,:p)');
            $stmt->execute([':cid' => $companyId, ':n' => $name, ':c' => $contact, ':p' => $phone]);
            auditLog('create', 'sarafi_companies', (int)$pdo->lastInsertId(), "افزودن شرکت صرافی: $name");
            flash('success', 'شرکت صرافی اضافه شد.');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM sarafi_companies WHERE id = :id')->execute([':id' => $id]);
        auditLog('delete', 'sarafi_companies', $id, 'حذف شرکت صرافی');
        flash('success', 'حذف شد.');
    }
    redirect('sarafi.php');
}

$sql = "SELECT s.*, c.name AS company_name FROM sarafi_companies s JOIN companies c ON c.id = s.company_id";
$params = [];
if ($user['role_key'] === 'company_manager') { $sql .= ' WHERE s.company_id = :cid'; $params[':cid'] = $user['company_id']; }
$sql .= ' ORDER BY s.name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">افزودن شرکت صرافی جدید</div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="add">
                    <?php if ($user['role_key'] === 'super_admin'): ?>
                    <div class="mb-2">
                        <label class="form-label">شرکت</label>
                        <select name="company_id" class="form-select" required>
                            <?php foreach ($companies as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="mb-2">
                        <label class="form-label">نام شرکت صرافی</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">شخص رابط</label>
                        <input type="text" name="contact_person" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">تلفن</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <button class="btn btn-primary"><i class="fa-solid fa-plus"></i> افزودن</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">لیست شرکت‌های صرافی</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>نام</th><th>شرکت</th><th>شخص رابط</th><th>تلفن</th><th>عملیات</th></tr></thead>
                    <tbody>
                    <?php foreach ($list as $s): ?>
                        <tr>
                            <td><?= e($s['name']) ?></td>
                            <td><?= e($s['company_name']) ?></td>
                            <td><?= e($s['contact_person']) ?></td>
                            <td><?= e($s['phone']) ?></td>
                            <td>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" data-confirm="حذف این شرکت صرافی؟"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
