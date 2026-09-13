<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
if (!canEditData($user)) { http_response_code(403); include __DIR__ . '/../403.php'; exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$ct = [
    'contract_no' => '', 'title' => '', 'party_type' => 'kahta', 'customer_id' => '', 'kahta_account_id' => '',
    'contract_date' => date('Y-m-d'), 'start_date' => '', 'end_date' => '', 'amount' => '', 'currency_id' => 1,
    'status' => 'active', 'scope_of_work' => '', 'payment_terms' => '', 'notes' => '', 'attachment_path' => null, 'attachment_name' => null,
];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = :id AND project_id = :pid');
    $stmt->execute([':id' => $id, ':pid' => $projectId]);
    $ct = $stmt->fetch();
    if (!$ct) { flash('danger', 'قرارداد یافت نشد.'); redirect('list.php?project_id=' . $projectId); }
}
$pageTitle = $id ? 'ویرایش قرارداد' : 'قرارداد جدید';
$errors = [];
$currencies = $pdo->query('SELECT * FROM currencies ORDER BY id')->fetchAll();

$custStmt = $pdo->prepare('SELECT id, name FROM customers WHERE project_id = :pid AND is_active = 1 ORDER BY name');
$custStmt->execute([':pid' => $projectId]);
$customers = $custStmt->fetchAll();

$kahtaStmt = $pdo->prepare("SELECT k.id, k.code, k.name FROM kahta_accounts k WHERE k.project_id = :pid AND k.is_active = 1 ORDER BY k.code");
$kahtaStmt->execute([':pid' => $projectId]);
$kahtas = $kahtaStmt->fetchAll();

$machStmt = $pdo->prepare("SELECT id, name, machine_type, plate_or_serial_no FROM machinery WHERE project_id = :pid AND status != 'inactive' ORDER BY name");
$machStmt->execute([':pid' => $projectId]);
$machineryList = $machStmt->fetchAll();

$selectedMachineryIds = [];
if ($id) {
    $selStmt = $pdo->prepare('SELECT machinery_id FROM contract_machinery WHERE contract_id = :id');
    $selStmt->execute([':id' => $id]);
    $selectedMachineryIds = array_map('intval', array_column($selStmt->fetchAll(), 'machinery_id'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $contractNo = trim($_POST['contract_no'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $partyType = in_array($_POST['party_type'] ?? '', ['customer', 'kahta'], true) ? $_POST['party_type'] : 'kahta';
    $customerId = $partyType === 'customer' ? ((int)($_POST['customer_id'] ?? 0) ?: null) : null;
    $kahtaId = $partyType === 'kahta' ? ((int)($_POST['kahta_account_id'] ?? 0) ?: null) : null;
    $contractDate = sanitizeDate($_POST['contract_date'] ?? null);
    $startDate = sanitizeDate($_POST['start_date'] ?? null);
    $endDate = sanitizeDate($_POST['end_date'] ?? null);
    $amount = (float)str_replace(',', '', $_POST['amount'] ?? 0);
    $currencyId = (int)($_POST['currency_id'] ?? 1);
    $status = in_array($_POST['status'] ?? '', ['pending', 'active', 'completed', 'terminated'], true) ? $_POST['status'] : 'active';
    $scopeOfWork = trim($_POST['scope_of_work'] ?? '');
    $paymentTerms = trim($_POST['payment_terms'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($title === '') { $errors[] = 'عنوان قرارداد الزامی است.'; }
    if ($partyType === 'customer' && !$customerId) { $errors[] = 'انتخاب مشتری الزامی است.'; }
    if ($partyType === 'kahta' && !$kahtaId) { $errors[] = 'انتخاب پیمانکار/فروشنده (کهاته) الزامی است.'; }

    $newAttachment = null;
    if (empty($errors)) {
        try {
            $newAttachment = handleFileUpload('attachment', 'contracts', $projectId);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        $attachmentPath = $ct['attachment_path'] ?? null;
        $attachmentName = $ct['attachment_name'] ?? null;
        if ($newAttachment) {
            deleteUploadedFile($attachmentPath);
            $attachmentPath = $newAttachment['path'];
            $attachmentName = $newAttachment['name'];
        } elseif (!empty($_POST['remove_attachment'])) {
            deleteUploadedFile($attachmentPath);
            $attachmentPath = null; $attachmentName = null;
        }

        $paramsArr = [
            ':cno'=>$contractNo, ':title'=>$title, ':pt'=>$partyType, ':cust'=>$customerId, ':kahta'=>$kahtaId,
            ':cd'=>$contractDate, ':sd'=>$startDate, ':ed'=>$endDate, ':amt'=>$amount, ':cur'=>$currencyId,
            ':st'=>$status, ':scope'=>$scopeOfWork, ':pay'=>$paymentTerms, ':notes'=>$notes, ':ap'=>$attachmentPath, ':an'=>$attachmentName,
        ];
        if ($id) {
            $paramsArr[':id'] = $id; $paramsArr[':pid'] = $projectId;
            $stmt = $pdo->prepare('UPDATE contracts SET contract_no=:cno, title=:title, party_type=:pt, customer_id=:cust, kahta_account_id=:kahta, contract_date=:cd, start_date=:sd, end_date=:ed, amount=:amt, currency_id=:cur, status=:st, scope_of_work=:scope, payment_terms=:pay, notes=:notes, attachment_path=:ap, attachment_name=:an WHERE id=:id AND project_id=:pid');
            $stmt->execute($paramsArr);
            $targetContractId = $id;
            auditLog('update', 'contracts', $id, "ویرایش قرارداد: $title");
            flash('success', 'قرارداد ویرایش شد.');
        } else {
            $paramsArr[':pid'] = $projectId; $paramsArr[':cb'] = $user['id'];
            $stmt = $pdo->prepare('INSERT INTO contracts (project_id, contract_no, title, party_type, customer_id, kahta_account_id, contract_date, start_date, end_date, amount, currency_id, status, scope_of_work, payment_terms, notes, attachment_path, attachment_name, created_by) VALUES (:pid,:cno,:title,:pt,:cust,:kahta,:cd,:sd,:ed,:amt,:cur,:st,:scope,:pay,:notes,:ap,:an,:cb)');
            $stmt->execute($paramsArr);
            $newId = (int)$pdo->lastInsertId();
            $targetContractId = $newId;
            auditLog('create', 'contracts', $newId, "ثبت قرارداد جدید: $title");
            flash('success', 'قرارداد جدید ثبت شد.');
        }

        // همگام‌سازی ماشین‌آلات مرتبط با این قرارداد (رابطه چندبه‌چند)
        $postedMachineryIds = array_map('intval', $_POST['machinery_ids'] ?? []);
        $postedMachineryIds = array_values(array_unique(array_filter($postedMachineryIds)));
        if ($postedMachineryIds) {
            $in = implode(',', array_fill(0, count($postedMachineryIds), '?'));
            $validStmt = $pdo->prepare("SELECT id FROM machinery WHERE project_id = ? AND id IN ($in)");
            $validStmt->execute(array_merge([$projectId], $postedMachineryIds));
            $postedMachineryIds = array_map('intval', array_column($validStmt->fetchAll(), 'id'));
        }
        $pdo->prepare('DELETE FROM contract_machinery WHERE contract_id = ?')->execute([$targetContractId]);
        if ($postedMachineryIds) {
            $insMach = $pdo->prepare('INSERT INTO contract_machinery (contract_id, machinery_id) VALUES (?, ?)');
            foreach ($postedMachineryIds as $mid) { $insMach->execute([$targetContractId, $mid]); }
        }

        redirect('list.php?project_id=' . $projectId);
    }
    $selectedMachineryIds = array_map('intval', $_POST['machinery_ids'] ?? []);
    $ct = compact('contractNo','title','partyType','customerId','kahtaId','contractDate','startDate','endDate','amount','currencyId','status','scopeOfWork','paymentTerms','notes');
    $ct['contract_no']=$contractNo; $ct['party_type']=$partyType; $ct['customer_id']=$customerId; $ct['kahta_account_id']=$kahtaId;
    $ct['contract_date']=$contractDate; $ct['start_date']=$startDate; $ct['end_date']=$endDate; $ct['currency_id']=$currencyId;
    $ct['scope_of_work']=$scopeOfWork; $ct['payment_terms']=$paymentTerms;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:860px">
    <div class="card-header"><?= e($pageTitle) ?></div>
    <div class="card-body">
        <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post" enctype="multipart/form-data" id="contractForm">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">نمبر قرارداد</label>
                    <input type="text" name="contract_no" class="form-control" value="<?= e($ct['contract_no']) ?>">
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label required">عنوان قرارداد</label>
                    <input type="text" name="title" class="form-control" required value="<?= e($ct['title']) ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label required">نوع طرف قرارداد</label>
                <select name="party_type" id="partyType" class="form-select" required onchange="togglePartyType()">
                    <option value="kahta" <?= $ct['party_type']==='kahta'?'selected':'' ?>>پیمانکار/فروشنده (کهاته)</option>
                    <option value="customer" <?= $ct['party_type']==='customer'?'selected':'' ?>>مشتری (کارفرما)</option>
                </select>
            </div>
            <div class="mb-3" id="kahtaField" style="<?= $ct['party_type']==='customer'?'display:none':'' ?>">
                <label class="form-label">پیمانکار / فروشنده</label>
                <select name="kahta_account_id" class="form-select">
                    <option value="">-- انتخاب کنید --</option>
                    <?php foreach ($kahtas as $k): ?>
                    <option value="<?= (int)$k['id'] ?>" <?= (int)$ct['kahta_account_id'] === (int)$k['id'] ? 'selected' : '' ?>><?= e($k['code']) ?> - <?= e($k['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3" id="customerField" style="<?= $ct['party_type']==='kahta'?'display:none':'' ?>">
                <label class="form-label">مشتری</label>
                <select name="customer_id" class="form-select">
                    <option value="">-- انتخاب کنید --</option>
                    <?php foreach ($customers as $cu): ?>
                    <option value="<?= (int)$cu['id'] ?>" <?= (int)$ct['customer_id'] === (int)$cu['id'] ? 'selected' : '' ?>><?= e($cu['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($customers)): ?><small class="text-muted">ابتدا از بخش «مشتریان» یک مشتری ثبت کنید.</small><?php endif; ?>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">تاریخ عقد قرارداد</label>
                    <input type="date" name="contract_date" class="form-control" value="<?= e($ct['contract_date']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">تاریخ شروع</label>
                    <input type="date" name="start_date" class="form-control" value="<?= e($ct['start_date']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">تاریخ ختم</label>
                    <input type="date" name="end_date" class="form-control" value="<?= e($ct['end_date']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">مبلغ قرارداد</label>
                    <input type="number" step="0.01" name="amount" class="form-control" value="<?= e((string)$ct['amount']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">واحد پولی</label>
                    <select name="currency_id" class="form-select">
                        <?php foreach ($currencies as $cur): ?>
                        <option value="<?= (int)$cur['id'] ?>" <?= (int)$ct['currency_id'] === (int)$cur['id'] ? 'selected' : '' ?>><?= e($cur['name_fa']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">وضعیت</label>
                    <select name="status" class="form-select">
                        <option value="pending" <?= $ct['status']==='pending'?'selected':'' ?>>در انتظار</option>
                        <option value="active" <?= $ct['status']==='active'?'selected':'' ?>>فعال</option>
                        <option value="completed" <?= $ct['status']==='completed'?'selected':'' ?>>تکمیل‌شده</option>
                        <option value="terminated" <?= $ct['status']==='terminated'?'selected':'' ?>>فسخ‌شده</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">ماشین‌آلات مرتبط با این قرارداد</label>
                <div class="border rounded p-2" style="max-height:220px; overflow-y:auto;">
                    <?php if (empty($machineryList)): ?>
                    <small class="text-muted">ماشینی ثبت نشده است. ابتدا از بخش «ماشین‌آلات» یک ماشین ثبت کنید.</small>
                    <?php endif; ?>
                    <?php foreach ($machineryList as $mc): ?>
                    <div class="form-check">
                        <input type="checkbox" name="machinery_ids[]" value="<?= (int)$mc['id'] ?>" class="form-check-input" id="mach_<?= (int)$mc['id'] ?>"
                            <?= in_array((int)$mc['id'], $selectedMachineryIds, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="mach_<?= (int)$mc['id'] ?>">
                            <?= e($mc['name']) ?><?= $mc['machine_type'] ? ' - ' . e($mc['machine_type']) : '' ?><?= $mc['plate_or_serial_no'] ? ' (' . e($mc['plate_or_serial_no']) . ')' : '' ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <small class="text-muted">در صورت نیاز می‌توانید چند ماشین را همزمان برای یک قرارداد انتخاب کنید (مثلاً یک قرارداد برای چند کامیون).</small>
            </div>
            <div class="mb-3">
                <label class="form-label">شرح دامنه کار (Scope of Work)</label>
                <textarea name="scope_of_work" class="form-control" rows="2"><?= e($ct['scope_of_work']) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">شرایط پرداخت</label>
                <textarea name="payment_terms" class="form-control" rows="2"><?= e($ct['payment_terms']) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-control" rows="2"><?= e($ct['notes']) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">فایل ضمیمه (اسکن قرارداد)</label>
                <?php if (!empty($ct['attachment_path'])): ?>
                <div class="mb-2">
                    <a href="<?= BASE_URL ?>/files/download.php?module=contracts&id=<?= (int)$id ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-paperclip"></i> <?= e($ct['attachment_name']) ?></a>
                    <div class="form-check d-inline-block ms-2">
                        <input type="checkbox" name="remove_attachment" value="1" class="form-check-input" id="rmatt">
                        <label class="form-check-label" for="rmatt">حذف فایل فعلی</label>
                    </div>
                </div>
                <?php endif; ?>
                <input type="file" name="attachment" class="form-control">
                <small class="text-muted">فرمت‌های مجاز: PDF, JPG, PNG, GIF, DOC, DOCX - حداکثر ۸ مگابایت</small>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> ذخیره</button>
            <a href="list.php?project_id=<?= $projectId ?>" class="btn btn-secondary">انصراف</a>
        </form>
    </div>
</div>
<script>
function togglePartyType() {
    const v = document.getElementById('partyType').value;
    document.getElementById('kahtaField').style.display = v === 'kahta' ? '' : 'none';
    document.getElementById('customerField').style.display = v === 'customer' ? '' : 'none';
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
