SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS contracts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_number VARCHAR(80) NOT NULL,
  contract_title VARCHAR(190) NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  deal_id INT UNSIGNED NULL,
  product VARCHAR(120) NOT NULL DEFAULT 'Other',
  vehicle_count INT UNSIGNED NOT NULL DEFAULT 0,
  contract_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  start_date DATE NULL,
  end_date DATE NOT NULL,
  renewal_reminder_date DATE NULL,
  owner_user_id INT UNSIGNED NULL,
  status VARCHAR(80) NOT NULL DEFAULT 'Active',
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_contracts_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_contracts_deal FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE SET NULL,
  CONSTRAINT fk_contracts_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @contract_column_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'activities'
    AND COLUMN_NAME = 'contract_id'
);
SET @contract_column_sql = IF(
  @contract_column_exists = 0,
  'ALTER TABLE activities ADD COLUMN contract_id INT UNSIGNED NULL AFTER deal_id',
  'SELECT 1'
);
PREPARE contract_column_stmt FROM @contract_column_sql;
EXECUTE contract_column_stmt;
DEALLOCATE PREPARE contract_column_stmt;

INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES
('contract_renewal_reminder_days', '30'),
('options_contract_statuses', 'Active|فعال
Renewal Due|نیازمند تمدید
Renewed|تمدید شده
Expired|منقضی شده
Cancelled|لغو شده');

UPDATE app_settings
SET setting_value = CONCAT(setting_value, '\nContract Renewal|تمدید قرارداد')
WHERE setting_key = 'options_activity_types'
  AND setting_value NOT LIKE '%Contract Renewal%';

SET @contracts_renewal_index_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'contracts'
    AND INDEX_NAME = 'idx_contracts_renewal'
);
SET @contracts_renewal_index_sql = IF(
  @contracts_renewal_index_exists = 0,
  'CREATE INDEX idx_contracts_renewal ON contracts(renewal_reminder_date, status)',
  'SELECT 1'
);
PREPARE contracts_renewal_index_stmt FROM @contracts_renewal_index_sql;
EXECUTE contracts_renewal_index_stmt;
DEALLOCATE PREPARE contracts_renewal_index_stmt;
