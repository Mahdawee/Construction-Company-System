<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT mr.*, c1.name_fa AS cur1, c2.name_fa AS cur2, p.name AS project_name, comp.name AS company_name
    FROM money_receipts mr
    JOIN projects p ON p.id = mr.project_id
    JOIN companies comp ON comp.id = p.company_id
    LEFT JOIN currencies c1 ON c1.id = mr.currency_id
    LEFT JOIN currencies c2 ON c2.id = mr.currency2_id
    WHERE mr.id = :id
");
$stmt->execute([':id' => $id]);
$r = $stmt->fetch();
if (!$r || !hasProjectAccess($pdo, $user, (int)$r['project_id'])) {
    die('رسید یافت نشد یا دسترسی ندارید.');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>رسید پول شماره <?= e($r['receipt_no'] ?: $r['id']) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body onload="window.print()">
<div class="container py-4">
    <div class="voucher-box">
        <div class="text-center mb-4">
            <h4><?= e($r['company_name']) ?></h4>
            <h6 class="text-muted">پروژه: <?= e($r['project_name']) ?></h6>
            <h5 class="mt-3">رسید دریافت پول از شرکت صرافی</h5>
        </div>
        <table class="table table-bordered">
            <tr><th style="width:220px">نمبر رسید</th><td><?= e($r['receipt_no'] ?: $r['id']) ?></td>
                <th style="width:220px">تاریخ حصول</th><td><?= e($r['receipt_date']) ?> <?= $r['receipt_date_shamsi'] ? '(' . e($r['receipt_date_shamsi']) . ')' : '' ?></td></tr>
            <tr><th>فرستنده</th><td><?= e($r['sender']) ?></td><th>گیرنده</th><td><?= e($r['receiver']) ?></td></tr>
            <tr><th>نمبر حواله</th><td><?= e($r['transfer_no']) ?></td><th>محل اخذ پول</th><td><?= e($r['source_province']) ?></td></tr>
            <tr><th>برای مصرف در</th><td colspan="3"><?= e($r['purpose']) ?></td></tr>
            <tr><th>مبلغ بنام پروژه</th><td class="fw-bold fs-5"><?= formatMoney($r['amount_project']) ?> <?= e($r['cur1']) ?></td>
                <th>مبلغ جمع صرافی</th><td class="fw-bold"><?= formatMoney($r['amount_sarafi_total']) ?> <?= e($r['cur2']) ?></td></tr>
            <tr><th>تفصیل</th><td colspan="3"><?= nl2br(e($r['details'])) ?></td></tr>
            <tr><th>ملاحظات</th><td colspan="3"><?= nl2br(e($r['notes'])) ?></td></tr>
        </table>
        <div class="row mt-5 text-center">
            <div class="col-4"><div style="border-top:1px solid #333;padding-top:6px">امضای فرستنده</div></div>
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
