-- ------------------------------------------------------------
-- Stage 20B Store Canvas Trigger Zones
-- ------------------------------------------------------------
-- Purpose:
--   Persists merchant-created Store Canvas trigger zones so merchants can
--   create multiple draggable/resizable campaign areas, assign active
--   campaigns, set trigger priority, and resolve overlapping zones by the
--   highest priority rating.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS mg_store_trigger_zones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL DEFAULT 'IN/OUT Box Trigger',
  trigger_key VARCHAR(120) NOT NULL DEFAULT 'store_canvas_zone',
  campaign_public_id CHAR(36) NULL,
  priority TINYINT UNSIGNED NOT NULL DEFAULT 3,
  x_percent DECIMAL(7,4) NOT NULL DEFAULT 8.0000,
  y_percent DECIMAL(7,4) NOT NULL DEFAULT 8.0000,
  width_percent DECIMAL(7,4) NOT NULL DEFAULT 28.0000,
  height_percent DECIMAL(7,4) NOT NULL DEFAULT 18.0000,
  status ENUM('active','paused','archived') NOT NULL DEFAULT 'active',
  metadata_json JSON NULL,
  last_triggered_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_store_trigger_zones_public_id (public_id),
  KEY idx_mg_store_trigger_zones_merchant_status_priority (merchant_user_id,status,priority,updated_at),
  KEY idx_mg_store_trigger_zones_campaign (merchant_user_id,campaign_public_id,status),
  CONSTRAINT chk_mg_store_trigger_zones_priority CHECK (priority BETWEEN 1 AND 5),
  CONSTRAINT chk_mg_store_trigger_zones_x CHECK (x_percent >= 0 AND x_percent <= 100),
  CONSTRAINT chk_mg_store_trigger_zones_y CHECK (y_percent >= 0 AND y_percent <= 100),
  CONSTRAINT chk_mg_store_trigger_zones_width CHECK (width_percent > 0 AND width_percent <= 100),
  CONSTRAINT chk_mg_store_trigger_zones_height CHECK (height_percent > 0 AND height_percent <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_20b_store_canvas_trigger_zones','Persistent merchant Store Canvas trigger zones with campaign assignment and priority.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
