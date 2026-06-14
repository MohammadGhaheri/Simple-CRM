SET NAMES utf8mb4;

ALTER TABLE app_settings CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO app_settings (setting_key, setting_value)
SELECT 'contact_activation_auto_reply_enabled', '1'
WHERE NOT EXISTS (
  SELECT 1 FROM app_settings WHERE setting_key = 'contact_activation_auto_reply_enabled'
);

INSERT INTO app_settings (setting_key, setting_value)
SELECT 'contact_activation_auto_reply_template', 'با سلام، احتراما پروفایل شما تایید و فعال شد
با سپاس {support_name}'
WHERE NOT EXISTS (
  SELECT 1 FROM app_settings WHERE setting_key = 'contact_activation_auto_reply_template'
);
