// اسکریپت‌های عمومی رابط کاربری
document.addEventListener('DOMContentLoaded', function () {
    // تایید حذف
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // فعال‌سازی خودکار tooltip های بوت‌استرپ
    var tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(function (el) { new bootstrap.Tooltip(el); });
});
