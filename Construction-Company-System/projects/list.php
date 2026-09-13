<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireRole(['super_admin', 'company_manager']);
$pdo = getDb();
$pageTitle = 'پروژه‌ها';

$sql = "SELECT p.*, c.name AS company_name,
        (SELECT COUNT(*) FROM kahta_accounts k WHERE k.project_id = p.id) AS kahta_count
        FROM projects p JOIN companies c ON c.id = p.company_id";
$params = [];
if ($user['role_key'] === 'company_manager') {
    $sql .= " WHERE p.company_id = :cid";
    $params[':cid'] = $user['company_id'];
}
$sql .= " ORDER BY c.name, p.name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll();

$statusLabels = ['active' => ['فعال', 'success'], 'completed' => ['تکمیل‌شده', 'primary'], 'suspended' => ['متوقف‌شده', 'secondary']];

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>لیست پروژه‌ها</span>
        <a href="form.php" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> پروژه جدید</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>شرکت</th><th>نام پروژه</th><th>کد</th><th>موقعیت</th><th>وضعیت</th><th>کهاته‌ها</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php if (empty($projects)): ?>
                <tr><td colspan="8" class="text-center text-muted py-3">پروژه‌ای ثبت نشده است</td></tr>
            <?php endif; ?>
            <?php foreach ($projects as $i => $p): [$label, $color] = $statusLabels[$p['status']] ?? ['-', 'secondary']; ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($p['company_name']) ?></td>
                    <td><?= e($p['name']) ?></td>
                    <td><?= e($p['code']) ?></td>
                    <td><?= e($p['location']) ?></td>
                    <td><span class="badge bg-<?= $color ?>"><?= $label ?></span></td>
                    <td><span class="badge bg-info text-dark"><?= (int)$p['kahta_count'] ?></span></td>
                    <td>
                        <a href="form.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                        <form action="delete.php" method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="آیا از حذف این پروژه مطمئن هستید؟ تمام اطلاعات مالی آن نیز حذف خواهد شد.">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
