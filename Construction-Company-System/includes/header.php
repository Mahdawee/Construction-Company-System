<?php
/**
 * سربرگ مشترک تمام صفحات (باید بعد از require شدن auth.php و پس از requireLogin() فراخوانی شود)
 * متغیر اختیاری $pageTitle قبل از include تعریف شود.
 */
$__user = currentUser();
$__pdo = getDb();
$__projects = $__user ? accessibleProjects($__pdo, $__user) : [];
$__currentProjectId = $__user ? getCurrentProjectId($__pdo, $__user) : null;
$pageTitle = $pageTitle ?? APP_NAME;

$roleLabels = [
    'super_admin' => 'سوپر ادمین',
    'company_manager' => 'مدیر شرکت',
    'project_manager' => 'مدیر پروژه',
    'accountant' => 'محاسب',
    'viewer' => 'ناظر',
];
$currentRoute = basename($_SERVER['SCRIPT_NAME']);
$currentDir = basename(dirname($_SERVER['SCRIPT_NAME']));
function navActive(string $dir, string $wantDir): string
{
    return $dir === $wantDir ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php if ($__user): ?>
    <aside class="sidebar" id="sidebar">
        <div class="brand"><i class="fa-solid fa-building"></i> <?= e(APP_NAME) ?></div>
        <nav class="nav flex-column py-2">
            <a class="nav-link <?= navActive($currentRoute,'index.php') === 'active' && $currentDir==='ccs' ? 'active':'' ?>" href="<?= BASE_URL ?>/index.php"><i class="fa-solid fa-gauge"></i> داشبورد</a>

            <?php if (canManageCompanies($__user)): ?>
            <div class="nav-section-title">مدیریت سیستم</div>
            <a class="nav-link <?= navActive($currentDir,'companies') ?>" href="<?= BASE_URL ?>/companies/list.php"><i class="fa-solid fa-city"></i> شرکت‌ها</a>
            <?php endif; ?>

            <?php if (canManageProjects($__user)): ?>
            <a class="nav-link <?= navActive($currentDir,'projects') ?>" href="<?= BASE_URL ?>/projects/list.php"><i class="fa-solid fa-diagram-project"></i> پروژه‌ها</a>
            <?php endif; ?>

            <?php if (canManageUsers($__user)): ?>
            <a class="nav-link <?= navActive($currentDir,'users') ?>" href="<?= BASE_URL ?>/users/list.php"><i class="fa-solid fa-users-gear"></i> کاربران</a>
            <?php endif; ?>

            <?php if (!empty($__projects)): ?>
            <div class="nav-section-title">پروژه جاری</div>
            <a class="nav-link <?= navActive($currentDir,'kahta') ?>" href="<?= BASE_URL ?>/kahta/list.php"><i class="fa-solid fa-people-group"></i> کهاته‌ها</a>
            <a class="nav-link <?= navActive($currentDir,'receipts') ?>" href="<?= BASE_URL ?>/receipts/list.php"><i class="fa-solid fa-money-bill-transfer"></i> رسیدات پول</a>
            <a class="nav-link <?= navActive($currentDir,'expenses') ?>" href="<?= BASE_URL ?>/expenses/list.php"><i class="fa-solid fa-file-invoice-dollar"></i> مصارف</a>
            <a class="nav-link <?= navActive($currentDir,'advances') ?>" href="<?= BASE_URL ?>/advances/list.php"><i class="fa-solid fa-hand-holding-dollar"></i> پیش‌پرداخت به کهاته‌ها</a>
            <a class="nav-link <?= navActive($currentDir,'revenue') ?>" href="<?= BASE_URL ?>/revenue/list.php"><i class="fa-solid fa-vault"></i> عواید تسلیم به صرافی</a>
            <a class="nav-link <?= navActive($currentDir,'reports') ?>" href="<?= BASE_URL ?>/reports/project_report.php"><i class="fa-solid fa-chart-column"></i> راپور پروژه</a>

            <div class="nav-section-title">مدیریت پروژه</div>
            <a class="nav-link <?= navActive($currentDir,'customers') ?>" href="<?= BASE_URL ?>/customers/list.php"><i class="fa-solid fa-address-book"></i> مشتریان</a>
            <a class="nav-link <?= navActive($currentDir,'contracts') ?>" href="<?= BASE_URL ?>/contracts/list.php"><i class="fa-solid fa-file-signature"></i> قراردادها</a>
            <a class="nav-link <?= navActive($currentDir,'machinery') ?>" href="<?= BASE_URL ?>/machinery/list.php"><i class="fa-solid fa-truck"></i> ماشین‌آلات</a>
            <a class="nav-link <?= navActive($currentDir,'employees') ?>" href="<?= BASE_URL ?>/employees/list.php"><i class="fa-solid fa-users-line"></i> منابع بشری</a>
            <a class="nav-link <?= navActive($currentDir,'attendance') ?>" href="<?= BASE_URL ?>/attendance/daily.php"><i class="fa-solid fa-calendar-check"></i> حاضری روزانه</a>
            <a class="nav-link <?= navActive($currentDir,'leaves') ?>" href="<?= BASE_URL ?>/leaves/list.php"><i class="fa-solid fa-plane-departure"></i> مرخصی‌ها</a>
            <?php endif; ?>

            <?php if (canManageProjects($__user)): ?>
            <div class="nav-section-title">تنظیمات</div>
            <a class="nav-link <?= navActive($currentDir,'settings') ?>" href="<?= BASE_URL ?>/settings/categories.php"><i class="fa-solid fa-sliders"></i> کتگوری‌های مصرف</a>
            <a class="nav-link <?= navActive($currentDir,'settings') && ($_GET['p'] ?? '')==='sarafi' ?>" href="<?= BASE_URL ?>/settings/sarafi.php"><i class="fa-solid fa-money-bill-wave"></i> شرکت‌های صرافی</a>
            <?php endif; ?>
            <?php if (in_array($__user['role_key'], ['super_admin','company_manager'], true)): ?>
            <a class="nav-link <?= navActive($currentDir,'audit') ?>" href="<?= BASE_URL ?>/audit/list.php"><i class="fa-solid fa-clock-rotate-left"></i> ثبت فعالیت‌ها</a>
            <?php endif; ?>
        </nav>
    </aside>
    <?php endif; ?>

    <div class="main-content">
        <?php if ($__user): ?>
        <div class="topbar">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-secondary d-md-none no-print" onclick="document.getElementById('sidebar').classList.toggle('show')"><i class="fa-solid fa-bars"></i></button>
                <h5 class="mb-0"><?= e($pageTitle) ?></h5>
            </div>
            <div class="d-flex align-items-center gap-2 no-print">
                <?php if (!empty($__projects)): ?>
                <form method="get" class="d-flex align-items-center gap-1">
                    <?php foreach ($_GET as $k => $v) { if ($k !== 'project_id') echo '<input type="hidden" name="'.e($k).'" value="'.e($v).'">'; } ?>
                    <select name="project_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:180px">
                        <?php foreach ($__projects as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === (int)$__currentProjectId ? 'selected' : '' ?>>
                            <?= e($p['company_name']) ?> - <?= e($p['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php endif; ?>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fa-solid fa-user-circle"></i> <?= e($__user['full_name']) ?>
                        <span class="badge bg-secondary badge-role"><?= e($roleLabels[$__user['role_key']] ?? $__user['role_key']) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/profile.php"><i class="fa-solid fa-id-badge"></i> مشخصات من</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="fa-solid fa-right-from-bracket"></i> خروج</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="page-body">
            <?php foreach (getFlashes() as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show no-print" role="alert">
                <?= e($f['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endforeach; ?>
