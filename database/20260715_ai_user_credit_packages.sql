-- Microgifter AI User Credits + Package Allowances
CREATE TABLE IF NOT EXISTS ai_user_credit_accounts (
  user_id BIGINT UNSIGNED NOT NULL,
  provider_key VARCHAR(80) NOT NULL DEFAULT 'anthropic',
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  package_id VARCHAR(80) NOT NULL DEFAULT 'free',
  package_period_start DATETIME NULL,
  package_period_end DATETIME NULL,
  package_tokens_allocated BIGINT UNSIGNED NULL,
  package_tokens_remaining BIGINT UNSIGNED NULL,
  manual_tokens_remaining BIGINT UNSIGNED NOT NULL DEFAULT 0,
  daily_token_limit BIGINT UNSIGNED NULL,
  weekly_token_limit BIGINT UNSIGNED NULL,
  monthly_token_limit BIGINT UNSIGNED NULL,
  requests_per_hour INT UNSIGNED NULL,
  requests_per_day INT UNSIGNED NULL,
  note VARCHAR(500) NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, provider_key),
  KEY idx_ai_user_credit_package (package_id, enabled),
  KEY idx_ai_user_credit_period (package_period_end),
  CONSTRAINT fk_ai_user_credit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_user_credit_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_credit_ledger (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  provider_key VARCHAR(80) NOT NULL DEFAULT 'anthropic',
  provider_id BIGINT UNSIGNED NULL,
  model_id BIGINT UNSIGNED NULL,
  entry_type VARCHAR(40) NOT NULL,
  token_delta BIGINT NOT NULL DEFAULT 0,
  package_token_delta BIGINT NOT NULL DEFAULT 0,
  manual_token_delta BIGINT NOT NULL DEFAULT 0,
  input_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
  output_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
  source_type VARCHAR(80) NOT NULL,
  source_reference VARCHAR(190) NULL,
  idempotency_key VARCHAR(190) NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ai_credit_ledger_public (public_id),
  UNIQUE KEY uq_ai_credit_ledger_idempotency (idempotency_key),
  KEY idx_ai_credit_ledger_user_created (user_id, provider_key, created_at),
  KEY idx_ai_credit_ledger_type_created (entry_type, created_at),
  KEY idx_ai_credit_ledger_source (source_type, source_reference),
  CONSTRAINT fk_ai_credit_ledger_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_credit_ledger_provider FOREIGN KEY (provider_id) REFERENCES ai_providers(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_credit_ledger_model FOREIGN KEY (model_id) REFERENCES ai_models(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_credit_ledger_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE ai_usage_events MODIFY block_scope VARCHAR(80) NULL;

UPDATE platform_subscription_packages SET limits_json=JSON_SET(COALESCE(limits_json,JSON_OBJECT()),'$.ai_tokens_monthly_included',50000,'$.ai_tokens_daily_limit',5000,'$.ai_tokens_weekly_limit',20000,'$.ai_tokens_monthly_limit',50000),updated_at=NOW() WHERE package_id='starter';
UPDATE platform_subscription_packages SET limits_json=JSON_SET(COALESCE(limits_json,JSON_OBJECT()),'$.ai_tokens_monthly_included',250000,'$.ai_tokens_daily_limit',25000,'$.ai_tokens_weekly_limit',100000,'$.ai_tokens_monthly_limit',250000),updated_at=NOW() WHERE package_id='growth';
UPDATE platform_subscription_packages SET limits_json=JSON_SET(COALESCE(limits_json,JSON_OBJECT()),'$.ai_tokens_monthly_included',1000000,'$.ai_tokens_daily_limit',100000,'$.ai_tokens_weekly_limit',400000,'$.ai_tokens_monthly_limit',1000000),updated_at=NOW() WHERE package_id='pro';
UPDATE platform_subscription_packages SET limits_json=JSON_SET(COALESCE(limits_json,JSON_OBJECT()),'$.ai_tokens_monthly_included',5000000,'$.ai_tokens_daily_limit',500000,'$.ai_tokens_weekly_limit',2000000,'$.ai_tokens_monthly_limit',5000000),updated_at=NOW() WHERE package_id='enterprise';

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at) VALUES ('20260715_ai_user_credit_packages','Package-backed AI token balances, user allowance overrides, manual grants, and audited usage ledger.',NULL,NOW()) ON DUPLICATE KEY UPDATE description=VALUES(description);
