<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
$pageTitle = 'عواید تسلیم شده به صرافی';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM revenue_to_sarafi WHERE project_id = :pid');
$countStmt->execute([':pid' => $projectId]);
$total = (int)$countStmt->fetchColumn();
[$totalPages, $page, $offset] = paginate($total, $perPage, $page);

$stmt = $pdo->prepare("
    SELECT rv.*, c.code AS cur_code
    FROM revenue_to_sarafi rv JOIN currencies c ON c.id = rv.currency_id
    WHERE rv.project_id = :pid ORDER BY rv.entry_date DESC, rv.id DESC LIMIT $perPage OFFSET $offset
");
$stmt->execute([':pid' => $projectId]);
$rows = $stmt->fetchAll();

$sumStmt = $pdo->prepare("SELECT c.code, SUM(rv.amount) AS total FROM revenue_to_sarafi rv JOIN currencies c ON c.id = rv.currency_id WHERE rv.project_id = :pid GROUP BY c.code");
$sumStmt->execute([':pid' => $projectId]);
$sums = ['AFN' => 0, 'USD' => 0];
foreach ($sumStmt->fetchAll() as $r) { $sums[$r['code']] = (float)$r['total']; }

include __DIR__ . '/../includes/header.php';
?>
<div class="row g-2 mb-3">
    <div class="col-md-3 col-6"><div class="stat-card bg-balance py-2"><div class="stat-label">مجموع افغانی تسلیم‌شده</div><div class="stat-value"><?= formatMoney($sums['AFN']) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card bg-balance py-2"><div class="stat-label">مجموع دالر تسلیم‌شده</div><div class="stat-value"><?= formatMoney($sums['USD']) ?></div></div></div>
</div>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>لیست عواید/پول‌های تسلیم‌شده به صرافی</span>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/export/export.php?<?= http_build_query(array_merge($_GET, ['module' => 'revenue'])) ?>" class="btn btn-sm btn-success"><i class="fa-solid fa-file-excel"></i> اکسل</a>
            <?php if (canEditData($user)): ?>
            <a href="form.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> ثبت جدید</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>تاریخ</th><th>تفصیل</th><th>از درک</th><th>تسلیم‌دهنده</th><th>تسلیم‌گیرنده</th><th>موقعیت</th><th>مبلغ</th><th>واحد</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="10" class="text-center text-muted py-3">رکوردی یافت نشد</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['entry_no']) ?: (int)$r['id'] ?></td>
                    <td><?= e($r['entry_date']) ?></td>
                    <td><?= e(mb_strimwidth($r['details'] ?? '', 0, 30, '...')) ?></td>
                    <td><?= e($r['source_desc']) ?></td>
                    <td><?= e($r['submitter']) ?></td>
                    <td><?= e($r['receiver']) ?></td>
                    <td><?= e($r['location']) ?></td>
                    <td class="fw-bold"><?= formatMoney($r['amount']) ?></td>
                    <td><?= currencyLabel($r['cur_code']) ?></td>
                    <td>
                        <?php if (canEditData($user)): ?>
                        <a href="form.php?id=<?= (int)$r['id'] ?>&project_id=<?= $projectId ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (canDeleteData($user)): ?>
                        <form action="delete.php" method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="آیا از حذف این رکورد مطمئن هستید؟"><i class="fa-solid fa-trash"></i></button>
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
