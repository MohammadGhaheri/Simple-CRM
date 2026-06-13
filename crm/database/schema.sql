SET NAMES utf8mb4;
-- Elm Simple CRM
-- Author: Mohammad Ghaheri Najafabadi
-- Email: mohammad.ghaheri@gmail.com

CREATE DATABASE IF NOT EXISTS simple_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE simple_crm;

DROP TABLE IF EXISTS activities;
DROP TABLE IF EXISTS contracts;
DROP TABLE IF EXISTS ticket_messages;
DROP TABLE IF EXISTS email_logs;
DROP TABLE IF EXISTS sms_logs;
DROP TABLE IF EXISTS usage_events;
DROP TABLE IF EXISTS login_events;
DROP TABLE IF EXISTS tickets;
DROP TABLE IF EXISTS deals;
DROP TABLE IF EXISTS contacts;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS app_settings;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  mobile VARCHAR(40) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','sales') NOT NULL DEFAULT 'sales',
  avatar_path VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE app_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE login_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_type ENUM('user','contact') NOT NULL,
  actor_id INT UNSIGNED NOT NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE usage_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_type ENUM('user','contact') NOT NULL,
  actor_id INT UNSIGNED NOT NULL,
  area VARCHAR(80) NOT NULL,
  action_name VARCHAR(80) NOT NULL,
  ip_address VARCHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE sms_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mobile VARCHAR(40) NOT NULL,
  message TEXT NOT NULL,
  status VARCHAR(30) NOT NULL,
  provider_response TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE email_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipient_email VARCHAR(190) NOT NULL,
  subject VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  status VARCHAR(30) NOT NULL,
  provider_response TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_code VARCHAR(50) NOT NULL UNIQUE,
  customer_name VARCHAR(190) NOT NULL,
  customer_type VARCHAR(80) NOT NULL DEFAULT 'Other',
  industry VARCHAR(120) NULL,
  city VARCHAR(100) NULL,
  lead_source VARCHAR(120) NULL,
  interested_product VARCHAR(120) NOT NULL DEFAULT 'Other',
  vehicle_count INT UNSIGNED NOT NULL DEFAULT 0,
  estimated_contract_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  sales_status VARCHAR(80) NOT NULL DEFAULT 'New',
  owner_user_id INT UNSIGNED NULL,
  last_followup_date DATE NULL,
  next_followup_date DATE NULL,
  is_vip TINYINT(1) NOT NULL DEFAULT 0,
  contact_invite_token VARCHAR(80) NULL UNIQUE,
  contact_invite_created_at DATETIME NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_customers_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE contacts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  contact_name VARCHAR(160) NOT NULL,
  position VARCHAR(120) NULL,
  mobile VARCHAR(40) NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(190) NULL,
  portal_enabled TINYINT(1) NOT NULL DEFAULT 0,
  password_hash VARCHAR(255) NULL,
  avatar_path VARCHAR(255) NULL,
  approval_status VARCHAR(30) NOT NULL DEFAULT 'approved',
  default_support_user_id INT UNSIGNED NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_contacts_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_contacts_default_support FOREIGN KEY (default_support_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE tickets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_code VARCHAR(40) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NOT NULL,
  contact_id INT UNSIGNED NOT NULL,
  subject VARCHAR(190) NOT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'Support',
  priority VARCHAR(80) NOT NULL DEFAULT 'Normal',
  status VARCHAR(80) NOT NULL DEFAULT 'Open',
  description TEXT NOT NULL,
  response TEXT NULL,
  assigned_user_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tickets_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_tickets_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
  CONSTRAINT fk_tickets_assigned_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE ticket_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT UNSIGNED NOT NULL,
  sender_type ENUM('contact','user') NOT NULL,
  sender_contact_id INT UNSIGNED NULL,
  sender_user_id INT UNSIGNED NULL,
  message TEXT NULL,
  attachment_path VARCHAR(255) NULL,
  attachment_name VARCHAR(190) NULL,
  attachment_mime VARCHAR(100) NULL,
  attachment_size INT UNSIGNED NULL,
  user_read_at DATETIME NULL,
  contact_read_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ticket_messages_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_ticket_messages_contact FOREIGN KEY (sender_contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
  CONSTRAINT fk_ticket_messages_user FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE deals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  deal_name VARCHAR(190) NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  product VARCHAR(120) NOT NULL DEFAULT 'Other',
  vehicle_count INT UNSIGNED NOT NULL DEFAULT 0,
  estimated_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  probability TINYINT UNSIGNED NOT NULL DEFAULT 0,
  weighted_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  deal_stage VARCHAR(80) NOT NULL DEFAULT 'Lead',
  expected_close_date DATE NULL,
  owner_user_id INT UNSIGNED NULL,
  win_loss_reason VARCHAR(255) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_deals_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_deals_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE contracts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_number VARCHAR(80) NOT NULL,
  contract_title VARCHAR(190) NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  deal_id INT UNSIGNED NULL,
  product VARCHAR(120) NOT NULL DEFAULT 'Other',
  vehicle_count INT UNSIGNED NOT NULL DEFAULT 0,
  contract_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  start_date DATE NULL,
  end_date DATE NOT NULL,
  renewal_reminder_date DATE NULL,
  owner_user_id INT UNSIGNED NULL,
  status VARCHAR(80) NOT NULL DEFAULT 'Active',
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_contracts_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_contracts_deal FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE SET NULL,
  CONSTRAINT fk_contracts_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE activities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  deal_id INT UNSIGNED NULL,
  contract_id INT UNSIGNED NULL,
  activity_date DATE NOT NULL,
  activity_type VARCHAR(80) NOT NULL DEFAULT 'Follow-up',
  summary VARCHAR(255) NOT NULL,
  next_action VARCHAR(255) NULL,
  next_followup_date DATE NULL,
  owner_user_id INT UNSIGNED NULL,
  status VARCHAR(80) NOT NULL DEFAULT 'Open',
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_activities_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_activities_deal FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE SET NULL,
  CONSTRAINT fk_activities_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE SET NULL,
  CONSTRAINT fk_activities_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_customers_status ON customers(sales_status);
CREATE INDEX idx_ticket_messages_ticket ON ticket_messages(ticket_id, created_at);
CREATE INDEX idx_deals_stage ON deals(deal_stage);
CREATE INDEX idx_contracts_renewal ON contracts(renewal_reminder_date, status);
CREATE INDEX idx_activities_followup ON activities(next_followup_date, status);
