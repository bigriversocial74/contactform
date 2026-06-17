CREATE TABLE IF NOT EXISTS merchant_pppm_cases (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  pppm_item_id BIGINT UNSIGNED NOT NULL,
  case_type VARCHAR(40) NOT NULL DEFAULT 'other',
  status ENUM('open','investigating','waiting','resolved','closed') NOT NULL DEFAULT 'open',
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  summary VARCHAR(240) NOT NULL,
  assigned_user_id BIGINT UNSIGNED NULL,
  opened_by_user_id BIGINT UNSIGNED NOT NULL,
  resolved_at DATETIME NULL,
  closed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_pppm_cases_public_id (public_id),
  KEY idx_merchant_pppm_cases_item_status (pppm_item_id,status,priority),
  KEY idx_merchant_pppm_cases_merchant_status (merchant_user_id,status,updated_at),
  CONSTRAINT fk_merchant_pppm_cases_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_pppm_cases_item FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_pppm_cases_assignee FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_pppm_cases_opener FOREIGN KEY (opened_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_pppm_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  pppm_item_id BIGINT UNSIGNED NOT NULL,
  case_id BIGINT UNSIGNED NULL,
  author_user_id BIGINT UNSIGNED NOT NULL,
  note_type ENUM('internal','customer_contact','merchant_action','system_followup') NOT NULL DEFAULT 'internal',
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_pppm_notes_public_id (public_id),
  KEY idx_merchant_pppm_notes_item_created (pppm_item_id,created_at,id),
  CONSTRAINT fk_merchant_pppm_notes_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_pppm_notes_item FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_pppm_notes_case FOREIGN KEY (case_id) REFERENCES merchant_pppm_cases(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_pppm_notes_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT chk_merchant_pppm_notes_body CHECK (CHAR_LENGTH(TRIM(body)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.pppm.view','View merchant PPPM operations','View merchant-scoped orders, items, lifecycle history, and fulfillment state.',NOW()),
('merchant.pppm.case.manage','Manage merchant PPPM cases','Create merchant operational cases and notes.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('merchant.pppm.view','merchant.pppm.case.manage')
WHERE r.slug IN ('merchant','admin','super_admin');
