<?php
/**
 * مدیریت احراز هویت و کنترل سطح دسترسی (RBAC چند سطحی)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

/** اطلاعات کاربر واردشده جاری یا null */
function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** الزام به ورود؛ در غیر این‌صورت به صفحه ورود هدایت می‌شود */
function requireLogin(): array
{
    $user = currentUser();
    if (!$user) {
        redirect(BASE_URL . '/login.php');
    }
    return $user;
}

/**
 * الزام به داشتن یکی از نقش‌های مجاز
 * @param string[] $roles کلید نقش‌های مجاز، مثال: ['super_admin','company_manager']
 */
function requireRole(array $roles): array
{
    $user = requireLogin();
    if (!in_array($user['role_key'], $roles, true)) {
        http_response_code(403);
        include __DIR__ . '/../403.php';
        exit;
    }
    return $user;
}

/** آیا کاربر می‌تواند اطلاعات مالی (رسیدات/مصارف/کهاته/عواید) ایجاد یا ویرایش کند */
function canEditData(array $user): bool
{
    return in_array($user['role_key'], ['super_admin', 'company_manager', 'project_manager', 'accountant'], true);
}

/** آیا کاربر می‌تواند رکوردهای مالی را حذف کند */
function canDeleteData(array $user): bool
{
    return in_array($user['role_key'], ['super_admin', 'company_manager', 'project_manager'], true);
}

/** آیا کاربر فقط دسترسی مشاهده دارد */
function isViewer(array $user): bool
{
    return $user['role_key'] === 'viewer';
}

/** آیا کاربر می‌تواند شرکت‌ها را مدیریت کند */
function canManageCompanies(array $user): bool
{
    return $user['role_key'] === 'super_admin';
}

/** آیا کاربر می‌تواند پروژه‌ها را مدیریت (ایجاد/ویرایش) کند */
function canManageProjects(array $user): bool
{
    return in_array($user['role_key'], ['super_admin', 'company_manager'], true);
}

/** آیا کاربر می‌تواند کاربران را مدیریت کند */
function canManageUsers(array $user): bool
{
    return in_array($user['role_key'], ['super_admin', 'company_manager'], true);
}

/**
 * لیست شناسه پروژه‌هایی که کاربر جاری به آن‌ها دسترسی دارد
 */
function accessibleProjectIds(PDO $pdo, array $user): array
{
    if ($user['role_key'] === 'super_admin') {
        $rows = $pdo->query('SELECT id FROM projects ORDER BY id')->fetchAll();
    } elseif ($user['role_key'] === 'company_manager') {
        $stmt = $pdo->prepare('SELECT id FROM projects WHERE company_id = :cid ORDER BY id');
        $stmt->execute([':cid' => $user['company_id']]);
        $rows = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare('SELECT project_id AS id FROM user_projects WHERE user_id = :uid');
        $stmt->execute([':uid' => $user['id']]);
        $rows = $stmt->fetchAll();
    }
    return array_map(static fn($r) => (int)$r['id'], $rows);
}

/**
 * پروژه‌های قابل دسترسی به همراه نام (برای منوی انتخاب پروژه)
 */
function accessibleProjects(PDO $pdo, array $user): array
{
    $ids = accessibleProjectIds($pdo, $user);
    if (empty($ids)) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT p.*, c.name AS company_name FROM projects p JOIN companies c ON c.id = p.company_id WHERE p.id IN ($in) ORDER BY c.name, p.name");
    $stmt->execute($ids);
    return $stmt->fetchAll();
}

/** بررسی این‌که آیا کاربر به یک پروژه مشخص دسترسی دارد */
function hasProjectAccess(PDO $pdo, array $user, int $projectId): bool
{
    return in_array($projectId, accessibleProjectIds($pdo, $user), true);
}

/**
 * دریافت پروژه جاری انتخاب‌شده (از نشست) و اعتبارسنجی دسترسی؛
 * در صورت نامعتبر بودن، اولین پروژه‌ی قابل دسترس انتخاب می‌شود.
 */
function getCurrentProjectId(PDO $pdo, array $user): ?int
{
    $ids = accessibleProjectIds($pdo, $user);
    if (empty($ids)) {
        return null;
    }
    if (isset($_GET['project_id']) && in_array((int)$_GET['project_id'], $ids, true)) {
        $_SESSION['current_project_id'] = (int)$_GET['project_id'];
    }
    $current = $_SESSION['current_project_id'] ?? null;
    if ($current === null || !in_array((int)$current, $ids, true)) {
        $current = $ids[0];
        $_SESSION['current_project_id'] = $current;
    }
    return (int)$current;
}

/** الزام به وجود پروژه انتخاب‌شده معتبر - در غیر این‌صورت پیام و توقف */
function requireCurrentProject(PDO $pdo, array $user): int
{
    $pid = getCurrentProjectId($pdo, $user);
    if ($pid === null) {
        include __DIR__ . '/../no_project.php';
        exit;
    }
    return $pid;
}
