<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
$pageTitle = 'مشتریان (کارفرمایان)';

$search = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM customers WHERE project_id = :pid";
$params = [':pid' => $projectId];
if ($search !== '') {
    $sql .= " AND (name LIKE :q1 OR contact_person LIKE :q2 OR phone LIKE :q3 OR id_number LIKE :q4)";
    $params[':q1'] = $params[':q2'] = $params[':q3'] = $params[':q4'] = "%$search%";
}
$sql .= " ORDER BY name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$typeLabels = ['individual' => 'شخص حقیقی', 'company' => 'شرکت/نهاد'];

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form method="get" class="d-flex gap-1">
            <input type="hidden" name="project_id" value="<?= $projectId ?>">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="جستجوی نام، شخص رابط، تلفن ..." value="<?= e($search) ?>">
            <button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/export/export.php?module=customers&project_id=<?= $projectId ?>" class="btn btn-sm btn-success"><i class="fa-solid fa-file-excel"></i> اکسل</a>
            <?php if (canEditData($user)): ?>
            <a href="form.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> مشتری جدید</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>نام</th><th>نوع</th><th>شخص رابط</th><th>تلفن</th><th>شماره تذکره/جواز</th><th>فایل ضمیمه</th><th>وضعیت</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php if (empty($customers)): ?>
                <tr><td colspan="9" class="text-center text-muted py-3">مشتری‌ای ثبت نشده است</td></tr>
            <?php endif; ?>
            <?php foreach ($customers as $i => $c): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($c['name']) ?></td>
                    <td><?= e($typeLabels[$c['customer_type']] ?? $c['customer_type']) ?></td>
                    <td><?= e($c['contact_person']) ?></td>
                    <td><?= e($c['phone']) ?></td>
                    <td><?= e($c['id_number']) ?></td>
                    <td><?php if ($c['attachment_path']): ?><a href="<?= BASE_URL ?>/files/download.php?module=customers&id=<?= (int)$c['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-paperclip"></i></a><?php else: ?>-<?php endif; ?></td>
                    <td><?= $c['is_active'] ? '<span class="badge bg-success">فعال</span>' : '<span class="badge bg-secondary">غیرفعال</span>' ?></td>
                    <td class="text-nowrap">
                        <?php if (canEditData($user)): ?>
                        <a href="form.php?id=<?= (int)$c['id'] ?>&project_id=<?= $projectId ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (canDeleteData($user)): ?>
                        <form action="delete.php" method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="آیا از حذف این مشتری مطمئن هستید؟"><i class="fa-solid fa-trash"></i></button>
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
