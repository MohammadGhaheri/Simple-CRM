SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS contract_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  document_type VARCHAR(80) NOT NULL DEFAULT 'Other',
  title VARCHAR(190) NOT NULL,
  notes TEXT NULL,
  file_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(190) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  INDEX idx_contract_documents_contract (contract_id, deleted_at, created_at),
  INDEX idx_contract_documents_uploader (uploaded_by_user_id, created_at),
  CONSTRAINT fk_contract_documents_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
  CONSTRAINT fk_contract_documents_uploader FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_settings (setting_key, setting_value) VALUES
('options_contract_document_types', 'Contract|نسخه قرارداد
Addendum|الحاقیه
Proposal|پیشنهاد
Minutes|صورتجلسه
Correspondence|مکاتبات
Other|سایر')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
