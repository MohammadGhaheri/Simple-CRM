SET NAMES utf8mb4;

-- Run after the three contract item migrations. Additive only: no legacy row is rewritten.
CREATE TABLE IF NOT EXISTS contract_item_change_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  contract_item_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('identity_amended','cancelled') NOT NULL,
  old_item_title VARCHAR(190) NULL,
  new_item_title VARCHAR(190) NULL,
  old_product VARCHAR(120) NULL,
  new_product VARCHAR(120) NULL,
  old_sku VARCHAR(120) NULL,
  new_sku VARCHAR(120) NULL,
  reason VARCHAR(500) NOT NULL,
  changed_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_contract_item_events_contract_time (contract_id, created_at, id),
  CONSTRAINT fk_contract_item_event_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
  CONSTRAINT fk_contract_item_event_item FOREIGN KEY (contract_item_id) REFERENCES contract_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_contract_item_event_actor FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contract_invoice_ref_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  invoice_ref_id BIGINT UNSIGNED NOT NULL,
  old_invoice_number VARCHAR(120) NULL,
  new_invoice_number VARCHAR(120) NULL,
  old_invoice_date DATE NULL,
  new_invoice_date DATE NULL,
  old_note TEXT NULL,
  new_note TEXT NULL,
  reason VARCHAR(500) NOT NULL,
  changed_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_contract_invoice_events_contract_time (contract_id, created_at, id),
  CONSTRAINT fk_contract_invoice_event_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
  CONSTRAINT fk_contract_invoice_event_ref FOREIGN KEY (invoice_ref_id) REFERENCES contract_invoice_refs(id) ON DELETE CASCADE,
  CONSTRAINT fk_contract_invoice_event_actor FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
