-- Microgifter Stamp System Consolidated Install
-- Imports Stage 17 + Stage 17B + Stage 17C in one file.
-- Safe to run more than once for table creation and seed records.

CREATE TABLE IF NOT EXISTS stamp_debit_actions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  action_key VARCHAR(120) NOT NULL,
  label VARCHAR(190) NOT NULL,
  channel VARCHAR(60) NOT NULL,
  scope VARCHAR(60) NOT NULL,
  stamp_value INT UNSIGNED NOT NULL DEFAULT 1,
  description VARCHAR(500) NULL,
  status ENUM('active','disabled','archived') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stamp_debit_actions_public_id (public_id),
  UNIQUE KEY uq_stamp_debit_actions_key (action_key),
  KEY idx_stamp_debit_actions_status (status,channel,scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_stamp_balances (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_user_id BIGINT UNSIGNED NOT NULL,
  balance INT NOT NULL DEFAULT 0,
  included_monthly_stamps INT UNSIGNED NOT NULL DEFAULT 0,
  purchased_stamps INT UNSIGNED NOT NULL DEFAULT 0,
  used_stamps INT UNSIGNED NOT NULL DEFAULT 0,
  voided_stamps INT UNSIGNED NOT NULL DEFAULT 0,
  current_period_key VARCHAR(20) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_account_stamp_balance_user_period (account_user_id,current_period_key),
  KEY idx_account_stamp_balance_user (account_user_id,updated_at),
  CONSTRAINT fk_account_stamp_balance_user FOREIGN KEY (account_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stamp_ledger_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  account_user_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  actor_type ENUM('system','merchant','admin','customer','api') NOT NULL DEFAULT 'system',
  entry_type ENUM('credit','debit','void','adjustment') NOT NULL,
  action_key VARCHAR(120) NULL,
  stamp_value INT UNSIGNED NOT NULL DEFAULT 0,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  delta INT NOT NULL,
  balance_after INT NOT NULL,
  source_type VARCHAR(80) NOT NULL DEFAULT 'manual',
  source_id VARCHAR(190) NULL,
  reference VARCHAR(190) NULL,
  reason_code VARCHAR(120) NULL,
  note VARCHAR(1000) NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stamp_ledger_public_id (public_id),
  UNIQUE KEY uq_stamp_ledger_account_idempotency (account_user_id,idempotency_key),
  KEY idx_stamp_ledger_account_created (account_user_id,created_at,id),
  KEY idx_stamp_ledger_action (action_key,created_at),
  KEY idx_stamp_ledger_source (source_type,source_id),
  CONSTRAINT fk_stamp_ledger_account_user FOREIGN KEY (account_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_stamp_ledger_actor_user FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stamp_bundles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  bundle_key VARCHAR(120) NOT NULL,
  label VARCHAR(190) NOT NULL,
  stamps INT UNSIGNED NOT NULL,
  price_cents INT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  status ENUM('active','disabled','archived') NOT NULL DEFAULT 'active',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stamp_bundles_public_id (public_id),
  UNIQUE KEY uq_stamp_bundles_key (bundle_key),
  KEY idx_stamp_bundles_status_sort (status,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stamp_purchases (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  account_user_id BIGINT UNSIGNED NOT NULL,
  bundle_id BIGINT UNSIGNED NOT NULL,
  bundle_key VARCHAR(120) NOT NULL,
  label_snapshot VARCHAR(190) NOT NULL,
  stamps_snapshot INT UNSIGNED NOT NULL,
  price_cents_snapshot INT UNSIGNED NOT NULL,
  currency_snapshot CHAR(3) NOT NULL DEFAULT 'USD',
  status ENUM('pending','checkout_created','paid','credited','cancelled','failed') NOT NULL DEFAULT 'pending',
  checkout_reference VARCHAR(190) NULL,
  credited_ledger_entry_public_id CHAR(36) NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  paid_at DATETIME NULL,
  credited_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stamp_purchases_public_id (public_id),
  UNIQUE KEY uq_stamp_purchases_account_idempotency (account_user_id,idempotency_key),
  KEY idx_stamp_purchases_account_created (account_user_id,created_at,id),
  KEY idx_stamp_purchases_bundle (bundle_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_package_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  account_user_id BIGINT UNSIGNED NOT NULL,
  package_id VARCHAR(80) NOT NULL,
  status ENUM('active','paused','cancelled','archived') NOT NULL DEFAULT 'active',
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  renews_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_account_package_assignment_public_id (public_id),
  KEY idx_account_package_assignment_user_status (account_user_id,status),
  KEY idx_account_package_assignment_status_package (status,package_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO stamp_debit_actions (public_id,action_key,label,channel,scope,stamp_value,description,status,created_at,updated_at) VALUES
(UUID(),'direct_microgift_send','Direct Microgift send','Direct','Microgift',1,'Sending one paid Microgift directly to one recipient.','active',NOW(),NOW()),
(UUID(),'direct_reward_send','Direct Reward send','Direct','Reward',1,'Sending one promotional Reward directly to one recipient.','active',NOW(),NOW()),
(UUID(),'campaign_feed_send','Campaign feed send','Feed','Campaign',1,'Publishing one campaign distribution item into the Microgifter feed.','active',NOW(),NOW()),
(UUID(),'email_list_send','Email list send','Email','CRM',1,'Sending one campaign, Microgift, or Reward message to one email recipient.','active',NOW(),NOW()),
(UUID(),'sms_send','SMS send','SMS','CRM',3,'Sending one campaign, Microgift, or Reward message to one SMS recipient.','active',NOW(),NOW()),
(UUID(),'qr_claim_prompt_send','QR claim prompt send','QR','Claim',1,'Sending a claim prompt or follow-up from a QR/table tent campaign.','active',NOW(),NOW()),
(UUID(),'regift_send','Regift send','Direct','Microgift',1,'Sending a regifted Microgift or promotional Reward to a new recipient.','active',NOW(),NOW()),
(UUID(),'agentic_discovery_send','Agentic discovery send','Discovery','Automation',2,'Automated discovery, recommendation, or agent-driven distribution send.','active',NOW(),NOW());

INSERT IGNORE INTO stamp_bundles (public_id,bundle_key,label,stamps,price_cents,currency,status,sort_order,created_at,updated_at) VALUES
(UUID(),'stamps_1000','1,000 Stamps',1000,1500,'USD','active',10,NOW(),NOW()),
(UUID(),'stamps_5000','5,000 Stamps',5000,6500,'USD','active',20,NOW(),NOW()),
(UUID(),'stamps_25000','25,000 Stamps',25000,25000,'USD','active',30,NOW(),NOW());

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('stamps.view_own','View own Stamp ledger','View merchant-scoped Stamp balances and ledger activity.',NOW()),
('stamps.debit','Debit Stamps','Debit Stamps for send and distribution actions.',NOW()),
('stamps.credit','Credit Stamps','Credit purchased, monthly, voided, or adjusted Stamps.',NOW()),
('admin.stamps.view','View all Stamp ledgers','View Stamp ledgers across merchant accounts.',NOW()),
('admin.stamps.manage','Manage Stamp ledgers','Apply Stamp credits, debits, voids, and adjustments.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN ('stamps.view_own','stamps.debit')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN ('stamps.credit','admin.stamps.view','admin.stamps.manage')
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_17_stamp_ledger','Stamp debit actions, balances, immutable ledger entries, bulk bundles, and API permissions.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_17b_stamp_purchases','Stamp bundle purchase tracking and post-payment credit lifecycle.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_17c_stamp_package_assignments','Account package assignments for automatic monthly Stamp allowance renewals.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
