USE simple_crm;

INSERT INTO app_settings (setting_key, setting_value) VALUES
('app_title', 'Elm Simple CRM'),
('home_title', 'Elm Simple CRM')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
