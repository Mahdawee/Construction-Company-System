-- =====================================================================
-- داده‌های اولیه (Seed Data)
-- =====================================================================
SET NAMES utf8mb4;

-- نقش‌ها (سطوح دسترسی)
INSERT INTO roles (id, role_key, name_fa, level, description) VALUES
(1, 'super_admin', 'سوپر ادمین', 1, 'دسترسی کامل به تمام شرکت‌ها، پروژه‌ها و تنظیمات سیستم'),
(2, 'company_manager', 'مدیر شرکت', 2, 'مدیریت تمام پروژه‌های یک شرکت مشخص'),
(3, 'project_manager', 'مدیر پروژه', 3, 'مدیریت یک یا چند پروژه مشخص'),
(4, 'accountant', 'محاسب', 4, 'ثبت رسیدات، مصارف، عواید و کهاته‌ها'),
(5, 'viewer', 'ناظر', 5, 'فقط مشاهده گزارش‌ها و اطلاعات (بدون امکان ویرایش)')
ON DUPLICATE KEY UPDATE name_fa = VALUES(name_fa);

-- واحدهای پولی
INSERT INTO currencies (id, code, name_fa) VALUES
(1, 'AFN', 'افغانی'),
(2, 'USD', 'دالر امریکایی')
ON DUPLICATE KEY UPDATE name_fa = VALUES(name_fa);

-- انواع کهاته
INSERT INTO kahta_types (id, code, name_fa, description) VALUES
(1, 'S', 'پرسونل', 'کود پرسونل: S1 - S100'),
(2, 'M', 'ماشینری', 'کود ماشینری: M1 - M100'),
(3, 'P', 'بخش‌های داخلی', 'کود بخش‌های داخلی: P, PB, PO, PC ...'),
(4, 'C', 'قراردادی‌های بزرگ', 'کود قراردادی‌های بزرگ: C1 - C10'),
(5, 'V', 'فروشنده‌ها', 'کود فروشنده‌ها: V1 - V100')
ON DUPLICATE KEY UPDATE name_fa = VALUES(name_fa);

-- کتگوری‌های مصرف پیش‌فرض
INSERT INTO expense_categories (name_fa) VALUES
('مواد ساختمانی'), ('اجرت کارگر'), ('حمل و نقل'), ('سوخت و روغنیات'),
('مصارف اداری'), ('تعمیرات و ترمیم'), ('کرایه ماشین‌آلات'), ('معاشات پرسونل'),
('مصارف صرافی و بانکی'), ('متفرقه')
ON DUPLICATE KEY UPDATE name_fa = VALUES(name_fa);

-- تنظیمات عمومی
INSERT INTO settings (setting_key, setting_value) VALUES
('system_name', 'سیستم مدیریت مالی پروژه‌های ساختمانی'),
('default_currency', 'AFN')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- کاربر سوپر ادمین پیش‌فرض
-- یوزرنیم: admin   |   رمز عبور: Admin@2026   (لطفاً بلافاصله بعد از ورود تغییر دهید)
INSERT INTO users (id, username, password_hash, full_name, email, role_id, is_active) VALUES
(1, 'admin', '$2y$12$eprhwWsI3LqghUxJLfrvder85fi/rJYmywuwZrkoeJopmw3tm0/HK', 'مدیر سیستم', NULL, 1, 1)
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

-- شرکت و پروژه نمونه (بر اساس فایل اکسل ارائه شده - لطفاً نام واقعی را در بخش «شرکت‌ها» ویرایش کنید)
INSERT INTO companies (id, name, address, phone) VALUES
(1, 'شرکت ساختمانی نمونه', NULL, NULL)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO sarafi_companies (id, company_id, name) VALUES
(1, 1, 'شرکت صرافی و خدمات پولی نمونه')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO projects (id, company_id, sarafi_company_id, name, code, status) VALUES
(1, 1, 1, 'پروژه نمونه', 'PRJ-001', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- انتساب کاربر ادمین شرکت نمونه (اختیاری - سوپر ادمین به همه دسترسی دارد)

-- --------------------------------------------------------------------
-- ایمپورت داده واقعی موجود در فایل اکسل ارائه شده (دیتابیس سفید 2.xlsx)
-- شیت «رسیدات پول»، ردیف شماره ۱: رسید ۱۰۰,۰۰۰ افغانی، معادل ۱۰ دالر جمع صرافی
-- --------------------------------------------------------------------
INSERT INTO money_receipts
    (id, project_id, receipt_no, receipt_date, amount_project, currency_id, amount_sarafi_total, currency2_id, created_by)
VALUES
    (1, 1, '1', '2026-08-02', 100000.00, 1, 10.00, 2, 1)
ON DUPLICATE KEY UPDATE amount_project = VALUES(amount_project);
