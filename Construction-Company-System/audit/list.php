<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireRole(['super_admin', 'company_manager']);
$pdo = getDb();
$pageTitle = 'ثبت فعالیت‌های کاربران (Audit Log)';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$moduleFilter = trim($_GET['module'] ?? '');

$where = ['1=1']; $params = [];
if ($user['role_key'] === 'company_manager') {
    $where[] = 'al.user_id IN (SELECT id FROM users WHERE company_id = :cid)';
    $params[':cid'] = $user['company_id'];
}
if ($moduleFilter !== '') { $where[] = 'al.module = :mod'; $params[':mod'] = $moduleFilter; }
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log al WHERE $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
[$totalPages, $page, $offset] = paginate($total, $perPage, $page);

$stmt = $pdo->prepare("SELECT al.* FROM audit_log al WHERE $whereSql ORDER BY al.id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$modules = $pdo->query("SELECT DISTINCT module FROM audit_log ORDER BY module")->fetchAll();

$actionLabels = ['login' => 'ورود', 'login_failed' => 'ورود ناموفق', 'logout' => 'خروج', 'create' => 'ایجاد', 'update' => 'ویرایش', 'delete' => 'حذف'];
$actionColors = ['login' => 'success', 'login_failed' => 'danger', 'logout' => 'secondary', 'create' => 'primary', 'update' => 'warning', 'delete' => 'danger'];

include __DIR__ . '/../includes/header.php';
?>
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="d-flex gap-2 align-items-end">
            <div>
                <label class="form-label mb-1">ماژول</label>
                <select name="module" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">همه</option>
                    <?php foreach ($modules as $m): ?>
                    <option value="<?= e($m['module']) ?>" <?= $moduleFilter === $m['module'] ? 'selected' : '' ?>><?= e($m['module']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>تاریخ/ساعت</th><th>کاربر</th><th>عملیات</th><th>ماژول</th><th>شرح</th><th>IP</th></tr></thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="7" class="text-center text-muted py-3">رکوردی یافت نشد</td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td><?= (int)$l['id'] ?></td>
                    <td><?= e($l['created_at']) ?></td>
                    <td><?= e($l['username'] ?? '-') ?></td>
                    <td><span class="badge bg-<?= $actionColors[$l['action']] ?? 'secondary' ?>"><?= e($actionLabels[$l['action']] ?? $l['action']) ?></span></td>
                    <td><?= e($l['module']) ?></td>
                    <td><?= e($l['description']) ?></td>
                    <td><?= e($l['ip_address']) ?></td>
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
