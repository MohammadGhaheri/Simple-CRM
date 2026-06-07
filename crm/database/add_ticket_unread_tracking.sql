SET NAMES utf8mb4;

SET @contact_read_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_messages' AND COLUMN_NAME = 'contact_read_at'
);
SET @contact_read_sql = IF(
  @contact_read_exists = 0,
  'ALTER TABLE ticket_messages ADD COLUMN contact_read_at DATETIME NULL AFTER attachment_size',
  'DO 0'
);
PREPARE contact_read_stmt FROM @contact_read_sql;
EXECUTE contact_read_stmt;
DEALLOCATE PREPARE contact_read_stmt;

UPDATE ticket_messages
SET contact_read_at = created_at
WHERE @contact_read_exists = 0
  AND sender_type = 'user'
  AND contact_read_at IS NULL;

ALTER TABLE ticket_messages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
