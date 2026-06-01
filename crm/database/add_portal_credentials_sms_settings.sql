SET NAMES utf8mb4;
USE simple_crm;

INSERT INTO app_settings (setting_key, setting_value) VALUES
('sms_portal_credentials_enabled', '1'),
('sms_portal_credentials_template', 'سلام {contact_name}
دسترسی شما به پرتال مشتری {app_title} فعال شد.
نام کاربری: {email}
رمز عبور: {password}
ورود: {portal_url}'),
('portal_public_url', '')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
