<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'عدم دسترسی';
include __DIR__ . '/includes/header.php';
?>
<div class="text-center py-5">
    <i class="fa-solid fa-ban text-danger" style="font-size:60px"></i>
    <h3 class="mt-3">شما اجازه دسترسی به این بخش را ندارید</h3>
    <p class="text-muted">سطح دسترسی شما برای انجام این عملیات کافی نیست.</p>
    <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary">بازگشت به داشبورد</a>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
