-- Stage 4C Feed Posts, Gift Stream Viewer, Product Pages, and Merchant Storefronts

CREATE TABLE IF NOT EXISTS merchant_storefronts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(160) NOT NULL,
  display_name VARCHAR(160) NOT NULL,
  headline VARCHAR(240) NULL,
  description TEXT NULL,
  logo_asset_id BIGINT UNSIGNED NULL,
  cover_asset_id BIGINT UNSIGNED NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  metadata_json JSON NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_storefronts_public_id (public_id),
  UNIQUE KEY uq_merchant_storefronts_slug (slug),
  UNIQUE KEY uq_merchant_storefronts_merchant (merchant_user_id),
  CONSTRAINT fk_storefront_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_storefront_logo FOREIGN KEY (logo_asset_id) REFERENCES catalog_assets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_storefront_cover FOREIGN KEY (cover_asset_id) REFERENCES catalog_assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_posts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  catalog_product_id BIGINT UNSIGNED NOT NULL,
  current_version_id BIGINT UNSIGNED NULL,
  post_type ENUM('simple','image','audio','video','greeting_card','multimedia_card','collab') NOT NULL DEFAULT 'simple',
  visibility ENUM('private','recipient','unlisted','public') NOT NULL DEFAULT 'recipient',
  status ENUM('draft','published','promoted','retired','archived') NOT NULL DEFAULT 'draft',
  promoted_at DATETIME NULL,
  archived_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_feed_posts_public_id (public_id),
  KEY idx_feed_posts_product_status (catalog_product_id, status),
  KEY idx_feed_posts_merchant_status (merchant_user_id, status, updated_at),
  CONSTRAINT fk_feed_posts_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_feed_posts_product FOREIGN KEY (catalog_product_id) REFERENCES catalog_products(id) ON DELETE RESTRICT,
  CONSTRAINT fk_feed_posts_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_post_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  feed_post_id BIGINT UNSIGNED NOT NULL,
  catalog_product_version_id BIGINT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  version_status ENUM('draft','published','retired') NOT NULL DEFAULT 'draft',
  headline VARCHAR(240) NULL,
  caption TEXT NULL,
  cta_label VARCHAR(120) NULL,
  cta_url VARCHAR(500) NULL,
  offer_snapshot_json JSON NULL,
  presentation_json JSON NULL,
  checksum CHAR(64) NOT NULL,
  immutable_at DATETIME NULL,
  published_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_feed_post_versions_public_id (public_id),
  UNIQUE KEY uq_feed_post_versions_number (feed_post_id, version_number),
  KEY idx_feed_post_versions_status (feed_post_id, version_status),
  CONSTRAINT fk_feed_post_versions_post FOREIGN KEY (feed_post_id) REFERENCES feed_posts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_feed_post_versions_product_version FOREIGN KEY (catalog_product_version_id) REFERENCES catalog_product_versions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_feed_post_versions_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_post_elements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  feed_post_version_id BIGINT UNSIGNED NOT NULL,
  element_type ENUM('text','image','audio','video','carousel','offer','cta','claim_panel','other') NOT NULL,
  asset_id BIGINT UNSIGNED NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  content_json JSON NULL,
  checksum CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_feed_post_elements_public_id (public_id),
  KEY idx_feed_post_elements_version_order (feed_post_version_id, sort_order),
  CONSTRAINT fk_feed_post_elements_version FOREIGN KEY (feed_post_version_id) REFERENCES feed_post_versions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_feed_post_elements_asset FOREIGN KEY (asset_id) REFERENCES catalog_assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pppm_feed_bindings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pppm_item_id BIGINT UNSIGNED NOT NULL,
  feed_post_version_id BIGINT UNSIGNED NOT NULL,
  bound_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pppm_feed_binding_item (pppm_item_id),
  KEY idx_pppm_feed_binding_version (feed_post_version_id),
  CONSTRAINT fk_pppm_feed_binding_item FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_feed_binding_version FOREIGN KEY (feed_post_version_id) REFERENCES feed_post_versions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_engagement_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  pppm_item_id BIGINT UNSIGNED NULL,
  feed_post_version_id BIGINT UNSIGNED NOT NULL,
  feed_post_element_id BIGINT UNSIGNED NULL,
  viewer_user_id BIGINT UNSIGNED NULL,
  anonymous_session_hash CHAR(64) NULL,
  event_type ENUM('impression','open','view','play','pause','progress_25','progress_50','progress_75','complete','replay','mute','unmute','carousel_advance','cta_click','claim_open','share') NOT NULL,
  playback_position_ms INT UNSIGNED NULL,
  metadata_json JSON NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_content_engagement_events_public_id (public_id),
  KEY idx_engagement_version_event_time (feed_post_version_id, event_type, occurred_at),
  KEY idx_engagement_pppm_time (pppm_item_id, occurred_at),
  CONSTRAINT fk_engagement_pppm FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_engagement_version FOREIGN KEY (feed_post_version_id) REFERENCES feed_post_versions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_engagement_element FOREIGN KEY (feed_post_element_id) REFERENCES feed_post_elements(id) ON DELETE SET NULL,
  CONSTRAINT fk_engagement_viewer FOREIGN KEY (viewer_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE feed_posts
  ADD CONSTRAINT fk_feed_posts_current_version FOREIGN KEY (current_version_id) REFERENCES feed_post_versions(id) ON DELETE RESTRICT;

INSERT IGNORE INTO permissions (slug, name, description, created_at) VALUES
('feed.posts.manage','Manage feed posts','Create and version feed presentation content.',NOW()),
('feed.posts.publish','Publish feed posts','Publish and promote immutable feed content.',NOW()),
('storefront.manage','Manage storefront','Manage merchant storefront profiles.',NOW()),
('engagement.record','Record engagement','Record content engagement events.',NOW());

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW() FROM roles r JOIN permissions p
ON p.slug IN ('feed.posts.manage','feed.posts.publish','storefront.manage','engagement.record')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW() FROM roles r JOIN permissions p
ON p.slug = 'engagement.record' WHERE r.slug = 'customer';