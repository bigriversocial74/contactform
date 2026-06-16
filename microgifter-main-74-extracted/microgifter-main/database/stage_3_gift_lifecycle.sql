-- Microgifter Stage 3 Gift Creation, Delivery, and Claim Lifecycle
-- MySQL-compatible idempotent column and index additions.

SET @mg_has_gifts_slug := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gifts' AND COLUMN_NAME = 'slug'
);
SET @mg_sql := IF(
  @mg_has_gifts_slug = 0,
  'ALTER TABLE gifts ADD COLUMN slug VARCHAR(160) NULL AFTER public_id',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_gifts_visibility := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gifts' AND COLUMN_NAME = 'visibility'
);
SET @mg_sql := IF(
  @mg_has_gifts_visibility = 0,
  'ALTER TABLE gifts ADD COLUMN visibility ENUM(''draft'',''private'',''published'') NOT NULL DEFAULT ''draft'' AFTER currency',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_gifts_published_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gifts' AND COLUMN_NAME = 'published_at'
);
SET @mg_sql := IF(
  @mg_has_gifts_published_at = 0,
  'ALTER TABLE gifts ADD COLUMN published_at DATETIME NULL AFTER claimed_at',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_gifts_expires_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gifts' AND COLUMN_NAME = 'expires_at'
);
SET @mg_sql := IF(
  @mg_has_gifts_expires_at = 0,
  'ALTER TABLE gifts ADD COLUMN expires_at DATETIME NULL AFTER published_at',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_gifts_sender_slug_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gifts' AND INDEX_NAME = 'uq_gifts_sender_slug'
);
SET @mg_sql := IF(
  @mg_has_gifts_sender_slug_index = 0,
  'ALTER TABLE gifts ADD UNIQUE KEY uq_gifts_sender_slug (sender_user_id, slug)',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

CREATE TABLE IF NOT EXISTS gift_deliveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  gift_id BIGINT UNSIGNED NOT NULL,
  channel ENUM('email','sms','link','manual') NOT NULL,
  destination VARCHAR(255) NULL,
  status ENUM('queued','sent','delivered','failed','cancelled') NOT NULL DEFAULT 'queued',
  provider VARCHAR(80) NULL,
  provider_reference VARCHAR(190) NULL,
  failure_code VARCHAR(80) NULL,
  failure_message VARCHAR(500) NULL,
  queued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  delivered_at DATETIME NULL,
  failed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gift_deliveries_public_id (public_id),
  KEY idx_gift_deliveries_gift_status (gift_id, status, created_at),
  CONSTRAINT fk_gift_deliveries_gift FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gift_claims (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  gift_id BIGINT UNSIGNED NOT NULL,
  claimant_user_id BIGINT UNSIGNED NULL,
  code_hash CHAR(64) NOT NULL,
  code_last4 CHAR(4) NOT NULL,
  status ENUM('pending','verified','redeemed','expired','cancelled','locked') NOT NULL DEFAULT 'pending',
  failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_at DATETIME NULL,
  verified_at DATETIME NULL,
  redeemed_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gift_claims_public_id (public_id),
  UNIQUE KEY uq_gift_claims_gift (gift_id),
  KEY idx_gift_claims_status_expiry (status, expires_at),
  CONSTRAINT fk_gift_claims_gift FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_gift_claims_claimant FOREIGN KEY (claimant_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gift_claim_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  claim_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  successful TINYINT(1) NOT NULL DEFAULT 0,
  ip_hash CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gift_claim_attempts_claim_created (claim_id, created_at),
  CONSTRAINT fk_gift_claim_attempts_claim FOREIGN KEY (claim_id) REFERENCES gift_claims(id) ON DELETE CASCADE,
  CONSTRAINT fk_gift_claim_attempts_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug, name, description, created_at) VALUES
('gift.create', 'Create gifts', 'Create and edit owned gift drafts.', NOW()),
('gift.publish', 'Publish gifts', 'Publish and queue owned gifts for delivery.', NOW()),
('gift.claim', 'Claim gifts', 'Verify and claim gifts addressed to the user.', NOW());

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('gift.create','gift.publish','gift.claim')
WHERE r.slug IN ('customer','merchant','admin','super_admin');