-- =====================================================================
-- داده‌های نمونه آزمایشی (Sample / Test Data)
-- این فایل یک «شرکت» و «پروژه» کاملاً جداگانه و آزمایشی می‌سازد و حدود ۱۰۰ رکورد
-- نمونه (مالی + مشتریان/قراردادها/ماشین‌آلات/منابع بشری) در همان پروژه ثبت می‌کند.
-- به داده‌های واقعی موجود شما دست نمی‌زند. هر زمان که خواستید، کافی است از بخش
-- «شرکت‌ها» همین شرکت آزمایشی را حذف کنید تا تمام این داده‌های نمونه پاک شوند
-- (حذف شرکت به‌صورت زنجیره‌ای پروژه و تمام اطلاعات مرتبط با آن را نیز حذف می‌کند).
-- =====================================================================
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- شرکت، شرکت صرافی و پروژه آزمایشی
-- ---------------------------------------------------------------------
INSERT INTO companies (name, address, phone, is_active) VALUES ('شرکت آزمایشی (داده نمونه)', 'صرف برای آزمایش سیستم', '0700112233', 1);
SET @test_company_id = LAST_INSERT_ID();

INSERT INTO sarafi_companies (company_id, name, contact_person, phone) VALUES (@test_company_id, 'صرافی آزمایشی نمونه', 'حاجی کریمی', '0700998877');
SET @test_sarafi_id = LAST_INSERT_ID();

INSERT INTO projects (company_id, sarafi_company_id, name, code, location, start_date, status, description)
VALUES (@test_company_id, @test_sarafi_id, 'پروژه آزمایشی (داده نمونه)', 'TEST-001', 'کابل', '2026-01-05', 'active', 'این پروژه صرفاً جهت آزمایش و دمو سیستم با داده نمونه ساخته شده است.');
SET @test_project_id = LAST_INSERT_ID();

SET @afn = (SELECT id FROM currencies WHERE code='AFN' LIMIT 1);
SET @usd = (SELECT id FROM currencies WHERE code='USD' LIMIT 1);
SET @admin_id = (SELECT id FROM users WHERE username='admin' LIMIT 1);
SET @k_s = (SELECT id FROM kahta_types WHERE code='S' LIMIT 1);
SET @k_m = (SELECT id FROM kahta_types WHERE code='M' LIMIT 1);
SET @k_p = (SELECT id FROM kahta_types WHERE code='P' LIMIT 1);
SET @k_c = (SELECT id FROM kahta_types WHERE code='C' LIMIT 1);
SET @k_v = (SELECT id FROM kahta_types WHERE code='V' LIMIT 1);
SET @cat1 = (SELECT id FROM expense_categories WHERE name_fa='مواد ساختمانی' LIMIT 1);
SET @cat2 = (SELECT id FROM expense_categories WHERE name_fa='اجرت کارگر' LIMIT 1);
SET @cat3 = (SELECT id FROM expense_categories WHERE name_fa='حمل و نقل' LIMIT 1);
SET @cat4 = (SELECT id FROM expense_categories WHERE name_fa='سوخت و روغنیات' LIMIT 1);
SET @cat5 = (SELECT id FROM expense_categories WHERE name_fa='مصارف اداری' LIMIT 1);
SET @cat6 = (SELECT id FROM expense_categories WHERE name_fa='تعمیرات و ترمیم' LIMIT 1);
SET @cat7 = (SELECT id FROM expense_categories WHERE name_fa='کرایه ماشین‌آلات' LIMIT 1);
SET @cat8 = (SELECT id FROM expense_categories WHERE name_fa='معاشات پرسونل' LIMIT 1);
SET @cat9 = (SELECT id FROM expense_categories WHERE name_fa='مصارف صرافی و بانکی' LIMIT 1);
SET @cat10 = (SELECT id FROM expense_categories WHERE name_fa='متفرقه' LIMIT 1);

-- ---------------------------------------------------------------------
-- کهاته‌ها (حساب‌داران) - ۸ رکورد
-- ---------------------------------------------------------------------
INSERT INTO kahta_accounts (project_id, kahta_type_id, code, name, phone, is_active) VALUES (@test_project_id, @k_s, 'S1', 'احمد ولی', '0700001001', 1);
SET @kahta1 = LAST_INSERT_ID();
INSERT INTO kahta_accounts (project_id, kahta_type_id, code, name, phone, is_active) VALUES (@test_project_id, @k_s, 'S2', 'محمد یوسف', '0700001002', 1);
SET @kahta2 = LAST_INSERT_ID();
INSERT INTO kahta_accounts (project_id, kahta_type_id, code, name, phone, is_active) VALUES (@test_project_id, @k_s, 'S3', 'نور آغا', '0700001003', 1);
SET @kahta3 = LAST_INSERT_ID();
INSERT INTO kahta_accounts (project_id, kahta_type_id, code, name, phone, is_active) VALUES (@test_project_id, @k_m, 'M1', 'کامیون شماره ۱ (کهاته)', '0700001004', 1);
SET @kahta4 = LAST_INSERT_ID();
INSERT INTO kahta_accounts (project_id, kahta_type_id, code, name, phone, is_active) VALUES (@test_project_id, @k_m, 'M2', 'لودر کوماتسو (کهاته)', '0700001005', 1);
SET @kahta5 = LAST_INSERT_ID();
INSERT INTO kahta_accounts (project_id, kahta_type_id, code, name, phone, is_active) VALUES (@test_project_id, @k_p, 'P1', 'بخش ذخیره و لوژستیک', '0700001006', 1);
SET @kahta6 = LAST_INSERT_ID();
INSERT INTO kahta_accounts (project_id, kahta_type_id, code, name, phone, is_active) VALUES (@test_project_id, @k_c, 'C1', 'شرکت قراردادی الفتح', '0700001007', 1);
SET @kahta7 = LAST_INSERT_ID();
INSERT INTO kahta_accounts (project_id, kahta_type_id, code, name, phone, is_active) VALUES (@test_project_id, @k_v, 'V1', 'فروشگاه مواد ساختمانی کابل', '0700001008', 1);
SET @kahta8 = LAST_INSERT_ID();

-- ---------------------------------------------------------------------
-- رسیدات پول - ۱۰ رکورد
-- ---------------------------------------------------------------------
INSERT INTO money_receipts (project_id, receipt_no, receipt_date, sender, receiver, transfer_no, source_province, purpose, amount_project, currency_id, amount_sarafi_total, currency2_id, notes, created_by)
VALUES (@test_project_id, 'TR-1', '2026-02-01', 'شرکت صرافی آریانا', 'مسئول ساحه', 'HW-2001', 'هرات', 'پرداخت معاشات', 203000, @afn, 2900, @usd, 'رسید نمونه آزمایشی شماره 1', @admin_id);
INSERT INTO money_receipts (project_id, receipt_no, receipt_date, sender, receiver, transfer_no, source_province, purpose, amount_project, currency_id, amount_sarafi_total, currency2_id, notes, created_by)
VALUES (@test_project_id, 'TR-2', '2026-08-24', 'دفتر مرکزی شرکت', 'دفتر پروژه', 'HW-2002', 'مزار شریف', 'مصارف جاری پروژه', 51000, @afn, 728.57, @usd, 'رسید نمونه آزمایشی شماره 2', @admin_id);
INSERT INTO money_receipts (project_id, receipt_no, receipt_date, sender, receiver, transfer_no, source_province, purpose, amount_project, currency_id, amount_sarafi_total, currency2_id, notes, created_by)
VALUES (@test_project_id, 'TR-3', '2026-06-27', 'حواله از دبی', 'مسئول مالی', 'HW-2003', 'قندهار', 'کرایه ماشین‌آلات', 297000, @afn, 4242.86, @usd, 'رسید نمونه آزمایشی شماره 3', @admin_id);
INSERT INTO money_receipts (project_id, receipt_no, receipt_date, sender, receiver, transfer_no, source_province, purpose, amount_project, currency_id, amount_sarafi_total, currency2_id, notes, created_by)
VALUES (@test_project_id, 'TR-4', '2026-05-03', 'شرکت انتقال وجه کابل', 'مدیر پروژه', 'HW-2004', 'ننگرهار', 'مصارف اداری', 284000, @afn, 4057.14, @usd, 'رسید نمونه آزمایشی شماره 4', @admin_id);
INSERT INTO money_receipts (project_id, receipt_no, receipt_date, sender, receiver, transfer_no, source_province, purpose, amount_project, currency_id, amount_sarafi_total, currency2_id, notes, created_by)
VALUES (@test_project_id, 'TR-5', '2026-07-14', 'حاجی محمد اسماعیل', 'محاسب پروژه', 'HW-2005', 'کابل', 'خرید مواد ساختمانی', 120000, @afn, 1714.29, @usd, 'رسید نمونه آزمایشی شماره 5', @admin_id);
INSERT INTO money_receipts (project_id, receipt_no, receipt_date, sender, receiver, transfer_no, source_province, purpose, amount_project, currency_id, amount_sarafi_total, currency2_id, notes, created_by)
VALUES (@test_project_id, 'TR-6', '2026-09-26', 'شرکت صرافی آریانا', 'مسئول ساحه', 'HW-2006', 'هرات', 'پرداخت معاشات', 274000, @afn, 3914.29, @usd, 'رسید نمونه آزمایشی شماره 6', @admin_id);
INSERT INTO money_receipts (project_id, receipt_no, receipt_date, sender, receiver, transfer_no, source_province, purpose, amount_project, currency_id, amount_sarafi_total, currency2_id, notes, created_by)
VALUES (@test_project_id, 'TR-7', '2026-09-04', 'دفتر مرکزی شرکت', 'دفتر پروژه', 'HW-2007', 'مزار شریف', 'مصارف جاری پروژه', 139000, @afn, 1985.71, @usd, 'رسید نمونه آزمایشی شماره 7', @admin_id);
INSERT INTO money_receipts (project_id, receipt_no, receipt_date, sender, receiver, transfer_no, source_province, purpose, amount_project, currency_id, amount_sarafi_total, currency2_id, notes, created_by)
VALUES (@test_project_id, 'TR-8', '2026-01-09', 'حواله از دبی', 'مسئول مالی', 'HW-2008', 'قندهار', 'کرایه ماشین‌آلات', 199000, @afn, 2842.86, @usd, 'رسید نمونه آزمایشی شماره 8', @admin_id);
INSERT INTO money_receipts (project_id, receipt_no, receipt_date, sender, receiver, transfer_no, source_province, purpose, amount_project, currency_id, amount_sarafi_total, currency2_id, notes, created_by)
VALUES (@test_project_id, 'TR-9', '2026-05-06', 'شرکت انتقال وجه کابل', 'مدیر پروژه', 'HW-2009', 'ننگرهار', 'مصارف اداری', 273000, @afn, 3900, @usd, 'رسید نمونه آزمایشی شماره 9', @admin_id);
INSERT INTO money_receipts (project_id, receipt_no, receipt_date, sender, receiver, transfer_no, source_province, purpose, amount_project, currency_id, amount_sarafi_total, currency2_id, notes, created_by)
VALUES (@test_project_id, 'TR-10', '2026-06-04', 'حاجی محمد اسماعیل', 'محاسب پروژه', 'HW-2010', 'کابل', 'خرید مواد ساختمانی', 161000, @afn, 2300, @usd, 'رسید نمونه آزمایشی شماره 10', @admin_id);

-- ---------------------------------------------------------------------
-- مصارف - ۱۵ رکورد
-- ---------------------------------------------------------------------
INSERT INTO expenses (project_id, voucher_no, expense_date, details, payer, kahta_account_id, received_via, bill_no, paid_from, category_id, amount, currency_id, notes, created_by)
VALUES (@test_project_id, 'BL-1', '2026-03-03', 'اجرت کارگران هفته', 'مسئول ساحه', @kahta2, 'نقدی', 'INV-3001', 'صندوق پروژه', @cat2, 30000, @afn, 'مصرف نمونه آزمایشی شماره 1', @admin_id);
INSERT INTO expenses (project_id, voucher_no, expense_date, details, payer, kahta_account_id, received_via, bill_no, paid_from, category_id, amount, currency_id, notes, created_by)
VALUES (@test_project_id, 'BL-2', '2026-04-21', 'کرایه ترانسپورت مواد', 'انجنیر ساحه', @kahta3, 'نقدی', 'INV-3002', 'صندوق پروژه', @cat3, 53000, @afn, 'مصرف نمونه آزمایشی شماره 2', @admin_id);
INSERT INTO expenses (project_id, voucher_no, expense_date, details, payer, kahta_account_id, received_via, bill_no, paid_from, category_id, amount, currency_id, notes, created_by)
VALUES (@test_project_id, 'BL-3', '2026-04-24', 'تیل و روغنیات ماشین', 'مدیر اداری', @kahta4, 'نقدی', 'INV-3003', 'صندوق پروژه', @cat4, 59000, @afn, 'مصرف نمونه آزمایشی شماره 3', @admin_id);
INSERT INTO expenses (project_id, voucher_no, expense_date, details, payer, kahta_account_id, received_via, bill_no, paid_from, category_id, amount, currency_id, notes, created_by)
VALUES (@test_project_id, 'BL-4', '2026-03-27', 'ترمیم موتر پروژه', 'محاسب پروژه', @kahta5, 'نقدی', 'INV-3004', 'صندوق پروژه', @cat5, 28000, @afn, 'مصرف نمونه آزمایشی شماره 4', @admin_id);
INSERT INTO expenses (project_id, voucher_no, expense_date, details, payer, kahta_account_id, received_via, bill_no, paid_from, category_id, amount, currency_id, notes, created_by)
VALUES (@test_project_id, 'BL-5', '2026-05-12', 'خرید تجهیزات دفتر', 'مسئول ساحه', @kahta6, 'نقدی', 'INV-3005', 'صندوق پروژه', @cat6, 32000, @afn, 'مصرف نمونه آزمایشی شماره 5', @admin_id);
INSERT INTO expenses (project_id, voucher_no, expense_date, details, payer, kahta_account_id, received_via, bill_no, paid_from, category_id, amount, currency_id, notes, created_by)
VALUES (@test_project_id, 'BL-6', '2026-07-27', 'کرایه ماشین‌آلات', 'انجنیر ساحه', @kahta7, 'نقدی', 'INV-3006', 'صندوق پروژه', @cat7, 35000, @afn, 'مصرف نمونه آزمایشی شماره 6', @admin_id);
INSERT INTO expenses (project_id, voucher_no, expense_date, details, payer, kahta_account_id, received_via, bill_no, paid_from, category_id, amount, currency_id, notes, created_by)
VALUES (@test_project_id, 'BL-7', '2026-02-27', 'معاش پرسونل', 'مدیر اداری', @kahta8, 'نقدی', 'INV-3007', 'صندوق پروژه', @cat8, 61000, @afn, 'مصرف نمونه آزمایشی شماره 7', @admin_id);
INSERT INTO expenses (project_id, voucher_no, expense_date, details, payer, kahta_account_id, received_via, bill_no, paid_from, category_id, amount, currency_id, notes, created_by)
VALUES (@test_project_id, 'BL-8', '2026-06-01', 'کمیشن صرافی', 'محاسب پروژه', @kahta1, 'نقدی', 'INV-3008', 'صندوق پروژه', @cat9, 8000, @afn, 'مصرف نمونه آزمایشی شماره 8', @admin_id);
INSERT INTO expenses (project_id, voucher_no, expense_date, details, payer, kahta_account_id, received_via, bill_no, paid_from, category_id, amount, currency_id, notes, created_by)
VALUES (@test_project_id, 'BL-9', '2026-09-18', 'مصارف متفرقه ساحه', 'مسئول ساحه', @kahta2, 'نقدی', 'INV-3009', 'صندوق پروژه', @cat10, 49000, @afn, 'مصرف نمونه آزمایشی شماره 9', @admin_id);
INSERT INTO expenses (project_id, voucher_no, expense_date, details, payer, kahta_account_id, received_via, bill_no, paid_from, category_id, amount, currency_id, notes, created_by)
VALUES (@test_project_id, 'BL-10', '2026-03-01', 'خرید سمنت و ریگ', 'انجنیر ساحه', @kahta3, 'نقدی', 'INV-3010', 'صندوق پروژه', @cat1, 76000, @afn, 'مصرف نمونه آزمایشی شماره 10', @admin_id);
INSERT INTO expenses (project_id, voucher_no, expense_date, details, payer, kahta_account_id, received_via, bill_no, paid_from, category_id, amount, currency_id, notes, created_by)
VALUES (@test_project_id, 'BL-11', '2026-06-07', 'اجرت کارگران هفته', 'مدیر اداری', @kahta4, 'نقدی', 'INV-3011', 'صندوق پروژه', @cat2, 67000, @afn, 'مصرف نمونه آزمایشی شماره 11', @admin_id);
INSERT INTO expenses (project_id, voucher_no, expense_date, details, payer, kahta_account_id, received_via, bill_no, paid_from, category_id, amount, currency_id, notes, created_by)
VALUES (@test_project_id, 'BL-12', '2026-02-01', 'کرایه ترانسپورت مواد', 'محاسب پروژه', @kahta5, 'نقدی', 'INV-3012', 'صندوق پروژه', @cat3, 27000, @afn, 'مصرف نمونه آزمایشی شماره 12', @admin_id);
INSERT INTO expenses (project_id, voucher_no, expense_date, details, payer, kahta_account_id, received_via, bill_no, paid_from, category_id, amount, currency_id, notes, created_by)
VALUES (@test_project_id, 'BL-13', '2026-09-16', 'تیل و روغنیات ماشین', 'مسئول ساحه', @kahta6, 'نقدی', 'INV-3013', 'صندوق پروژه', @cat4, 30000, @afn, 'مصرف نمونه آزمایشی شماره 13', @admin_id);
INSERT INTO expenses (project_id, voucher_no, expense_date, details, payer, kahta_account_id, received_via, bill_no, paid_from, category_id, amount, currency_id, notes, created_by)
VALUES (@test_project_id, 'BL-14', '2026-02-26', 'ترمیم موتر پروژه', 'انجنیر ساحه', @kahta7, 'نقدی', 'INV-3014', 'صندوق پروژه', @cat5, 38000, @afn, 'مصرف نمونه آزمایشی شماره 14', @admin_id);
INSERT INTO expenses (project_id, voucher_no, expense_date, details, payer, kahta_account_id, received_via, bill_no, paid_from, category_id, amount, currency_id, notes, created_by)
VALUES (@test_project_id, 'BL-15', '2026-05-15', 'خرید تجهیزات دفتر', 'مدیر اداری', @kahta8, 'نقدی', 'INV-3015', 'صندوق پروژه', @cat6, 33000, @afn, 'مصرف نمونه آزمایشی شماره 15', @admin_id);

-- ---------------------------------------------------------------------
-- پیش‌پرداخت به کهاته‌ها - ۸ رکورد
-- ---------------------------------------------------------------------
INSERT INTO kahta_advances (kahta_account_id, project_id, source, currency_id, amount, advance_date, notes, created_by)
VALUES (@kahta2, @test_project_id, 'site', @afn, 29000, '2026-01-09', 'پیش‌پرداخت نمونه آزمایشی شماره 1', @admin_id);
INSERT INTO kahta_advances (kahta_account_id, project_id, source, currency_id, amount, advance_date, notes, created_by)
VALUES (@kahta3, @test_project_id, 'center', @afn, 30000, '2026-03-12', 'پیش‌پرداخت نمونه آزمایشی شماره 2', @admin_id);
INSERT INTO kahta_advances (kahta_account_id, project_id, source, currency_id, amount, advance_date, notes, created_by)
VALUES (@kahta4, @test_project_id, 'internal', @afn, 85000, '2026-05-06', 'پیش‌پرداخت نمونه آزمایشی شماره 3', @admin_id);
INSERT INTO kahta_advances (kahta_account_id, project_id, source, currency_id, amount, advance_date, notes, created_by)
VALUES (@kahta5, @test_project_id, 'site', @afn, 56000, '2026-02-04', 'پیش‌پرداخت نمونه آزمایشی شماره 4', @admin_id);
INSERT INTO kahta_advances (kahta_account_id, project_id, source, currency_id, amount, advance_date, notes, created_by)
VALUES (@kahta6, @test_project_id, 'center', @afn, 73000, '2026-01-27', 'پیش‌پرداخت نمونه آزمایشی شماره 5', @admin_id);
INSERT INTO kahta_advances (kahta_account_id, project_id, source, currency_id, amount, advance_date, notes, created_by)
VALUES (@kahta7, @test_project_id, 'internal', @afn, 92000, '2026-01-10', 'پیش‌پرداخت نمونه آزمایشی شماره 6', @admin_id);
INSERT INTO kahta_advances (kahta_account_id, project_id, source, currency_id, amount, advance_date, notes, created_by)
VALUES (@kahta8, @test_project_id, 'site', @afn, 11000, '2026-02-07', 'پیش‌پرداخت نمونه آزمایشی شماره 7', @admin_id);
INSERT INTO kahta_advances (kahta_account_id, project_id, source, currency_id, amount, advance_date, notes, created_by)
VALUES (@kahta1, @test_project_id, 'center', @afn, 12000, '2026-03-06', 'پیش‌پرداخت نمونه آزمایشی شماره 8', @admin_id);

-- ---------------------------------------------------------------------
-- عواید تسلیم به صرافی - ۶ رکورد
-- ---------------------------------------------------------------------
INSERT INTO revenue_to_sarafi (project_id, entry_no, entry_date, details, amount, currency_id, source_desc, submitter, receiver, location, notes, created_by)
VALUES (@test_project_id, 'RV-1', '2026-07-02', 'تسلیمی نقدی به صرافی', 125000, @afn, 'باقیمانده صندوق پروژه', 'محاسب پروژه', 'صرافی آزمایشی نمونه', 'کابل', 'رکورد نمونه آزمایشی شماره 1', @admin_id);
INSERT INTO revenue_to_sarafi (project_id, entry_no, entry_date, details, amount, currency_id, source_desc, submitter, receiver, location, notes, created_by)
VALUES (@test_project_id, 'RV-2', '2026-03-10', 'تسلیمی نقدی به صرافی', 28000, @afn, 'باقیمانده صندوق پروژه', 'محاسب پروژه', 'صرافی آزمایشی نمونه', 'کابل', 'رکورد نمونه آزمایشی شماره 2', @admin_id);
INSERT INTO revenue_to_sarafi (project_id, entry_no, entry_date, details, amount, currency_id, source_desc, submitter, receiver, location, notes, created_by)
VALUES (@test_project_id, 'RV-3', '2026-06-16', 'تسلیمی نقدی به صرافی', 60000, @afn, 'باقیمانده صندوق پروژه', 'محاسب پروژه', 'صرافی آزمایشی نمونه', 'کابل', 'رکورد نمونه آزمایشی شماره 3', @admin_id);
INSERT INTO revenue_to_sarafi (project_id, entry_no, entry_date, details, amount, currency_id, source_desc, submitter, receiver, location, notes, created_by)
VALUES (@test_project_id, 'RV-4', '2026-07-22', 'تسلیمی نقدی به صرافی', 84000, @afn, 'باقیمانده صندوق پروژه', 'محاسب پروژه', 'صرافی آزمایشی نمونه', 'کابل', 'رکورد نمونه آزمایشی شماره 4', @admin_id);
INSERT INTO revenue_to_sarafi (project_id, entry_no, entry_date, details, amount, currency_id, source_desc, submitter, receiver, location, notes, created_by)
VALUES (@test_project_id, 'RV-5', '2026-09-06', 'تسلیمی نقدی به صرافی', 121000, @afn, 'باقیمانده صندوق پروژه', 'محاسب پروژه', 'صرافی آزمایشی نمونه', 'کابل', 'رکورد نمونه آزمایشی شماره 5', @admin_id);
INSERT INTO revenue_to_sarafi (project_id, entry_no, entry_date, details, amount, currency_id, source_desc, submitter, receiver, location, notes, created_by)
VALUES (@test_project_id, 'RV-6', '2026-07-04', 'تسلیمی نقدی به صرافی', 114000, @afn, 'باقیمانده صندوق پروژه', 'محاسب پروژه', 'صرافی آزمایشی نمونه', 'کابل', 'رکورد نمونه آزمایشی شماره 6', @admin_id);

-- ---------------------------------------------------------------------
-- مشتریان - ۶ رکورد
-- ---------------------------------------------------------------------
INSERT INTO customers (project_id, customer_type, name, contact_person, phone, address, id_number, notes, is_active, created_by)
VALUES (@test_project_id, 'company', 'شرکت ساختمانی البرز', 'انجنیر بشیر احمدی', '0788111222', 'کابل، افغانستان', 'ID-4001', 'مشتری نمونه آزمایشی شماره 1', 1, @admin_id);
SET @cust1 = LAST_INSERT_ID();
INSERT INTO customers (project_id, customer_type, name, contact_person, phone, address, id_number, notes, is_active, created_by)
VALUES (@test_project_id, 'individual', 'حاجی صاحب کریمی', 'حاجی کریمی', '0700333444', 'کابل، افغانستان', 'ID-4002', 'مشتری نمونه آزمایشی شماره 2', 1, @admin_id);
SET @cust2 = LAST_INSERT_ID();
INSERT INTO customers (project_id, customer_type, name, contact_person, phone, address, id_number, notes, is_active, created_by)
VALUES (@test_project_id, 'company', 'شرکت تجارتی آریانا', 'محمد داود', '0788555666', 'کابل، افغانستان', 'ID-4003', 'مشتری نمونه آزمایشی شماره 3', 1, @admin_id);
SET @cust3 = LAST_INSERT_ID();
INSERT INTO customers (project_id, customer_type, name, contact_person, phone, address, id_number, notes, is_active, created_by)
VALUES (@test_project_id, 'individual', 'محمد اسماعیل', 'محمد اسماعیل', '0700777888', 'کابل، افغانستان', 'ID-4004', 'مشتری نمونه آزمایشی شماره 4', 1, @admin_id);
SET @cust4 = LAST_INSERT_ID();
INSERT INTO customers (project_id, customer_type, name, contact_person, phone, address, id_number, notes, is_active, created_by)
VALUES (@test_project_id, 'company', 'شرکت انکشافی وطن', 'عبدالقدیر', '0788999000', 'کابل، افغانستان', 'ID-4005', 'مشتری نمونه آزمایشی شماره 5', 1, @admin_id);
SET @cust5 = LAST_INSERT_ID();
INSERT INTO customers (project_id, customer_type, name, contact_person, phone, address, id_number, notes, is_active, created_by)
VALUES (@test_project_id, 'individual', 'انجنیر ظاهر شاه', 'ظاهر شاه', '0700111333', 'کابل، افغانستان', 'ID-4006', 'مشتری نمونه آزمایشی شماره 6', 1, @admin_id);
SET @cust6 = LAST_INSERT_ID();

-- ---------------------------------------------------------------------
-- ماشین‌آلات - ۸ رکورد
-- ---------------------------------------------------------------------
INSERT INTO machinery (project_id, name, machine_type, plate_or_serial_no, model, ownership_type, owner_name, daily_rate, currency_id, status, notes, created_by)
VALUES (@test_project_id, 'کامیون شماره ۱', 'کامیون', 'KBL-1001', 'Hino 2018', 'owned', NULL, 1800, @afn, 'active', 'ماشین نمونه آزمایشی شماره 1', @admin_id);
SET @mach1 = LAST_INSERT_ID();
INSERT INTO machinery (project_id, name, machine_type, plate_or_serial_no, model, ownership_type, owner_name, daily_rate, currency_id, status, notes, created_by)
VALUES (@test_project_id, 'کامیون شماره ۲', 'کامیون', 'KBL-1002', 'Hino 2019', 'owned', NULL, 1800, @afn, 'active', 'ماشین نمونه آزمایشی شماره 2', @admin_id);
SET @mach2 = LAST_INSERT_ID();
INSERT INTO machinery (project_id, name, machine_type, plate_or_serial_no, model, ownership_type, owner_name, daily_rate, currency_id, status, notes, created_by)
VALUES (@test_project_id, 'لودر کوماتسو', 'لودر', 'KBL-2001', 'Komatsu WA380', 'owned', NULL, 3500, @afn, 'active', 'ماشین نمونه آزمایشی شماره 3', @admin_id);
SET @mach3 = LAST_INSERT_ID();
INSERT INTO machinery (project_id, name, machine_type, plate_or_serial_no, model, ownership_type, owner_name, daily_rate, currency_id, status, notes, created_by)
VALUES (@test_project_id, 'بلدوزر کاترپیلار', 'بلدوزر', 'KBL-2002', 'Caterpillar D6', 'rented', 'شرکت کرایه ماشین‌آلات شرق', 5000, @afn, 'active', 'ماشین نمونه آزمایشی شماره 4', @admin_id);
SET @mach4 = LAST_INSERT_ID();
INSERT INTO machinery (project_id, name, machine_type, plate_or_serial_no, model, ownership_type, owner_name, daily_rate, currency_id, status, notes, created_by)
VALUES (@test_project_id, 'گریدر', 'گریدر', 'KBL-2003', 'Caterpillar 140K', 'rented', 'شرکت کرایه ماشین‌آلات شرق', 4200, @afn, 'active', 'ماشین نمونه آزمایشی شماره 5', @admin_id);
SET @mach5 = LAST_INSERT_ID();
INSERT INTO machinery (project_id, name, machine_type, plate_or_serial_no, model, ownership_type, owner_name, daily_rate, currency_id, status, notes, created_by)
VALUES (@test_project_id, 'جنراتور برق', 'جنراتور', 'GEN-3001', 'Cummins 100KVA', 'owned', NULL, 900, @afn, 'active', 'ماشین نمونه آزمایشی شماره 6', @admin_id);
SET @mach6 = LAST_INSERT_ID();
INSERT INTO machinery (project_id, name, machine_type, plate_or_serial_no, model, ownership_type, owner_name, daily_rate, currency_id, status, notes, created_by)
VALUES (@test_project_id, 'میکسر بتون', 'میکسر بتون', 'MIX-4001', 'Local Mixer 500L', 'owned', NULL, 700, @afn, 'active', 'ماشین نمونه آزمایشی شماره 7', @admin_id);
SET @mach7 = LAST_INSERT_ID();
INSERT INTO machinery (project_id, name, machine_type, plate_or_serial_no, model, ownership_type, owner_name, daily_rate, currency_id, status, notes, created_by)
VALUES (@test_project_id, 'حفار (اکسکاویتور)', 'حفار', 'KBL-2004', 'Komatsu PC200', 'rented', 'شرکت کرایه ماشین‌آلات شرق', 4800, @afn, 'maintenance', 'ماشین نمونه آزمایشی شماره 8', @admin_id);
SET @mach8 = LAST_INSERT_ID();

-- ---------------------------------------------------------------------
-- قراردادها - ۶ رکورد (با اتصال ماشین‌آلات)
-- ---------------------------------------------------------------------
INSERT INTO contracts (project_id, contract_no, title, party_type, customer_id, kahta_account_id, contract_date, start_date, end_date, amount, currency_id, status, scope_of_work, payment_terms, notes, created_by)
VALUES (@test_project_id, 'CT-101', 'قرارداد حمل مواد با ۲ کامیون', 'customer', @cust1, NULL, '2026-02-01', '2026-02-01', '2026-08-01', 60000, @afn, 'active', 'حمل مواد ساختمانی از انبار تا ساحه پروژه', 'پرداخت به‌صورت اقساط ماهانه', 'قرارداد نمونه آزمایشی شماره 1', @admin_id);
SET @ctr1 = LAST_INSERT_ID();
INSERT INTO contract_machinery (contract_id, machinery_id) VALUES (@ctr1, @mach1);
INSERT INTO contract_machinery (contract_id, machinery_id) VALUES (@ctr1, @mach2);
INSERT INTO contracts (project_id, contract_no, title, party_type, customer_id, kahta_account_id, contract_date, start_date, end_date, amount, currency_id, status, scope_of_work, payment_terms, notes, created_by)
VALUES (@test_project_id, 'CT-102', 'قرارداد کرایه لودر و بلدوزر', 'kahta', NULL, @kahta7, '2026-03-01', '2026-03-05', '2026-09-05', 90000, @afn, 'active', 'خاک‌برداری و تسطیح ساحه پروژه', 'پرداخت به‌صورت اقساط ماهانه', 'قرارداد نمونه آزمایشی شماره 2', @admin_id);
SET @ctr2 = LAST_INSERT_ID();
INSERT INTO contract_machinery (contract_id, machinery_id) VALUES (@ctr2, @mach3);
INSERT INTO contract_machinery (contract_id, machinery_id) VALUES (@ctr2, @mach4);
INSERT INTO contracts (project_id, contract_no, title, party_type, customer_id, kahta_account_id, contract_date, start_date, end_date, amount, currency_id, status, scope_of_work, payment_terms, notes, created_by)
VALUES (@test_project_id, 'CT-103', 'قرارداد تهیه مواد ساختمانی', 'kahta', NULL, @kahta8, '2026-01-15', '2026-01-20', '2026-12-20', 250000, @afn, 'active', 'تهیه سمنت، ریگ و آهن‌آلات', 'پرداخت به‌صورت اقساط ماهانه', 'قرارداد نمونه آزمایشی شماره 3', @admin_id);
SET @ctr3 = LAST_INSERT_ID();
INSERT INTO contracts (project_id, contract_no, title, party_type, customer_id, kahta_account_id, contract_date, start_date, end_date, amount, currency_id, status, scope_of_work, payment_terms, notes, created_by)
VALUES (@test_project_id, 'CT-104', 'قرارداد کرایه جنراتور و میکسر بتون', 'customer', @cust3, NULL, '2026-04-01', '2026-04-01', '2026-07-01', 25000, @afn, 'completed', 'تامین برق و بتون‌ریزی ساحه', 'پرداخت به‌صورت اقساط ماهانه', 'قرارداد نمونه آزمایشی شماره 4', @admin_id);
SET @ctr4 = LAST_INSERT_ID();
INSERT INTO contract_machinery (contract_id, machinery_id) VALUES (@ctr4, @mach6);
INSERT INTO contract_machinery (contract_id, machinery_id) VALUES (@ctr4, @mach7);
INSERT INTO contracts (project_id, contract_no, title, party_type, customer_id, kahta_account_id, contract_date, start_date, end_date, amount, currency_id, status, scope_of_work, payment_terms, notes, created_by)
VALUES (@test_project_id, 'CT-105', 'قرارداد کرایه حفار و گریدر', 'customer', @cust5, NULL, '2026-05-10', '2026-05-15', '2026-11-15', 110000, @afn, 'pending', 'حفاری کانال و تسطیح جاده دسترسی', 'پرداخت به‌صورت اقساط ماهانه', 'قرارداد نمونه آزمایشی شماره 5', @admin_id);
SET @ctr5 = LAST_INSERT_ID();
INSERT INTO contract_machinery (contract_id, machinery_id) VALUES (@ctr5, @mach5);
INSERT INTO contract_machinery (contract_id, machinery_id) VALUES (@ctr5, @mach8);
INSERT INTO contracts (project_id, contract_no, title, party_type, customer_id, kahta_account_id, contract_date, start_date, end_date, amount, currency_id, status, scope_of_work, payment_terms, notes, created_by)
VALUES (@test_project_id, 'CT-106', 'قرارداد خدمات مشورتی انجنیری', 'customer', @cust6, NULL, '2026-01-10', '2026-01-10', '2026-06-10', 15000, @afn, 'terminated', 'مشوره فنی و نظارت ابتدایی پروژه - قرارداد فسخ‌شده', 'پرداخت به‌صورت اقساط ماهانه', 'قرارداد نمونه آزمایشی شماره 6', @admin_id);
SET @ctr6 = LAST_INSERT_ID();

-- ---------------------------------------------------------------------
-- کارمندان (منابع بشری) - ۱۰ رکورد
-- ---------------------------------------------------------------------
INSERT INTO employees (project_id, full_name, father_name, tazkira_no, position, department, phone, address, hire_date, employment_type, salary_amount, salary_currency_id, status, kahta_account_id, notes, created_by)
VALUES (@test_project_id, 'احمد ولی', 'غلام سخی', 'TZ-5001', 'انجنیر ساختمان', 'ساختمانی', '0700006001', 'کابل، افغانستان', '2025-08-01', 'permanent', 35000, @afn, 'active', @kahta1, 'کارمند نمونه آزمایشی شماره 1', @admin_id);
SET @emp1 = LAST_INSERT_ID();
INSERT INTO employees (project_id, full_name, father_name, tazkira_no, position, department, phone, address, hire_date, employment_type, salary_amount, salary_currency_id, status, kahta_account_id, notes, created_by)
VALUES (@test_project_id, 'محمد یوسف', 'عبدالرحیم', 'TZ-5002', 'سرپرست ساحه', 'ساختمانی', '0700006002', 'کابل، افغانستان', '2025-12-01', 'permanent', 28000, @afn, 'active', @kahta2, 'کارمند نمونه آزمایشی شماره 2', @admin_id);
SET @emp2 = LAST_INSERT_ID();
INSERT INTO employees (project_id, full_name, father_name, tazkira_no, position, department, phone, address, hire_date, employment_type, salary_amount, salary_currency_id, status, kahta_account_id, notes, created_by)
VALUES (@test_project_id, 'نور آغا', 'فضل احمد', 'TZ-5003', 'محاسب پروژه', 'مالی', '0700006003', 'کابل، افغانستان', '2025-10-01', 'permanent', 25000, @afn, 'active', @kahta3, 'کارمند نمونه آزمایشی شماره 3', @admin_id);
SET @emp3 = LAST_INSERT_ID();
INSERT INTO employees (project_id, full_name, father_name, tazkira_no, position, department, phone, address, hire_date, employment_type, salary_amount, salary_currency_id, status, kahta_account_id, notes, created_by)
VALUES (@test_project_id, 'عبدالباسط', 'محمد شریف', 'TZ-5004', 'راننده کامیون', 'ترانسپورت', '0700006004', 'کابل، افغانستان', '2025-06-01', 'contract', 15000, @afn, 'active', NULL, 'کارمند نمونه آزمایشی شماره 4', @admin_id);
SET @emp4 = LAST_INSERT_ID();
INSERT INTO employees (project_id, full_name, father_name, tazkira_no, position, department, phone, address, hire_date, employment_type, salary_amount, salary_currency_id, status, kahta_account_id, notes, created_by)
VALUES (@test_project_id, 'سید جان', 'سید امین', 'TZ-5005', 'راننده لودر', 'ترانسپورت', '0700006005', 'کابل، افغانستان', '2025-06-01', 'contract', 18000, @afn, 'active', NULL, 'کارمند نمونه آزمایشی شماره 5', @admin_id);
SET @emp5 = LAST_INSERT_ID();
INSERT INTO employees (project_id, full_name, father_name, tazkira_no, position, department, phone, address, hire_date, employment_type, salary_amount, salary_currency_id, status, kahta_account_id, notes, created_by)
VALUES (@test_project_id, 'غلام حیدر', 'غلام سرور', 'TZ-5006', 'گارد امنیتی', 'امنیتی', '0700006006', 'کابل، افغانستان', '2025-08-01', 'daily_wage', 500, @afn, 'active', NULL, 'کارمند نمونه آزمایشی شماره 6', @admin_id);
SET @emp6 = LAST_INSERT_ID();
INSERT INTO employees (project_id, full_name, father_name, tazkira_no, position, department, phone, address, hire_date, employment_type, salary_amount, salary_currency_id, status, kahta_account_id, notes, created_by)
VALUES (@test_project_id, 'فضل الرحمن', 'عبدالغفور', 'TZ-5007', 'کارگر ساده', 'ساختمانی', '0700006007', 'کابل، افغانستان', '2025-12-01', 'daily_wage', 450, @afn, 'active', NULL, 'کارمند نمونه آزمایشی شماره 7', @admin_id);
SET @emp7 = LAST_INSERT_ID();
INSERT INTO employees (project_id, full_name, father_name, tazkira_no, position, department, phone, address, hire_date, employment_type, salary_amount, salary_currency_id, status, kahta_account_id, notes, created_by)
VALUES (@test_project_id, 'شیر آغا', 'محمد عارف', 'TZ-5008', 'ذخیره‌دار', 'لوژستیک', '0700006008', 'کابل، افغانستان', '2025-11-01', 'permanent', 20000, @afn, 'active', NULL, 'کارمند نمونه آزمایشی شماره 8', @admin_id);
SET @emp8 = LAST_INSERT_ID();
INSERT INTO employees (project_id, full_name, father_name, tazkira_no, position, department, phone, address, hire_date, employment_type, salary_amount, salary_currency_id, status, kahta_account_id, notes, created_by)
VALUES (@test_project_id, 'رحمت الله', 'عبدالواسع', 'TZ-5009', 'تخنیکر جنراتور', 'تخنیکی', '0700006009', 'کابل، افغانستان', '2025-11-01', 'contract', 17000, @afn, 'active', NULL, 'کارمند نمونه آزمایشی شماره 9', @admin_id);
SET @emp9 = LAST_INSERT_ID();
INSERT INTO employees (project_id, full_name, father_name, tazkira_no, position, department, phone, address, hire_date, employment_type, salary_amount, salary_currency_id, status, kahta_account_id, notes, created_by)
VALUES (@test_project_id, 'کریم داد', 'محمد کریم', 'TZ-5010', 'نگهبان شبانه', 'امنیتی', '0700006010', 'کابل، افغانستان', '2025-07-01', 'daily_wage', 480, @afn, 'active', NULL, 'کارمند نمونه آزمایشی شماره 10', @admin_id);
SET @emp10 = LAST_INSERT_ID();

-- ---------------------------------------------------------------------
-- حاضری روزانه - ۱۵ رکورد
-- ---------------------------------------------------------------------
INSERT INTO employee_attendance (employee_id, project_id, attendance_date, status, notes, created_by) VALUES (@emp1, @test_project_id, '2026-09-01', 'present', 'حاضری نمونه آزمایشی', @admin_id);
INSERT INTO employee_attendance (employee_id, project_id, attendance_date, status, notes, created_by) VALUES (@emp2, @test_project_id, '2026-09-01', 'present', 'حاضری نمونه آزمایشی', @admin_id);
INSERT INTO employee_attendance (employee_id, project_id, attendance_date, status, notes, created_by) VALUES (@emp3, @test_project_id, '2026-09-01', 'absent', 'حاضری نمونه آزمایشی', @admin_id);
INSERT INTO employee_attendance (employee_id, project_id, attendance_date, status, notes, created_by) VALUES (@emp4, @test_project_id, '2026-09-01', 'leave', 'حاضری نمونه آزمایشی', @admin_id);
INSERT INTO employee_attendance (employee_id, project_id, attendance_date, status, notes, created_by) VALUES (@emp5, @test_project_id, '2026-09-01', 'present', 'حاضری نمونه آزمایشی', @admin_id);
INSERT INTO employee_attendance (employee_id, project_id, attendance_date, status, notes, created_by) VALUES (@emp1, @test_project_id, '2026-09-02', 'present', 'حاضری نمونه آزمایشی', @admin_id);
INSERT INTO employee_attendance (employee_id, project_id, attendance_date, status, notes, created_by) VALUES (@emp2, @test_project_id, '2026-09-02', 'absent', 'حاضری نمونه آزمایشی', @admin_id);
INSERT INTO employee_attendance (employee_id, project_id, attendance_date, status, notes, created_by) VALUES (@emp3, @test_project_id, '2026-09-02', 'leave', 'حاضری نمونه آزمایشی', @admin_id);
INSERT INTO employee_attendance (employee_id, project_id, attendance_date, status, notes, created_by) VALUES (@emp4, @test_project_id, '2026-09-02', 'present', 'حاضری نمونه آزمایشی', @admin_id);
INSERT INTO employee_attendance (employee_id, project_id, attendance_date, status, notes, created_by) VALUES (@emp5, @test_project_id, '2026-09-02', 'present', 'حاضری نمونه آزمایشی', @admin_id);
INSERT INTO employee_attendance (employee_id, project_id, attendance_date, status, notes, created_by) VALUES (@emp1, @test_project_id, '2026-09-03', 'absent', 'حاضری نمونه آزمایشی', @admin_id);
INSERT INTO employee_attendance (employee_id, project_id, attendance_date, status, notes, created_by) VALUES (@emp2, @test_project_id, '2026-09-03', 'leave', 'حاضری نمونه آزمایشی', @admin_id);
INSERT INTO employee_attendance (employee_id, project_id, attendance_date, status, notes, created_by) VALUES (@emp3, @test_project_id, '2026-09-03', 'present', 'حاضری نمونه آزمایشی', @admin_id);
INSERT INTO employee_attendance (employee_id, project_id, attendance_date, status, notes, created_by) VALUES (@emp4, @test_project_id, '2026-09-03', 'present', 'حاضری نمونه آزمایشی', @admin_id);
INSERT INTO employee_attendance (employee_id, project_id, attendance_date, status, notes, created_by) VALUES (@emp5, @test_project_id, '2026-09-03', 'present', 'حاضری نمونه آزمایشی', @admin_id);

-- ---------------------------------------------------------------------
-- مرخصی‌ها - ۸ رکورد
-- ---------------------------------------------------------------------
INSERT INTO employee_leaves (employee_id, project_id, leave_type, start_date, end_date, days_count, reason, status, notes, created_by)
VALUES (@emp2, @test_project_id, 'sick', '2026-05-16', '2026-05-16', 1, 'مرخصی نمونه آزمایشی شماره 1', 'pending', 'رکورد نمونه آزمایشی', @admin_id);
INSERT INTO employee_leaves (employee_id, project_id, leave_type, start_date, end_date, days_count, reason, status, notes, created_by)
VALUES (@emp3, @test_project_id, 'unpaid', '2026-02-13', '2026-02-17', 5, 'مرخصی نمونه آزمایشی شماره 2', 'approved', 'رکورد نمونه آزمایشی', @admin_id);
INSERT INTO employee_leaves (employee_id, project_id, leave_type, start_date, end_date, days_count, reason, status, notes, created_by)
VALUES (@emp4, @test_project_id, 'other', '2026-02-11', '2026-02-14', 4, 'مرخصی نمونه آزمایشی شماره 3', 'rejected', 'رکورد نمونه آزمایشی', @admin_id);
INSERT INTO employee_leaves (employee_id, project_id, leave_type, start_date, end_date, days_count, reason, status, notes, created_by)
VALUES (@emp5, @test_project_id, 'annual', '2026-06-11', '2026-06-13', 3, 'مرخصی نمونه آزمایشی شماره 4', 'approved', 'رکورد نمونه آزمایشی', @admin_id);
INSERT INTO employee_leaves (employee_id, project_id, leave_type, start_date, end_date, days_count, reason, status, notes, created_by)
VALUES (@emp6, @test_project_id, 'sick', '2026-07-14', '2026-07-14', 1, 'مرخصی نمونه آزمایشی شماره 5', 'pending', 'رکورد نمونه آزمایشی', @admin_id);
INSERT INTO employee_leaves (employee_id, project_id, leave_type, start_date, end_date, days_count, reason, status, notes, created_by)
VALUES (@emp7, @test_project_id, 'unpaid', '2026-04-02', '2026-04-06', 5, 'مرخصی نمونه آزمایشی شماره 6', 'approved', 'رکورد نمونه آزمایشی', @admin_id);
INSERT INTO employee_leaves (employee_id, project_id, leave_type, start_date, end_date, days_count, reason, status, notes, created_by)
VALUES (@emp8, @test_project_id, 'other', '2026-05-10', '2026-05-10', 1, 'مرخصی نمونه آزمایشی شماره 7', 'rejected', 'رکورد نمونه آزمایشی', @admin_id);
INSERT INTO employee_leaves (employee_id, project_id, leave_type, start_date, end_date, days_count, reason, status, notes, created_by)
VALUES (@emp9, @test_project_id, 'annual', '2026-08-14', '2026-08-16', 3, 'مرخصی نمونه آزمایشی شماره 8', 'approved', 'رکورد نمونه آزمایشی', @admin_id);

-- پایان داده‌های نمونه آزمایشی
