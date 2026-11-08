SET NAMES utf8mb4;

-- Apply this migration before deploying Deal::create/update stage-history writes.
-- Existing deals are intentionally not backfilled: historical transition dates are unknown.
-- This additive migration is safe for existing data.

CREATE TABLE IF NOT EXISTS deal_stage_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  deal_id INT UNSIGNED NOT NULL,
  from_stage VARCHAR(80) NULL,
  to_stage VARCHAR(80) NOT NULL,
  changed_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_deal_stage_events_deal_time (deal_id, created_at, id),
  INDEX idx_deal_stage_events_time (created_at, to_stage),
  INDEX idx_deal_stage_events_actor (changed_by_user_id, created_at),
  CONSTRAINT fk_deal_stage_events_deal
    FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE CASCADE,
  CONSTRAINT fk_deal_stage_events_actor
    FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
