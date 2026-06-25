-- Stage 19 Merchant Market Snapshots
-- Stores daily merchant market score/ticker snapshots for historical charts and trend analysis.

CREATE TABLE IF NOT EXISTS merchant_market_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  public_profile_id BIGINT UNSIGNED NULL,
  profile_slug VARCHAR(140) NOT NULL,
  snapshot_date DATE NOT NULL,
  formula_version VARCHAR(120) NOT NULL,
  ticker_symbol VARCHAR(12) NOT NULL,
  merchant_score INT UNSIGNED NOT NULL DEFAULT 0,
  ticker_value_cents BIGINT NOT NULL DEFAULT 0,
  demand_value_cents BIGINT NOT NULL DEFAULT 0,
  campaign_conversion_value_cents BIGINT NOT NULL DEFAULT 0,
  funnel_quality_score DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  funnel_quality_value_cents BIGINT NOT NULL DEFAULT 0,
  distribution_value_cents BIGINT NOT NULL DEFAULT 0,
  stamp_inventory_value_cents BIGINT NOT NULL DEFAULT 0,
  stamp_spend_value_cents BIGINT NOT NULL DEFAULT 0,
  follower_brand_value_cents BIGINT NOT NULL DEFAULT 0,
  risk_adjustment_cents BIGINT NOT NULL DEFAULT 0,
  snapshot_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_market_snapshots_public_id (public_id),
  UNIQUE KEY uq_merchant_market_snapshot_day (merchant_user_id,snapshot_date,formula_version),
  KEY idx_merchant_market_snapshot_slug_day (profile_slug,snapshot_date),
  KEY idx_merchant_market_snapshot_score (merchant_score,snapshot_date),
  CONSTRAINT fk_merchant_market_snapshots_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_market_snapshots_profile FOREIGN KEY (public_profile_id) REFERENCES public_profiles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_19_merchant_market_snapshots','Daily merchant market score and ticker value snapshots for profile market charts.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
