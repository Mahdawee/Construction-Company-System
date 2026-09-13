<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
$pageTitle = 'مصارف و پرداخت‌های پروژه';

$search = trim($_GET['q'] ?? '');
$dateFrom = sanitizeDate($_GET['from'] ?? null);
$dateTo = sanitizeDate($_GET['to'] ?? null);
$categoryId = (int)($_GET['category_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = ['ex.project_id = :pid'];
$params = [':pid' => $projectId];
if ($search !== '') { $where[] = '(ex.payer LIKE :q1 OR ex.details LIKE :q2 OR ex.bill_no LIKE :q3)'; $params[':q1'] = $params[':q2'] = $params[':q3'] = "%$search%"; }
if ($dateFrom) { $where[] = 'ex.expense_date >= :from'; $params[':from'] = $dateFrom; }
if ($dateTo) { $where[] = 'ex.expense_date <= :to'; $params[':to'] = $dateTo; }
if ($categoryId) { $where[] = 'ex.category_id = :cat'; $params[':cat'] = $categoryId; }
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM expenses ex WHERE $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
[$totalPages, $page, $offset] = paginate($total, $perPage, $page);

$stmt = $pdo->prepare("
    SELECT ex.*, c.code AS cur_code, k.code AS kahta_code, k.name AS kahta_name, cat.name_fa AS category_name
    FROM expenses ex
    LEFT JOIN currencies c ON c.id = ex.currency_id
    LEFT JOIN kahta_accounts k ON k.id = ex.kahta_account_id
    LEFT JOIN expense_categories cat ON cat.id = ex.category_id
    WHERE $whereSql ORDER BY ex.expense_date DESC, ex.id DESC LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$sumStmt = $pdo->prepare("SELECT c.code, SUM(ex.amount) AS total FROM expenses ex JOIN currencies c ON c.id = ex.currency_id WHERE $whereSql GROUP BY c.code");
$sumStmt->execute($params);
$sums = ['AFN' => 0, 'USD' => 0];
foreach ($sumStmt->fetchAll() as $row) { $sums[$row['code']] = (float)$row['total']; }

$categories = $pdo->query('SELECT * FROM expense_categories ORDER BY name_fa')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <input type="hidden" name="project_id" value="<?= $projectId ?>">
            <div class="col-md-3">
                <label class="form-label mb-1">جستجو</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="پرداخت‌کننده، تفصیل، نمبر بل ..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">از تاریخ</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">تا تاریخ</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">کتگوری</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="0">همه</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $categoryId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name_fa']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 text-md-end">
                <button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-filter"></i> فیلتر</button>
                <a href="<?= BASE_URL ?>/export/export.php?<?= http_build_query(array_merge($_GET, ['module' => 'expenses'])) ?>" class="btn btn-sm btn-success"><i class="fa-solid fa-file-excel"></i> اکسل</a>
                <?php if (canEditData($user)): ?>
                <a href="form.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> مصرف جدید</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-md-3 col-6"><div class="stat-card bg-expense py-2"><div class="stat-label">مجموع افغانی</div><div class="stat-value"><?= formatMoney($sums['AFN']) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card bg-expense py-2"><div class="stat-label">مجموع دالر</div><div class="stat-value"><?= formatMoney($sums['USD']) ?></div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th>#</th><th>تاریخ</th><th>پرداخت‌کننده</th><th>کهاته</th><th>کتگوری</th><th>نمبر بل</th><th>مبلغ</th><th>واحد</th><th>عملیات</th>
            </tr></thead>
            <tbody>
            <?php if (empty($expenses)): ?>
                <tr><td colspan="9" class="text-center text-muted py-3">مصرفی یافت نشد</td></tr>
            <?php endif; ?>
            <?php foreach ($expenses as $ex): ?>
                <tr>
                    <td><?= e($ex['voucher_no']) ?: (int)$ex['id'] ?></td>
                    <td><?= e($ex['expense_date']) ?></td>
                    <td><?= e($ex['payer']) ?></td>
                    <td><?= $ex['kahta_code'] ? '<span class="badge bg-dark">'.e($ex['kahta_code']).'</span> '.e($ex['kahta_name']) : '-' ?></td>
                    <td><?= e($ex['category_name'] ?? '-') ?></td>
                    <td><?= e($ex['bill_no']) ?></td>
                    <td class="fw-bold"><?= formatMoney($ex['amount']) ?></td>
                    <td><?= currencyLabel($ex['cur_code']) ?></td>
                    <td class="text-nowrap">
                        <a href="print.php?id=<?= (int)$ex['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-print"></i></a>
                        <?php if (canEditData($user)): ?>
                        <a href="form.php?id=<?= (int)$ex['id'] ?>&project_id=<?= $projectId ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (canDeleteData($user)): ?>
                        <form action="delete.php" method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$ex['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="آیا از حذف این مصرف مطمئن هستید؟"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer d-flex justify-content-center gap-1">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="<?= queryWith(['page' => $p]) ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
