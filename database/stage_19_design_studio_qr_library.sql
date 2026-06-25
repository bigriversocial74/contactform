-- Stage 19 Design Studio QR Library
-- Adds merchant-owned QR destinations for Design Studio print assets.

CREATE TABLE IF NOT EXISTS merchant_qr_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(180) NOT NULL,
  qr_type ENUM('claim','lead','campaign','storefront','product','custom') NOT NULL DEFAULT 'claim',
  status ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'draft',
  short_code VARCHAR(80) NOT NULL,
  destination_url VARCHAR(1000) NOT NULL,
  qr_payload_url VARCHAR(1000) NOT NULL,
  campaign_ref VARCHAR(160) NULL,
  product_ref VARCHAR(160) NULL,
  scan_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  last_scanned_at DATETIME NULL,
  metadata_json JSON NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  archived_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_qr_codes_public_id (public_id),
  UNIQUE KEY uq_merchant_qr_codes_short_code (short_code),
  KEY idx_merchant_qr_codes_workspace_status (workspace_id,status,qr_type),
  KEY idx_merchant_qr_codes_merchant_status (merchant_user_id,status,updated_at),
  CONSTRAINT fk_merchant_qr_codes_workspace FOREIGN KEY (workspace_id) REFERENCES merchant_workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_qr_codes_merchant_user FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_qr_codes_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_qr_codes_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_qr_code_scans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  qr_code_id BIGINT UNSIGNED NOT NULL,
  public_id CHAR(36) NOT NULL,
  scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_hash CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  referer_url VARCHAR(1000) NULL,
  metadata_json JSON NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_qr_code_scans_public_id (public_id),
  KEY idx_merchant_qr_code_scans_code_date (qr_code_id,scanned_at),
  CONSTRAINT fk_merchant_qr_code_scans_code FOREIGN KEY (qr_code_id) REFERENCES merchant_qr_codes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.design_studio.view','View Design Studio','Open the merchant design studio workspace.',NOW()),
('merchant.design_studio.manage','Manage Design Studio','Create, edit, and export merchant design studio assets.',NOW()),
('merchant.qr_library.view','View merchant QR library','View QR codes available to merchant design and campaign tools.',NOW()),
('merchant.qr_library.manage','Manage merchant QR library','Create, update, pause, and archive merchant QR codes.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('merchant.design_studio.view','merchant.design_studio.manage','merchant.qr_library.view','merchant.qr_library.manage')
WHERE r.slug IN ('merchant','admin','super_admin');
