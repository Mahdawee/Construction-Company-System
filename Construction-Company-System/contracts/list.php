<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
$pageTitle = 'قراردادها';

$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT ct.*, cu.name AS customer_name, k.code AS kahta_code, k.name AS kahta_name, cur.code AS cur_code,
        (SELECT GROUP_CONCAT(m.name SEPARATOR '، ') FROM contract_machinery cm JOIN machinery m ON m.id = cm.machinery_id WHERE cm.contract_id = ct.id) AS machinery_names
        FROM contracts ct
        LEFT JOIN customers cu ON cu.id = ct.customer_id
        LEFT JOIN kahta_accounts k ON k.id = ct.kahta_account_id
        LEFT JOIN currencies cur ON cur.id = ct.currency_id
        WHERE ct.project_id = :pid";
$params = [':pid' => $projectId];
if ($statusFilter !== '') { $sql .= ' AND ct.status = :st'; $params[':st'] = $statusFilter; }
$sql .= ' ORDER BY ct.contract_date DESC, ct.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contracts = $stmt->fetchAll();

$statusLabels = ['pending' => ['در انتظار', 'secondary'], 'active' => ['فعال', 'success'], 'completed' => ['تکمیل‌شده', 'primary'], 'terminated' => ['فسخ‌شده', 'danger']];

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form method="get" class="d-flex gap-1">
            <input type="hidden" name="project_id" value="<?= $projectId ?>">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">همه وضعیت‌ها</option>
                <?php foreach ($statusLabels as $key => [$label, $color]): ?>
                <option value="<?= $key ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/export/export.php?module=contracts&project_id=<?= $projectId ?>" class="btn btn-sm btn-success"><i class="fa-solid fa-file-excel"></i> اکسل</a>
            <?php if (canEditData($user)): ?>
            <a href="form.php?project_id=<?= $projectId ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> قرارداد جدید</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>نمبر قرارداد</th><th>عنوان</th><th>طرف قرارداد</th><th>ماشین‌آلات</th><th>تاریخ عقد</th><th>مدت</th><th>مبلغ</th><th>وضعیت</th><th>فایل</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php if (empty($contracts)): ?>
                <tr><td colspan="10" class="text-center text-muted py-3">قراردادی ثبت نشده است</td></tr>
            <?php endif; ?>
            <?php foreach ($contracts as $ct): [$label, $color] = $statusLabels[$ct['status']] ?? ['-', 'secondary']; ?>
                <tr>
                    <td><?= e($ct['contract_no']) ?: (int)$ct['id'] ?></td>
                    <td><?= e($ct['title']) ?></td>
                    <td>
                        <?php if ($ct['party_type'] === 'customer'): ?>
                            <span class="badge bg-info text-dark">مشتری</span> <?= e($ct['customer_name'] ?? '-') ?>
                        <?php else: ?>
                            <span class="badge bg-dark"><?= e($ct['kahta_code'] ?? '-') ?></span> <?= e($ct['kahta_name'] ?? '-') ?>
                        <?php endif; ?>
                    </td>
                    <td><?= $ct['machinery_names'] ? e($ct['machinery_names']) : '<span class="text-muted">-</span>' ?></td>
                    <td><?= e($ct['contract_date']) ?></td>
                    <td><?= e($ct['start_date']) ?> — <?= e($ct['end_date']) ?></td>
                    <td class="fw-bold"><?= formatMoney($ct['amount']) ?> <?= currencyLabel($ct['cur_code']) ?></td>
                    <td><span class="badge bg-<?= $color ?>"><?= $label ?></span></td>
                    <td><?php if ($ct['attachment_path']): ?><a href="<?= BASE_URL ?>/files/download.php?module=contracts&id=<?= (int)$ct['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-paperclip"></i></a><?php else: ?>-<?php endif; ?></td>
                    <td class="text-nowrap">
                        <?php if (canEditData($user)): ?>
                        <a href="form.php?id=<?= (int)$ct['id'] ?>&project_id=<?= $projectId ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (canDeleteData($user)): ?>
                        <form action="delete.php" method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$ct['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="آیا از حذف این قرارداد مطمئن هستید؟"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
