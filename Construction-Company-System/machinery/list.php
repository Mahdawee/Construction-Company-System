<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
$pageTitle = 'ماشین‌آلات';

$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT m.*, c.code AS cur_code FROM machinery m LEFT JOIN currencies c ON c.id = m.currency_id WHERE m.project_id = :pid";
$params = [':pid' => $projectId];
if ($search !== '') {
    $sql .= " AND (m.name LIKE :q OR m.machine_type LIKE :q OR m.plate_or_serial_no LIKE :q OR m.model LIKE :q)";
    $params[':q'] = "%$search%";
}
if ($statusFilter !== '') { $sql .= ' AND m.status = :st'; $params[':st'] = $statusFilter; }
$sql .= ' ORDER BY m.name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$machines = $stmt->fetchAll();

$ownershipLabels = ['owned' => 'ملکیت شرکت', 'rented' => 'کرایی'];
$statusLabels = ['active' => ['فعال', 'success'], 'maintenance' => ['تعمیراتی', 'warning'], 'inactive' => ['غیرفعال', 'secondary']];

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form method="get" class="d-flex gap-1 flex-wrap">
            <input type="hidden" name="project_id" value="<?= $projectId ?>">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="جستجوی نام، نوع، شماره پلیت/سریال ..." value="<?= e($search) ?>">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">همه وضعیت‌ها</option>
                <?php foreach ($statusLabels as $key => [$label, $color]): ?>
                <option value="<?= $key ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/export/export.php?module=machinery&project_id=<?= $projectId ?>" class="btn btn-sm btn-success"><i class="fa-solid fa-file-excel"></i> اکسل</a>
            <?php if (canEditData($user)): ?>
            <a href="form.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> ماشین جدید</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>نام</th><th>نوع</th><th>شماره پلیت/سریال</th><th>مالکیت</th><th>نرخ روزانه</th><th>وضعیت</th><th>فایل ضمیمه</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php if (empty($machines)): ?>
                <tr><td colspan="9" class="text-center text-muted py-3">ماشینی ثبت نشده است</td></tr>
            <?php endif; ?>
            <?php foreach ($machines as $i => $m): [$label, $color] = $statusLabels[$m['status']] ?? ['-', 'secondary']; ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($m['name']) ?></td>
                    <td><?= e($m['machine_type']) ?></td>
                    <td><?= e($m['plate_or_serial_no']) ?></td>
                    <td><?= e($ownershipLabels[$m['ownership_type']] ?? $m['ownership_type']) ?><?= $m['owner_name'] ? ' - ' . e($m['owner_name']) : '' ?></td>
                    <td><?= $m['daily_rate'] !== null ? formatMoney($m['daily_rate']) . ' ' . currencyLabel($m['cur_code']) : '-' ?></td>
                    <td><span class="badge bg-<?= $color ?>"><?= $label ?></span></td>
                    <td><?php if ($m['attachment_path']): ?><a href="<?= BASE_URL ?>/files/download.php?module=machinery&id=<?= (int)$m['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-paperclip"></i></a><?php else: ?>-<?php endif; ?></td>
                    <td class="text-nowrap">
                        <?php if (canEditData($user)): ?>
                        <a href="form.php?id=<?= (int)$m['id'] ?>&project_id=<?= $projectId ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (canDeleteData($user)): ?>
                        <form action="delete.php" method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="آیا از حذف این ماشین مطمئن هستید؟"><i class="fa-solid fa-trash"></i></button>
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
