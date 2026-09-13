<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireRole(['super_admin']);
$pdo = getDb();
$pageTitle = 'شرکت‌ها';

$search = trim($_GET['q'] ?? '');
$sql = "SELECT c.*, (SELECT COUNT(*) FROM projects p WHERE p.company_id = c.id) AS project_count FROM companies c";
$params = [];
if ($search !== '') {
    $sql .= " WHERE c.name LIKE :q";
    $params[':q'] = "%$search%";
}
$sql .= " ORDER BY c.name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$companies = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>لیست شرکت‌ها</span>
        <div class="d-flex gap-2">
            <form class="d-flex gap-1" method="get">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="جستجوی نام شرکت" value="<?= e($search) ?>">
                <button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
            <a href="form.php" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> شرکت جدید</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>نام شرکت</th><th>آدرس</th><th>تلفن</th><th>تعداد پروژه‌ها</th><th>وضعیت</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php if (empty($companies)): ?>
                <tr><td colspan="7" class="text-center text-muted py-3">هیچ شرکتی ثبت نشده است</td></tr>
            <?php endif; ?>
            <?php foreach ($companies as $i => $c): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($c['name']) ?></td>
                    <td><?= e($c['address']) ?></td>
                    <td><?= e($c['phone']) ?></td>
                    <td><span class="badge bg-info text-dark"><?= (int)$c['project_count'] ?></span></td>
                    <td><?= $c['is_active'] ? '<span class="badge bg-success">فعال</span>' : '<span class="badge bg-secondary">غیرفعال</span>' ?></td>
                    <td>
                        <a href="form.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                        <form action="delete.php" method="post" class="d-inline" data-confirm-form>
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="آیا از حذف این شرکت مطمئن هستید؟ تمام پروژه‌های آن نیز حذف خواهند شد.">
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
