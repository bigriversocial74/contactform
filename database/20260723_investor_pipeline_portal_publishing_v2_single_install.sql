-- Microgifter Investor Pipeline, Portal Publishing & Live Evidence v2
-- Additive single-install migration. Requires Investor Role & Investment Wizard v1.

INSERT INTO permissions (slug,name,created_at) VALUES
('admin.investor_pipeline.view','View investor pipeline operations',NOW()),
('admin.investor_pipeline.manage','Manage investor pipeline, access and follow-ups',NOW()),
('admin.investment.publish','Manage private Investor Portal publishing',NOW()),
('admin.investment.metrics.refresh','Refresh governed investment evidence metrics',NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r JOIN permissions p ON p.slug IN (
  'admin.investor_pipeline.view','admin.investor_pipeline.manage',
  'admin.investment.publish','admin.investment.metrics.refresh'
)
WHERE r.slug IN ('admin','super_admin');

CREATE TABLE IF NOT EXISTS investor_pipeline_records (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  investor_user_id BIGINT UNSIGNED NOT NULL,
  investor_profile_id BIGINT UNSIGNED NOT NULL,
  stage ENUM('approved','qualified','contacted','meeting_scheduled','due_diligence','interested','soft_committed','signed','funded','passed','declined','archived') NOT NULL DEFAULT 'approved',
  priority ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  qualification_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  source VARCHAR(180) NULL,
  capacity_range VARCHAR(80) NULL,
  assigned_user_id BIGINT UNSIGNED NULL,
  tags_json JSON NULL,
  internal_notes TEXT NULL,
  last_contact_at DATETIME NULL,
  next_follow_up_at DATETIME NULL,
  archived_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investor_pipeline_public (public_id),
  UNIQUE KEY uq_investor_pipeline_user (investor_user_id),
  KEY idx_investor_pipeline_stage (stage,priority,next_follow_up_at),
  CONSTRAINT fk_investor_pipeline_user FOREIGN KEY (investor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_pipeline_profile FOREIGN KEY (investor_profile_id) REFERENCES investor_profiles(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_pipeline_assignee FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investor_pipeline_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investor_pipeline_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investor_round_interests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  round_id BIGINT UNSIGNED NOT NULL,
  investor_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('invited','reviewing','interested','soft_committed','signed','funded','passed','declined','archived') NOT NULL DEFAULT 'invited',
  indicated_interest_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  soft_commitment_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  signed_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  funded_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  probability_bps INT UNSIGNED NOT NULL DEFAULT 0,
  next_step VARCHAR(500) NULL,
  notes TEXT NULL,
  last_activity_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investor_round_interest_public (public_id),
  UNIQUE KEY uq_investor_round_interest (round_id,investor_user_id),
  KEY idx_investor_round_interest_status (round_id,status,updated_at),
  CONSTRAINT fk_investor_round_interest_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_round_interest_user FOREIGN KEY (investor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_round_interest_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investor_round_interest_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investor_pipeline_activities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  investor_user_id BIGINT UNSIGNED NOT NULL,
  round_id BIGINT UNSIGNED NULL,
  activity_type ENUM('note','call','email','meeting','access_granted','access_revoked','document_view','portal_view','status_change','commitment_update','task_completed','ai_draft') NOT NULL,
  subject VARCHAR(220) NOT NULL,
  details TEXT NULL,
  metadata_json JSON NULL,
  occurred_at DATETIME NOT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investor_pipeline_activity_public (public_id),
  KEY idx_investor_pipeline_activity_user (investor_user_id,occurred_at),
  KEY idx_investor_pipeline_activity_round (round_id,occurred_at),
  CONSTRAINT fk_investor_pipeline_activity_user FOREIGN KEY (investor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_pipeline_activity_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE SET NULL,
  CONSTRAINT fk_investor_pipeline_activity_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investor_follow_up_tasks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  investor_user_id BIGINT UNSIGNED NOT NULL,
  round_id BIGINT UNSIGNED NULL,
  title VARCHAR(220) NOT NULL,
  details TEXT NULL,
  priority ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  status ENUM('open','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
  assigned_user_id BIGINT UNSIGNED NULL,
  due_at DATETIME NULL,
  completed_at DATETIME NULL,
  completed_by_user_id BIGINT UNSIGNED NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investor_follow_up_public (public_id),
  KEY idx_investor_follow_up_due (status,due_at,priority),
  KEY idx_investor_follow_up_user (investor_user_id,status,due_at),
  CONSTRAINT fk_investor_follow_up_user FOREIGN KEY (investor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_follow_up_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE SET NULL,
  CONSTRAINT fk_investor_follow_up_assignee FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investor_follow_up_completer FOREIGN KEY (completed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investor_follow_up_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_round_publication (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  round_id BIGINT UNSIGNED NOT NULL,
  publication_status ENUM('draft','internal_preview','private_preview','published','paused','archived') NOT NULL DEFAULT 'draft',
  sections_json JSON NOT NULL,
  founder_update TEXT NULL,
  important_notice TEXT NULL,
  preview_token_hash CHAR(64) NULL,
  preview_expires_at DATETIME NULL,
  published_by_user_id BIGINT UNSIGNED NULL,
  published_at DATETIME NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_round_publication (round_id),
  CONSTRAINT fk_investment_round_publication_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_round_publication_publisher FOREIGN KEY (published_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_round_publication_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_portal_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  investor_user_id BIGINT UNSIGNED NOT NULL,
  round_id BIGINT UNSIGNED NULL,
  event_type ENUM('portal_open','round_view','document_open','metric_view') NOT NULL,
  subject_public_id CHAR(36) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_portal_event_public (public_id),
  KEY idx_investment_portal_events_user (investor_user_id,created_at),
  KEY idx_investment_portal_events_round (round_id,created_at),
  CONSTRAINT fk_investment_portal_events_user FOREIGN KEY (investor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_portal_events_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_metric_adapters (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  metric_key VARCHAR(120) NOT NULL,
  label VARCHAR(180) NOT NULL,
  adapter_key VARCHAR(120) NOT NULL,
  description TEXT NULL,
  unit VARCHAR(40) NULL,
  value_type ENUM('actual','projected','manual') NOT NULL DEFAULT 'actual',
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_metric_adapter_metric (metric_key),
  UNIQUE KEY uq_investment_metric_adapter_key (adapter_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO investment_metric_adapters (metric_key,label,adapter_key,description,unit,value_type,enabled) VALUES
('registered_users','Registered users','registered_users','Current non-anonymized Microgifter user accounts.','users','actual',1),
('active_investors','Approved investors','active_investors','Investor profiles with active portal access.','investors','actual',1),
('active_merchants','Active merchants','active_merchants','Active merchant identity profiles when the canonical merchant profile table is available.','merchants','actual',1),
('published_products','Published products','published_products','Published or active products from the canonical product catalog when available.','products','actual',1),
('active_campaigns','Active campaigns','active_campaigns','Active or published merchant campaigns from the canonical campaign system when available.','campaigns','actual',1),
('completed_orders','Completed orders','completed_orders','Completed or paid orders from canonical commerce records when available.','orders','actual',1),
('funded_round_total','Round funded total','funded_round_total','Total funded amount recorded across official investment rounds.','USD','actual',1)
ON DUPLICATE KEY UPDATE label=VALUES(label),description=VALUES(description),unit=VALUES(unit),value_type=VALUES(value_type),enabled=VALUES(enabled);

INSERT INTO investor_pipeline_records
(public_id,investor_user_id,investor_profile_id,stage,priority,qualification_score,capacity_range,created_at,updated_at)
SELECT UUID(),ip.user_id,ip.id,'approved','normal',0,ip.expected_investment_range,NOW(),NOW()
FROM investor_profiles ip
LEFT JOIN investor_pipeline_records pr ON pr.investor_user_id=ip.user_id
WHERE ip.status='active' AND pr.id IS NULL;

INSERT IGNORE INTO schema_migrations (migration_key,description,applied_at)
VALUES ('20260723_investor_pipeline_portal_publishing_v2','Adds investor pipeline operations, round relationships, publication controls, portal events and governed evidence adapters.',NOW());
