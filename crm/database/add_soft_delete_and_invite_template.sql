SET NAMES utf8mb4;

SET @exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'deleted_at'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE customers ADD COLUMN deleted_at DATETIME NULL AFTER updated_at', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contacts' AND COLUMN_NAME = 'deleted_at'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE contacts ADD COLUMN deleted_at DATETIME NULL AFTER updated_at', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'deleted_at'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE tickets ADD COLUMN deleted_at DATETIME NULL AFTER updated_at', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deals' AND COLUMN_NAME = 'deleted_at'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE deals ADD COLUMN deleted_at DATETIME NULL AFTER updated_at', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts' AND COLUMN_NAME = 'deleted_at'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE contracts ADD COLUMN deleted_at DATETIME NULL AFTER updated_at', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activities' AND COLUMN_NAME = 'deleted_at'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE activities ADD COLUMN deleted_at DATETIME NULL AFTER updated_at', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE customers CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE contacts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE tickets CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE deals CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE contracts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE activities CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE app_settings CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO app_settings (setting_key, setting_value)
SELECT 'contact_invite_message_template', 'سلام، شما به عنوان همکار {customer_name} برای ایجاد حساب کاربری در سامانه {app_title} دعوت شده‌اید.
لینک اختصاصی ثبت‌نام:
{invite_url}'
WHERE NOT EXISTS (
  SELECT 1 FROM app_settings WHERE setting_key = 'contact_invite_message_template'
);
