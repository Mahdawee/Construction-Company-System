<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
if (!canEditData($user)) { http_response_code(403); include __DIR__ . '/../403.php'; exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$a = ['kahta_account_id' => '', 'source' => 'site', 'currency_id' => 1, 'amount' => '', 'advance_date' => date('Y-m-d'), 'notes' => ''];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM kahta_advances WHERE id = :id AND project_id = :pid');
    $stmt->execute([':id' => $id, ':pid' => $projectId]);
    $a = $stmt->fetch();
    if (!$a) { flash('danger', 'رکورد یافت نشد.'); redirect('list.php?project_id=' . $projectId); }
}
$pageTitle = $id ? 'ویرایش پیش‌پرداخت' : 'پیش‌پرداخت جدید';
$errors = [];
$currencies = $pdo->query('SELECT * FROM currencies ORDER BY id')->fetchAll();
$kahtaStmt = $pdo->prepare('SELECT id, code, name FROM kahta_accounts WHERE project_id = :pid AND is_active = 1 ORDER BY code');
$kahtaStmt->execute([':pid' => $projectId]);
$kahtas = $kahtaStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $kahtaId = (int)($_POST['kahta_account_id'] ?? 0);
    $source = in_array($_POST['source'] ?? '', ['internal', 'site', 'center'], true) ? $_POST['source'] : 'site';
    $currencyId = (int)($_POST['currency_id'] ?? 1);
    $amount = (float)str_replace(',', '', $_POST['amount'] ?? 0);
    $advanceDate = sanitizeDate($_POST['advance_date'] ?? null);
    $notes = trim($_POST['notes'] ?? '');

    if (!$kahtaId) { $errors[] = 'انتخاب کهاته الزامی است.'; }
    if ($amount <= 0) { $errors[] = 'مبلغ باید بزرگتر از صفر باشد.'; }
    if (!$advanceDate) { $errors[] = 'تاریخ الزامی و باید معتبر باشد.'; }

    if (empty($errors)) {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE kahta_advances SET kahta_account_id=:kid, source=:src, currency_id=:cur, amount=:amt, advance_date=:dt, notes=:notes WHERE id=:id AND project_id=:pid');
            $stmt->execute([':kid'=>$kahtaId, ':src'=>$source, ':cur'=>$currencyId, ':amt'=>$amount, ':dt'=>$advanceDate, ':notes'=>$notes, ':id'=>$id, ':pid'=>$projectId]);
            auditLog('update', 'kahta_advances', $id, 'ویرایش پیش‌پرداخت');
            flash('success', 'رکورد ویرایش شد.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO kahta_advances (kahta_account_id, project_id, source, currency_id, amount, advance_date, notes, created_by) VALUES (:kid,:pid,:src,:cur,:amt,:dt,:notes,:cb)');
            $stmt->execute([':kid'=>$kahtaId, ':pid'=>$projectId, ':src'=>$source, ':cur'=>$currencyId, ':amt'=>$amount, ':dt'=>$advanceDate, ':notes'=>$notes, ':cb'=>$user['id']]);
            $newId = (int)$pdo->lastInsertId();
            auditLog('create', 'kahta_advances', $newId, 'ثبت پیش‌پرداخت جدید');
            flash('success', 'پیش‌پرداخت جدید ثبت شد.');
        }
        redirect('list.php?project_id=' . $projectId);
    }
    $a = compact('kahtaId','source','currencyId','amount','advanceDate','notes');
    $a['kahta_account_id']=$kahtaId; $a['currency_id']=$currencyId; $a['advance_date']=$advanceDate;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:640px">
    <div class="card-header"><?= e($pageTitle) ?></div>
    <div class="card-body">
        <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="mb-3">
                <label class="form-label required">کهاته</label>
                <select name="kahta_account_id" class="form-select" required>
                    <option value="">-- انتخاب کنید --</option>
                    <?php foreach ($kahtas as $k): ?>
                    <option value="<?= (int)$k['id'] ?>" <?= (int)$a['kahta_account_id'] === (int)$k['id'] ? 'selected' : '' ?>><?= e($k['code']) ?> - <?= e($k['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required">منبع</label>
                    <select name="source" class="form-select" required>
                        <option value="internal" <?= $a['source']==='internal'?'selected':'' ?>>منابع داخلی</option>
                        <option value="site" <?= $a['source']==='site'?'selected':'' ?>>ساحه</option>
                        <option value="center" <?= $a['source']==='center'?'selected':'' ?>>مرکز</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required">تاریخ</label>
                    <input type="date" name="advance_date" class="form-control" required value="<?= e($a['advance_date']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required">مبلغ</label>
                    <input type="number" step="0.01" name="amount" class="form-control" required value="<?= e((string)$a['amount']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required">واحد پولی</label>
                    <select name="currency_id" class="form-select" required>
                        <?php foreach ($currencies as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)$a['currency_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name_fa']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-control" rows="2"><?= e($a['notes']) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> ذخیره</button>
            <a href="list.php?project_id=<?= $projectId ?>" class="btn btn-secondary">انصراف</a>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
