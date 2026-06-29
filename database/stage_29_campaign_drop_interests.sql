-- Stage 29 Campaign Drop Interests
-- Safe to re-run.
-- Adds public/guest/user interest capture for World Canvas Target Drops.

CREATE TABLE IF NOT EXISTS merchant_target_drop_interests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(64) NOT NULL,
  target_drop_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  guest_email VARCHAR(190) NULL,
  guest_phone VARCHAR(64) NULL,
  status ENUM('interested','joined','claimed','dismissed') NOT NULL DEFAULT 'interested',
  source ENUM('world_canvas','shared_link','qr','feed','admin') NOT NULL DEFAULT 'world_canvas',
  ip_country VARCHAR(80) NULL,
  ip_region VARCHAR(120) NULL,
  ip_city VARCHAR(120) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_target_drop_interest_public_id (public_id),
  UNIQUE KEY uq_target_drop_interest_user (target_drop_id, user_id),
  KEY idx_target_drop_interest_drop_status (target_drop_id, status, created_at),
  KEY idx_target_drop_interest_merchant_status (merchant_user_id, status, created_at),
  KEY idx_target_drop_interest_guest_email (guest_email),
  CONSTRAINT fk_target_drop_interest_drop FOREIGN KEY (target_drop_id) REFERENCES merchant_target_drops(id) ON DELETE CASCADE,
  CONSTRAINT fk_target_drop_interest_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_target_drop_interest_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key, description, checksum, applied_at)
VALUES ('stage_29_campaign_drop_interests', 'Campaign Drop interest capture', NULL, NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description);
