SET NAMES utf8mb4;

ALTER TABLE users
  MODIFY role ENUM('admin','sales','support','operations','finance') NOT NULL DEFAULT 'sales';

CREATE TABLE contract_payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  payment_date DATE NOT NULL,
  payment_method VARCHAR(80) NOT NULL DEFAULT 'Other',
  reference_number VARCHAR(120) NULL,
  notes TEXT NULL,
  created_by_user_id INT UNSIGNED NULL,
  updated_by_user_id INT UNSIGNED NULL,
  deleted_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  INDEX idx_contract_payments_contract (contract_id, deleted_at, payment_date),
  INDEX idx_contract_payments_creator (created_by_user_id, created_at),
  INDEX idx_contract_payments_reference (reference_number),
  CONSTRAINT fk_contract_payments_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
  CONSTRAINT fk_contract_payments_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_contract_payments_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_contract_payments_deleted_by FOREIGN KEY (deleted_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_settings (setting_key, setting_value)
VALUES ('options_payment_methods', 'Bank Transfer|واریز بانکی\nCheque|چک\nCash|نقدی\nPOS|کارت‌خوان\nOther|سایر')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
