-- Creator Campaign Budget Controls v7
-- Scope: campaign budgets, append-only bucket ledger, earning reservations, commitments, releases, caps, and merchant controls.
-- Creator payouts, provider transfers, disputes, tax reporting, and MCP execution remain later phases.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS creator_campaign_budgets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  status ENUM('draft','active','paused','closed') NOT NULL DEFAULT 'draft',
  limit_minor BIGINT UNSIGNED NOT NULL,
  warning_threshold_bps INT UNSIGNED NOT NULL DEFAULT 8000,
  allow_overage TINYINT(1) NOT NULL DEFAULT 0,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_budget_public (public_id),
  UNIQUE KEY uq_cc_budget_campaign_currency (campaign_id,currency),
  KEY idx_cc_budget_status (status,updated_at,id),
  CONSTRAINT fk_cc_budget_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_budget_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_budget_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT chk_cc_budget_warning CHECK (warning_threshold_bps <= 10000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_budget_reservations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  budget_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  earning_event_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  creator_user_id BIGINT UNSIGNED NOT NULL,
  amount_minor BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL,
  status ENUM('reserved','committed','released','cancelled') NOT NULL DEFAULT 'reserved',
  idempotency_hash CHAR(64) NOT NULL,
  reserved_at DATETIME NOT NULL,
  committed_at DATETIME NULL,
  released_at DATETIME NULL,
  reason VARCHAR(2000) NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_budget_reservation_public (public_id),
  UNIQUE KEY uq_cc_budget_reservation_earning (earning_event_id),
  UNIQUE KEY uq_cc_budget_reservation_idempotency (budget_id,idempotency_hash),
  KEY idx_cc_budget_reservation_budget (budget_id,status,updated_at,id),
  KEY idx_cc_budget_reservation_creator (creator_user_id,status,updated_at,id),
  CONSTRAINT fk_cc_budget_reservation_budget FOREIGN KEY (budget_id) REFERENCES creator_campaign_budgets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_budget_reservation_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_budget_reservation_earning FOREIGN KEY (earning_event_id) REFERENCES creator_campaign_earning_events(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_budget_reservation_participant FOREIGN KEY (participant_id) REFERENCES creator_campaign_participants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_budget_reservation_creator FOREIGN KEY (creator_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_budget_reservation_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_budget_reservation_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT chk_cc_budget_reservation_amount CHECK (amount_minor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_budget_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  budget_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  reservation_id BIGINT UNSIGNED NULL,
  earning_event_id BIGINT UNSIGNED NULL,
  event_type ENUM('allocation','allocation_adjustment','reserve','commit','release','restore','close') NOT NULL,
  available_delta_minor BIGINT NOT NULL DEFAULT 0,
  reserved_delta_minor BIGINT NOT NULL DEFAULT 0,
  committed_delta_minor BIGINT NOT NULL DEFAULT 0,
  idempotency_hash CHAR(64) NOT NULL,
  balance_snapshot_json JSON NOT NULL,
  reason VARCHAR(2000) NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_budget_event_public (public_id),
  UNIQUE KEY uq_cc_budget_event_idempotency (budget_id,idempotency_hash),
  KEY idx_cc_budget_event_budget (budget_id,created_at,id),
  KEY idx_cc_budget_event_reservation (reservation_id,created_at,id),
  KEY idx_cc_budget_event_earning (earning_event_id,created_at,id),
  CONSTRAINT fk_cc_budget_event_budget FOREIGN KEY (budget_id) REFERENCES creator_campaign_budgets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_budget_event_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_budget_event_reservation FOREIGN KEY (reservation_id) REFERENCES creator_campaign_budget_reservations(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_budget_event_earning FOREIGN KEY (earning_event_id) REFERENCES creator_campaign_earning_events(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_budget_event_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT chk_cc_budget_event_nonzero CHECK (available_delta_minor <> 0 OR reserved_delta_minor <> 0 OR committed_delta_minor <> 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.creator_budgets.view','View creator campaign budgets','View campaign budget allocation, reservations, commitments, releases, and immutable ledger events.',NOW()),
('merchant.creator_budgets.manage','Manage creator campaign budgets','Create and adjust campaign budgets, reserve earnings, commit obligations, and release reservations.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('merchant.creator_budgets.view','merchant.creator_budgets.manage')
WHERE r.slug IN ('merchant','admin','super_admin');

COMMIT;
