SET NAMES utf8mb4;

SET @customer_vip_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'is_vip'
);
SET @customer_vip_sql = IF(
  @customer_vip_exists = 0,
  'ALTER TABLE customers ADD COLUMN is_vip TINYINT(1) NOT NULL DEFAULT 0 AFTER next_followup_date',
  'DO 0'
);
PREPARE customer_vip_stmt FROM @customer_vip_sql;
EXECUTE customer_vip_stmt;
DEALLOCATE PREPARE customer_vip_stmt;

SET @contact_support_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contacts' AND COLUMN_NAME = 'default_support_user_id'
);
SET @contact_support_sql = IF(
  @contact_support_exists = 0,
  'ALTER TABLE contacts ADD COLUMN default_support_user_id INT UNSIGNED NULL AFTER password_hash',
  'DO 0'
);
PREPARE contact_support_stmt FROM @contact_support_sql;
EXECUTE contact_support_stmt;
DEALLOCATE PREPARE contact_support_stmt;

CREATE TABLE IF NOT EXISTS ticket_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT UNSIGNED NOT NULL,
  sender_type ENUM('contact','user') NOT NULL,
  sender_contact_id INT UNSIGNED NULL,
  sender_user_id INT UNSIGNED NULL,
  message TEXT NULL,
  attachment_path VARCHAR(255) NULL,
  attachment_name VARCHAR(190) NULL,
  attachment_mime VARCHAR(100) NULL,
  attachment_size INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ticket_messages_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_ticket_messages_contact FOREIGN KEY (sender_contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
  CONSTRAINT fk_ticket_messages_user FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @ticket_messages_index_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ticket_messages'
    AND INDEX_NAME = 'idx_ticket_messages_ticket'
);
SET @ticket_messages_index_sql = IF(
  @ticket_messages_index_exists = 0,
  'CREATE INDEX idx_ticket_messages_ticket ON ticket_messages(ticket_id, created_at)',
  'DO 0'
);
PREPARE ticket_messages_index_stmt FROM @ticket_messages_index_sql;
EXECUTE ticket_messages_index_stmt;
DEALLOCATE PREPARE ticket_messages_index_stmt;

INSERT INTO ticket_messages (ticket_id, sender_type, sender_contact_id, message, created_at)
SELECT t.id, 'contact', t.contact_id, t.description, t.created_at
FROM tickets t
WHERE t.description IS NOT NULL
  AND t.description <> ''
  AND NOT EXISTS (
    SELECT 1 FROM ticket_messages tm
    WHERE tm.ticket_id = t.id
      AND tm.sender_type = 'contact'
      AND tm.created_at = t.created_at
  );

INSERT INTO ticket_messages (ticket_id, sender_type, sender_user_id, message, created_at)
SELECT t.id, 'user', t.assigned_user_id, t.response, t.updated_at
FROM tickets t
WHERE t.response IS NOT NULL
  AND t.response <> ''
  AND NOT EXISTS (
    SELECT 1 FROM ticket_messages tm
    WHERE tm.ticket_id = t.id
      AND tm.sender_type = 'user'
      AND tm.message = t.response
  );

ALTER TABLE customers CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE contacts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE tickets CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE ticket_messages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
