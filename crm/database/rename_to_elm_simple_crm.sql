SET NAMES utf8mb4;
USE simple_crm;

INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES
('app_title', 'Elm Simple CRM'),
('home_title', 'Elm Simple CRM');

UPDATE app_settings
SET setting_value = 'Elm Simple CRM'
WHERE setting_key IN ('app_title', 'home_title')
  AND setting_value IN ('Mammut Connect CRM', 'Mammut Connect', 'Simple CRM');
