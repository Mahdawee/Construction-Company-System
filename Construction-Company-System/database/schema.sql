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
