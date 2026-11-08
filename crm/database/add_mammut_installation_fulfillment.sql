SET NAMES utf8mb4;

-- Mammut Connect only. Run after the core contract-item migrations.
-- Obligations are derived from contract_items; no committed/remaining quantity is duplicated.
CREATE TABLE IF NOT EXISTS installation_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  contract_item_id BIGINT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  device_model VARCHAR(190) NOT NULL,
  installation_kind ENUM('sale','warranty_replacement','reinstallation') NOT NULL DEFAULT 'sale',
  replaces_installation_id BIGINT UNSIGNED NULL,
  imei VARCHAR(64) NULL,
  serial_number VARCHAR(120) NULL,
  active_imei VARCHAR(64) AS (CASE WHEN deleted_at IS NULL AND status NOT IN ('failed','cancelled') THEN imei ELSE NULL END) PERSISTENT,
  active_serial VARCHAR(120) AS (CASE WHEN deleted_at IS NULL AND status NOT IN ('failed','cancelled') THEN serial_number ELSE NULL END) PERSISTENT,
  license_plate VARCHAR(40) NULL,
  vin VARCHAR(80) NULL,
  vehicle_type VARCHAR(120) NULL,
  technician_user_id INT UNSIGNED NULL,
  assigned_operations_user_id INT UNSIGNED NULL,
  scheduled_at DATETIME NULL,
  installed_at DATETIME NULL,
  status ENUM('scheduled','in_progress','failed','installed','pending_approval','approved','cancelled') NOT NULL DEFAULT 'scheduled',
  technical_result VARCHAR(500) NULL,
  notes TEXT NULL,
  created_by_user_id INT UNSIGNED NULL,
  approved_by_user_id INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_installation_parent (contract_id, id),
  UNIQUE KEY uq_installation_active_imei (active_imei),
  UNIQUE KEY uq_installation_active_serial (active_serial),
  INDEX idx_installation_item_status (contract_item_id, status),
  INDEX idx_installation_customer_status (customer_id, status),
  INDEX idx_installation_assignee (assigned_operations_user_id, status),
  CONSTRAINT fk_installation_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
  CONSTRAINT fk_installation_item FOREIGN KEY (contract_id, contract_item_id) REFERENCES contract_items(contract_id, id) ON DELETE CASCADE,
  CONSTRAINT fk_installation_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_installation_technician FOREIGN KEY (technician_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_installation_assignee FOREIGN KEY (assigned_operations_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_installation_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_installation_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_installation_replaced_record FOREIGN KEY (replaces_installation_id) REFERENCES installation_records(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS installation_status_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_record_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(40) NULL,
  to_status VARCHAR(40) NOT NULL,
  reason VARCHAR(500) NULL,
  changed_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_installation_events_record_time (installation_record_id, created_at, id),
  CONSTRAINT fk_installation_event_record FOREIGN KEY (installation_record_id) REFERENCES installation_records(id) ON DELETE CASCADE,
  CONSTRAINT fk_installation_event_actor FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS delivery_receipts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  receipt_number VARCHAR(120) NOT NULL,
  delivered_at DATETIME NOT NULL,
  receiver_name VARCHAR(190) NOT NULL,
  evidence_path VARCHAR(255) NULL,
  evidence_name VARCHAR(255) NULL,
  status ENUM('approved','cancelled') NOT NULL DEFAULT 'approved',
  created_by_user_id INT UNSIGNED NULL,
  approved_by_user_id INT UNSIGNED NULL,
  approved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cancelled_by_user_id INT UNSIGNED NULL,
  cancelled_at DATETIME NULL,
  cancellation_reason VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_delivery_receipt_number (contract_id, receipt_number),
  UNIQUE KEY uq_delivery_receipt_parent (contract_id, id),
  CONSTRAINT fk_delivery_receipt_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
  CONSTRAINT fk_delivery_receipt_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_delivery_receipt_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_delivery_receipt_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_delivery_receipt_canceller FOREIGN KEY (cancelled_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS delivery_receipt_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  delivery_receipt_id BIGINT UNSIGNED NOT NULL,
  installation_record_id BIGINT UNSIGNED NOT NULL,
  revoked_at DATETIME NULL,
  active_installation_id BIGINT UNSIGNED AS (CASE WHEN revoked_at IS NULL THEN installation_record_id ELSE NULL END) PERSISTENT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_delivery_active_installation (active_installation_id),
  INDEX idx_delivery_items_receipt (delivery_receipt_id),
  CONSTRAINT fk_delivery_item_receipt FOREIGN KEY (contract_id, delivery_receipt_id) REFERENCES delivery_receipts(contract_id, id) ON DELETE CASCADE,
  CONSTRAINT fk_delivery_item_installation FOREIGN KEY (contract_id, installation_record_id) REFERENCES installation_records(contract_id, id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fulfillment_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  contract_item_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('obligation_completed','obligation_reopened','receipt_cancelled') NOT NULL,
  event_key VARCHAR(190) NOT NULL,
  finance_followup_status ENUM('not_required','pending','done') NOT NULL DEFAULT 'not_required',
  details VARCHAR(500) NULL,
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fulfillment_event_key (event_key),
  INDEX idx_fulfillment_contract_time (contract_id, created_at),
  CONSTRAINT fk_fulfillment_item FOREIGN KEY (contract_id, contract_item_id) REFERENCES contract_items(contract_id, id) ON DELETE CASCADE,
  CONSTRAINT fk_fulfillment_actor FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
