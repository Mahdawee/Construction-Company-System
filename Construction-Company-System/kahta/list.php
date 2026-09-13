<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
$pageTitle = 'کهاته‌ها (حساب‌داران پروژه)';

$typeFilter = $_GET['type'] ?? '';
$sql = "SELECT k.*, t.name_fa AS type_name, t.code AS type_code,
        COALESCE((SELECT SUM(a.amount) FROM kahta_advances a WHERE a.kahta_account_id = k.id AND a.currency_id = 1),0) AS adv_afn,
        COALESCE((SELECT SUM(a.amount) FROM kahta_advances a WHERE a.kahta_account_id = k.id AND a.currency_id = 2),0) AS adv_usd,
        COALESCE((SELECT SUM(e.amount) FROM expenses e WHERE e.kahta_account_id = k.id AND e.currency_id = 1),0) AS exp_afn,
        COALESCE((SELECT SUM(e.amount) FROM expenses e WHERE e.kahta_account_id = k.id AND e.currency_id = 2),0) AS exp_usd
        FROM kahta_accounts k JOIN kahta_types t ON t.id = k.kahta_type_id
        WHERE k.project_id = :pid";
$params = [':pid' => $projectId];
if ($typeFilter !== '') { $sql .= " AND t.code = :tc"; $params[':tc'] = $typeFilter; }
$sql .= " ORDER BY t.code, k.code";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$kahtas = $stmt->fetchAll();
$types = $pdo->query('SELECT * FROM kahta_types ORDER BY id')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>لیست کهاته‌ها</span>
        <div class="d-flex gap-2">
            <form method="get" class="d-flex gap-1">
                <input type="hidden" name="project_id" value="<?= $projectId ?>">
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">همه انواع</option>
                    <?php foreach ($types as $t): ?>
                    <option value="<?= e($t['code']) ?>" <?= $typeFilter === $t['code'] ? 'selected' : '' ?>><?= e($t['code']) ?> - <?= e($t['name_fa']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if (canEditData($user)): ?>
            <a href="form.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> کهاته جدید</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th>کد</th><th>نوع</th><th>نام</th><th>تلفن</th>
                <th>دریافتی (AFN)</th><th>دریافتی (USD)</th>
                <th>مصرف‌شده (AFN)</th><th>مصرف‌شده (USD)</th>
                <th>باقیمانده (AFN)</th><th>باقیمانده (USD)</th>
                <th>عملیات</th>
            </tr></thead>
            <tbody>
            <?php if (empty($kahtas)): ?>
                <tr><td colspan="11" class="text-center text-muted py-3">کهاته‌ای ثبت نشده است</td></tr>
            <?php endif; ?>
            <?php foreach ($kahtas as $k): ?>
                <tr>
                    <td><span class="badge bg-dark"><?= e($k['code']) ?></span></td>
                    <td><?= e($k['type_name']) ?></td>
                    <td><?= e($k['name']) ?></td>
                    <td><?= e($k['phone']) ?></td>
                    <td><?= formatMoney($k['adv_afn']) ?></td>
                    <td><?= formatMoney($k['adv_usd']) ?></td>
                    <td><?= formatMoney($k['exp_afn']) ?></td>
                    <td><?= formatMoney($k['exp_usd']) ?></td>
                    <td class="fw-bold"><?= formatMoney($k['adv_afn'] - $k['exp_afn']) ?></td>
                    <td class="fw-bold"><?= formatMoney($k['adv_usd'] - $k['exp_usd']) ?></td>
                    <td>
                        <?php if (canEditData($user)): ?>
                        <a href="form.php?id=<?= (int)$k['id'] ?>&project_id=<?= $projectId ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (canDeleteData($user)): ?>
                        <form action="delete.php" method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="آیا از حذف این کهاته مطمئن هستید؟"><i class="fa-solid fa-trash"></i></button>
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
