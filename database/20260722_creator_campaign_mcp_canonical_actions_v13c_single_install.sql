-- Creator Campaign MCP Canonical Actions v13C
-- Adds explicit owner-approved action scopes, action approval evidence, and native earning decisions.
-- External MCP clients may request actions only. Approval and execution remain separate merchant-owner actions.

START TRANSACTION;

INSERT INTO mcp_scope_catalog
(scope_key,display_name,description,operation_class,active,grantable,created_at,updated_at)
VALUES
('creator_campaigns:publish','Manage Creator Campaign lifecycle','Request owner-approved publication, scheduling, pause, resume, completion, or cancellation through native services.','approval_gated',1,1,NOW(),NOW()),
('creator_campaign_participants:manage','Manage Creator Campaign participants','Request owner-approved application decisions, invitations, and participant controls through native services.','approval_gated',1,1,NOW(),NOW()),
('creator_campaign_agreements:manage','Manage Creator Campaign agreements','Request owner-approved agreement offers through native immutable agreement services.','approval_gated',1,1,NOW(),NOW()),
('creator_campaign_submissions:review','Review Creator Campaign submissions','Request owner-approved submission approval, revision, or rejection through native review services.','approval_gated',1,1,NOW(),NOW()),
('creator_campaign_attribution:manage','Manage Creator Campaign attribution','Request owner-approved attribution overrides through native attribution services.','approval_gated',1,1,NOW(),NOW()),
('creator_campaign_earnings:manage','Manage Creator Campaign earnings','Request owner-approved earning approval, hold, rejection, or reversal through native earning services.','approval_gated',1,1,NOW(),NOW()),
('creator_campaign_payouts:manage','Record Creator Campaign payouts','Request owner-approved creation of internal payout records from eligible committed reservations. No payment provider is called.','approval_gated',1,1,NOW(),NOW()),
('creator_campaign_disputes:manage','Resolve Creator Campaign disputes','Request owner-approved dispute resolutions through native dispute services.','approval_gated',1,1,NOW(),NOW())
ON DUPLICATE KEY UPDATE
  display_name=VALUES(display_name),
  description=VALUES(description),
  operation_class='approval_gated',
  active=1,
  grantable=1,
  updated_at=NOW();

CREATE TABLE IF NOT EXISTS mcp_creator_campaign_action_approvals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  action_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','approved','rejected','expired','cancelled','consumed') NOT NULL DEFAULT 'pending',
  requested_reason VARCHAR(1000) NOT NULL,
  decision_reason VARCHAR(1000) NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_at DATETIME NULL,
  decided_by_user_id BIGINT UNSIGNED NULL,
  expires_at DATETIME NOT NULL,
  executed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mcp_cc_action_approval_public (public_id),
  UNIQUE KEY uq_mcp_cc_action_approval_action (action_id),
  KEY idx_mcp_cc_action_approval_owner (owner_user_id,status,requested_at),
  KEY idx_mcp_cc_action_approval_expiry (status,expires_at),
  CONSTRAINT fk_mcp_cc_action_approval_action FOREIGN KEY (action_id) REFERENCES mcp_automation_actions(id) ON DELETE CASCADE,
  CONSTRAINT fk_mcp_cc_action_approval_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_mcp_cc_action_approval_decider FOREIGN KEY (decided_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_earning_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  earning_event_id BIGINT UNSIGNED NOT NULL,
  status ENUM('approved','held','rejected','reversed') NOT NULL,
  decision_reason VARCHAR(2000) NOT NULL,
  budget_reservation_id BIGINT UNSIGNED NULL,
  reversal_event_id BIGINT UNSIGNED NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  decided_by_user_id BIGINT UNSIGNED NOT NULL,
  approved_at DATETIME NULL,
  held_at DATETIME NULL,
  rejected_at DATETIME NULL,
  reversed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_earning_review_public (public_id),
  UNIQUE KEY uq_cc_earning_review_event (earning_event_id),
  KEY idx_cc_earning_review_status (status,updated_at),
  CONSTRAINT fk_cc_earning_review_event FOREIGN KEY (earning_event_id) REFERENCES creator_campaign_earning_events(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_earning_review_reservation FOREIGN KEY (budget_reservation_id) REFERENCES creator_campaign_budget_reservations(id) ON DELETE SET NULL,
  CONSTRAINT fk_cc_earning_review_reversal FOREIGN KEY (reversal_event_id) REFERENCES creator_campaign_earning_events(id) ON DELETE SET NULL,
  CONSTRAINT fk_cc_earning_review_decider FOREIGN KEY (decided_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.creator_earnings.manage','Manage creator earning decisions','Approve, hold, reject, and reverse Creator Campaign earnings through native services.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug='merchant.creator_earnings.manage'
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  '20260722_creator_campaign_mcp_canonical_actions_v13c_single_install',
  'Creator Campaign MCP Phase 13C approval-gated scopes, owner action approvals, and native earning decisions.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
