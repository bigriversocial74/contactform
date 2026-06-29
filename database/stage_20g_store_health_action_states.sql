-- ------------------------------------------------------------
-- Stage 20G Store Health Action State Persistence
-- ------------------------------------------------------------
-- Stores merchant-scoped Store Health action states so action history
-- survives browser/device changes. Import this after the Merchant CRM
-- and Store Canvas tables are installed.

CREATE TABLE IF NOT EXISTS merchant_store_health_actions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  action_key VARCHAR(191) NOT NULL,
  action_type VARCHAR(80) NOT NULL,
  condition_key VARCHAR(120) NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  priority ENUM('high','medium','warning','safe','low') NOT NULL DEFAULT 'low',
  status ENUM('suggested','started','completed','snoozed','dismissed') NOT NULL DEFAULT 'suggested',
  condition_count INT UNSIGNED NOT NULL DEFAULT 0,
  metadata_json JSON NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  snoozed_until DATETIME NULL,
  dismissed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_store_health_actions_public_id (public_id),
  UNIQUE KEY uq_merchant_store_health_actions_scope_key (merchant_user_id, action_key),
  KEY idx_merchant_store_health_actions_status (merchant_user_id, status, updated_at),
  KEY idx_merchant_store_health_actions_type (merchant_user_id, action_type, condition_key),
  CONSTRAINT fk_store_health_actions_merchant_user
    FOREIGN KEY (merchant_user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
