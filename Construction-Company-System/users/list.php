<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireRole(['super_admin', 'company_manager']);
$pdo = getDb();
$pageTitle = 'کاربران';

$sql = "SELECT u.*, r.name_fa AS role_name, r.role_key, c.name AS company_name FROM users u
        JOIN roles r ON r.id = u.role_id LEFT JOIN companies c ON c.id = u.company_id";
$params = [];
if ($user['role_key'] === 'company_manager') {
    $sql .= " WHERE (u.company_id = :cid OR u.id = :uid)";
    $params[':cid'] = $user['company_id'];
    $params[':uid'] = $user['id'];
}
$sql .= " ORDER BY u.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>لیست کاربران</span>
        <a href="form.php" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> کاربر جدید</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>نام کامل</th><th>یوزرنیم</th><th>نقش</th><th>شرکت</th><th>وضعیت</th><th>آخرین ورود</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($users as $i => $u): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($u['full_name']) ?></td>
                    <td><?= e($u['username']) ?></td>
                    <td><span class="badge bg-primary badge-role"><?= e($u['role_name']) ?></span></td>
                    <td><?= e($u['company_name'] ?? '-') ?></td>
                    <td><?= $u['is_active'] ? '<span class="badge bg-success">فعال</span>' : '<span class="badge bg-secondary">غیرفعال</span>' ?></td>
                    <td><?= $u['last_login'] ? e($u['last_login']) : '-' ?></td>
                    <td>
                        <a href="form.php?id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                        <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                        <form action="delete.php" method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="آیا از حذف این کاربر مطمئن هستید؟"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
