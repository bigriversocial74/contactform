-- Stage 18H feed media assets

CREATE TABLE IF NOT EXISTS feed_post_assets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  feed_post_id BIGINT UNSIGNED NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  role ENUM('primary','gallery','audio','video','attachment') NOT NULL DEFAULT 'gallery',
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  alt_text VARCHAR(240) NULL,
  caption VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_feed_post_assets_post_asset (feed_post_id,asset_id),
  KEY idx_feed_post_assets_post_order (feed_post_id,sort_order,id),
  KEY idx_feed_post_assets_asset (asset_id,feed_post_id),
  CONSTRAINT fk_feed_post_assets_post FOREIGN KEY (feed_post_id) REFERENCES feed_posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_feed_post_assets_asset FOREIGN KEY (asset_id) REFERENCES catalog_assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_18h_feed_media_assets','Links uploaded media assets to feed posts with durable ordering and presentation metadata.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
