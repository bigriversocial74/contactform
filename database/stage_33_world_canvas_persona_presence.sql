-- Stage 33 World Canvas Persona + Merchant Presence
-- Safe to re-run.
-- Adds dual-persona state, location-level unattended/closed policy, and customer return watchers.

SET @mg_has_merchant_locations := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='merchant_locations');
SET @mg_has_presence_mode := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='merchant_locations' AND COLUMN_NAME='world_presence_mode');
SET @mg_sql := IF(@mg_has_merchant_locations=1 AND @mg_has_presence_mode=0,"ALTER TABLE merchant_locations ADD COLUMN world_presence_mode VARCHAR(32) NOT NULL DEFAULT 'allow_unattended' AFTER world_zone_radius_meters",'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_presence_status := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='merchant_locations' AND COLUMN_NAME='world_presence_status');
SET @mg_sql := IF(@mg_has_merchant_locations=1 AND @mg_has_presence_status=0,"ALTER TABLE merchant_locations ADD COLUMN world_presence_status VARCHAR(32) NOT NULL DEFAULT 'in_store' AFTER world_presence_mode",'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_presence_cycle := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='merchant_locations' AND COLUMN_NAME='world_presence_cycle');
SET @mg_sql := IF(@mg_has_merchant_locations=1 AND @mg_has_presence_cycle=0,'ALTER TABLE merchant_locations ADD COLUMN world_presence_cycle BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER world_presence_status','SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_presence_message := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='merchant_locations' AND COLUMN_NAME='world_presence_message');
SET @mg_sql := IF(@mg_has_merchant_locations=1 AND @mg_has_presence_message=0,'ALTER TABLE merchant_locations ADD COLUMN world_presence_message VARCHAR(500) NULL AFTER world_presence_cycle','SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_return_message := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='merchant_locations' AND COLUMN_NAME='world_return_message');
SET @mg_sql := IF(@mg_has_merchant_locations=1 AND @mg_has_return_message=0,'ALTER TABLE merchant_locations ADD COLUMN world_return_message VARCHAR(500) NULL AFTER world_presence_message','SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_presence_updated := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='merchant_locations' AND COLUMN_NAME='world_presence_updated_at');
SET @mg_sql := IF(@mg_has_merchant_locations=1 AND @mg_has_presence_updated=0,'ALTER TABLE merchant_locations ADD COLUMN world_presence_updated_at DATETIME NULL AFTER world_return_message','SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_presence_actor := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='merchant_locations' AND COLUMN_NAME='world_presence_actor_user_id');
SET @mg_sql := IF(@mg_has_merchant_locations=1 AND @mg_has_presence_actor=0,'ALTER TABLE merchant_locations ADD COLUMN world_presence_actor_user_id BIGINT UNSIGNED NULL AFTER world_presence_updated_at','SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_store_sessions := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_sessions');
SET @mg_has_session_location := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_sessions' AND COLUMN_NAME='merchant_location_id');
SET @mg_sql := IF(@mg_has_store_sessions=1 AND @mg_has_session_location=0,'ALTER TABLE mg_store_sessions ADD COLUMN merchant_location_id BIGINT UNSIGNED NULL AFTER merchant_user_id','SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_session_location_index := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_sessions' AND INDEX_NAME='idx_mg_store_sessions_location_active');
SET @mg_sql := IF(@mg_has_store_sessions=1 AND @mg_has_session_location_index=0,'CREATE INDEX idx_mg_store_sessions_location_active ON mg_store_sessions(merchant_location_id, active_key, status)','SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

CREATE TABLE IF NOT EXISTS world_canvas_persona_state (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  persona_kind VARCHAR(24) NOT NULL DEFAULT 'user',
  merchant_location_id BIGINT UNSIGNED NULL,
  active_surface VARCHAR(32) NOT NULL DEFAULT 'world_canvas',
  last_heartbeat_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_world_canvas_persona_user (user_id),
  UNIQUE KEY uq_world_canvas_persona_public_id (public_id),
  KEY idx_world_canvas_persona_location (merchant_location_id, active_surface, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_store_presence_watchers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_location_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  customer_user_id BIGINT UNSIGNED NOT NULL,
  presence_cycle BIGINT UNSIGNED NOT NULL DEFAULT 0,
  reason VARCHAR(32) NOT NULL,
  source_post_public_id CHAR(36) NULL,
  source_session_id BIGINT UNSIGNED NULL,
  away_message_id CHAR(36) NULL,
  return_message_id CHAR(36) NULL,
  away_message_sent_at DATETIME NULL,
  return_notified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_presence_watcher_cycle (merchant_location_id, customer_user_id, presence_cycle),
  UNIQUE KEY uq_store_presence_watcher_public_id (public_id),
  KEY idx_store_presence_return_queue (merchant_location_id, presence_cycle, return_notified_at),
  KEY idx_store_presence_customer (customer_user_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_33_world_canvas_persona_presence','World Canvas dual personas and merchant location presence policy',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
