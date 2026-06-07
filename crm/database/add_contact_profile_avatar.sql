SET NAMES utf8mb4;

SET @contact_avatar_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contacts' AND COLUMN_NAME = 'avatar_path'
);
SET @contact_avatar_sql = IF(
  @contact_avatar_exists = 0,
  'ALTER TABLE contacts ADD COLUMN avatar_path VARCHAR(255) NULL AFTER password_hash',
  'DO 0'
);
PREPARE contact_avatar_stmt FROM @contact_avatar_sql;
EXECUTE contact_avatar_stmt;
DEALLOCATE PREPARE contact_avatar_stmt;

ALTER TABLE contacts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
