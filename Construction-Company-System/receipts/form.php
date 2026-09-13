<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
if (!canEditData($user)) { http_response_code(403); include __DIR__ . '/../403.php'; exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$r = [
    'receipt_no' => '', 'whatsapp_date' => '', 'receipt_date' => date('Y-m-d'), 'receipt_date_shamsi' => '',
    'details' => '', 'sender' => '', 'receiver' => '', 'transfer_no' => '', 'source_province' => '', 'purpose' => '',
    'amount_project' => '', 'currency_id' => 1, 'amount_sarafi_total' => '', 'currency2_id' => 2, 'notes' => '',
];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM money_receipts WHERE id = :id AND project_id = :pid');
    $stmt->execute([':id' => $id, ':pid' => $projectId]);
    $r = $stmt->fetch();
    if (!$r) { flash('danger', 'رسید یافت نشد.'); redirect('list.php?project_id=' . $projectId); }
}
$pageTitle = $id ? 'ویرایش رسید پول' : 'رسید پول جدید';
$errors = [];
$currencies = $pdo->query('SELECT * FROM currencies ORDER BY id')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $receiptNo = trim($_POST['receipt_no'] ?? '');
    $whatsappDate = sanitizeDate($_POST['whatsapp_date'] ?? null);
    $receiptDate = sanitizeDate($_POST['receipt_date'] ?? null);
    $receiptDateShamsi = trim($_POST['receipt_date_shamsi'] ?? '');
    $details = trim($_POST['details'] ?? '');
    $sender = trim($_POST['sender'] ?? '');
    $receiver = trim($_POST['receiver'] ?? '');
    $transferNo = trim($_POST['transfer_no'] ?? '');
    $sourceProvince = trim($_POST['source_province'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $amountProject = (float)str_replace(',', '', $_POST['amount_project'] ?? 0);
    $currencyId = (int)($_POST['currency_id'] ?? 1);
    $amountSarafi = (float)str_replace(',', '', $_POST['amount_sarafi_total'] ?? 0);
    $currency2Id = (int)($_POST['currency2_id'] ?? 0) ?: null;
    $notes = trim($_POST['notes'] ?? '');

    if (!$receiptDate) { $errors[] = 'تاریخ حصول الزامی و باید معتبر باشد.'; }
    if ($amountProject <= 0) { $errors[] = 'مبلغ بنام پروژه باید بزرگتر از صفر باشد.'; }

    if (empty($errors)) {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE money_receipts SET receipt_no=:rn, whatsapp_date=:wd, receipt_date=:rd, receipt_date_shamsi=:rds, details=:det, sender=:sen, receiver=:rec, transfer_no=:tn, source_province=:sp, purpose=:pur, amount_project=:amt, currency_id=:cur, amount_sarafi_total=:ast, currency2_id=:cur2, notes=:notes WHERE id=:id AND project_id=:pid");
            $stmt->execute([':rn'=>$receiptNo, ':wd'=>$whatsappDate, ':rd'=>$receiptDate, ':rds'=>$receiptDateShamsi, ':det'=>$details, ':sen'=>$sender, ':rec'=>$receiver, ':tn'=>$transferNo, ':sp'=>$sourceProvince, ':pur'=>$purpose, ':amt'=>$amountProject, ':cur'=>$currencyId, ':ast'=>$amountSarafi, ':cur2'=>$currency2Id, ':notes'=>$notes, ':id'=>$id, ':pid'=>$projectId]);
            auditLog('update', 'money_receipts', $id, 'ویرایش رسید پول');
            flash('success', 'رسید ویرایش شد.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO money_receipts (project_id, receipt_no, whatsapp_date, receipt_date, receipt_date_shamsi, details, sender, receiver, transfer_no, source_province, purpose, amount_project, currency_id, amount_sarafi_total, currency2_id, notes, created_by) VALUES (:pid,:rn,:wd,:rd,:rds,:det,:sen,:rec,:tn,:sp,:pur,:amt,:cur,:ast,:cur2,:notes,:cb)");
            $stmt->execute([':pid'=>$projectId, ':rn'=>$receiptNo, ':wd'=>$whatsappDate, ':rd'=>$receiptDate, ':rds'=>$receiptDateShamsi, ':det'=>$details, ':sen'=>$sender, ':rec'=>$receiver, ':tn'=>$transferNo, ':sp'=>$sourceProvince, ':pur'=>$purpose, ':amt'=>$amountProject, ':cur'=>$currencyId, ':ast'=>$amountSarafi, ':cur2'=>$currency2Id, ':notes'=>$notes, ':cb'=>$user['id']]);
            $newId = (int)$pdo->lastInsertId();
            auditLog('create', 'money_receipts', $newId, 'ثبت رسید پول جدید');
            flash('success', 'رسید جدید ثبت شد.');
        }
        redirect('list.php?project_id=' . $projectId);
    }
    $r = compact('receiptNo','whatsappDate','receiptDate','receiptDateShamsi','details','sender','receiver','transferNo','sourceProvince','purpose','amountProject','currencyId','amountSarafi','currency2Id','notes');
    $r['receipt_no']=$receiptNo; $r['whatsapp_date']=$whatsappDate; $r['receipt_date']=$receiptDate; $r['receipt_date_shamsi']=$receiptDateShamsi;
    $r['source_province']=$sourceProvince; $r['amount_project']=$amountProject; $r['currency_id']=$currencyId; $r['amount_sarafi_total']=$amountSarafi; $r['currency2_id']=$currency2Id;
    $r['transfer_no']=$transferNo;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:820px">
    <div class="card-header"><?= e($pageTitle) ?></div>
    <div class="card-body">
        <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">نمبر دیتابیس/سند</label>
                    <input type="text" name="receipt_no" class="form-control" value="<?= e($r['receipt_no']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label required">تاریخ حصول</label>
                    <input type="date" name="receipt_date" class="form-control" required value="<?= e($r['receipt_date']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">تاریخ حصول (هجری شمسی)</label>
                    <input type="text" name="receipt_date_shamsi" class="form-control" placeholder="مثال: ۱۴۰۵/۰۵/۱۱" value="<?= e($r['receipt_date_shamsi']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">تاریخ گزارش واتساپ</label>
                    <input type="date" name="whatsapp_date" class="form-control" value="<?= e($r['whatsapp_date']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">فرستنده</label>
                    <input type="text" name="sender" class="form-control" value="<?= e($r['sender']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">گیرنده</label>
                    <input type="text" name="receiver" class="form-control" value="<?= e($r['receiver']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">نمبر حواله</label>
                    <input type="text" name="transfer_no" class="form-control" value="<?= e($r['transfer_no']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">محل اخذ پول (ولایت)</label>
                    <input type="text" name="source_province" class="form-control" value="<?= e($r['source_province']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">برای مصرف و خرید در</label>
                    <input type="text" name="purpose" class="form-control" value="<?= e($r['purpose']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label required">مبلغ بنام پروژه</label>
                    <input type="number" step="0.01" name="amount_project" class="form-control" required value="<?= e((string)$r['amount_project']) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label required">واحد پولی</label>
                    <select name="currency_id" class="form-select" required>
                        <?php foreach ($currencies as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)$r['currency_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name_fa']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">مبلغ جمع صرافی</label>
                    <input type="number" step="0.01" name="amount_sarafi_total" class="form-control" value="<?= e((string)$r['amount_sarafi_total']) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">واحد پولی ۲</label>
                    <select name="currency2_id" class="form-select">
                        <option value="">--</option>
                        <?php foreach ($currencies as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)$r['currency2_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name_fa']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">تفصیل</label>
                <textarea name="details" class="form-control" rows="2"><?= e($r['details']) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-control" rows="2"><?= e($r['notes']) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> ذخیره</button>
            <a href="list.php?project_id=<?= $projectId ?>" class="btn btn-secondary">انصراف</a>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
