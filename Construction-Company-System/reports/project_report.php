<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectId = requireCurrentProject($pdo, $user);
$pageTitle = 'راپور خلاصه پروژه';

$stmt = $pdo->prepare('SELECT p.*, c.name AS company_name, s.name AS sarafi_name FROM projects p JOIN companies c ON c.id = p.company_id LEFT JOIN sarafi_companies s ON s.id = p.sarafi_company_id WHERE p.id = :id');
$stmt->execute([':id' => $projectId]);
$project = $stmt->fetch();

function sumByCurrency(PDO $pdo, string $table, string $amountCol, string $currencyCol, int $projectId, string $extraWhere = ''): array
{
    $sql = "SELECT c.code, SUM(t.$amountCol) AS total FROM $table t JOIN currencies c ON c.id = t.$currencyCol WHERE t.project_id = :pid $extraWhere GROUP BY c.code";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':pid' => $projectId]);
    $result = ['AFN' => 0, 'USD' => 0];
    foreach ($stmt->fetchAll() as $r) { $result[$r['code']] = (float)$r['total']; }
    return $result;
}

$receipts = sumByCurrency($pdo, 'money_receipts', 'amount_project', 'currency_id', $projectId);
$expenses = sumByCurrency($pdo, 'expenses', 'amount', 'currency_id', $projectId);
$advAll = sumByCurrency($pdo, 'kahta_advances', 'amount', 'currency_id', $projectId);
$advInternal = sumByCurrency($pdo, 'kahta_advances', 'amount', 'currency_id', $projectId, "AND t.source = 'internal'");
$advSite = sumByCurrency($pdo, 'kahta_advances', 'amount', 'currency_id', $projectId, "AND t.source = 'site'");
$advCenter = sumByCurrency($pdo, 'kahta_advances', 'amount', 'currency_id', $projectId, "AND t.source = 'center'");
$sarafiReceived = sumByCurrency($pdo, 'money_receipts', 'amount_sarafi_total', 'currency2_id', $projectId);
$revenueSubmitted = sumByCurrency($pdo, 'revenue_to_sarafi', 'amount', 'currency_id', $projectId);

$balance = ['AFN' => $receipts['AFN'] - $expenses['AFN'], 'USD' => $receipts['USD'] - $expenses['USD']];
$sarafiBalance = ['AFN' => $sarafiReceived['AFN'] - $revenueSubmitted['AFN'], 'USD' => $sarafiReceived['USD'] - $revenueSubmitted['USD']];

// خلاصه به تفکیک نوع کهاته
$stmt = $pdo->prepare("
    SELECT kt.name_fa, kt.code,
        COALESCE(SUM(CASE WHEN a.currency_id = 1 THEN a.amount ELSE 0 END),0) AS adv_afn,
        COALESCE(SUM(CASE WHEN a.currency_id = 2 THEN a.amount ELSE 0 END),0) AS adv_usd
    FROM kahta_types kt
    LEFT JOIN kahta_accounts ka ON ka.kahta_type_id = kt.id AND ka.project_id = :pid
    LEFT JOIN kahta_advances a ON a.kahta_account_id = ka.id
    GROUP BY kt.id ORDER BY kt.id
");
$stmt->execute([':pid' => $projectId]);
$byType = $stmt->fetchAll();

// خلاصه مصارف بر اساس کتگوری
$stmt = $pdo->prepare("
    SELECT COALESCE(cat.name_fa,'بدون کتگوری') AS name_fa,
        COALESCE(SUM(CASE WHEN e.currency_id=1 THEN e.amount ELSE 0 END),0) AS afn,
        COALESCE(SUM(CASE WHEN e.currency_id=2 THEN e.amount ELSE 0 END),0) AS usd
    FROM expenses e LEFT JOIN expense_categories cat ON cat.id = e.category_id
    WHERE e.project_id = :pid GROUP BY cat.name_fa ORDER BY afn DESC
");
$stmt->execute([':pid' => $projectId]);
$byCategory = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div></div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/export/export.php?module=project_report&project_id=<?= $projectId ?>" class="btn btn-success btn-sm"><i class="fa-solid fa-file-excel"></i> خروجی اکسل</a>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-print"></i> چاپ</button>
    </div>
</div>

<div class="text-center mb-4 print-title">
    <h4><?= e($project['company_name']) ?></h4>
    <h6 class="text-muted">راپور خلاصه رسیدات و مصارف پروژه: <?= e($project['name']) ?><?= $project['sarafi_name'] ? ' — طرف صرافی: ' . e($project['sarafi_name']) : '' ?></h6>
    <small class="text-muted">تاریخ گزارش: <?= date('Y-m-d') ?></small>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">صورت‌حساب رسیدات و مصارف پروژه</div>
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead><tr><th>شرح</th><th>افغانی</th><th>دالر</th></tr></thead>
                    <tbody>
                        <tr><td>جمله پول حصول‌شده (رسیدات)</td><td><?= formatMoney($receipts['AFN']) ?></td><td><?= formatMoney($receipts['USD']) ?></td></tr>
                        <tr><td>پرداخت/حواله از دخل مرکزی به کهاته‌ها</td><td><?= formatMoney($advCenter['AFN']) ?></td><td><?= formatMoney($advCenter['USD']) ?></td></tr>
                        <tr><td>پرداخت/حواله از دخل ساحوی به کهاته‌ها</td><td><?= formatMoney($advSite['AFN']) ?></td><td><?= formatMoney($advSite['USD']) ?></td></tr>
                        <tr><td>جمله شد اجراآت از منابع داخلی</td><td><?= formatMoney($advInternal['AFN']) ?></td><td><?= formatMoney($advInternal['USD']) ?></td></tr>
                        <tr><td>جمله شد پرداخت‌ها به کهاته‌ها (مجموع)</td><td><?= formatMoney($advAll['AFN']) ?></td><td><?= formatMoney($advAll['USD']) ?></td></tr>
                        <tr class="table-danger"><td>جمله شد مصارف پروژه</td><td><?= formatMoney($expenses['AFN']) ?></td><td><?= formatMoney($expenses['USD']) ?></td></tr>
                        <tr class="table-success fw-bold"><td>پول باقیمانده / موجود پروژه</td><td><?= formatMoney($balance['AFN']) ?></td><td><?= formatMoney($balance['USD']) ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">خلاصه داد و گرفت پولی با شرکت صرافی</div>
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead><tr><th>شرح</th><th>افغانی</th><th>دالر</th></tr></thead>
                    <tbody>
                        <tr><td>مجموع پول حصول‌شده از صرافی (معادل)</td><td><?= formatMoney($sarafiReceived['AFN']) ?></td><td><?= formatMoney($sarafiReceived['USD']) ?></td></tr>
                        <tr><td>مجموع پول/عواید تسلیم‌شده به صرافی</td><td><?= formatMoney($revenueSubmitted['AFN']) ?></td><td><?= formatMoney($revenueSubmitted['USD']) ?></td></tr>
                        <tr class="table-info fw-bold"><td>مانده حساب با صرافی (درک این پروژه)</td><td><?= formatMoney($sarafiBalance['AFN']) ?></td><td><?= formatMoney($sarafiBalance['USD']) ?></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="card-body">
                <canvas id="sarafiChart" height="140"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">پیش‌پرداخت‌ها به تفکیک نوع کهاته</div>
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead><tr><th>نوع کهاته</th><th>افغانی</th><th>دالر</th></tr></thead>
                    <tbody>
                    <?php foreach ($byType as $t): ?>
                        <tr><td><?= e($t['code']) ?> - <?= e($t['name_fa']) ?></td><td><?= formatMoney($t['adv_afn']) ?></td><td><?= formatMoney($t['adv_usd']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">مصارف به تفکیک کتگوری</div>
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead><tr><th>کتگوری</th><th>افغانی</th><th>دالر</th></tr></thead>
                    <tbody>
                    <?php if (empty($byCategory)): ?><tr><td colspan="3" class="text-center text-muted">مصرفی ثبت نشده</td></tr><?php endif; ?>
                    <?php foreach ($byCategory as $c): ?>
                        <tr><td><?= e($c['name_fa']) ?></td><td><?= formatMoney($c['afn']) ?></td><td><?= formatMoney($c['usd']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('sarafiChart'), {
    type: 'bar',
    data: {
        labels: ['افغانی', 'دالر'],
        datasets: [
            { label: 'حصول‌شده از صرافی', data: [<?= $sarafiReceived['AFN'] ?>, <?= $sarafiReceived['USD'] ?>], backgroundColor: '#1f8fd8' },
            { label: 'تسلیم‌شده به صرافی', data: [<?= $revenueSubmitted['AFN'] ?>, <?= $revenueSubmitted['USD'] ?>], backgroundColor: '#8a6fd6' }
        ]
    },
    options: { responsive: true }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
