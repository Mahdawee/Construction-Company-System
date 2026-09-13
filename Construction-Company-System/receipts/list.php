<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
$pageTitle = 'رسیدات پول';

$search = trim($_GET['q'] ?? '');
$dateFrom = sanitizeDate($_GET['from'] ?? null);
$dateTo = sanitizeDate($_GET['to'] ?? null);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = ['mr.project_id = :pid'];
$params = [':pid' => $projectId];
if ($search !== '') { $where[] = '(mr.sender LIKE :q OR mr.receiver LIKE :q OR mr.details LIKE :q OR mr.transfer_no LIKE :q)'; $params[':q'] = "%$search%"; }
if ($dateFrom) { $where[] = 'mr.receipt_date >= :from'; $params[':from'] = $dateFrom; }
if ($dateTo) { $where[] = 'mr.receipt_date <= :to'; $params[':to'] = $dateTo; }
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM money_receipts mr WHERE $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
[$totalPages, $page, $offset] = paginate($total, $perPage, $page);

$stmt = $pdo->prepare("
    SELECT mr.*, c1.code AS cur1, c2.code AS cur2
    FROM money_receipts mr
    LEFT JOIN currencies c1 ON c1.id = mr.currency_id
    LEFT JOIN currencies c2 ON c2.id = mr.currency2_id
    WHERE $whereSql ORDER BY mr.receipt_date DESC, mr.id DESC LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$receipts = $stmt->fetchAll();

$sumStmt = $pdo->prepare("SELECT c.code, SUM(mr.amount_project) AS total FROM money_receipts mr JOIN currencies c ON c.id = mr.currency_id WHERE $whereSql GROUP BY c.code");
$sumStmt->execute($params);
$sums = ['AFN' => 0, 'USD' => 0];
foreach ($sumStmt->fetchAll() as $r) { $sums[$r['code']] = (float)$r['total']; }

include __DIR__ . '/../includes/header.php';
?>
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <input type="hidden" name="project_id" value="<?= $projectId ?>">
            <div class="col-md-3">
                <label class="form-label mb-1">جستجو</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="فرستنده، گیرنده، حواله ..." value="<?= e($search) ?>">
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
                <button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-filter"></i> فیلتر</button>
                <a href="list.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-outline-secondary">حذف فیلتر</a>
            </div>
            <div class="col-md-3 text-md-end">
                <a href="<?= BASE_URL ?>/export/export.php?<?= http_build_query(array_merge($_GET, ['module' => 'receipts'])) ?>" class="btn btn-sm btn-success"><i class="fa-solid fa-file-excel"></i> خروجی اکسل</a>
                <?php if (canEditData($user)): ?>
                <a href="form.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> رسید جدید</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-md-3 col-6"><div class="stat-card bg-afn py-2"><div class="stat-label">مجموع افغانی</div><div class="stat-value"><?= formatMoney($sums['AFN']) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card bg-usd py-2"><div class="stat-label">مجموع دالر</div><div class="stat-value"><?= formatMoney($sums['USD']) ?></div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th>#</th><th>تاریخ حصول</th><th>فرستنده</th><th>گیرنده</th><th>نمبر حواله</th>
                <th>محل اخذ</th><th>مبلغ پروژه</th><th>واحد</th><th>جمع صرافی</th><th>ملاحظات</th><th>عملیات</th>
            </tr></thead>
            <tbody>
            <?php if (empty($receipts)): ?>
                <tr><td colspan="11" class="text-center text-muted py-3">رسیدی یافت نشد</td></tr>
            <?php endif; ?>
            <?php foreach ($receipts as $r): ?>
                <tr>
                    <td><?= e($r['receipt_no']) ?: (int)$r['id'] ?></td>
                    <td><?= e($r['receipt_date']) ?></td>
                    <td><?= e($r['sender']) ?></td>
                    <td><?= e($r['receiver']) ?></td>
                    <td><?= e($r['transfer_no']) ?></td>
                    <td><?= e($r['source_province']) ?></td>
                    <td class="fw-bold"><?= formatMoney($r['amount_project']) ?></td>
                    <td><?= currencyLabel($r['cur1']) ?></td>
                    <td><?= formatMoney($r['amount_sarafi_total']) ?> <?= currencyLabel($r['cur2']) ?></td>
                    <td><?= e(mb_strimwidth($r['notes'] ?? '', 0, 30, '...')) ?></td>
                    <td class="text-nowrap">
                        <a href="print.php?id=<?= (int)$r['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-print"></i></a>
                        <?php if (canEditData($user)): ?>
                        <a href="form.php?id=<?= (int)$r['id'] ?>&project_id=<?= $projectId ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (canDeleteData($user)): ?>
                        <form action="delete.php" method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="آیا از حذف این رسید مطمئن هستید؟"><i class="fa-solid fa-trash"></i></button>
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
