-- Public Distribution API quotas.

CREATE TABLE IF NOT EXISTS public_api_quota_buckets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  app_id BIGINT UNSIGNED NOT NULL,
  api_key_id BIGINT UNSIGNED NOT NULL,
  bucket_scope ENUM('minute','day','month') NOT NULL,
  bucket_key VARCHAR(32) NOT NULL,
  limit_value INT UNSIGNED NOT NULL,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  window_start DATETIME NOT NULL,
  window_end DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_public_api_quota_buckets_public_id (public_id),
  UNIQUE KEY uq_public_api_quota_buckets_window (api_key_id,bucket_scope,bucket_key),
  KEY idx_public_api_quota_buckets_app (app_id,bucket_scope,window_start),
  KEY idx_public_api_quota_buckets_merchant (merchant_user_id,bucket_scope,window_start),
  CONSTRAINT fk_public_api_quota_buckets_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_public_api_quota_buckets_app FOREIGN KEY (app_id) REFERENCES merchant_developer_apps(id) ON DELETE CASCADE,
  CONSTRAINT fk_public_api_quota_buckets_key FOREIGN KEY (api_key_id) REFERENCES merchant_api_keys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
