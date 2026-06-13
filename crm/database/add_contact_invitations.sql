SET NAMES utf8mb4;

SET @customer_invite_token_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'contact_invite_token'
);
SET @customer_invite_token_sql = IF(
  @customer_invite_token_exists = 0,
  'ALTER TABLE customers ADD COLUMN contact_invite_token VARCHAR(80) NULL UNIQUE AFTER is_vip',
  'DO 0'
);
PREPARE customer_invite_token_stmt FROM @customer_invite_token_sql;
EXECUTE customer_invite_token_stmt;
DEALLOCATE PREPARE customer_invite_token_stmt;

SET @customer_invite_created_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'contact_invite_created_at'
);
SET @customer_invite_created_sql = IF(
  @customer_invite_created_exists = 0,
  'ALTER TABLE customers ADD COLUMN contact_invite_created_at DATETIME NULL AFTER contact_invite_token',
  'DO 0'
);
PREPARE customer_invite_created_stmt FROM @customer_invite_created_sql;
EXECUTE customer_invite_created_stmt;
DEALLOCATE PREPARE customer_invite_created_stmt;

SET @contact_approval_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contacts' AND COLUMN_NAME = 'approval_status'
);
SET @contact_approval_sql = IF(
  @contact_approval_exists = 0,
  'ALTER TABLE contacts ADD COLUMN approval_status VARCHAR(30) NOT NULL DEFAULT ''approved'' AFTER avatar_path',
  'DO 0'
);
PREPARE contact_approval_stmt FROM @contact_approval_sql;
EXECUTE contact_approval_stmt;
DEALLOCATE PREPARE contact_approval_stmt;

UPDATE contacts SET approval_status = 'approved' WHERE approval_status IS NULL OR approval_status = '';

ALTER TABLE customers CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE contacts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
