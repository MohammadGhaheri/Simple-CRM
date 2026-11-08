SET NAMES utf8mb4;

-- Upgrade AFTER add_contract_items.sql and add_contract_item_amendments.sql.
-- Existing allocations remain active; no historical reversals are fabricated.
-- Allows corrected allocations for the same invoice/item after a reversal.
ALTER TABLE contract_invoice_lines
  DROP INDEX uq_contract_invoice_lines,
  ADD INDEX idx_invoice_lines_ref_item (invoice_ref_id, contract_item_id),
  ADD COLUMN reversed_at DATETIME NULL,
  ADD COLUMN reversed_by_user_id INT UNSIGNED NULL,
  ADD COLUMN reversal_reason VARCHAR(500) NULL,
  ADD INDEX idx_invoice_lines_reversal (contract_id, reversed_at),
  ADD CONSTRAINT fk_invoice_line_reversed_by
    FOREIGN KEY (reversed_by_user_id) REFERENCES users(id) ON DELETE SET NULL;
