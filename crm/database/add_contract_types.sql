SET NAMES utf8mb4;

ALTER TABLE contracts
  ADD COLUMN contract_type VARCHAR(40) NOT NULL DEFAULT 'formal' AFTER id,
  MODIFY contract_number VARCHAR(80) NULL,
  MODIFY end_date DATE NULL;
