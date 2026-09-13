<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
if (!canEditData($user)) { http_response_code(403); include __DIR__ . '/../403.php'; exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$r = ['entry_no' => '', 'entry_date' => date('Y-m-d'), 'details' => '', 'amount' => '', 'currency_id' => 1,
      'source_desc' => '', 'submitter' => '', 'receiver' => '', 'location' => '', 'notes' => ''];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM revenue_to_sarafi WHERE id = :id AND project_id = :pid');
    $stmt->execute([':id' => $id, ':pid' => $projectId]);
    $r = $stmt->fetch();
    if (!$r) { flash('danger', 'رکورد یافت نشد.'); redirect('list.php?project_id=' . $projectId); }
}
$pageTitle = $id ? 'ویرایش رکورد' : 'ثبت جدید';
$errors = [];
$currencies = $pdo->query('SELECT * FROM currencies ORDER BY id')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $entryNo = trim($_POST['entry_no'] ?? '');
    $entryDate = sanitizeDate($_POST['entry_date'] ?? null);
    $details = trim($_POST['details'] ?? '');
    $amount = (float)str_replace(',', '', $_POST['amount'] ?? 0);
    $currencyId = (int)($_POST['currency_id'] ?? 1);
    $sourceDesc = trim($_POST['source_desc'] ?? '');
    $submitter = trim($_POST['submitter'] ?? '');
    $receiver = trim($_POST['receiver'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!$entryDate) { $errors[] = 'تاریخ الزامی و باید معتبر باشد.'; }
    if ($amount <= 0) { $errors[] = 'مبلغ باید بزرگتر از صفر باشد.'; }

    if (empty($errors)) {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE revenue_to_sarafi SET entry_no=:en, entry_date=:ed, details=:det, amount=:amt, currency_id=:cur, source_desc=:sd, submitter=:sub, receiver=:rec, location=:loc, notes=:notes WHERE id=:id AND project_id=:pid");
            $stmt->execute([':en'=>$entryNo, ':ed'=>$entryDate, ':det'=>$details, ':amt'=>$amount, ':cur'=>$currencyId, ':sd'=>$sourceDesc, ':sub'=>$submitter, ':rec'=>$receiver, ':loc'=>$location, ':notes'=>$notes, ':id'=>$id, ':pid'=>$projectId]);
            auditLog('update', 'revenue_to_sarafi', $id, 'ویرایش عاید تسلیم‌شده به صرافی');
            flash('success', 'رکورد ویرایش شد.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO revenue_to_sarafi (project_id, entry_no, entry_date, details, amount, currency_id, source_desc, submitter, receiver, location, notes, created_by) VALUES (:pid,:en,:ed,:det,:amt,:cur,:sd,:sub,:rec,:loc,:notes,:cb)");
            $stmt->execute([':pid'=>$projectId, ':en'=>$entryNo, ':ed'=>$entryDate, ':det'=>$details, ':amt'=>$amount, ':cur'=>$currencyId, ':sd'=>$sourceDesc, ':sub'=>$submitter, ':rec'=>$receiver, ':loc'=>$location, ':notes'=>$notes, ':cb'=>$user['id']]);
            $newId = (int)$pdo->lastInsertId();
            auditLog('create', 'revenue_to_sarafi', $newId, 'ثبت عاید جدید تسلیم‌شده به صرافی');
            flash('success', 'رکورد جدید ثبت شد.');
        }
        redirect('list.php?project_id=' . $projectId);
    }
    $r = compact('entryNo','entryDate','details','amount','currencyId','sourceDesc','submitter','receiver','location','notes');
    $r['entry_no']=$entryNo; $r['entry_date']=$entryDate; $r['currency_id']=$currencyId; $r['source_desc']=$sourceDesc;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:720px">
    <div class="card-header"><?= e($pageTitle) ?></div>
    <div class="card-body">
        <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">نمبر</label>
                    <input type="text" name="entry_no" class="form-control" value="<?= e($r['entry_no']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label required">تاریخ</label>
                    <input type="date" name="entry_date" class="form-control" required value="<?= e($r['entry_date']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">از درک</label>
                    <input type="text" name="source_desc" class="form-control" value="<?= e($r['source_desc']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">تسلیم دهنده</label>
                    <input type="text" name="submitter" class="form-control" value="<?= e($r['submitter']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">تسلیم گیرنده</label>
                    <input type="text" name="receiver" class="form-control" value="<?= e($r['receiver']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">موقعیت</label>
                    <input type="text" name="location" class="form-control" value="<?= e($r['location']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required">مبلغ</label>
                    <input type="number" step="0.01" name="amount" class="form-control" required value="<?= e((string)$r['amount']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required">واحد پولی</label>
                    <select name="currency_id" class="form-select" required>
                        <?php foreach ($currencies as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)$r['currency_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name_fa']) ?></option>
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
