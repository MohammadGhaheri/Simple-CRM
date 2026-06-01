SET NAMES utf8mb4;
USE simple_crm;

CREATE TABLE IF NOT EXISTS email_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipient_email VARCHAR(190) NOT NULL,
  subject VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  status VARCHAR(30) NOT NULL,
  provider_response TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_settings (setting_key, setting_value) VALUES
('email_enabled', '0'),
('email_transport', 'mail'),
('email_from_name', 'Elm Simple CRM'),
('email_from_address', ''),
('email_smtp_host', ''),
('email_smtp_port', '587'),
('email_smtp_username', ''),
('email_smtp_password', ''),
('email_smtp_encryption', 'tls'),
('email_test_recipient', ''),
('email_portal_credentials_enabled', '1'),
('email_ticket_answered_enabled', '1'),
('email_activity_reminder_enabled', '1'),
('email_portal_credentials_subject', 'اطلاعات ورود پرتال مشتری'),
('email_portal_credentials_template', 'سلام {contact_name}
دسترسی شما به پرتال مشتری {app_title} فعال شد.
نام کاربری: {email}
رمز عبور: {password}
ورود: {portal_url}')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
