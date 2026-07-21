START TRANSACTION;

CREATE TABLE IF NOT EXISTS gift_bundle_release_controls (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  release_key VARCHAR(100) NOT NULL,
  environment ENUM('test','live') NOT NULL,
  rollout_stage ENUM('disabled','internal','pilot','limited','general') NOT NULL DEFAULT 'disabled',
  traffic_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  transfers_enabled TINYINT(1) NOT NULL DEFAULT 0,
  reversals_enabled TINYINT(1) NOT NULL DEFAULT 0,
  emergency_stop TINYINT(1) NOT NULL DEFAULT 1,
  approved_by_user_id BIGINT UNSIGNED NULL,
  approval_note VARCHAR(500) NULL,
  approved_at DATETIME NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bundle_release_control_key_env (release_key,environment),
  UNIQUE KEY uq_bundle_release_control_public (public_id),
  CONSTRAINT fk_bundle_release_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id),
  CONSTRAINT fk_bundle_release_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gift_bundle_release_health_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  environment ENUM('test','live') NOT NULL,
  overall_status ENUM('healthy','degraded','blocked') NOT NULL,
  readiness_score DECIMAL(5,2) NOT NULL,
  transfers_pending INT UNSIGNED NOT NULL DEFAULT 0,
  transfers_failed INT UNSIGNED NOT NULL DEFAULT 0,
  reversals_pending INT UNSIGNED NOT NULL DEFAULT 0,
  reversals_failed INT UNSIGNED NOT NULL DEFAULT 0,
  open_dead_letters INT UNSIGNED NOT NULL DEFAULT 0,
  critical_incidents INT UNSIGNED NOT NULL DEFAULT 0,
  stale_provider_events INT UNSIGNED NOT NULL DEFAULT 0,
  checks_json JSON NOT NULL,
  captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bundle_health_public (public_id),
  KEY idx_bundle_health_env_time (environment,captured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gift_bundle_release_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  release_control_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(100) NOT NULL,
  previous_state_json JSON NULL,
  next_state_json JSON NULL,
  reason VARCHAR(500) NOT NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bundle_release_event_public (public_id),
  UNIQUE KEY uq_bundle_release_event_idempotency (idempotency_key),
  KEY idx_bundle_release_event_control (release_control_id,created_at),
  CONSTRAINT fk_bundle_release_event_control FOREIGN KEY (release_control_id) REFERENCES gift_bundle_release_controls(id) ON DELETE SET NULL,
  CONSTRAINT fk_bundle_release_event_actor FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO gift_bundle_release_controls (public_id,release_key,environment,rollout_stage,traffic_percent,transfers_enabled,reversals_enabled,emergency_stop)
VALUES (UUID(),'product_bundles','test','disabled',0,0,0,1),(UUID(),'product_bundles','live','disabled',0,0,0,1)
ON DUPLICATE KEY UPDATE release_key=VALUES(release_key);

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('20260721_product_bundle_staged_activation_monitoring_v13','Product Bundle staged activation, health snapshots, emergency stop, and immutable release events.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
