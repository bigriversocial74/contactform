-- Public Distribution API sandbox reward storage.

CREATE TABLE IF NOT EXISTS public_api_sandbox_rewards (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  app_id BIGINT UNSIGNED NOT NULL,
  api_key_id BIGINT UNSIGNED NOT NULL,
  program_public_id VARCHAR(80) NOT NULL,
  template_public_id VARCHAR(80) NOT NULL,
  linked_account_public_id VARCHAR(120) NOT NULL,
  external_event_id VARCHAR(180) NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  idempotency_key CHAR(64) NOT NULL,
  status ENUM('sandbox_queued','sandbox_delivered','sandbox_failed') NOT NULL DEFAULT 'sandbox_delivered',
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_public_api_sandbox_rewards_public_id (public_id),
  UNIQUE KEY uq_public_api_sandbox_rewards_idempotency (app_id,idempotency_key),
  KEY idx_public_api_sandbox_rewards_app (app_id,created_at),
  KEY idx_public_api_sandbox_rewards_merchant (merchant_user_id,created_at),
  CONSTRAINT fk_public_api_sandbox_rewards_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_public_api_sandbox_rewards_app FOREIGN KEY (app_id) REFERENCES merchant_developer_apps(id) ON DELETE CASCADE,
  CONSTRAINT fk_public_api_sandbox_rewards_key FOREIGN KEY (api_key_id) REFERENCES merchant_api_keys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
