-- Stage 4D Digital Fulfillment, Secure Downloads, and Production Media Delivery

CREATE TABLE IF NOT EXISTS media_delivery_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  name VARCHAR(120) NOT NULL,
  media_type ENUM('image','audio','video','document','download') NOT NULL,
  profile_key VARCHAR(80) NOT NULL,
  settings_json JSON NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_media_delivery_profiles_public_id (public_id),
  UNIQUE KEY uq_media_delivery_profiles_key (profile_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_asset_variants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  source_asset_id BIGINT UNSIGNED NOT NULL,
  profile_id BIGINT UNSIGNED NOT NULL,
  storage_provider VARCHAR(80) NOT NULL,
  storage_key VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  byte_size BIGINT UNSIGNED NULL,
  checksum_sha256 CHAR(64) NULL,
  width_px INT UNSIGNED NULL,
  height_px INT UNSIGNED NULL,
  duration_ms INT UNSIGNED NULL,
  bitrate_kbps INT UNSIGNED NULL,
  status ENUM('queued','processing','ready','failed','quarantined','retired') NOT NULL DEFAULT 'queued',
  failure_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catalog_asset_variants_public_id (public_id),
  UNIQUE KEY uq_catalog_asset_variants_profile (source_asset_id, profile_id),
  UNIQUE KEY uq_catalog_asset_variants_storage (storage_provider, storage_key),
  KEY idx_catalog_asset_variants_status (status, updated_at),
  CONSTRAINT fk_asset_variants_source FOREIGN KEY (source_asset_id) REFERENCES catalog_assets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_asset_variants_profile FOREIGN KEY (profile_id) REFERENCES media_delivery_profiles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media_processing_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  source_asset_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NULL,
  job_type ENUM('transcode','thumbnail','poster','normalize_audio','scan','metadata') NOT NULL,
  status ENUM('queued','processing','completed','failed','cancelled') NOT NULL DEFAULT 'queued',
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  next_attempt_at DATETIME NULL,
  locked_at DATETIME NULL,
  locked_by VARCHAR(120) NULL,
  payload_json JSON NULL,
  failure_message VARCHAR(500) NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_media_processing_jobs_public_id (public_id),
  KEY idx_media_processing_jobs_queue (status, next_attempt_at, created_at),
  CONSTRAINT fk_media_processing_jobs_source FOREIGN KEY (source_asset_id) REFERENCES catalog_assets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_media_processing_jobs_variant FOREIGN KEY (variant_id) REFERENCES catalog_asset_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS digital_fulfillment_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  product_version_id BIGINT UNSIGNED NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  access_mode ENUM('stream','download','both') NOT NULL DEFAULT 'download',
  max_downloads INT UNSIGNED NULL,
  access_duration_seconds INT UNSIGNED NULL,
  filename_override VARCHAR(255) NULL,
  disposition ENUM('inline','attachment') NOT NULL DEFAULT 'attachment',
  status ENUM('active','inactive','retired') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_digital_fulfillment_rules_public_id (public_id),
  UNIQUE KEY uq_digital_fulfillment_rules_version_asset (product_version_id, asset_id),
  CONSTRAINT fk_fulfillment_rules_version FOREIGN KEY (product_version_id) REFERENCES catalog_product_versions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_fulfillment_rules_asset FOREIGN KEY (asset_id) REFERENCES catalog_assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS digital_entitlements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  pppm_item_id BIGINT UNSIGNED NOT NULL,
  fulfillment_rule_id BIGINT UNSIGNED NOT NULL,
  entitled_user_id BIGINT UNSIGNED NULL,
  status ENUM('active','exhausted','expired','revoked') NOT NULL DEFAULT 'active',
  downloads_used INT UNSIGNED NOT NULL DEFAULT 0,
  first_accessed_at DATETIME NULL,
  last_accessed_at DATETIME NULL,
  expires_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_digital_entitlements_public_id (public_id),
  UNIQUE KEY uq_digital_entitlements_item_rule (pppm_item_id, fulfillment_rule_id),
  KEY idx_digital_entitlements_user_status (entitled_user_id, status, expires_at),
  CONSTRAINT fk_digital_entitlements_item FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_digital_entitlements_rule FOREIGN KEY (fulfillment_rule_id) REFERENCES digital_fulfillment_rules(id) ON DELETE RESTRICT,
  CONSTRAINT fk_digital_entitlements_user FOREIGN KEY (entitled_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS digital_access_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  entitlement_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  event_type ENUM('token_issued','stream_started','download_started','download_completed','denied','expired','revoked') NOT NULL,
  ip_hash CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  bytes_served BIGINT UNSIGNED NULL,
  metadata_json JSON NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_digital_access_events_public_id (public_id),
  KEY idx_digital_access_events_entitlement_time (entitlement_id, occurred_at),
  CONSTRAINT fk_digital_access_events_entitlement FOREIGN KEY (entitlement_id) REFERENCES digital_entitlements(id) ON DELETE RESTRICT,
  CONSTRAINT fk_digital_access_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media_delivery_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  asset_id BIGINT UNSIGNED NULL,
  variant_id BIGINT UNSIGNED NULL,
  entitlement_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  purpose ENUM('feed_stream','public_product','download','preview') NOT NULL,
  disposition ENUM('inline','attachment') NOT NULL DEFAULT 'inline',
  expires_at DATETIME NOT NULL,
  max_uses INT UNSIGNED NULL,
  use_count INT UNSIGNED NOT NULL DEFAULT 0,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_media_delivery_tokens_public_id (public_id),
  UNIQUE KEY uq_media_delivery_tokens_hash (token_hash),
  KEY idx_media_delivery_tokens_expiry (expires_at, revoked_at),
  CONSTRAINT fk_media_tokens_asset FOREIGN KEY (asset_id) REFERENCES catalog_assets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_media_tokens_variant FOREIGN KEY (variant_id) REFERENCES catalog_asset_variants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_media_tokens_entitlement FOREIGN KEY (entitlement_id) REFERENCES digital_entitlements(id) ON DELETE RESTRICT,
  CONSTRAINT fk_media_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_engagement_daily (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  metric_date DATE NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  feed_post_version_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  event_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  unique_viewers BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_playback_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_content_engagement_daily (metric_date, merchant_user_id, feed_post_version_id, event_type),
  CONSTRAINT fk_engagement_daily_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_engagement_daily_version FOREIGN KEY (feed_post_version_id) REFERENCES feed_post_versions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE catalog_assets
  ADD COLUMN moderation_status ENUM('unreviewed','approved','quarantined','blocked','takedown') NOT NULL DEFAULT 'unreviewed' AFTER status,
  ADD COLUMN retention_until DATETIME NULL AFTER moderation_status,
  ADD COLUMN deleted_at DATETIME NULL AFTER retention_until;

INSERT IGNORE INTO media_delivery_profiles (public_id, name, media_type, profile_key, settings_json, status, created_at, updated_at) VALUES
(UUID(), 'Video 720p', 'video', 'video_720p', JSON_OBJECT('width',1280,'height',720,'codec','h264','container','mp4'), 'active', NOW(), NOW()),
(UUID(), 'Video poster', 'image', 'video_poster', JSON_OBJECT('width',720,'format','webp'), 'active', NOW(), NOW()),
(UUID(), 'Audio normalized', 'audio', 'audio_normalized', JSON_OBJECT('codec','aac','loudness_lufs',-16), 'active', NOW(), NOW()),
(UUID(), 'Image medium', 'image', 'image_medium', JSON_OBJECT('width',1280,'format','webp'), 'active', NOW(), NOW());

INSERT IGNORE INTO permissions (slug, name, description, created_at) VALUES
('fulfillment.rules.manage','Manage fulfillment rules','Configure digital fulfillment for published product versions.',NOW()),
('fulfillment.entitlements.issue','Issue digital entitlements','Create digital entitlements for PPPM items.',NOW()),
('fulfillment.analytics.view','View fulfillment analytics','View media, download, and engagement analytics.',NOW()),
('media.moderate','Moderate media','Quarantine, block, or approve media assets.',NOW());

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW() FROM roles r JOIN permissions p
ON p.slug IN ('fulfillment.rules.manage','fulfillment.entitlements.issue','fulfillment.analytics.view')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW() FROM roles r JOIN permissions p
ON p.slug = 'media.moderate' WHERE r.slug IN ('admin','super_admin');