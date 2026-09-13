<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'پروژه‌ای یافت نشد';
include __DIR__ . '/includes/header.php';
?>
<div class="text-center py-5">
    <i class="fa-solid fa-diagram-project text-warning" style="font-size:60px"></i>
    <h3 class="mt-3">هیچ پروژه‌ای برای شما تعریف نشده است</h3>
    <p class="text-muted">لطفاً با مدیر سیستم تماس بگیرید تا شما را به یک پروژه اختصاص دهد.</p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
