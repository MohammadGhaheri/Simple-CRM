SET NAMES utf8mb4;

-- Additive schema only: existing contracts, vehicle_count and financial records are not changed.
-- Existing contracts must be reviewed and itemized explicitly; do not infer device types.
-- The contracts.contract_amount field remains the financial source of truth.

CREATE TABLE IF NOT EXISTS contract_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  line_number INT UNSIGNED NOT NULL,
  item_type ENUM('device','product','service') NOT NULL DEFAULT 'product',
  product VARCHAR(120) NOT NULL DEFAULT 'Other',
  sku VARCHAR(120) NULL,
  item_title VARCHAR(190) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  line_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  requires_installation TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_contract_items_line (contract_id, line_number),
  UNIQUE KEY uq_contract_items_parent (contract_id, id),
  INDEX idx_contract_items_type (item_type, requires_installation),
  CONSTRAINT fk_contract_items_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contract_invoice_refs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  invoice_number VARCHAR(120) NOT NULL,
  invoice_date DATE NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_contract_invoice_refs_number (contract_id, invoice_number),
  UNIQUE KEY uq_contract_invoice_refs_parent (contract_id, id),
  CONSTRAINT fk_contract_invoice_refs_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contract_invoice_lines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  invoice_ref_id BIGINT UNSIGNED NOT NULL,
  contract_item_id BIGINT UNSIGNED NOT NULL,
  invoiced_quantity INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_contract_invoice_lines (invoice_ref_id, contract_item_id),
  INDEX idx_invoice_line_item (contract_id, contract_item_id),
  CONSTRAINT fk_invoice_line_ref FOREIGN KEY (contract_id, invoice_ref_id)
    REFERENCES contract_invoice_refs(contract_id, id) ON DELETE CASCADE,
  CONSTRAINT fk_invoice_line_item FOREIGN KEY (contract_id, contract_item_id)
    REFERENCES contract_items(contract_id, id) ON DELETE CASCADE
) ENGINE=InnoDB;
