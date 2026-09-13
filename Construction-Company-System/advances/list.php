<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
$pageTitle = 'پیش‌پرداخت به کهاته‌ها';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM kahta_advances WHERE project_id = :pid');
$countStmt->execute([':pid' => $projectId]);
$total = (int)$countStmt->fetchColumn();
[$totalPages, $page, $offset] = paginate($total, $perPage, $page);

$stmt = $pdo->prepare("
    SELECT a.*, c.code AS cur_code, k.code AS kahta_code, k.name AS kahta_name
    FROM kahta_advances a
    JOIN currencies c ON c.id = a.currency_id
    JOIN kahta_accounts k ON k.id = a.kahta_account_id
    WHERE a.project_id = :pid ORDER BY a.advance_date DESC, a.id DESC LIMIT $perPage OFFSET $offset
");
$stmt->execute([':pid' => $projectId]);
$advances = $stmt->fetchAll();

$sourceLabels = ['internal' => 'منابع داخلی', 'site' => 'ساحه', 'center' => 'مرکز'];

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>لیست پیش‌پرداخت‌ها (حواله به کهاته‌ها از منابع داخلی/ساحه/مرکز)</span>
        <?php if (canEditData($user)): ?>
        <a href="form.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> پیش‌پرداخت جدید</a>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>تاریخ</th><th>کهاته</th><th>منبع</th><th>مبلغ</th><th>واحد</th><th>ملاحظات</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php if (empty($advances)): ?>
                <tr><td colspan="8" class="text-center text-muted py-3">پیش‌پرداختی ثبت نشده است</td></tr>
            <?php endif; ?>
            <?php foreach ($advances as $a): ?>
                <tr>
                    <td><?= (int)$a['id'] ?></td>
                    <td><?= e($a['advance_date']) ?></td>
                    <td><span class="badge bg-dark"><?= e($a['kahta_code']) ?></span> <?= e($a['kahta_name']) ?></td>
                    <td><?= e($sourceLabels[$a['source']] ?? $a['source']) ?></td>
                    <td class="fw-bold"><?= formatMoney($a['amount']) ?></td>
                    <td><?= currencyLabel($a['cur_code']) ?></td>
                    <td><?= e(mb_strimwidth($a['notes'] ?? '', 0, 30, '...')) ?></td>
                    <td>
                        <?php if (canEditData($user)): ?>
                        <a href="form.php?id=<?= (int)$a['id'] ?>&project_id=<?= $projectId ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (canDeleteData($user)): ?>
                        <form action="delete.php" method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="آیا از حذف این پیش‌پرداخت مطمئن هستید؟"><i class="fa-solid fa-trash"></i></button>
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
