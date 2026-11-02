SET NAMES utf8mb4;

-- Safe, additive audit trail for quantity changes on existing contract lines.
-- Run after add_contract_items.sql. It never rewrites legacy quantities.
CREATE TABLE IF NOT EXISTS contract_item_amendments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_item_id BIGINT UNSIGNED NOT NULL,
  old_quantity INT UNSIGNED NOT NULL,
  new_quantity INT UNSIGNED NOT NULL,
  old_line_amount DECIMAL(18,2) NOT NULL,
  new_line_amount DECIMAL(18,2) NOT NULL,
  reason VARCHAR(500) NOT NULL,
  changed_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_contract_item_amendments_item_time (contract_item_id, created_at, id),
  CONSTRAINT fk_contract_item_amendment_item FOREIGN KEY (contract_item_id) REFERENCES contract_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_contract_item_amendment_actor FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
