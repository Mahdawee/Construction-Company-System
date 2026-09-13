<?php
/**
 * خروجی اکسل بدون نیاز به کتابخانه خارجی (فرمت HTML قابل بازشدن در Excel)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$pdo = getDb();
$module = $_GET['module'] ?? '';

// خروجی داشبورد چند پروژه‌ای است و به پروژه جاری محدود نمی‌شود؛ بقیه ماژول‌ها به پروژه انتخاب‌شده نیاز دارند
if ($module !== 'dashboard_summary') {
    $projectId = requireCurrentProject($pdo, $user);
}

function dateFragNamed(string $col, ?string $from, ?string $to, array &$params): string
{
    $sql = '';
    if ($from) { $sql .= " AND $col >= ?"; $params[] = $from; }
    if ($to)   { $sql .= " AND $col <= ?"; $params[] = $to; }
    return $sql;
}

function xlsHeader(string $filename): void
{
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    echo "\xEF\xBB\xBF"; // BOM برای نمایش صحیح یونیکد
    echo '<html><head><meta charset="UTF-8"></head><body dir="rtl">';
}
function xlsFooter(): void
{
    echo '</body></html>';
}
function tbl(array $headers, array $rows): void
{
    echo '<table border="1"><tr>';
    foreach ($headers as $h) { echo '<th>' . e($h) . '</th>'; }
    echo '</tr>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $cell) { echo '<td>' . e((string)$cell) . '</td>'; }
        echo '</tr>';
    }
    echo '</table>';
}

switch ($module) {
    case 'receipts':
        $where = ['mr.project_id = :pid']; $params = [':pid' => $projectId];
        $search = trim($_GET['q'] ?? ''); $dateFrom = sanitizeDate($_GET['from'] ?? null); $dateTo = sanitizeDate($_GET['to'] ?? null);
        if ($search !== '') { $where[] = '(mr.sender LIKE :q OR mr.receiver LIKE :q OR mr.details LIKE :q OR mr.transfer_no LIKE :q)'; $params[':q'] = "%$search%"; }
        if ($dateFrom) { $where[] = 'mr.receipt_date >= :from'; $params[':from'] = $dateFrom; }
        if ($dateTo) { $where[] = 'mr.receipt_date <= :to'; $params[':to'] = $dateTo; }
        $stmt = $pdo->prepare('SELECT mr.*, c1.name_fa AS cur1, c2.name_fa AS cur2 FROM money_receipts mr LEFT JOIN currencies c1 ON c1.id=mr.currency_id LEFT JOIN currencies c2 ON c2.id=mr.currency2_id WHERE ' . implode(' AND ', $where) . ' ORDER BY mr.receipt_date DESC');
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        xlsHeader('receipts_' . date('Ymd'));
        tbl(['نمبر', 'تاریخ حصول', 'فرستنده', 'گیرنده', 'نمبر حواله', 'محل اخذ', 'برای مصرف در', 'مبلغ پروژه', 'واحد', 'جمع صرافی', 'واحد۲', 'ملاحظات'],
            array_map(fn($r) => [$r['receipt_no'] ?: $r['id'], $r['receipt_date'], $r['sender'], $r['receiver'], $r['transfer_no'], $r['source_province'], $r['purpose'], $r['amount_project'], $r['cur1'], $r['amount_sarafi_total'], $r['cur2'], $r['notes']], $data));
        xlsFooter();
        break;

    case 'expenses':
        $where = ['ex.project_id = :pid']; $params = [':pid' => $projectId];
        $search = trim($_GET['q'] ?? ''); $dateFrom = sanitizeDate($_GET['from'] ?? null); $dateTo = sanitizeDate($_GET['to'] ?? null);
        $categoryId = (int)($_GET['category_id'] ?? 0);
        if ($search !== '') { $where[] = '(ex.payer LIKE :q OR ex.details LIKE :q OR ex.bill_no LIKE :q)'; $params[':q'] = "%$search%"; }
        if ($dateFrom) { $where[] = 'ex.expense_date >= :from'; $params[':from'] = $dateFrom; }
        if ($dateTo) { $where[] = 'ex.expense_date <= :to'; $params[':to'] = $dateTo; }
        if ($categoryId) { $where[] = 'ex.category_id = :cat'; $params[':cat'] = $categoryId; }
        $stmt = $pdo->prepare("SELECT ex.*, c.name_fa AS cur_name, k.code AS kahta_code, k.name AS kahta_name, cat.name_fa AS category_name
            FROM expenses ex LEFT JOIN currencies c ON c.id=ex.currency_id LEFT JOIN kahta_accounts k ON k.id=ex.kahta_account_id LEFT JOIN expense_categories cat ON cat.id=ex.category_id
            WHERE " . implode(' AND ', $where) . " ORDER BY ex.expense_date DESC");
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        xlsHeader('expenses_' . date('Ymd'));
        tbl(['نمبر بل', 'تاریخ', 'پرداخت کننده', 'کهاته', 'کتگوری', 'بدست/از طریق', 'پرداخت از', 'مبلغ', 'واحد', 'تفصیل', 'ملاحظات'],
            array_map(fn($r) => [$r['voucher_no'] ?: $r['id'], $r['expense_date'], $r['payer'], trim(($r['kahta_code'] ?? '') . ' ' . ($r['kahta_name'] ?? '')), $r['category_name'], $r['received_via'], $r['paid_from'], $r['amount'], $r['cur_name'], $r['details'], $r['notes']], $data));
        xlsFooter();
        break;

    case 'revenue':
        $stmt = $pdo->prepare('SELECT rv.*, c.name_fa AS cur_name FROM revenue_to_sarafi rv JOIN currencies c ON c.id = rv.currency_id WHERE rv.project_id = :pid ORDER BY rv.entry_date DESC');
        $stmt->execute([':pid' => $projectId]);
        $data = $stmt->fetchAll();
        xlsHeader('revenue_' . date('Ymd'));
        tbl(['نمبر', 'تاریخ', 'تفصیل', 'از درک', 'تسلیم دهنده', 'تسلیم گیرنده', 'موقعیت', 'مبلغ', 'واحد', 'ملاحظات'],
            array_map(fn($r) => [$r['entry_no'] ?: $r['id'], $r['entry_date'], $r['details'], $r['source_desc'], $r['submitter'], $r['receiver'], $r['location'], $r['amount'], $r['cur_name'], $r['notes']], $data));
        xlsFooter();
        break;

    case 'kahta':
        $stmt = $pdo->prepare("SELECT k.*, t.name_fa AS type_name,
            COALESCE((SELECT SUM(a.amount) FROM kahta_advances a WHERE a.kahta_account_id=k.id AND a.currency_id=1),0) AS adv_afn,
            COALESCE((SELECT SUM(a.amount) FROM kahta_advances a WHERE a.kahta_account_id=k.id AND a.currency_id=2),0) AS adv_usd,
            COALESCE((SELECT SUM(e.amount) FROM expenses e WHERE e.kahta_account_id=k.id AND e.currency_id=1),0) AS exp_afn,
            COALESCE((SELECT SUM(e.amount) FROM expenses e WHERE e.kahta_account_id=k.id AND e.currency_id=2),0) AS exp_usd
            FROM kahta_accounts k JOIN kahta_types t ON t.id=k.kahta_type_id WHERE k.project_id = :pid ORDER BY t.code, k.code");
        $stmt->execute([':pid' => $projectId]);
        $data = $stmt->fetchAll();
        xlsHeader('kahta_' . date('Ymd'));
        tbl(['کد', 'نوع', 'نام', 'دریافتی AFN', 'دریافتی USD', 'مصرف‌شده AFN', 'مصرف‌شده USD', 'باقیمانده AFN', 'باقیمانده USD'],
            array_map(fn($r) => [$r['code'], $r['type_name'], $r['name'], $r['adv_afn'], $r['adv_usd'], $r['exp_afn'], $r['exp_usd'], $r['adv_afn'] - $r['exp_afn'], $r['adv_usd'] - $r['exp_usd']], $data));
        xlsFooter();
        break;

    case 'customers':
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE project_id = :pid ORDER BY name');
        $stmt->execute([':pid' => $projectId]);
        $data = $stmt->fetchAll();
        $typeLabels = ['individual' => 'شخص حقیقی', 'company' => 'شرکت/نهاد'];
        xlsHeader('customers_' . date('Ymd'));
        tbl(['نام', 'نوع', 'شخص رابط', 'تلفن', 'ایمیل', 'آدرس', 'شماره تذکره/جواز', 'وضعیت', 'ملاحظات'],
            array_map(fn($r) => [$r['name'], $typeLabels[$r['customer_type']] ?? $r['customer_type'], $r['contact_person'], $r['phone'], $r['email'], $r['address'], $r['id_number'], $r['is_active'] ? 'فعال' : 'غیرفعال', $r['notes']], $data));
        xlsFooter();
        break;

    case 'contracts':
        $stmt = $pdo->prepare("SELECT ct.*, cu.name AS customer_name, k.code AS kahta_code, k.name AS kahta_name, cur.name_fa AS cur_name,
            (SELECT GROUP_CONCAT(m.name SEPARATOR '، ') FROM contract_machinery cm JOIN machinery m ON m.id = cm.machinery_id WHERE cm.contract_id = ct.id) AS machinery_names
            FROM contracts ct LEFT JOIN customers cu ON cu.id = ct.customer_id LEFT JOIN kahta_accounts k ON k.id = ct.kahta_account_id
            LEFT JOIN currencies cur ON cur.id = ct.currency_id WHERE ct.project_id = :pid ORDER BY ct.contract_date DESC");
        $stmt->execute([':pid' => $projectId]);
        $data = $stmt->fetchAll();
        $statusLabels = ['pending' => 'در انتظار', 'active' => 'فعال', 'completed' => 'تکمیل‌شده', 'terminated' => 'فسخ‌شده'];
        xlsHeader('contracts_' . date('Ymd'));
        tbl(['نمبر قرارداد', 'عنوان', 'طرف قرارداد', 'ماشین‌آلات', 'تاریخ عقد', 'شروع', 'ختم', 'مبلغ', 'واحد', 'وضعیت', 'ملاحظات'],
            array_map(fn($r) => [$r['contract_no'] ?: $r['id'], $r['title'], $r['party_type'] === 'customer' ? ($r['customer_name'] ?? '-') : trim(($r['kahta_code'] ?? '') . ' ' . ($r['kahta_name'] ?? '')), $r['machinery_names'] ?: '-', $r['contract_date'], $r['start_date'], $r['end_date'], $r['amount'], $r['cur_name'], $statusLabels[$r['status']] ?? $r['status'], $r['notes']], $data));
        xlsFooter();
        break;

    case 'machinery':
        $stmt = $pdo->prepare('SELECT mc.*, cur.name_fa AS cur_name FROM machinery mc LEFT JOIN currencies cur ON cur.id = mc.currency_id WHERE mc.project_id = :pid ORDER BY mc.name');
        $stmt->execute([':pid' => $projectId]);
        $data = $stmt->fetchAll();
        $ownershipLabels = ['owned' => 'ملکیت شرکت', 'rented' => 'کرایی'];
        $statusLabels = ['active' => 'فعال', 'maintenance' => 'تعمیراتی', 'inactive' => 'غیرفعال'];
        xlsHeader('machinery_' . date('Ymd'));
        tbl(['نام', 'نوع', 'شماره پلیت/سریال', 'مودل', 'مالکیت', 'مالک/کرایه‌دهنده', 'نرخ روزانه', 'واحد', 'وضعیت', 'ملاحظات'],
            array_map(fn($r) => [$r['name'], $r['machine_type'], $r['plate_or_serial_no'], $r['model'], $ownershipLabels[$r['ownership_type']] ?? $r['ownership_type'], $r['owner_name'], $r['daily_rate'], $r['cur_name'], $statusLabels[$r['status']] ?? $r['status'], $r['notes']], $data));
        xlsFooter();
        break;

    case 'employees':
        $stmt = $pdo->prepare('SELECT e.*, c.name_fa AS cur_name FROM employees e LEFT JOIN currencies c ON c.id = e.salary_currency_id WHERE e.project_id = :pid ORDER BY e.full_name');
        $stmt->execute([':pid' => $projectId]);
        $data = $stmt->fetchAll();
        $typeLabels = ['permanent' => 'دایمی', 'contract' => 'قراردادی', 'daily_wage' => 'روزمزد'];
        xlsHeader('employees_' . date('Ymd'));
        tbl(['نام کامل', 'نام پدر', 'شماره تذکره', 'سمت', 'بخش', 'نوع استخدام', 'تاریخ استخدام', 'معاش', 'واحد', 'وضعیت', 'ملاحظات'],
            array_map(fn($r) => [$r['full_name'], $r['father_name'], $r['tazkira_no'], $r['position'], $r['department'], $typeLabels[$r['employment_type']] ?? $r['employment_type'], $r['hire_date'], $r['salary_amount'], $r['cur_name'], $r['status'] === 'active' ? 'فعال' : 'برکنارشده', $r['notes']], $data));
        xlsFooter();
        break;

    case 'project_report':
        $stmt = $pdo->prepare('SELECT p.name, c.name AS company_name FROM projects p JOIN companies c ON c.id=p.company_id WHERE p.id = :id');
        $stmt->execute([':id' => $projectId]);
        $project = $stmt->fetch();

        function sumCur(PDO $pdo, string $table, string $amt, string $cur, int $pid, string $extra = ''): array {
            $stmt = $pdo->prepare("SELECT c.code, SUM(t.$amt) AS total FROM $table t JOIN currencies c ON c.id=t.$cur WHERE t.project_id=:pid $extra GROUP BY c.code");
            $stmt->execute([':pid' => $pid]);
            $r = ['AFN' => 0, 'USD' => 0];
            foreach ($stmt->fetchAll() as $row) { $r[$row['code']] = (float)$row['total']; }
            return $r;
        }
        $receipts = sumCur($pdo, 'money_receipts', 'amount_project', 'currency_id', $projectId);
        $expenses = sumCur($pdo, 'expenses', 'amount', 'currency_id', $projectId);
        $sarafiRecv = sumCur($pdo, 'money_receipts', 'amount_sarafi_total', 'currency2_id', $projectId);
        $revenue = sumCur($pdo, 'revenue_to_sarafi', 'amount', 'currency_id', $projectId);

        xlsHeader('project_report_' . date('Ymd'));
        echo '<h3>' . e($project['company_name'] . ' - ' . $project['name']) . '</h3>';
        tbl(['شرح', 'افغانی', 'دالر'], [
            ['جمله پول حصول‌شده (رسیدات)', $receipts['AFN'], $receipts['USD']],
            ['جمله شد مصارف', $expenses['AFN'], $expenses['USD']],
            ['پول باقیمانده/موجود', $receipts['AFN'] - $expenses['AFN'], $receipts['USD'] - $expenses['USD']],
            ['مجموع پول حصول‌شده از صرافی', $sarafiRecv['AFN'], $sarafiRecv['USD']],
            ['مجموع پول تسلیم‌شده به صرافی', $revenue['AFN'], $revenue['USD']],
            ['مانده حساب با صرافی', $sarafiRecv['AFN'] - $revenue['AFN'], $sarafiRecv['USD'] - $revenue['USD']],
        ]);
        xlsFooter();
        break;

    case 'dashboard_summary':
        $dateFrom = sanitizeDate($_GET['from'] ?? null);
        $dateTo = sanitizeDate($_GET['to'] ?? null);
        $projectIds = accessibleProjectIds($pdo, $user);
        $rangeLabel = ($dateFrom ?: 'ابتدا') . ' تا ' . ($dateTo ?: 'اکنون');
        $rows = [];
        if (!empty($projectIds)) {
            $in = implode(',', array_fill(0, count($projectIds), '?'));
            $parR = []; $fragR = dateFragNamed('mr.receipt_date', $dateFrom, $dateTo, $parR);
            $parE = []; $fragE = dateFragNamed('ex.expense_date', $dateFrom, $dateTo, $parE);
            $stmt = $pdo->prepare("
                SELECT p.name, comp.name AS company_name,
                    COALESCE((SELECT SUM(mr.amount_project) FROM money_receipts mr WHERE mr.project_id = p.id AND mr.currency_id = 1 $fragR),0) AS rec_afn,
                    COALESCE((SELECT SUM(mr.amount_project) FROM money_receipts mr WHERE mr.project_id = p.id AND mr.currency_id = 2 $fragR),0) AS rec_usd,
                    COALESCE((SELECT SUM(ex.amount) FROM expenses ex WHERE ex.project_id = p.id AND ex.currency_id = 1 $fragE),0) AS exp_afn,
                    COALESCE((SELECT SUM(ex.amount) FROM expenses ex WHERE ex.project_id = p.id AND ex.currency_id = 2 $fragE),0) AS exp_usd
                FROM projects p JOIN companies comp ON comp.id = p.company_id
                WHERE p.id IN ($in)
                ORDER BY comp.name, p.name
            ");
            $stmt->execute(array_merge($parR, $parR, $parE, $parE, $projectIds));
            $rows = $stmt->fetchAll();
        }
        xlsHeader('dashboard_' . date('Ymd'));
        echo '<h3>خلاصه داشبورد - بازه: ' . e($rangeLabel) . '</h3>';
        tbl(['شرکت', 'پروژه', 'رسیدات AFN', 'رسیدات USD', 'مصارف AFN', 'مصارف USD', 'باقیمانده AFN', 'باقیمانده USD'],
            array_map(fn($r) => [$r['company_name'], $r['name'], $r['rec_afn'], $r['rec_usd'], $r['exp_afn'], $r['exp_usd'], $r['rec_afn'] - $r['exp_afn'], $r['rec_usd'] - $r['exp_usd']], $rows));
        xlsFooter();
        break;

    default:
        die('نوع خروجی نامعتبر است.');
}
