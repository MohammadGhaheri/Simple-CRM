SET NAMES utf8mb4;
USE simple_crm;

ALTER TABLE users
  ADD COLUMN avatar_path VARCHAR(255) NULL AFTER role;

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
('home_text', 'یک سامانه سبک برای مدیریت مشتریان، مخاطبین، فرصت‌های فروش، پیگیری‌ها و تیکت‌های مشتریان.')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
