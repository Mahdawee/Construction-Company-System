<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
$pageTitle = 'کارمندان (منابع بشری)';

$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT e.*, c.code AS cur_code, k.code AS kahta_code FROM employees e
        LEFT JOIN currencies c ON c.id = e.salary_currency_id
        LEFT JOIN kahta_accounts k ON k.id = e.kahta_account_id
        WHERE e.project_id = :pid";
$params = [':pid' => $projectId];
if ($search !== '') { $sql .= ' AND (e.full_name LIKE :q OR e.position LIKE :q OR e.tazkira_no LIKE :q)'; $params[':q'] = "%$search%"; }
if ($statusFilter !== '') { $sql .= ' AND e.status = :st'; $params[':st'] = $statusFilter; }
$sql .= ' ORDER BY e.full_name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

$typeLabels = ['permanent' => 'دایمی', 'contract' => 'قراردادی', 'daily_wage' => 'روزمزد'];

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form method="get" class="d-flex gap-1">
            <input type="hidden" name="project_id" value="<?= $projectId ?>">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="نام، سمت، تذکره ..." value="<?= e($search) ?>">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">همه</option>
                <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>فعال</option>
                <option value="terminated" <?= $statusFilter==='terminated'?'selected':'' ?>>برکنارشده</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/attendance/daily.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-calendar-check"></i> حاضری روزانه</a>
            <a href="<?= BASE_URL ?>/leaves/list.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-plane-departure"></i> مرخصی‌ها</a>
            <a href="<?= BASE_URL ?>/export/export.php?module=employees&project_id=<?= $projectId ?>" class="btn btn-sm btn-success"><i class="fa-solid fa-file-excel"></i> اکسل</a>
            <?php if (canEditData($user)): ?>
            <a href="form.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> کارمند جدید</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>نام کامل</th><th>سمت</th><th>بخش</th><th>نوع استخدام</th><th>معاش</th><th>کهاته مرتبط</th><th>وضعیت</th><th>فایل</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php if (empty($employees)): ?>
                <tr><td colspan="10" class="text-center text-muted py-3">کارمندی ثبت نشده است</td></tr>
            <?php endif; ?>
            <?php foreach ($employees as $i => $emp): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($emp['full_name']) ?></td>
                    <td><?= e($emp['position']) ?></td>
                    <td><?= e($emp['department']) ?></td>
                    <td><?= e($typeLabels[$emp['employment_type']] ?? $emp['employment_type']) ?></td>
                    <td><?= formatMoney($emp['salary_amount']) ?> <?= currencyLabel($emp['cur_code']) ?></td>
                    <td><?= $emp['kahta_code'] ? '<span class="badge bg-dark">'.e($emp['kahta_code']).'</span>' : '-' ?></td>
                    <td><?= $emp['status'] === 'active' ? '<span class="badge bg-success">فعال</span>' : '<span class="badge bg-secondary">برکنارشده</span>' ?></td>
                    <td><?php if ($emp['attachment_path']): ?><a href="<?= BASE_URL ?>/files/download.php?module=employees&id=<?= (int)$emp['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-paperclip"></i></a><?php else: ?>-<?php endif; ?></td>
                    <td class="text-nowrap">
                        <a href="<?= BASE_URL ?>/leaves/list.php?project_id=<?= $projectId ?>&employee_id=<?= (int)$emp['id'] ?>" class="btn btn-sm btn-outline-secondary" title="مرخصی‌ها"><i class="fa-solid fa-plane-departure"></i></a>
                        <?php if (canEditData($user)): ?>
                        <a href="form.php?id=<?= (int)$emp['id'] ?>&project_id=<?= $projectId ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (canDeleteData($user)): ?>
                        <form action="delete.php" method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="آیا از حذف این کارمند مطمئن هستید؟"><i class="fa-solid fa-trash"></i></button>
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
