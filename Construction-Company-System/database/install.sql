-- =====================================================================
-- سیستم مدیریت مالی پروژه‌های ساختمانی (Construction Company System)
-- ساختار دیتابیس - MySQL / MariaDB
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- نقش‌های کاربری (سطوح دسترسی چندگانه)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_key VARCHAR(30) NOT NULL UNIQUE,     -- super_admin, company_manager, project_manager, accountant, viewer
    name_fa VARCHAR(100) NOT NULL,
    level INT NOT NULL,                       -- 1=بالاترین سطح ... 5=پایین‌ترین سطح
    description VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- شرکت‌ها (سطح اول سلسله مراتب: شرکت → پروژه → حساب‌ها)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    logo_path VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- شرکت‌های صرافی (طرف مقابل تبادلات پولی)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sarafi_companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- پروژه‌ها (سطح دوم سلسله مراتب)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    sarafi_company_id INT DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(30) DEFAULT NULL,
    location VARCHAR(150) DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    status ENUM('active','completed','suspended') NOT NULL DEFAULT 'active',
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (sarafi_company_id) REFERENCES sarafi_companies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- کاربران سیستم
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    role_id INT NOT NULL,
    company_id INT DEFAULT NULL,          -- محدوده شرکت (برای مدیر شرکت و پایین‌تر)
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- دسترسی کاربر به پروژه‌های خاص (برای مدیر پروژه، محاسب، ناظر)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    project_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_project (user_id, project_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- واحدهای پولی
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS currencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,   -- AFN, USD, ...
    name_fa VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- انواع کهاته (حساب‌داران): S=پرسونل، M=ماشینری، P=بخش داخلی، C=قراردادی بزرگ، V=فروشنده
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kahta_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code CHAR(1) NOT NULL UNIQUE,       -- S, M, P, C, V
    name_fa VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- کهاته‌ها (حساب‌داران پروژه: پرسونل، ماشین‌آلات، بخش‌ها، قراردادی‌ها، فروشندگان)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kahta_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    kahta_type_id INT NOT NULL,
    code VARCHAR(20) NOT NULL,          -- کد تولید خودکار مثل S1, M3, C2 ...
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_project_code (project_id, code),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (kahta_type_id) REFERENCES kahta_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- رسیدات پول: دریافت پول از شرکت صرافی به پروژه
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS money_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    receipt_no VARCHAR(30) DEFAULT NULL,          -- نمبر دیتابیس/سند
    whatsapp_date DATE DEFAULT NULL,              -- تاریخ گزارش واتساپ
    receipt_date DATE NOT NULL,                   -- تاریخ حصول (میلادی ذخیره می‌شود)
    receipt_date_shamsi VARCHAR(20) DEFAULT NULL, -- تاریخ حصول به هجری شمسی (نمایشی)
    details TEXT DEFAULT NULL,
    sender VARCHAR(150) DEFAULT NULL,
    receiver VARCHAR(150) DEFAULT NULL,
    transfer_no VARCHAR(60) DEFAULT NULL,         -- نمبر حواله
    source_province VARCHAR(100) DEFAULT NULL,    -- محل اخذ پول / نام ولایت
    purpose VARCHAR(150) DEFAULT NULL,             -- برای مصرف و خرید در
    amount_project DECIMAL(18,2) NOT NULL DEFAULT 0,   -- مبلغ بنام پروژه
    currency_id INT NOT NULL,
    amount_sarafi_total DECIMAL(18,2) DEFAULT 0,       -- مبلغ جمع صرافی
    currency2_id INT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (currency2_id) REFERENCES currencies(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- کتگوری‌های مصرف
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS expense_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_fa VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- مصارف و پرداخت‌های پروژه
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    voucher_no VARCHAR(30) DEFAULT NULL,       -- نمبر دیتابیس/سند
    expense_date DATE NOT NULL,                -- تاریخ میلادی
    expense_date_shamsi VARCHAR(20) DEFAULT NULL,
    shamsi_month VARCHAR(20) DEFAULT NULL,
    details TEXT DEFAULT NULL,
    payer VARCHAR(150) DEFAULT NULL,           -- مصرف کننده / پرداخت کننده
    kahta_account_id INT DEFAULT NULL,         -- گیرنده / حساب - کهاته
    received_via VARCHAR(150) DEFAULT NULL,    -- بدست / از طریق
    bill_no VARCHAR(60) DEFAULT NULL,          -- نمبر بل
    paid_from VARCHAR(150) DEFAULT NULL,       -- پرداخت از
    category_id INT DEFAULT NULL,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    currency_id INT NOT NULL,
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (kahta_account_id) REFERENCES kahta_accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- پیش‌پرداخت/حواله به کهاته‌ها (منبع: منابع داخلی / ساحه / مرکز)
-- این جدول معادل ستون‌های «دریافت افغانی از ساحه/مرکز» و «دریافت دالر از ساحه/مرکز» در اکسل است
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kahta_advances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kahta_account_id INT NOT NULL,
    project_id INT NOT NULL,
    source ENUM('internal','site','center') NOT NULL DEFAULT 'site',  -- منابع داخلی / ساحه / مرکز
    currency_id INT NOT NULL,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    advance_date DATE NOT NULL,
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kahta_account_id) REFERENCES kahta_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- عواید/پول‌های تسلیم شده به صرافی
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS revenue_to_sarafi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    entry_no VARCHAR(30) DEFAULT NULL,
    entry_date DATE NOT NULL,
    details TEXT DEFAULT NULL,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    currency_id INT NOT NULL,
    source_desc VARCHAR(150) DEFAULT NULL,     -- از درک
    submitter VARCHAR(150) DEFAULT NULL,       -- تسلیم دهنده
    receiver VARCHAR(150) DEFAULT NULL,        -- تسلیم گیرنده
    location VARCHAR(150) DEFAULT NULL,        -- موقعیت
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ثبت فعالیت کاربران (Audit Log)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    username VARCHAR(60) DEFAULT NULL,
    action VARCHAR(30) NOT NULL,          -- login, logout, create, update, delete
    module VARCHAR(60) NOT NULL,          -- receipts, expenses, kahta, users, ...
    record_id INT DEFAULT NULL,
    description VARCHAR(500) DEFAULT NULL,
    old_data LONGTEXT DEFAULT NULL,
    new_data LONGTEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- تنظیمات عمومی سیستم (نام سیستم، لوگو، ...)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(60) PRIMARY KEY,
    setting_value TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
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
-- =====================================================================
-- افزونه دیتابیس نسخه ۲: مدیریت مشتریان، قراردادها و منابع بشری
-- (این فایل را بعد از schema.sql و seed.sql اجرا کنید — یا از install.sql استفاده کنید)
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- مشتریان (کارفرمایان) پروژه
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    customer_type ENUM('individual','company') NOT NULL DEFAULT 'individual',
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    id_number VARCHAR(60) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    attachment_path VARCHAR(255) DEFAULT NULL,
    attachment_name VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- قراردادها (با مشتریان یا با پیمانکاران/فروشندگان - کهاته‌های نوع C و V)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    contract_no VARCHAR(60) DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    party_type ENUM('customer','kahta') NOT NULL DEFAULT 'kahta',
    customer_id INT DEFAULT NULL,
    kahta_account_id INT DEFAULT NULL,
    contract_date DATE DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    amount DECIMAL(18,2) DEFAULT 0,
    currency_id INT DEFAULT NULL,
    status ENUM('pending','active','completed','terminated') NOT NULL DEFAULT 'active',
    scope_of_work TEXT DEFAULT NULL,
    payment_terms TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    attachment_path VARCHAR(255) DEFAULT NULL,
    attachment_name VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (kahta_account_id) REFERENCES kahta_accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- کارمندان (منابع بشری)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    father_name VARCHAR(150) DEFAULT NULL,
    tazkira_no VARCHAR(60) DEFAULT NULL,
    position VARCHAR(120) DEFAULT NULL,
    department VARCHAR(120) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    hire_date DATE DEFAULT NULL,
    termination_date DATE DEFAULT NULL,
    employment_type ENUM('permanent','contract','daily_wage') NOT NULL DEFAULT 'contract',
    salary_amount DECIMAL(18,2) DEFAULT 0,
    salary_currency_id INT DEFAULT NULL,
    status ENUM('active','terminated') NOT NULL DEFAULT 'active',
    kahta_account_id INT DEFAULT NULL,
    attachment_path VARCHAR(255) DEFAULT NULL,
    attachment_name VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (salary_currency_id) REFERENCES currencies(id),
    FOREIGN KEY (kahta_account_id) REFERENCES kahta_accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- حاضری روزانه کارمندان
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS employee_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    project_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present','absent','leave','holiday') NOT NULL DEFAULT 'present',
    notes VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_emp_date (employee_id, attendance_date),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- مرخصی‌های کارمندان
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS employee_leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    project_id INT NOT NULL,
    leave_type ENUM('annual','sick','unpaid','other') NOT NULL DEFAULT 'annual',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_count INT DEFAULT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
-- =====================================================================
-- افزونه دیتابیس نسخه ۳: مدیریت ماشین‌آلات (Machinery) و اتصال آن به قراردادها
-- (این فایل را بعد از schema_v2.sql اجرا کنید — یا از install.sql استفاده کنید)
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- ماشین‌آلات پروژه (کامیون، لودر، بلدوزر، جنراتور و ...)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS machinery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    machine_type VARCHAR(100) DEFAULT NULL,
    plate_or_serial_no VARCHAR(60) DEFAULT NULL,
    model VARCHAR(100) DEFAULT NULL,
    ownership_type ENUM('owned','rented') NOT NULL DEFAULT 'owned',
    owner_name VARCHAR(150) DEFAULT NULL,
    daily_rate DECIMAL(18,2) DEFAULT NULL,
    currency_id INT DEFAULT NULL,
    status ENUM('active','maintenance','inactive') NOT NULL DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    attachment_path VARCHAR(255) DEFAULT NULL,
    attachment_name VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- اتصال چندبه‌چند ماشین‌آلات به قراردادها (یک قرارداد می‌تواند شامل چند ماشین باشد)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contract_machinery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    machinery_id INT NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_contract_machine (contract_id, machinery_id),
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (machinery_id) REFERENCES machinery(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
