<?php
/**
 * مدیریت آپلود و دانلود فایل‌های ضمیمه (تصویر تذکره، اسکن قرارداد، CV و غیره)
 */

const UPLOAD_ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'];
const UPLOAD_MAX_SIZE = 8 * 1024 * 1024; // ۸ مگابایت

/**
 * پردازش فایل آپلودشده از یک فرم و ذخیره آن در uploads/{subDir}/{projectId}/
 * @return array{path:string,name:string}|null آرایه شامل مسیر نسبی و نام اصلی فایل، یا null اگر فایلی ارسال نشده باشد
 * @throws RuntimeException در صورت نامعتبر بودن فایل
 */
function handleFileUpload(string $fieldName, string $subDir, int $projectId): ?array
{
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('خطا در آپلود فایل. لطفاً دوباره تلاش کنید.');
    }
    $tmp = $_FILES[$fieldName]['tmp_name'];
    $origName = $_FILES[$fieldName]['name'];
    $size = (int)$_FILES[$fieldName]['size'];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    if (!in_array($ext, UPLOAD_ALLOWED_EXT, true)) {
        throw new RuntimeException('نوع فایل مجاز نیست. فرمت‌های مجاز: ' . implode('، ', UPLOAD_ALLOWED_EXT));
    }
    if ($size > UPLOAD_MAX_SIZE) {
        throw new RuntimeException('حجم فایل بیشتر از حد مجاز (۸ مگابایت) است.');
    }

    $dir = __DIR__ . '/../uploads/' . $subDir . '/' . $projectId;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('امکان ایجاد پوشه ذخیره‌سازی وجود ندارد.');
    }
    $storedName = bin2hex(random_bytes(10)) . '.' . $ext;
    $destPath = $dir . '/' . $storedName;
    if (!move_uploaded_file($tmp, $destPath)) {
        throw new RuntimeException('ذخیره فایل روی سرور ناموفق بود.');
    }
    return ['path' => $subDir . '/' . $projectId . '/' . $storedName, 'name' => $origName];
}

/** حذف فایل ضمیمه از دیسک (در صورت وجود) */
function deleteUploadedFile(?string $relativePath): void
{
    if (!$relativePath) { return; }
    $full = __DIR__ . '/../uploads/' . $relativePath;
    if (is_file($full)) { @unlink($full); }
}
