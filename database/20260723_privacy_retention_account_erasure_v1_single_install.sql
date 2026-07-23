-- Microgifter Privacy Retention and Account Erasure v1
-- Single-install migration. Import after the current identity/auth schema.
-- Provides governed privacy requests, jurisdiction deadlines, legal holds,
-- merchant handoffs, data-action receipts, retention policies, and suppression tombstones.

SET @mg_schema := DATABASE();

DROP PROCEDURE IF EXISTS mg_privacy_add_column_if_missing;
DELIMITER $$
CREATE PROCEDURE mg_privacy_add_column_if_missing(
  IN p_table VARCHAR(128),
  IN p_column VARCHAR(128),
  IN p_definition TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @mg_schema AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
  ) THEN
    SET @mg_sql := CONCAT('ALTER TABLE `', REPLACE(p_table,'`','``'), '` ADD COLUMN `', REPLACE(p_column,'`','``'), '` ', p_definition);
    PREPARE mg_stmt FROM @mg_sql;
    EXECUTE mg_stmt;
    DEALLOCATE PREPARE mg_stmt;
  END IF;
END$$
DELIMITER ;

CALL mg_privacy_add_column_if_missing('users','privacy_state',"ENUM('active','deletion_pending','restricted','anonymized') NOT NULL DEFAULT 'active' AFTER status");
CALL mg_privacy_add_column_if_missing('users','deletion_requested_at','DATETIME NULL AFTER privacy_state');
CALL mg_privacy_add_column_if_missing('users','deletion_due_at','DATETIME NULL AFTER deletion_requested_at');
CALL mg_privacy_add_column_if_missing('users','privacy_restricted_at','DATETIME NULL AFTER deletion_due_at');
CALL mg_privacy_add_column_if_missing('users','anonymized_at','DATETIME NULL AFTER privacy_restricted_at');
CALL mg_privacy_add_column_if_missing('users','identity_tombstone_hash','CHAR(64) NULL AFTER anonymized_at');
DROP PROCEDURE IF EXISTS mg_privacy_add_column_if_missing;

CREATE TABLE IF NOT EXISTS privacy_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  request_type ENUM('delete','access','correct','export','restrict','object') NOT NULL DEFAULT 'delete',
  jurisdiction ENUM('eu_eea','uk','california','other_us','other') NOT NULL DEFAULT 'other',
  source ENUM('self_service','admin','email','merchant','support','regulator') NOT NULL DEFAULT 'self_service',
  status ENUM('submitted','identity_verified','acknowledged','under_review','approved','restricted','blocked_by_hold','processing','completed','partially_completed','denied','cancelled') NOT NULL DEFAULT 'submitted',
  contact_email VARCHAR(255) NULL,
  contact_email_hash CHAR(64) NOT NULL,
  verification_method VARCHAR(40) NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  acknowledgement_due_at DATETIME NULL,
  acknowledged_at DATETIME NULL,
  identity_verified_at DATETIME NULL,
  response_due_at DATETIME NOT NULL,
  grace_ends_at DATETIME NULL,
  extended_due_at DATETIME NULL,
  extension_reason VARCHAR(500) NULL,
  decision ENUM('pending','approve','partial','deny') NOT NULL DEFAULT 'pending',
  decision_reason TEXT NULL,
  restricted_at DATETIME NULL,
  processing_started_at DATETIME NULL,
  completed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  completed_receipt_hash CHAR(64) NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  assigned_to_user_id BIGINT UNSIGNED NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_privacy_requests_public_id (public_id),
  KEY idx_privacy_requests_user (user_id, status),
  KEY idx_privacy_requests_due (status, response_due_at),
  KEY idx_privacy_requests_grace (status, grace_ends_at),
  KEY idx_privacy_requests_email_hash (contact_email_hash),
  CONSTRAINT fk_privacy_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_privacy_requests_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_privacy_requests_assignee FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS privacy_request_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  details_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_privacy_events_request (request_id, created_at),
  CONSTRAINT fk_privacy_events_request FOREIGN KEY (request_id) REFERENCES privacy_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_privacy_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS privacy_legal_holds (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  status ENUM('active','released') NOT NULL DEFAULT 'active',
  reason VARCHAR(500) NOT NULL,
  scope_json JSON NULL,
  placed_by_user_id BIGINT UNSIGNED NULL,
  released_by_user_id BIGINT UNSIGNED NULL,
  placed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  released_at DATETIME NULL,
  release_reason VARCHAR(500) NULL,
  PRIMARY KEY (id),
  KEY idx_privacy_holds_user (user_id, status),
  KEY idx_privacy_holds_request (request_id, status),
  CONSTRAINT fk_privacy_holds_request FOREIGN KEY (request_id) REFERENCES privacy_requests(id) ON DELETE SET NULL,
  CONSTRAINT fk_privacy_holds_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_privacy_holds_placer FOREIGN KEY (placed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_privacy_holds_releaser FOREIGN KEY (released_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS privacy_data_actions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id BIGINT UNSIGNED NOT NULL,
  action_key VARCHAR(120) NOT NULL,
  table_name VARCHAR(128) NULL,
  action_type ENUM('restrict','delete','anonymize','retain','notify','export','verify') NOT NULL,
  status ENUM('pending','running','completed','skipped','failed','retained_by_policy','blocked_by_hold') NOT NULL DEFAULT 'pending',
  row_count INT UNSIGNED NOT NULL DEFAULT 0,
  legal_basis VARCHAR(500) NULL,
  error_message VARCHAR(1000) NULL,
  details_json JSON NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_privacy_action_request_key (request_id, action_key),
  KEY idx_privacy_actions_status (status, created_at),
  CONSTRAINT fk_privacy_actions_request FOREIGN KEY (request_id) REFERENCES privacy_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS privacy_merchant_handoffs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  handoff_type ENUM('controller_record','account_ownership') NOT NULL DEFAULT 'controller_record',
  status ENUM('pending','notified','acknowledged','completed','declined','not_applicable') NOT NULL DEFAULT 'pending',
  due_at DATETIME NOT NULL,
  notified_at DATETIME NULL,
  acknowledged_at DATETIME NULL,
  completed_at DATETIME NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_privacy_handoff_request_merchant_type (request_id, merchant_user_id, handoff_type),
  KEY idx_privacy_handoff_due (status, due_at),
  KEY idx_privacy_handoff_type (handoff_type, status, due_at),
  CONSTRAINT fk_privacy_handoff_request FOREIGN KEY (request_id) REFERENCES privacy_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_privacy_handoff_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS privacy_retention_policies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  policy_key VARCHAR(100) NOT NULL,
  data_category VARCHAR(120) NOT NULL,
  default_action ENUM('delete','anonymize','retain','restrict','review') NOT NULL,
  retention_days INT UNSIGNED NULL,
  jurisdiction VARCHAR(40) NOT NULL DEFAULT 'global',
  legal_basis VARCHAR(500) NOT NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_privacy_retention_policy (policy_key, jurisdiction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS privacy_suppression_tombstones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  identity_hash CHAR(64) NOT NULL,
  request_id BIGINT UNSIGNED NULL,
  reason VARCHAR(180) NOT NULL DEFAULT 'account_erasure',
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_privacy_tombstone_hash (identity_hash),
  CONSTRAINT fk_privacy_tombstone_request FOREIGN KEY (request_id) REFERENCES privacy_requests(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO privacy_retention_policies (policy_key,data_category,default_action,retention_days,jurisdiction,legal_basis,is_enabled) VALUES
('identity_credentials','Passwords, sessions, MFA and recovery credentials','delete',0,'global','No longer required after verified account closure.',1),
('profile_preferences','Profile, preferences, device and personalization data','delete',0,'global','Erase when no longer required to provide the service.',1),
('private_agent_data','Private agent prompts, memory and planning records','delete',0,'global','Erase private planning information after account closure.',1),
('commerce_evidence','Orders, payments, refunds, commissions and payout evidence','anonymize',2555,'global','Retain minimum accounting, tax, fraud, chargeback and legal-claim evidence.',1),
('gift_lifecycle','Gift ownership, delivery, claim and redemption evidence','anonymize',2555,'global','Retain minimum transaction and ownership evidence while removing direct identifiers.',1),
('audit_security','Audit, consent, privacy-request and security evidence','anonymize',2555,'global','Retain limited evidence for compliance, security and legal claims.',1),
('merchant_crm','Merchant-controlled CRM and campaign records','review',NULL,'global','Merchant-controlled records require controller review and processor assistance.',1),
('backup_expiry','Encrypted backups','retain',35,'global','Backups expire through the documented rotation and must not restore erased identity data.',1)
ON DUPLICATE KEY UPDATE data_category=VALUES(data_category),default_action=VALUES(default_action),retention_days=VALUES(retention_days),legal_basis=VALUES(legal_basis),is_enabled=VALUES(is_enabled),updated_at=NOW();

INSERT IGNORE INTO permissions (slug,name) VALUES
('admin.privacy_requests.view','View privacy requests and retention status'),
('admin.privacy_requests.manage','Manage privacy requests, legal holds and erasure processing');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN ('admin.privacy_requests.view','admin.privacy_requests.manage') WHERE r.slug IN ('admin','super_admin');
