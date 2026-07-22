-- Creator Campaign Tracking and Attribution v5
-- Scope: creator share sources, privacy-safe immutable events, attribution decisions, audit, and reporting.
-- Compensation, earnings, budget ledger, payouts, disputes, and MCP execution remain later phases.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS creator_campaign_tracking_sources (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  creator_user_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(180) NOT NULL,
  channel ENUM('link','social','email','sms','qr','embed','other') NOT NULL DEFAULT 'link',
  platform VARCHAR(80) NULL,
  destination_path VARCHAR(1000) NOT NULL,
  tracking_code CHAR(32) NOT NULL,
  attribution_model ENUM('first_touch','last_touch','direct') NOT NULL DEFAULT 'last_touch',
  click_window_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  conversion_window_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  metadata_json JSON NULL,
  status ENUM('active','paused','retired') NOT NULL DEFAULT 'active',
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_tracking_source_public (public_id),
  UNIQUE KEY uq_cc_tracking_source_code (tracking_code),
  KEY idx_cc_tracking_source_campaign (campaign_id,status,updated_at,id),
  KEY idx_cc_tracking_source_participant (participant_id,status,updated_at,id),
  KEY idx_cc_tracking_source_creator (creator_user_id,status,updated_at,id),
  CONSTRAINT fk_cc_tracking_source_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_tracking_source_participant FOREIGN KEY (participant_id) REFERENCES creator_campaign_participants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_tracking_source_creator FOREIGN KEY (creator_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_tracking_source_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_tracking_source_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_tracking_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  source_id BIGINT UNSIGNED NULL,
  participant_id BIGINT UNSIGNED NULL,
  creator_user_id BIGINT UNSIGNED NULL,
  event_type ENUM('click','landing_view','engagement','lead','checkout','purchase','claim','redemption','custom') NOT NULL,
  event_key VARCHAR(190) NOT NULL,
  session_hash CHAR(64) NULL,
  visitor_hash CHAR(64) NULL,
  request_hash CHAR(64) NULL,
  target_path VARCHAR(1000) NULL,
  referrer_host VARCHAR(255) NULL,
  metadata_json JSON NULL,
  status ENUM('accepted','duplicate','suspect','invalidated') NOT NULL DEFAULT 'accepted',
  is_unique TINYINT(1) NOT NULL DEFAULT 1,
  risk_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  risk_flags_json JSON NULL,
  occurred_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_tracking_event_public (public_id),
  UNIQUE KEY uq_cc_tracking_event_key (campaign_id,event_key),
  KEY idx_cc_tracking_event_campaign (campaign_id,event_type,occurred_at,id),
  KEY idx_cc_tracking_event_source (source_id,event_type,occurred_at,id),
  KEY idx_cc_tracking_event_participant (participant_id,event_type,occurred_at,id),
  KEY idx_cc_tracking_event_session (campaign_id,session_hash,occurred_at,id),
  KEY idx_cc_tracking_event_visitor (campaign_id,visitor_hash,occurred_at,id),
  KEY idx_cc_tracking_event_status (status,occurred_at,id),
  CONSTRAINT fk_cc_tracking_event_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_tracking_event_source FOREIGN KEY (source_id) REFERENCES creator_campaign_tracking_sources(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_tracking_event_participant FOREIGN KEY (participant_id) REFERENCES creator_campaign_participants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_tracking_event_creator FOREIGN KEY (creator_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_attributions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  conversion_event_id BIGINT UNSIGNED NOT NULL,
  touch_event_id BIGINT UNSIGNED NULL,
  source_id BIGINT UNSIGNED NULL,
  participant_id BIGINT UNSIGNED NULL,
  creator_user_id BIGINT UNSIGNED NULL,
  attribution_model ENUM('first_touch','last_touch','direct','manual') NOT NULL,
  status ENUM('attributed','unattributed','overridden','invalidated') NOT NULL DEFAULT 'attributed',
  confidence_score SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  decision_reason VARCHAR(2000) NULL,
  window_started_at DATETIME NULL,
  window_ended_at DATETIME NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  decided_by_user_id BIGINT UNSIGNED NULL,
  attributed_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_attribution_public (public_id),
  UNIQUE KEY uq_cc_attribution_conversion (conversion_event_id),
  KEY idx_cc_attribution_touch (touch_event_id,status,attributed_at,id),
  KEY idx_cc_attribution_campaign (campaign_id,status,attributed_at,id),
  KEY idx_cc_attribution_source (source_id,status,attributed_at,id),
  KEY idx_cc_attribution_participant (participant_id,status,attributed_at,id),
  CONSTRAINT fk_cc_attribution_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_attribution_conversion FOREIGN KEY (conversion_event_id) REFERENCES creator_campaign_tracking_events(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_attribution_touch FOREIGN KEY (touch_event_id) REFERENCES creator_campaign_tracking_events(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_attribution_source FOREIGN KEY (source_id) REFERENCES creator_campaign_tracking_sources(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_attribution_participant FOREIGN KEY (participant_id) REFERENCES creator_campaign_participants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_attribution_creator FOREIGN KEY (creator_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_attribution_decider FOREIGN KEY (decided_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_attribution_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  attribution_id BIGINT UNSIGNED NOT NULL,
  conversion_event_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  event_type ENUM('auto_attributed','auto_unattributed','manual_override','invalidated','reprocessed') NOT NULL,
  from_source_id BIGINT UNSIGNED NULL,
  to_source_id BIGINT UNSIGNED NULL,
  reason VARCHAR(2000) NULL,
  context_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_attribution_event_public (public_id),
  KEY idx_cc_attribution_event_attribution (attribution_id,created_at,id),
  KEY idx_cc_attribution_event_conversion (conversion_event_id,created_at,id),
  CONSTRAINT fk_cc_attr_event_attribution FOREIGN KEY (attribution_id) REFERENCES creator_campaign_attributions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_attr_event_conversion FOREIGN KEY (conversion_event_id) REFERENCES creator_campaign_tracking_events(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_attr_event_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_attr_event_from_source FOREIGN KEY (from_source_id) REFERENCES creator_campaign_tracking_sources(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_attr_event_to_source FOREIGN KEY (to_source_id) REFERENCES creator_campaign_tracking_sources(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.creator_tracking.view','View creator tracking','View creator tracking sources, privacy-safe events, and performance reporting.',NOW()),
('merchant.creator_tracking.manage','Manage creator tracking','Create, pause, retire, and reconcile creator campaign tracking sources and events.',NOW()),
('merchant.creator_attribution.view','View creator attribution','View attribution decisions and immutable attribution audit history.',NOW()),
('merchant.creator_attribution.manage','Manage creator attribution','Override, invalidate, and reprocess creator campaign attribution decisions.',NOW()),
('creator.campaign_tracking.view_own','View own campaign tracking','View the authenticated Creator account tracking sources and performance.',NOW()),
('creator.campaign_tracking.manage_own','Manage own campaign tracking','Create, pause, and retire tracking sources for active accepted campaign participation.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN (
  'merchant.creator_tracking.view',
  'merchant.creator_tracking.manage',
  'merchant.creator_attribution.view',
  'merchant.creator_attribution.manage'
)
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN (
  'creator.campaign_tracking.view_own',
  'creator.campaign_tracking.manage_own'
)
WHERE r.slug IN ('creator','admin','super_admin');

COMMIT;
