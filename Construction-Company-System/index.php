<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$projectIds = accessibleProjectIds($pdo, $user);
$pageTitle = 'داشبورد';

/** ساخت بخش شرطی تاریخ برای یک کوئری + پارامترهای positional آن (به ترتیب ظهور) */
function dateFrag(string $col, ?string $from, ?string $to): array
{
    $sql = ''; $params = [];
    if ($from) { $sql .= " AND $col >= ?"; $params[] = $from; }
    if ($to)   { $sql .= " AND $col <= ?"; $params[] = $to; }
    return [$sql, $params];
}

// ------------------------------------------------------------------
// تعیین بازه زمانی گزارش: پیش‌فرض‌های آماده یا تاریخ دستی
// ------------------------------------------------------------------
$today = date('Y-m-d');
$customFrom = sanitizeDate($_GET['from'] ?? null);
$customTo = sanitizeDate($_GET['to'] ?? null);
$preset = $_GET['preset'] ?? '';

if ($customFrom || $customTo) {
    $dateFrom = $customFrom;
    $dateTo = $customTo;
    $activePreset = 'custom';
} else {
    $activePreset = $preset ?: 'all';
    switch ($activePreset) {
        case 'today':      $dateFrom = $today; $dateTo = $today; break;
        case 'yesterday':  $d = date('Y-m-d', strtotime('-1 day')); $dateFrom = $d; $dateTo = $d; break;
        case 'last7':      $dateFrom = date('Y-m-d', strtotime('-6 days')); $dateTo = $today; break;
        case 'this_month': $dateFrom = date('Y-m-01'); $dateTo = $today; break;
        case 'this_year':  $dateFrom = date('Y-01-01'); $dateTo = $today; break;
        default:           $activePreset = 'all'; $dateFrom = null; $dateTo = null; break;
    }
}

$presetLabels = [
    'today' => 'امروز', 'yesterday' => 'دیروز', 'last7' => '۷ روز اخیر',
    'this_month' => 'این ماه', 'this_year' => 'امسال', 'all' => 'همه بازه‌ها', 'custom' => 'بازه دلخواه',
];
if ($activePreset === 'custom') {
    $rangeLabel = ($dateFrom ?: '...') . ' تا ' . ($dateTo ?: '...');
} else {
    $rangeLabel = $presetLabels[$activePreset];
    if ($dateFrom && $dateTo && $dateFrom !== $dateTo) { $rangeLabel .= ' (' . $dateFrom . ' تا ' . $dateTo . ')'; }
    elseif ($dateFrom) { $rangeLabel .= ' (' . $dateFrom . ')'; }
}

// آیا نمودار باید به تفکیک روز نمایش داده شود یا ماه؟
$spanDays = ($dateFrom && $dateTo) ? (strtotime($dateTo) - strtotime($dateFrom)) / 86400 : null;
$groupByDay = $spanDays !== null && $spanDays <= 62;
$chartFormatSql = $groupByDay ? '%Y-%m-%d' : '%Y-%m';
$chartTitle = $groupByDay ? 'روند روزانه رسیدات و مصارف (افغانی)' : 'روند ماهانه رسیدات و مصارف (افغانی)';

$totals = ['receipts' => ['AFN' => 0, 'USD' => 0], 'expenses' => ['AFN' => 0, 'USD' => 0], 'revenue' => ['AFN' => 0, 'USD' => 0]];
$projectRows = [];
$trend = [];
$categoryBreakdown = [];

if (!empty($projectIds)) {
    $in = implode(',', array_fill(0, count($projectIds), '?'));

    // مجموع رسیدات پول به تفکیک واحد پولی
    [$frag, $fragParams] = dateFrag('mr.receipt_date', $dateFrom, $dateTo);
    $stmt = $pdo->prepare("SELECT c.code, SUM(mr.amount_project) AS total FROM money_receipts mr JOIN currencies c ON c.id = mr.currency_id WHERE mr.project_id IN ($in) $frag GROUP BY c.code");
    $stmt->execute(array_merge($projectIds, $fragParams));
    foreach ($stmt->fetchAll() as $r) { $totals['receipts'][$r['code']] = (float)$r['total']; }

    // مجموع مصارف به تفکیک واحد پولی
    [$frag, $fragParams] = dateFrag('ex.expense_date', $dateFrom, $dateTo);
    $stmt = $pdo->prepare("SELECT c.code, SUM(ex.amount) AS total FROM expenses ex JOIN currencies c ON c.id = ex.currency_id WHERE ex.project_id IN ($in) $frag GROUP BY c.code");
    $stmt->execute(array_merge($projectIds, $fragParams));
    foreach ($stmt->fetchAll() as $r) { $totals['expenses'][$r['code']] = (float)$r['total']; }

    // مجموع عواید تسلیم شده به صرافی
    [$frag, $fragParams] = dateFrag('rv.entry_date', $dateFrom, $dateTo);
    $stmt = $pdo->prepare("SELECT c.code, SUM(rv.amount) AS total FROM revenue_to_sarafi rv JOIN currencies c ON c.id = rv.currency_id WHERE rv.project_id IN ($in) $frag GROUP BY c.code");
    $stmt->execute(array_merge($projectIds, $fragParams));
    foreach ($stmt->fetchAll() as $r) { $totals['revenue'][$r['code']] = (float)$r['total']; }

    // جدول خلاصه به تفکیک پروژه (با اعمال فیلتر تاریخ در هر یک از زیرکوئری‌ها)
    [$fragR, $parR] = dateFrag('mr.receipt_date', $dateFrom, $dateTo);
    [$fragE, $parE] = dateFrag('ex.expense_date', $dateFrom, $dateTo);
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, comp.name AS company_name,
            COALESCE((SELECT SUM(mr.amount_project) FROM money_receipts mr WHERE mr.project_id = p.id AND mr.currency_id = 1 $fragR),0) AS rec_afn,
            COALESCE((SELECT SUM(mr.amount_project) FROM money_receipts mr WHERE mr.project_id = p.id AND mr.currency_id = 2 $fragR),0) AS rec_usd,
            COALESCE((SELECT SUM(ex.amount) FROM expenses ex WHERE ex.project_id = p.id AND ex.currency_id = 1 $fragE),0) AS exp_afn,
            COALESCE((SELECT SUM(ex.amount) FROM expenses ex WHERE ex.project_id = p.id AND ex.currency_id = 2 $fragE),0) AS exp_usd
        FROM projects p JOIN companies comp ON comp.id = p.company_id
        WHERE p.id IN ($in)
        ORDER BY comp.name, p.name
    ");
    $stmt->execute(array_merge($parR, $parR, $parE, $parE, $projectIds));
    $projectRows = $stmt->fetchAll();

    // روند رسیدات و مصارف (روزانه یا ماهانه، بسته به بازه انتخاب‌شده) - افغانی
    [$fragR2, $parR2] = dateFrag('receipt_date', $dateFrom, $dateTo);
    [$fragE2, $parE2] = dateFrag('expense_date', $dateFrom, $dateTo);
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(d.dt,'$chartFormatSql') AS bucket, 'receipt' AS kind, SUM(d.amt) AS total
        FROM (SELECT receipt_date AS dt, amount_project AS amt FROM money_receipts WHERE project_id IN ($in) AND currency_id = 1 $fragR2) d
        GROUP BY bucket
        UNION ALL
        SELECT DATE_FORMAT(d.dt,'$chartFormatSql') AS bucket, 'expense' AS kind, SUM(d.amt) AS total
        FROM (SELECT expense_date AS dt, amount AS amt FROM expenses WHERE project_id IN ($in) AND currency_id = 1 $fragE2) d
        GROUP BY bucket
        ORDER BY bucket
    ");
    $stmt->execute(array_merge($projectIds, $parR2, $projectIds, $parE2));
    foreach ($stmt->fetchAll() as $r) {
        $trend[$r['bucket']][$r['kind']] = (float)$r['total'];
    }
    ksort($trend);

    // توزیع مصارف بر اساس کتگوری (افغانی) در بازه انتخاب‌شده
    [$fragC, $parC] = dateFrag('ex.expense_date', $dateFrom, $dateTo);
    $stmt = $pdo->prepare("
        SELECT COALESCE(cat.name_fa,'بدون کتگوری') AS name_fa, SUM(ex.amount) AS total
        FROM expenses ex LEFT JOIN expense_categories cat ON cat.id = ex.category_id
        WHERE ex.project_id IN ($in) AND ex.currency_id = 1 $fragC
        GROUP BY cat.name_fa ORDER BY total DESC LIMIT 8
    ");
    $stmt->execute(array_merge($projectIds, $parC));
    $categoryBreakdown = $stmt->fetchAll();
}

$balanceAfn = $totals['receipts']['AFN'] - $totals['expenses']['AFN'];
$balanceUsd = $totals['receipts']['USD'] - $totals['expenses']['USD'];

include __DIR__ . '/includes/header.php';
?>
<div class="card mb-3 no-print">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-auto">
                <label class="form-label mb-1 d-block">گزارش سریع</label>
                <div class="btn-group btn-group-sm" role="group">
                    <?php foreach (['today' => 'امروز', 'yesterday' => 'دیروز', 'last7' => '۷ روز اخیر', 'this_month' => 'این ماه', 'this_year' => 'امسال', 'all' => 'همه'] as $key => $label): ?>
                    <a href="?preset=<?= $key ?>" class="btn <?= $activePreset === $key ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">از تاریخ</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">تا تاریخ</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-md-auto">
                <button class="btn btn-sm btn-dark"><i class="fa-solid fa-filter"></i> اعمال بازه دلخواه</button>
            </div>
            <div class="col-md-auto ms-auto text-muted small">
                <i class="fa-solid fa-calendar-day"></i> بازه گزارش جاری: <strong><?= e($rangeLabel) ?></strong>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3 col-6">
        <div class="stat-card bg-afn"><i class="fa-solid fa-sack-dollar stat-icon"></i>
            <div class="stat-label">مجموع رسیدات (افغانی)</div>
            <div class="stat-value"><?= formatMoney($totals['receipts']['AFN']) ?></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-usd"><i class="fa-solid fa-dollar-sign stat-icon"></i>
            <div class="stat-label">مجموع رسیدات (دالر)</div>
            <div class="stat-value"><?= formatMoney($totals['receipts']['USD']) ?></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-expense"><i class="fa-solid fa-file-invoice-dollar stat-icon"></i>
            <div class="stat-label">مجموع مصارف (افغانی / دالر)</div>
            <div class="stat-value"><?= formatMoney($totals['expenses']['AFN']) ?> / <?= formatMoney($totals['expenses']['USD']) ?></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-balance"><i class="fa-solid fa-scale-balanced stat-icon"></i>
            <div class="stat-label">باقیمانده (افغانی / دالر)</div>
            <div class="stat-value"><?= formatMoney($balanceAfn) ?> / <?= formatMoney($balanceUsd) ?></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><?= e($chartTitle) ?></div>
            <div class="card-body">
                <?php if (empty($trend)): ?>
                <p class="text-muted text-center py-4 mb-0">داده‌ای برای این بازه یافت نشد</p>
                <?php else: ?>
                <canvas id="trendChart" height="110"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">توزیع مصارف بر اساس کتگوری (در بازه جاری)</div>
            <div class="card-body">
                <?php if (empty($categoryBreakdown)): ?>
                <p class="text-muted text-center py-4 mb-0">مصرفی در این بازه ثبت نشده است</p>
                <?php else: ?>
                <canvas id="categoryChart" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>خلاصه به تفکیک پروژه (بازه: <?= e($rangeLabel) ?>)</span>
        <a href="<?= BASE_URL ?>/export/export.php?module=dashboard_summary&from=<?= e($dateFrom) ?>&to=<?= e($dateTo) ?>&preset=<?= e($activePreset) ?>" class="btn btn-sm btn-success no-print"><i class="fa-solid fa-file-excel"></i> خروجی اکسل</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th>شرکت</th><th>پروژه</th>
                <th>رسیدات (AFN)</th><th>رسیدات (USD)</th>
                <th>مصارف (AFN)</th><th>مصارف (USD)</th>
                <th>باقیمانده (AFN)</th><th>باقیمانده (USD)</th>
            </tr></thead>
            <tbody>
            <?php if (empty($projectRows)): ?>
                <tr><td colspan="8" class="text-center text-muted py-3">پروژه‌ای یافت نشد</td></tr>
            <?php endif; ?>
            <?php foreach ($projectRows as $p): ?>
                <tr>
                    <td><?= e($p['company_name']) ?></td>
                    <td><?= e($p['name']) ?></td>
                    <td><?= formatMoney($p['rec_afn']) ?></td>
                    <td><?= formatMoney($p['rec_usd']) ?></td>
                    <td><?= formatMoney($p['exp_afn']) ?></td>
                    <td><?= formatMoney($p['exp_usd']) ?></td>
                    <td class="fw-bold"><?= formatMoney($p['rec_afn'] - $p['exp_afn']) ?></td>
                    <td class="fw-bold"><?= formatMoney($p['rec_usd'] - $p['exp_usd']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
<script>
<?php if (!empty($trend)): ?>
const trendLabels = <?= json_encode(array_keys($trend)) ?>;
const receiptsData = <?= json_encode(array_map(fn($m) => $m['receipt'] ?? 0, $trend)) ?>;
const expensesData = <?= json_encode(array_map(fn($m) => $m['expense'] ?? 0, $trend)) ?>;

new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: trendLabels,
        datasets: [
            { label: 'رسیدات', data: receiptsData, backgroundColor: '#28a862' },
            { label: 'مصارف', data: expensesData, backgroundColor: '#d1442f' }
        ]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true } } }
});
<?php endif; ?>

<?php if (!empty($categoryBreakdown)): ?>
const catLabels = <?= json_encode(array_column($categoryBreakdown, 'name_fa')) ?>;
const catData = <?= json_encode(array_map('floatval', array_column($categoryBreakdown, 'total'))) ?>;
new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: catLabels,
        datasets: [{ data: catData, backgroundColor: ['#10557a','#28a862','#d1442f','#8a6fd6','#1f8fd8','#e0a800','#6c757d','#20c997'] }]
    },
    options: { responsive: true }
});
<?php endif; ?>
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
