-- Stage 31 Campaign Drop Intercept Tools
-- Safe to re-run.

CREATE TABLE IF NOT EXISTS campaign_drop_tools (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(64) NOT NULL,
  tool_key VARCHAR(80) NOT NULL,
  name VARCHAR(140) NOT NULL,
  category ENUM('scanner','booster','vehicle','shield','signal','badge') NOT NULL DEFAULT 'scanner',
  rarity ENUM('common','rare','epic','legendary') NOT NULL DEFAULT 'common',
  description TEXT NULL,
  range_meters INT UNSIGNED NOT NULL DEFAULT 500,
  speed_bonus_percent INT UNSIGNED NOT NULL DEFAULT 0,
  success_bonus_percent INT UNSIGNED NOT NULL DEFAULT 0,
  cooldown_seconds INT UNSIGNED NOT NULL DEFAULT 300,
  uses_limit INT UNSIGNED NULL,
  status ENUM('active','hidden','retired') NOT NULL DEFAULT 'active',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campaign_drop_tools_public_id (public_id),
  UNIQUE KEY uq_campaign_drop_tools_key (tool_key),
  KEY idx_campaign_drop_tools_category_status (category, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_campaign_drop_tools (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(64) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  tool_id BIGINT UNSIGNED NOT NULL,
  status ENUM('owned','equipped','cooldown','expired','revoked') NOT NULL DEFAULT 'owned',
  source ENUM('grant','purchase','reward','campaign','admin') NOT NULL DEFAULT 'grant',
  uses_remaining INT UNSIGNED NULL,
  cooldown_until DATETIME NULL,
  expires_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_campaign_drop_tools_public_id (public_id),
  UNIQUE KEY uq_user_campaign_drop_tools_user_tool (user_id, tool_id),
  KEY idx_user_campaign_drop_tools_user_status (user_id, status),
  CONSTRAINT fk_user_campaign_drop_tools_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_campaign_drop_tools_tool FOREIGN KEY (tool_id) REFERENCES campaign_drop_tools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_target_drop_intercepts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(64) NOT NULL,
  delivery_run_id BIGINT UNSIGNED NOT NULL,
  target_drop_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  user_tool_id BIGINT UNSIGNED NULL,
  tool_id BIGINT UNSIGNED NULL,
  status ENUM('attempted','success','failed','blocked','expired') NOT NULL DEFAULT 'attempted',
  result_reason VARCHAR(120) NULL,
  success_score INT UNSIGNED NOT NULL DEFAULT 0,
  required_score INT UNSIGNED NOT NULL DEFAULT 50,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_target_drop_intercepts_public_id (public_id),
  KEY idx_target_drop_intercepts_run_user (delivery_run_id, user_id, created_at),
  KEY idx_target_drop_intercepts_drop_status (target_drop_id, status, created_at),
  KEY idx_target_drop_intercepts_merchant_status (merchant_user_id, status, created_at),
  CONSTRAINT fk_target_drop_intercepts_run FOREIGN KEY (delivery_run_id) REFERENCES merchant_target_drop_delivery_runs(id) ON DELETE CASCADE,
  CONSTRAINT fk_target_drop_intercepts_drop FOREIGN KEY (target_drop_id) REFERENCES merchant_target_drops(id) ON DELETE CASCADE,
  CONSTRAINT fk_target_drop_intercepts_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_target_drop_intercepts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_target_drop_intercepts_user_tool FOREIGN KEY (user_tool_id) REFERENCES user_campaign_drop_tools(id) ON DELETE SET NULL,
  CONSTRAINT fk_target_drop_intercepts_tool FOREIGN KEY (tool_id) REFERENCES campaign_drop_tools(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO campaign_drop_tools (public_id, tool_key, name, category, rarity, description, range_meters, speed_bonus_percent, success_bonus_percent, cooldown_seconds, uses_limit, status, metadata_json)
VALUES
('tool_scanner_basic', 'scanner_basic', 'Basic Signal Scanner', 'scanner', 'common', 'Entry-level scanner for detecting nearby active delivery runs.', 800, 0, 15, 300, NULL, 'active', JSON_OBJECT('starter', true)),
('tool_booster_route', 'route_booster', 'Route Booster', 'booster', 'rare', 'Improves timing when attempting to catch a moving package.', 1200, 15, 20, 600, 10, 'active', JSON_OBJECT('starter', false)),
('tool_vehicle_bike', 'bike_courier', 'Bike Courier Kit', 'vehicle', 'rare', 'Vehicle equipment for larger local intercept windows.', 2500, 25, 25, 900, NULL, 'active', JSON_OBJECT('vehicle', 'bike'))
ON DUPLICATE KEY UPDATE name=VALUES(name), category=VALUES(category), rarity=VALUES(rarity), description=VALUES(description), range_meters=VALUES(range_meters), speed_bonus_percent=VALUES(speed_bonus_percent), success_bonus_percent=VALUES(success_bonus_percent), cooldown_seconds=VALUES(cooldown_seconds), uses_limit=VALUES(uses_limit), status=VALUES(status), metadata_json=VALUES(metadata_json);

INSERT INTO schema_migrations (migration_key, description, checksum, applied_at)
VALUES ('stage_31_campaign_drop_intercept_tools', 'Campaign Drop intercept tools and attempts', NULL, NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description);
