SET NAMES utf8mb4;
USE simple_crm;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS mobile VARCHAR(40) NULL AFTER email,
  ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255) NULL AFTER role,
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER avatar_path;

ALTER TABLE contacts
  ADD COLUMN IF NOT EXISTS portal_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER email,
  ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER portal_enabled;

ALTER TABLE customers
  MODIFY customer_type VARCHAR(80) NOT NULL DEFAULT 'Other',
  MODIFY interested_product VARCHAR(120) NOT NULL DEFAULT 'Other',
  MODIFY sales_status VARCHAR(80) NOT NULL DEFAULT 'New';

ALTER TABLE deals
  MODIFY product VARCHAR(120) NOT NULL DEFAULT 'Other',
  MODIFY deal_stage VARCHAR(80) NOT NULL DEFAULT 'Lead';

ALTER TABLE activities
  MODIFY activity_type VARCHAR(80) NOT NULL DEFAULT 'Follow-up',
  MODIFY status VARCHAR(80) NOT NULL DEFAULT 'Open';

CREATE TABLE IF NOT EXISTS app_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tickets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_code VARCHAR(40) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NOT NULL,
  contact_id INT UNSIGNED NOT NULL,
  subject VARCHAR(190) NOT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'Support',
  priority VARCHAR(80) NOT NULL DEFAULT 'Normal',
  status VARCHAR(80) NOT NULL DEFAULT 'Open',
  description TEXT NOT NULL,
  response TEXT NULL,
  assigned_user_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tickets_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_tickets_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
  CONSTRAINT fk_tickets_assigned_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_type ENUM('user','contact') NOT NULL,
  actor_id INT UNSIGNED NOT NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usage_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_type ENUM('user','contact') NOT NULL,
  actor_id INT UNSIGNED NOT NULL,
  area VARCHAR(80) NOT NULL,
  action_name VARCHAR(80) NOT NULL,
  ip_address VARCHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mobile VARCHAR(40) NOT NULL,
  message TEXT NOT NULL,
  status VARCHAR(30) NOT NULL,
  provider_response TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipient_email VARCHAR(190) NOT NULL,
  subject VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  status VARCHAR(30) NOT NULL,
  provider_response TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES
('app_title', 'Elm Simple CRM'),
('app_subtitle', 'مدیریت مشتریان، فرصت‌ها و پیگیری‌های فروش'),
('primary_color', '#155eef'),
('sidebar_color', '#111827'),
('app_icon', ''),
('home_title', 'Elm Simple CRM'),
('home_text', 'یک سامانه سبک برای مدیریت مشتریان، مخاطبین، فرصت‌های فروش، پیگیری‌ها و تیکت‌های مشتریان.'),
('currency_unit', 'ریال'),
('customer_code_mode', 'manual'),
('customer_code_format', 'CUS-{YYYY}-{SEQ4}'),
('options_customer_types', 'B2B Fleet|ناوگان سازمانی
B2C Owner|مالک شخصی
B2D Dealer|نمایندگی
OEM|خودروساز
Strategic Partner|شریک راهبردی
Other|سایر'),
('options_sales_statuses', 'New|جدید
Contacted|تماس گرفته شده
Meeting Scheduled|جلسه تنظیم شده
Proposal Sent|پیشنهاد ارسال شده
Negotiation|مذاکره
Won|برنده
Lost|از دست رفته
Inactive|غیرفعال'),
('options_products', 'FMS|FMS
TBox|TBox
Connected Vehicle Platform|Connected Vehicle Platform
Owner App|Owner App
API Integration|API Integration
Dashboard / BI|Dashboard / BI
onCloud|onCloud
onPremises|onPremises
Other|سایر'),
('options_deal_stages', 'Lead|سرنخ
Qualified|تایید شده
Proposal|پیشنهاد
Negotiation|مذاکره
Won|برنده
Lost|از دست رفته'),
('options_activity_types', 'Call|تماس
Meeting|جلسه
WhatsApp / Message|پیام
Email|ایمیل
Proposal Sent|پیشنهاد ارسال شده
Demo|دمو
Follow-up|پیگیری
Contract|قرارداد
Support|پشتیبانی
Other|سایر'),
('options_activity_statuses', 'Open|باز
Done|انجام شده
Cancelled|لغو شده
Waiting|در انتظار'),
('options_ticket_statuses', 'Open|باز
In Progress|در حال بررسی
Waiting Customer|در انتظار مشتری
Resolved|حل شده
Closed|بسته'),
('options_ticket_priorities', 'Low|کم
Normal|عادی
High|زیاد
Urgent|فوری'),
('options_ticket_categories', 'Support|پشتیبانی
Request|درخواست
Bug|خطا
Training|آموزش
Billing|مالی
Other|سایر'),
('sms_enabled', '0'),
('sms_ticket_created_enabled', '0'),
('sms_ticket_answered_enabled', '0'),
('sms_portal_credentials_enabled', '1'),
('sms_portal_credentials_template', 'سلام {contact_name}
دسترسی شما به پرتال مشتری {app_title} فعال شد.
نام کاربری: {email}
رمز عبور: {password}
ورود: {portal_url}'),
('sms_daily_summary_enabled', '0'),
('sms_api_key', ''),
('sms_line_number', ''),
('sms_admin_mobile', ''),
('sms_default_assigned_user_id', ''),
('portal_public_url', ''),
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
ورود: {portal_url}');

ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE app_settings CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE customers CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE contacts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE deals CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE activities CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE tickets CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE login_events CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE usage_events CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE sms_logs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE email_logs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
