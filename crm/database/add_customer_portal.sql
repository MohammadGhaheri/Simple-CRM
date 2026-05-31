SET NAMES utf8mb4;
USE simple_crm;

ALTER TABLE contacts
  ADD COLUMN portal_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER email,
  ADD COLUMN password_hash VARCHAR(255) NULL AFTER portal_enabled;

CREATE TABLE IF NOT EXISTS tickets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_code VARCHAR(40) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NOT NULL,
  contact_id INT UNSIGNED NOT NULL,
  subject VARCHAR(190) NOT NULL,
  category ENUM('Support','Request','Bug','Training','Billing','Other') NOT NULL DEFAULT 'Support',
  priority ENUM('Low','Normal','High','Urgent') NOT NULL DEFAULT 'Normal',
  status ENUM('Open','In Progress','Waiting Customer','Resolved','Closed') NOT NULL DEFAULT 'Open',
  description TEXT NOT NULL,
  response TEXT NULL,
  assigned_user_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tickets_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_tickets_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
  CONSTRAINT fk_tickets_assigned_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

UPDATE contacts
SET portal_enabled = 1,
    password_hash = '$2y$10$mnF.X3Ttd5vSqxYfNny61ePYeI9Ox30SCuUD69XkszHS8Z/jGteWi'
WHERE id IN (1, 2);

INSERT IGNORE INTO tickets (ticket_code, customer_id, contact_id, subject, category, priority, status, description, assigned_user_id) VALUES
('TCK-1001', 1, 1, 'درخواست بررسی گزارش مصرف سوخت', 'Support', 'Normal', 'Open', 'در گزارش مصرف سوخت برخی خودروها داده روز گذشته دیده نمی‌شود. لطفا بررسی شود.', 1),
('TCK-1002', 2, 2, 'درخواست جلسه آموزشی API', 'Training', 'High', 'In Progress', 'برای تیم فنی نیاز به یک جلسه آموزشی درباره endpointهای اصلی API داریم.', 1);

UPDATE tickets
SET subject = 'درخواست بررسی گزارش مصرف سوخت',
    description = 'در گزارش مصرف سوخت برخی خودروها داده روز گذشته دیده نمی‌شود. لطفا بررسی شود.'
WHERE ticket_code = 'TCK-1001';

UPDATE tickets
SET subject = 'درخواست جلسه آموزشی API',
    description = 'برای تیم فنی نیاز به یک جلسه آموزشی درباره endpointهای اصلی API داریم.'
WHERE ticket_code = 'TCK-1002';
