SET NAMES utf8mb4;
USE simple_crm;

-- Elm Simple CRM
-- Author: Mohammad Ghaheri Najafabadi
-- Email: mohammad.ghaheri@gmail.com
--
-- Removes only bundled sample CRM data.
-- Admin users, settings, login reports, usage reports, and SMS logs are preserved.

DELETE FROM customers
WHERE customer_code IN (
  'ELM-1001',
  'ELM-1002',
  'SC-1001',
  'SC-1002',
  'SC-1003',
  'SC-1004'
);
