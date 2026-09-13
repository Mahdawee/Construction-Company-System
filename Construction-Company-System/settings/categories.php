<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireRole(['super_admin', 'company_manager']);
$pdo = getDb();
$pageTitle = 'کتگوری‌های مصرف';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name_fa'] ?? '');
        if ($name !== '') {
            $stmt = $pdo->prepare('INSERT IGNORE INTO expense_categories (name_fa) VALUES (:n)');
            $stmt->execute([':n' => $name]);
            auditLog('create', 'expense_categories', (int)$pdo->lastInsertId(), "افزودن کتگوری: $name");
            flash('success', 'کتگوری اضافه شد.');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM expense_categories WHERE id = :id')->execute([':id' => $id]);
        auditLog('delete', 'expense_categories', $id, 'حذف کتگوری مصرف');
        flash('success', 'کتگوری حذف شد.');
    }
    redirect('categories.php');
}

$categories = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM expenses e WHERE e.category_id = c.id) AS usage_count FROM expense_categories c ORDER BY c.name_fa')->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">افزودن کتگوری جدید</div>
            <div class="card-body">
                <form method="post" class="d-flex gap-2">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="add">
                    <input type="text" name="name_fa" class="form-control" placeholder="نام کتگوری" required>
                    <button class="btn btn-primary"><i class="fa-solid fa-plus"></i></button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">لیست کتگوری‌ها</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>#</th><th>نام</th><th>تعداد استفاده</th><th>عملیات</th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $i => $c): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= e($c['name_fa']) ?></td>
                            <td><span class="badge bg-info text-dark"><?= (int)$c['usage_count'] ?></span></td>
                            <td>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" data-confirm="حذف این کتگوری؟"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
