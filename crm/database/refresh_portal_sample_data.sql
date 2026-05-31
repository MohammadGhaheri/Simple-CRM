SET NAMES utf8mb4;
SET character_set_client = utf8mb4;
SET character_set_connection = utf8mb4;
SET character_set_results = utf8mb4;
USE simple_crm;

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
