<?php
/**
 * توابع کمکی عمومی
 */

/** خروجی امن HTML */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** انتقال به یک آدرس دیگر و توقف اجرا */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** تنظیم پیام فلش (نمایش یک‌بار پس از ریدایرکت) */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** دریافت و پاک کردن پیام‌های فلش */
function getFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** تولید/بازیابی توکن CSRF */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** بررسی صحت توکن CSRF - در صورت نامعتبر بودن متوقف می‌شود */
function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        die('درخواست نامعتبر است (CSRF). لطفاً صفحه را دوباره بارگذاری کنید.');
    }
}

/** فرمت نمایش مبلغ پول با جداکننده هزارگان */
function formatMoney($amount, int $decimals = 2): string
{
    if ($amount === null || $amount === '') {
        return '0';
    }
    // اگر عدد صحیح باشد بدون اعشار نمایش بده
    $amount = (float)$amount;
    if ($decimals === 2 && floor($amount) == $amount) {
        $decimals = 0;
    }
    return number_format($amount, $decimals);
}

/** نام واحد پولی بر اساس کد */
function currencyLabel(?string $code): string
{
    $map = ['AFN' => 'افغانی', 'USD' => 'دالر'];
    return $map[$code] ?? ($code ?? '');
}

/** ثبت یک رکورد در جدول فعالیت کاربران (Audit Log) */
function auditLog(string $action, string $module, ?int $recordId = null, string $description = '', $oldData = null, $newData = null): void
{
    try {
        $pdo = getDb();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_log (user_id, username, action, module, record_id, description, old_data, new_data, ip_address)
             VALUES (:uid, :uname, :action, :module, :rid, :desc, :old, :new, :ip)'
        );
        $user = currentUser();
        $stmt->execute([
            ':uid'    => $user['id'] ?? null,
            ':uname'  => $user['username'] ?? null,
            ':action' => $action,
            ':module' => $module,
            ':rid'    => $recordId,
            ':desc'   => $description,
            ':old'    => $oldData !== null ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
            ':new'    => $newData !== null ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
            ':ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        // ثبت لاگ هرگز نباید باعث توقف برنامه شود
        error_log('Audit log error: ' . $e->getMessage());
    }
}

/**
 * تولید کد خودکار برای یک کهاته بر اساس نوع آن (مثال: S1, S2, M1, C3 ...)
 */
function generateKahtaCode(PDO $pdo, int $projectId, string $typeCode): string
{
    $stmt = $pdo->prepare(
        "SELECT code FROM kahta_accounts WHERE project_id = :pid AND code LIKE :pattern"
    );
    $stmt->execute([':pid' => $projectId, ':pattern' => $typeCode . '%']);
    $max = 0;
    foreach ($stmt->fetchAll() as $row) {
        if (preg_match('/^' . preg_quote($typeCode, '/') . '(\d+)$/', $row['code'], $m)) {
            $max = max($max, (int)$m[1]);
        }
    }
    return $typeCode . ($max + 1);
}

/** تبدیل رشته ورودی تاریخ (yyyy-mm-dd) به مقدار معتبر یا null */
function sanitizeDate(?string $date): ?string
{
    if (!$date) {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return ($d && $d->format('Y-m-d') === $date) ? $date : null;
}

/** صفحه‌بندی ساده - محاسبه آفست */
function paginate(int $totalRows, int $perPage, int $currentPage): array
{
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    return [$totalPages, $currentPage, $offset];
}

/** ساخت رشته کوئری برای لینک‌های صفحه‌بندی/فیلتر با نگه‌داشتن پارامترهای فعلی */
function queryWith(array $overrides): string
{
    $params = array_merge($_GET, $overrides);
    return '?' . http_build_query($params);
}

/**
 * نگه‌داشتن مقدار فعلی یک فیلد انتخابی در فورم ویرایش
 *
 * فهرست‌های کشویی/چک‌باکسی معمولاً فقط رکوردهای «فعال» را نشان می‌دهند
 * (is_active = 1 یا status != 'inactive'). اگر رکوردی که در حال ویرایش است به
 * گزینه‌ای لینک باشد که بعداً غیرفعال شده، آن گزینه در HTML وجود نخواهد داشت؛
 * در نتیجه مرورگر یا گزینه‌ی خالی را می‌فرستد (و لینک بی‌صدا پاک می‌شود) و یا
 * وقتی گزینه‌ی خالی وجود ندارد، نخستین گزینه را می‌فرستد (و لینک بی‌صدا به یک
 * رکورد دیگر تغییر می‌کند).
 *
 * این تابع گزینه‌های لینک‌شده‌ی مفقود را به فهرست اضافه می‌کند تا مقدار ذخیره‌شده
 * همیشه از یک رفت‌وبرگشتِ فرم سالم بیرون بیاید. گزینه‌ی اضافه‌شده در برچسب خود
 * با «(غیرفعال)» علامت‌گذاری می‌شود تا برای کاربر روشن باشد.
 *
 * @param array          $rows     ردیف‌های گزینه‌ای که قبلاً خوانده شده‌اند (هرکدام دارای id)
 * @param int|array|null $linked   شناسه(های) فعلیِ لینک‌شده در رکورد
 * @param string         $sql      کوئری SELECT با همان ستون‌ها برای «یک» شناسه (با یک ?)
 * @param array          $bind     مقادیر بایندِ پیش از شناسه
 * @param string         $labelCol ستونی که نشانه‌ی «(غیرفعال)» به آن اضافه می‌شود
 * @return array همان فهرست، به‌همراه گزینه‌های مفقود
 */
function includeLinkedOptions(PDO $pdo, array $rows, $linked, string $sql, array $bind = [], string $labelCol = 'name'): array
{
    $ids = array_filter(array_map('intval', is_array($linked) ? $linked : [$linked]));
    if (!$ids) {
        return $rows;
    }

    $have = [];
    foreach ($rows as $r) {
        $have[(int)($r['id'] ?? 0)] = true;
    }

    $stmt = $pdo->prepare($sql);
    foreach ($ids as $linkedId) {
        if (isset($have[$linkedId])) {
            continue;
        }
        $stmt->execute(array_merge($bind, [$linkedId]));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            continue;
        }
        if (isset($row[$labelCol]) && is_string($row[$labelCol])) {
            $row[$labelCol] .= ' (غیرفعال)';
        }
        $rows[] = $row;
        $have[$linkedId] = true;
    }

    return $rows;
}
