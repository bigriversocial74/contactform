-- Stage 12L Reward Media Pack Schema Repair
-- Ensures existing reward_templates tables support Stage 12K audio/media packs.
-- Safe to rerun.

CREATE TABLE IF NOT EXISTS schema_migrations (
  migration_key VARCHAR(190) NOT NULL,
  description VARCHAR(500) NULL,
  checksum VARCHAR(128) NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @mg12l_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reward_templates'
    AND COLUMN_NAME = 'metadata_json'
);

SET @mg12l_sql := IF(
  @mg12l_col = 0,
  'ALTER TABLE reward_templates ADD COLUMN metadata_json JSON NULL AFTER status',
  'SELECT 1'
);
PREPARE mg12l_stmt FROM @mg12l_sql;
EXECUTE mg12l_stmt;
DEALLOCATE PREPARE mg12l_stmt;

ALTER TABLE reward_templates
  MODIFY reward_type ENUM('dollar_credit','free_item','discount','perk_upgrade','event_reward','audio_pack','media_pack','custom') NOT NULL DEFAULT 'custom';

INSERT INTO schema_migrations (migration_key, description, checksum, applied_at)
VALUES ('stage_12l_reward_media_pack_schema_repair', 'Defensive reward_templates metadata_json and audio/media reward type repair.', NULL, NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description);