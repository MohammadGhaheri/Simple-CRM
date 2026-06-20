SET NAMES utf8mb4;

SET @exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activities' AND COLUMN_NAME = 'is_internal_task'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE activities ADD COLUMN is_internal_task TINYINT(1) NOT NULL DEFAULT 0 AFTER status', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activities' AND COLUMN_NAME = 'attachment_path'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE activities ADD COLUMN attachment_path VARCHAR(255) NULL AFTER is_internal_task', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activities' AND COLUMN_NAME = 'attachment_name'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE activities ADD COLUMN attachment_name VARCHAR(190) NULL AFTER attachment_path', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activities' AND COLUMN_NAME = 'attachment_mime'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE activities ADD COLUMN attachment_mime VARCHAR(100) NULL AFTER attachment_name', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activities' AND COLUMN_NAME = 'attachment_size'
);
SET @sql := IF(@exists = 0, 'ALTER TABLE activities ADD COLUMN attachment_size INT UNSIGNED NULL AFTER attachment_mime', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE activities CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
