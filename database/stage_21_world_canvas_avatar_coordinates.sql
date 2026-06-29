-- ------------------------------------------------------------
-- Stage 21 World Canvas Avatar Coordinates
-- ------------------------------------------------------------
-- Purpose:
--   Adds optional saved latitude/longitude fields for Store Canvas avatars so
--   the World Canvas can place avatars into the world map from real geo anchors
--   while still allowing affinity-based clustering when coordinates are missing.
--
-- Notes:
--   Uses information_schema guards instead of ALTER ... IF NOT EXISTS so this
--   remains safer across MySQL/MariaDB variants.
-- ------------------------------------------------------------

SET @mg_stage21_db = DATABASE();

SET @mg_stage21_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@mg_stage21_db AND TABLE_NAME='mg_store_sessions' AND COLUMN_NAME='avatar_latitude') = 0,
  'ALTER TABLE mg_store_sessions ADD COLUMN avatar_latitude DECIMAL(10,7) NULL AFTER store_agent_id',
  'SELECT 1'
);
PREPARE mg_stage21_stmt FROM @mg_stage21_sql;
EXECUTE mg_stage21_stmt;
DEALLOCATE PREPARE mg_stage21_stmt;

SET @mg_stage21_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@mg_stage21_db AND TABLE_NAME='mg_store_sessions' AND COLUMN_NAME='avatar_longitude') = 0,
  'ALTER TABLE mg_store_sessions ADD COLUMN avatar_longitude DECIMAL(10,7) NULL AFTER avatar_latitude',
  'SELECT 1'
);
PREPARE mg_stage21_stmt FROM @mg_stage21_sql;
EXECUTE mg_stage21_stmt;
DEALLOCATE PREPARE mg_stage21_stmt;

SET @mg_stage21_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@mg_stage21_db AND TABLE_NAME='mg_store_sessions' AND COLUMN_NAME='avatar_geo_accuracy_meters') = 0,
  'ALTER TABLE mg_store_sessions ADD COLUMN avatar_geo_accuracy_meters INT UNSIGNED NULL AFTER avatar_longitude',
  'SELECT 1'
);
PREPARE mg_stage21_stmt FROM @mg_stage21_sql;
EXECUTE mg_stage21_stmt;
DEALLOCATE PREPARE mg_stage21_stmt;

SET @mg_stage21_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@mg_stage21_db AND TABLE_NAME='mg_store_sessions' AND COLUMN_NAME='avatar_geo_source') = 0,
  'ALTER TABLE mg_store_sessions ADD COLUMN avatar_geo_source VARCHAR(80) NULL AFTER avatar_geo_accuracy_meters',
  'SELECT 1'
);
PREPARE mg_stage21_stmt FROM @mg_stage21_sql;
EXECUTE mg_stage21_stmt;
DEALLOCATE PREPARE mg_stage21_stmt;

SET @mg_stage21_sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@mg_stage21_db AND TABLE_NAME='mg_store_sessions' AND INDEX_NAME='idx_mg_store_sessions_avatar_geo') = 0,
  'CREATE INDEX idx_mg_store_sessions_avatar_geo ON mg_store_sessions (avatar_latitude, avatar_longitude, last_active_at)',
  'SELECT 1'
);
PREPARE mg_stage21_stmt FROM @mg_stage21_sql;
EXECUTE mg_stage21_stmt;
DEALLOCATE PREPARE mg_stage21_stmt;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_21_world_canvas_avatar_coordinates','World Canvas avatar latitude/longitude placement fields.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
