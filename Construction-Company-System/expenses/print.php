<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT ex.*, c.name_fa AS cur_name, k.code AS kahta_code, k.name AS kahta_name, cat.name_fa AS category_name,
           p.name AS project_name, comp.name AS company_name
    FROM expenses ex
    JOIN projects p ON p.id = ex.project_id
    JOIN companies comp ON comp.id = p.company_id
    LEFT JOIN currencies c ON c.id = ex.currency_id
    LEFT JOIN kahta_accounts k ON k.id = ex.kahta_account_id
    LEFT JOIN expense_categories cat ON cat.id = ex.category_id
    WHERE ex.id = :id
");
$stmt->execute([':id' => $id]);
$ex = $stmt->fetch();
if (!$ex || !hasProjectAccess($pdo, $user, (int)$ex['project_id'])) {
    die('رکورد یافت نشد یا دسترسی ندارید.');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>بل مصرف شماره <?= e($ex['voucher_no'] ?: $ex['id']) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body onload="window.print()">
<div class="container py-4">
    <div class="voucher-box">
        <div class="text-center mb-4">
            <h4><?= e($ex['company_name']) ?></h4>
            <h6 class="text-muted">پروژه: <?= e($ex['project_name']) ?></h6>
            <h5 class="mt-3">بل / سند مصرف</h5>
        </div>
        <table class="table table-bordered">
            <tr><th style="width:220px">نمبر بل/سند</th><td><?= e($ex['voucher_no'] ?: $ex['id']) ?></td>
                <th style="width:220px">تاریخ</th><td><?= e($ex['expense_date']) ?> <?= $ex['expense_date_shamsi'] ? '(' . e($ex['expense_date_shamsi']) . ')' : '' ?></td></tr>
            <tr><th>پرداخت کننده</th><td><?= e($ex['payer']) ?></td><th>گیرنده / کهاته</th><td><?= $ex['kahta_code'] ? e($ex['kahta_code']).' - '.e($ex['kahta_name']) : '-' ?></td></tr>
            <tr><th>کتگوری</th><td><?= e($ex['category_name'] ?? '-') ?></td><th>پرداخت از</th><td><?= e($ex['paid_from']) ?></td></tr>
            <tr><th>مبلغ</th><td colspan="3" class="fw-bold fs-5"><?= formatMoney($ex['amount']) ?> <?= e($ex['cur_name']) ?></td></tr>
            <tr><th>تفصیل</th><td colspan="3"><?= nl2br(e($ex['details'])) ?></td></tr>
            <tr><th>ملاحظات</th><td colspan="3"><?= nl2br(e($ex['notes'])) ?></td></tr>
        </table>
        <div class="row mt-5 text-center">
            <div class="col-4"><div style="border-top:1px solid #333;padding-top:6px">امضای پرداخت‌کننده</div></div>
            <div class="col-4"><div style="border-top:1px solid #333;padding-top:6px">امضای گیرنده</div></div>
            <div class="col-4"><div style="border-top:1px solid #333;padding-top:6px">امضای مسئول پروژه</div></div>
        </div>
    </div>
    <div class="text-center mt-3 no-print">
        <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> چاپ</button>
    </div>
</div>
</body>
</html>
