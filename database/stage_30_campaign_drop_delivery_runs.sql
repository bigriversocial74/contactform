-- Stage 30 Campaign Drop Delivery Runs
-- Safe to re-run.
-- Turns Target Drop launch/test animations into trackable delivery runs.

CREATE TABLE IF NOT EXISTS merchant_target_drop_delivery_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(64) NOT NULL,
  target_drop_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('queued','sending','delivered','intercepted','expired','cancelled') NOT NULL DEFAULT 'queued',
  run_type ENUM('test','live') NOT NULL DEFAULT 'live',
  launch_latitude DECIMAL(10,7) NULL,
  launch_longitude DECIMAL(10,7) NULL,
  target_latitude DECIMAL(10,7) NOT NULL,
  target_longitude DECIMAL(10,7) NOT NULL,
  launch_x DECIMAL(9,4) NULL,
  launch_y DECIMAL(9,4) NULL,
  target_x DECIMAL(9,4) NULL,
  target_y DECIMAL(9,4) NULL,
  control_point_json JSON NULL,
  animation_duration_ms INT UNSIGNED NOT NULL DEFAULT 1700,
  animation_started_at DATETIME NULL,
  delivered_at DATETIME NULL,
  intercepted_at DATETIME NULL,
  intercepted_by_user_id BIGINT UNSIGNED NULL,
  intercept_tool_public_id VARCHAR(64) NULL,
  intercept_window_opens_at DATETIME NULL,
  intercept_window_closes_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_target_drop_delivery_runs_public_id (public_id),
  KEY idx_target_drop_delivery_runs_drop_status (target_drop_id, status, created_at),
  KEY idx_target_drop_delivery_runs_merchant_status (merchant_user_id, status, created_at),
  KEY idx_target_drop_delivery_runs_public_feed (run_type, status, animation_started_at, delivered_at),
  KEY idx_target_drop_delivery_runs_intercept (intercepted_by_user_id, intercepted_at),
  CONSTRAINT fk_target_drop_delivery_runs_drop FOREIGN KEY (target_drop_id) REFERENCES merchant_target_drops(id) ON DELETE CASCADE,
  CONSTRAINT fk_target_drop_delivery_runs_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_target_drop_delivery_runs_intercept_user FOREIGN KEY (intercepted_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key, description, checksum, applied_at)
VALUES ('stage_30_campaign_drop_delivery_runs', 'Campaign Drop delivery runs foundation', NULL, NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description);
