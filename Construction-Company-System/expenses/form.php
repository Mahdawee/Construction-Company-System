<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
if (!canEditData($user)) { http_response_code(403); include __DIR__ . '/../403.php'; exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$ex = [
    'voucher_no' => '', 'expense_date' => date('Y-m-d'), 'expense_date_shamsi' => '', 'shamsi_month' => '',
    'details' => '', 'payer' => '', 'kahta_account_id' => '', 'received_via' => '', 'bill_no' => '',
    'paid_from' => '', 'category_id' => '', 'amount' => '', 'currency_id' => 1, 'notes' => '',
];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM expenses WHERE id = :id AND project_id = :pid');
    $stmt->execute([':id' => $id, ':pid' => $projectId]);
    $ex = $stmt->fetch();
    if (!$ex) { flash('danger', 'مصرف یافت نشد.'); redirect('list.php?project_id=' . $projectId); }
}
$pageTitle = $id ? 'ویرایش مصرف' : 'ثبت مصرف جدید';
$errors = [];
$currencies = $pdo->query('SELECT * FROM currencies ORDER BY id')->fetchAll();
$categories = $pdo->query('SELECT * FROM expense_categories ORDER BY name_fa')->fetchAll();
$kahtaStmt = $pdo->prepare("SELECT k.id, k.code, k.name FROM kahta_accounts k WHERE k.project_id = :pid AND k.is_active = 1 ORDER BY k.code");
$kahtaStmt->execute([':pid' => $projectId]);
$kahtas = $kahtaStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $voucherNo = trim($_POST['voucher_no'] ?? '');
    $expenseDate = sanitizeDate($_POST['expense_date'] ?? null);
    $expenseDateShamsi = trim($_POST['expense_date_shamsi'] ?? '');
    $shamsiMonth = trim($_POST['shamsi_month'] ?? '');
    $details = trim($_POST['details'] ?? '');
    $payer = trim($_POST['payer'] ?? '');
    $kahtaId = (int)($_POST['kahta_account_id'] ?? 0) ?: null;
    $receivedVia = trim($_POST['received_via'] ?? '');
    $billNo = trim($_POST['bill_no'] ?? '');
    $paidFrom = trim($_POST['paid_from'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
    $amount = (float)str_replace(',', '', $_POST['amount'] ?? 0);
    $currencyId = (int)($_POST['currency_id'] ?? 1);
    $notes = trim($_POST['notes'] ?? '');

    if (!$expenseDate) { $errors[] = 'تاریخ مصرف الزامی و باید معتبر باشد.'; }
    if ($amount <= 0) { $errors[] = 'مبلغ باید بزرگتر از صفر باشد.'; }

    if (empty($errors)) {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE expenses SET voucher_no=:vn, expense_date=:ed, expense_date_shamsi=:eds, shamsi_month=:sm, details=:det, payer=:payer, kahta_account_id=:kid, received_via=:rv, bill_no=:bn, paid_from=:pf, category_id=:cat, amount=:amt, currency_id=:cur, notes=:notes WHERE id=:id AND project_id=:pid");
            $stmt->execute([':vn'=>$voucherNo, ':ed'=>$expenseDate, ':eds'=>$expenseDateShamsi, ':sm'=>$shamsiMonth, ':det'=>$details, ':payer'=>$payer, ':kid'=>$kahtaId, ':rv'=>$receivedVia, ':bn'=>$billNo, ':pf'=>$paidFrom, ':cat'=>$categoryId, ':amt'=>$amount, ':cur'=>$currencyId, ':notes'=>$notes, ':id'=>$id, ':pid'=>$projectId]);
            auditLog('update', 'expenses', $id, 'ویرایش مصرف');
            flash('success', 'مصرف ویرایش شد.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO expenses (project_id, voucher_no, expense_date, expense_date_shamsi, shamsi_month, details, payer, kahta_account_id, received_via, bill_no, paid_from, category_id, amount, currency_id, notes, created_by) VALUES (:pid,:vn,:ed,:eds,:sm,:det,:payer,:kid,:rv,:bn,:pf,:cat,:amt,:cur,:notes,:cb)");
            $stmt->execute([':pid'=>$projectId, ':vn'=>$voucherNo, ':ed'=>$expenseDate, ':eds'=>$expenseDateShamsi, ':sm'=>$shamsiMonth, ':det'=>$details, ':payer'=>$payer, ':kid'=>$kahtaId, ':rv'=>$receivedVia, ':bn'=>$billNo, ':pf'=>$paidFrom, ':cat'=>$categoryId, ':amt'=>$amount, ':cur'=>$currencyId, ':notes'=>$notes, ':cb'=>$user['id']]);
            $newId = (int)$pdo->lastInsertId();
            auditLog('create', 'expenses', $newId, 'ثبت مصرف جدید');
            flash('success', 'مصرف جدید ثبت شد.');
        }
        redirect('list.php?project_id=' . $projectId);
    }
    $ex = compact('voucherNo','expenseDate','expenseDateShamsi','shamsiMonth','details','payer','kahtaId','receivedVia','billNo','paidFrom','categoryId','amount','currencyId','notes');
    $ex['voucher_no']=$voucherNo; $ex['expense_date']=$expenseDate; $ex['expense_date_shamsi']=$expenseDateShamsi; $ex['shamsi_month']=$shamsiMonth;
    $ex['kahta_account_id']=$kahtaId; $ex['received_via']=$receivedVia; $ex['bill_no']=$billNo; $ex['paid_from']=$paidFrom; $ex['category_id']=$categoryId; $ex['currency_id']=$currencyId;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:860px">
    <div class="card-header"><?= e($pageTitle) ?></div>
    <div class="card-body">
        <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">نمبر دیتابیس/بل</label>
                    <input type="text" name="voucher_no" class="form-control" value="<?= e($ex['voucher_no']) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label required">تاریخ (میلادی)</label>
                    <input type="date" name="expense_date" class="form-control" required value="<?= e($ex['expense_date']) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">تاریخ شمسی</label>
                    <input type="text" name="expense_date_shamsi" class="form-control" value="<?= e($ex['expense_date_shamsi']) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">ماه شمسی</label>
                    <input type="text" name="shamsi_month" class="form-control" value="<?= e($ex['shamsi_month']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">مصرف کننده / پرداخت کننده</label>
                    <input type="text" name="payer" class="form-control" value="<?= e($ex['payer']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">گیرنده / حساب (کهاته)</label>
                    <select name="kahta_account_id" class="form-select">
                        <option value="">-- بدون کهاته --</option>
                        <?php foreach ($kahtas as $k): ?>
                        <option value="<?= (int)$k['id'] ?>" <?= (int)$ex['kahta_account_id'] === (int)$k['id'] ? 'selected' : '' ?>><?= e($k['code']) ?> - <?= e($k['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">بدست / از طریق</label>
                    <input type="text" name="received_via" class="form-control" value="<?= e($ex['received_via']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">نمبر بل</label>
                    <input type="text" name="bill_no" class="form-control" value="<?= e($ex['bill_no']) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">پرداخت از</label>
                    <input type="text" name="paid_from" class="form-control" value="<?= e($ex['paid_from']) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">کتگوری</label>
                    <select name="category_id" class="form-select">
                        <option value="">-- انتخاب کنید --</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)$ex['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name_fa']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label required">واحد پولی</label>
                    <select name="currency_id" class="form-select" required>
                        <?php foreach ($currencies as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)$ex['currency_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name_fa']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label required">مبلغ</label>
                    <input type="number" step="0.01" name="amount" class="form-control" required value="<?= e((string)$ex['amount']) ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">تفصیل</label>
                <textarea name="details" class="form-control" rows="2"><?= e($ex['details']) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-control" rows="2"><?= e($ex['notes']) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> ذخیره</button>
            <a href="list.php?project_id=<?= $projectId ?>" class="btn btn-secondary">انصراف</a>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
