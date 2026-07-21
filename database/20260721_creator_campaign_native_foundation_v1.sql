-- Creator Campaign Native Foundation v1
-- Scope: isolated campaign ownership, product links, eligibility rules,
-- lifecycle history, optimistic locking, permissions, and audit-ready metadata.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS creator_campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  internal_reference VARCHAR(100) NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  objective VARCHAR(180) NULL,
  category VARCHAR(100) NULL,
  access_mode ENUM('open','invite_only','hybrid') NOT NULL DEFAULT 'open',
  status ENUM('draft','scheduled','active','paused','completed','archived','cancelled') NOT NULL DEFAULT 'draft',
  timezone VARCHAR(80) NOT NULL DEFAULT 'UTC',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  application_deadline_at DATETIME NULL,
  geographic_scope_json JSON NULL,
  cover_asset_id BIGINT UNSIGNED NULL,
  metadata_json JSON NULL,
  creation_idempotency_hash CHAR(64) NOT NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  published_at DATETIME NULL,
  paused_at DATETIME NULL,
  completed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  archived_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_public (public_id),
  UNIQUE KEY uq_creator_campaign_workspace_reference (workspace_id,internal_reference),
  UNIQUE KEY uq_creator_campaign_workspace_idempotency (workspace_id,creation_idempotency_hash),
  KEY idx_creator_campaign_workspace_status (workspace_id,status,updated_at),
  KEY idx_creator_campaign_schedule (status,starts_at,ends_at),
  KEY idx_creator_campaign_creator (created_by_user_id,created_at),
  CONSTRAINT fk_creator_campaign_workspace FOREIGN KEY (workspace_id) REFERENCES merchant_workspaces(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_cover_asset FOREIGN KEY (cover_asset_id) REFERENCES catalog_assets(id) ON DELETE SET NULL,
  CONSTRAINT fk_creator_campaign_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  selected_product_version_id BIGINT UNSIGNED NULL,
  relationship_type ENUM('primary','featured','commissionable','excluded','creator_compensation') NOT NULL DEFAULT 'featured',
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  value_snapshot_cents INT UNSIGNED NULL,
  currency CHAR(3) NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_product_public (public_id),
  UNIQUE KEY uq_creator_campaign_product_relation (campaign_id,product_id,relationship_type),
  KEY idx_creator_campaign_products_order (campaign_id,sort_order,id),
  KEY idx_creator_campaign_products_product (product_id,campaign_id),
  CONSTRAINT fk_creator_campaign_product_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_creator_campaign_product_product FOREIGN KEY (product_id) REFERENCES catalog_products(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_product_version FOREIGN KEY (selected_product_version_id) REFERENCES catalog_product_versions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_product_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_product_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_eligibility_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  rule_type ENUM('specialty','category','platform','verification','location','audience','existing_relationship') NOT NULL,
  operator_key ENUM('equals','not_equals','contains','in','gte','lte','between','exists') NOT NULL DEFAULT 'equals',
  value_json JSON NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_rule_public (public_id),
  KEY idx_creator_campaign_rules_order (campaign_id,sort_order,id),
  KEY idx_creator_campaign_rules_type (campaign_id,rule_type,is_required),
  CONSTRAINT fk_creator_campaign_rule_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_creator_campaign_rule_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_rule_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_status_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(40) NULL,
  to_status VARCHAR(40) NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(500) NULL,
  idempotency_hash CHAR(64) NOT NULL,
  before_snapshot_json JSON NULL,
  after_snapshot_json JSON NULL,
  context_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_status_public (public_id),
  UNIQUE KEY uq_creator_campaign_status_idempotency (campaign_id,idempotency_hash),
  KEY idx_creator_campaign_status_timeline (campaign_id,created_at,id),
  KEY idx_creator_campaign_status_actor (actor_user_id,created_at),
  CONSTRAINT fk_creator_campaign_status_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_status_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.creator_campaigns.view','View creator campaigns','View creator campaigns owned by an authorized merchant workspace.',NOW()),
('merchant.creator_campaigns.manage','Manage creator campaigns','Create and update creator campaign drafts and native configuration.',NOW()),
('merchant.creator_campaigns.publish','Publish creator campaigns','Move creator campaigns through approved publication lifecycle states.',NOW()),
('merchant.creator_directory.view','View creator directory','View eligible Creator-model participants without exposing affiliate identities.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN (
  'merchant.creator_campaigns.view',
  'merchant.creator_campaigns.manage',
  'merchant.creator_campaigns.publish',
  'merchant.creator_directory.view'
)
WHERE r.slug IN ('merchant','admin','super_admin');

COMMIT;
