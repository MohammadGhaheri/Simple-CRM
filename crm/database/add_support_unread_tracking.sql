SET NAMES utf8mb4;

SET @user_read_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_messages' AND COLUMN_NAME = 'user_read_at'
);
SET @user_read_sql = IF(
  @user_read_exists = 0,
  'ALTER TABLE ticket_messages ADD COLUMN user_read_at DATETIME NULL AFTER attachment_size',
  'DO 0'
);
PREPARE user_read_stmt FROM @user_read_sql;
EXECUTE user_read_stmt;
DEALLOCATE PREPARE user_read_stmt;

UPDATE ticket_messages
SET user_read_at = created_at
WHERE @user_read_exists = 0
  AND sender_type = 'contact'
  AND user_read_at IS NULL;

ALTER TABLE ticket_messages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
