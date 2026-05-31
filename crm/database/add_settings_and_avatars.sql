SET NAMES utf8mb4;
USE simple_crm;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255) NULL AFTER role;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS mobile VARCHAR(40) NULL AFTER email;

CREATE TABLE IF NOT EXISTS app_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO app_settings (setting_key, setting_value) VALUES
('app_title', 'Simple CRM'),
('app_subtitle', 'مدیریت مشتریان، فرصت‌ها و پیگیری‌های فروش'),
('primary_color', '#155eef'),
('sidebar_color', '#111827'),
('app_icon', ''),
('home_title', 'Simple CRM'),
('home_text', 'یک سامانه سبک برای مدیریت مشتریان، مخاطبین، فرصت‌های فروش، پیگیری‌ها و تیکت‌های مشتریان.'),
('sms_enabled', '0'),
('sms_ticket_created_enabled', '0'),
('sms_ticket_answered_enabled', '0'),
('sms_daily_summary_enabled', '0'),
('sms_api_key', ''),
('sms_line_number', ''),
('sms_admin_mobile', ''),
('sms_default_assigned_user_id', '')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

CREATE TABLE IF NOT EXISTS login_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_type ENUM('user','contact') NOT NULL,
  actor_id INT UNSIGNED NOT NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usage_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_type ENUM('user','contact') NOT NULL,
  actor_id INT UNSIGNED NOT NULL,
  area VARCHAR(80) NOT NULL,
  action_name VARCHAR(80) NOT NULL,
  ip_address VARCHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sms_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mobile VARCHAR(40) NOT NULL,
  message TEXT NOT NULL,
  status VARCHAR(30) NOT NULL,
  provider_response TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
