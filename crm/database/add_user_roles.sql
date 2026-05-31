SET NAMES utf8mb4;
USE simple_crm;

ALTER TABLE users
  ADD COLUMN role ENUM('admin','sales') NOT NULL DEFAULT 'sales' AFTER password_hash;

UPDATE users
SET role = 'admin'
WHERE email = 'admin@simple-crm.local';
