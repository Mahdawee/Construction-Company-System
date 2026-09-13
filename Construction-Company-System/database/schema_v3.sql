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
