SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS simple_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE simple_crm;

DROP TABLE IF EXISTS activities;
DROP TABLE IF EXISTS tickets;
DROP TABLE IF EXISTS deals;
DROP TABLE IF EXISTS contacts;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','sales') NOT NULL DEFAULT 'sales',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_code VARCHAR(50) NOT NULL UNIQUE,
  customer_name VARCHAR(190) NOT NULL,
  customer_type ENUM('B2B Fleet','B2C Owner','B2D Dealer','OEM','Strategic Partner','Other') NOT NULL DEFAULT 'Other',
  industry VARCHAR(120) NULL,
  city VARCHAR(100) NULL,
  lead_source VARCHAR(120) NULL,
  interested_product ENUM('FMS','TBox','Connected Vehicle Platform','Owner App','API Integration','Dashboard / BI','onCloud','onPremises','Other') NOT NULL DEFAULT 'Other',
  vehicle_count INT UNSIGNED NOT NULL DEFAULT 0,
  estimated_contract_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  sales_status ENUM('New','Contacted','Meeting Scheduled','Proposal Sent','Negotiation','Won','Lost','Inactive') NOT NULL DEFAULT 'New',
  owner_user_id INT UNSIGNED NULL,
  last_followup_date DATE NULL,
  next_followup_date DATE NULL,
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
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_contacts_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tickets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_code VARCHAR(40) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NOT NULL,
  contact_id INT UNSIGNED NOT NULL,
  subject VARCHAR(190) NOT NULL,
  category ENUM('Support','Request','Bug','Training','Billing','Other') NOT NULL DEFAULT 'Support',
  priority ENUM('Low','Normal','High','Urgent') NOT NULL DEFAULT 'Normal',
  status ENUM('Open','In Progress','Waiting Customer','Resolved','Closed') NOT NULL DEFAULT 'Open',
  description TEXT NOT NULL,
  response TEXT NULL,
  assigned_user_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tickets_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_tickets_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
  CONSTRAINT fk_tickets_assigned_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE deals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  deal_name VARCHAR(190) NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  product ENUM('FMS','TBox','Connected Vehicle Platform','Owner App','API Integration','Dashboard / BI','onCloud','onPremises','Other') NOT NULL DEFAULT 'Other',
  vehicle_count INT UNSIGNED NOT NULL DEFAULT 0,
  estimated_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  probability TINYINT UNSIGNED NOT NULL DEFAULT 0,
  weighted_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  deal_stage ENUM('Lead','Qualified','Proposal','Negotiation','Won','Lost') NOT NULL DEFAULT 'Lead',
  expected_close_date DATE NULL,
  owner_user_id INT UNSIGNED NULL,
  win_loss_reason VARCHAR(255) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_deals_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_deals_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE activities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  deal_id INT UNSIGNED NULL,
  activity_date DATE NOT NULL,
  activity_type ENUM('Call','Meeting','WhatsApp / Message','Email','Proposal Sent','Demo','Follow-up','Contract','Support','Other') NOT NULL DEFAULT 'Follow-up',
  summary VARCHAR(255) NOT NULL,
  next_action VARCHAR(255) NULL,
  next_followup_date DATE NULL,
  owner_user_id INT UNSIGNED NULL,
  status ENUM('Open','Done','Cancelled','Waiting') NOT NULL DEFAULT 'Open',
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_activities_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_activities_deal FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE SET NULL,
  CONSTRAINT fk_activities_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_customers_status ON customers(sales_status);
CREATE INDEX idx_deals_stage ON deals(deal_stage);
CREATE INDEX idx_activities_followup ON activities(next_followup_date, status);
