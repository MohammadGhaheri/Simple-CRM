-- Mammut Connect website lead intake. Apply only after production release preflight and backup.
CREATE TABLE IF NOT EXISTS website_leads (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 request_id VARCHAR(100) NOT NULL,
 payload_sha256 CHAR(64) NOT NULL,
 source VARCHAR(120) NOT NULL,
 submitted_at DATETIME NOT NULL,
 contact_name VARCHAR(160) NOT NULL,
 mobile VARCHAR(20) NOT NULL,
 email VARCHAR(190) NULL,
 company VARCHAR(190) NULL,
 fleet_type VARCHAR(40) NOT NULL DEFAULT 'other',
 fleet_type_label VARCHAR(160) NULL,
 service_code VARCHAR(60) NOT NULL DEFAULT 'other',
 service_label VARCHAR(160) NULL,
 vehicle_count INT UNSIGNED NOT NULL DEFAULT 0,
 message TEXT NULL,
 consent TINYINT(1) NOT NULL DEFAULT 1,
 page_url VARCHAR(1024) NOT NULL,
 utm_source VARCHAR(120) NULL,
 utm_medium VARCHAR(120) NULL,
 utm_campaign VARCHAR(120) NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'new',
 assigned_user_id INT UNSIGNED NULL,
 call_result TEXT NULL,
 next_followup_at DATETIME NULL,
 converted_customer_id INT UNSIGNED NULL,
 converted_contact_id INT UNSIGNED NULL,
 converted_deal_id INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_website_leads_request (request_id),
 KEY idx_website_leads_status_owner (status, assigned_user_id, created_at),
 KEY idx_website_leads_followup (next_followup_at),
 KEY idx_website_leads_mobile (mobile),
 CONSTRAINT fk_website_leads_owner FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_website_leads_customer FOREIGN KEY (converted_customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 CONSTRAINT fk_website_leads_contact FOREIGN KEY (converted_contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
 CONSTRAINT fk_website_leads_deal FOREIGN KEY (converted_deal_id) REFERENCES deals(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS website_api_nonces (
 nonce VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 expires_at DATETIME NOT NULL,
 KEY idx_website_nonces_expiry (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS website_api_rate_limits (
 client_key CHAR(64) CHARACTER SET ascii NOT NULL,
 minute_bucket BIGINT UNSIGNED NOT NULL,
 count INT UNSIGNED NOT NULL DEFAULT 1,
 PRIMARY KEY (client_key, minute_bucket),
 KEY idx_website_rate_bucket (minute_bucket)
) ENGINE=InnoDB;
