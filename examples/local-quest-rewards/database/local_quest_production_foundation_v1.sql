-- Local Quest Rewards production foundation v1
-- Apply after local_quest_rewards.sql and local_quest_admin_auth.sql.

CREATE TABLE IF NOT EXISTS lqr_schema_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  version_key VARCHAR(120) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lqr_schema_version (version_key),
  KEY idx_lqr_schema_applied (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE lqr_schema_versions
  ADD COLUMN IF NOT EXISTS description VARCHAR(255) DEFAULT NULL AFTER version_key;

INSERT INTO lqr_schema_versions (version_key, applied_at)
VALUES ('2026.07.10-production-foundation-v1', NOW())
ON DUPLICATE KEY UPDATE applied_at=VALUES(applied_at);
