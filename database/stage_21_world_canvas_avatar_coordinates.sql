-- ------------------------------------------------------------
-- Stage 21 World Canvas Avatar Coordinates
-- ------------------------------------------------------------
-- Purpose:
--   Adds optional saved latitude/longitude fields for Store Canvas avatars so
--   the World Canvas can place avatars into the world map from real geo anchors
--   while still allowing affinity-based clustering when coordinates are missing.
-- ------------------------------------------------------------

ALTER TABLE mg_store_sessions
  ADD COLUMN IF NOT EXISTS avatar_latitude DECIMAL(10,7) NULL AFTER store_agent_id,
  ADD COLUMN IF NOT EXISTS avatar_longitude DECIMAL(10,7) NULL AFTER avatar_latitude,
  ADD COLUMN IF NOT EXISTS avatar_geo_accuracy_meters INT UNSIGNED NULL AFTER avatar_longitude,
  ADD COLUMN IF NOT EXISTS avatar_geo_source VARCHAR(80) NULL AFTER avatar_geo_accuracy_meters;

CREATE INDEX IF NOT EXISTS idx_mg_store_sessions_avatar_geo
  ON mg_store_sessions (avatar_latitude, avatar_longitude, last_active_at);

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_21_world_canvas_avatar_coordinates','World Canvas avatar latitude/longitude placement fields.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
