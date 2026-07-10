-- Merchant Canvas Real Customer Analytics & Journey Data v1
-- Canonical, merchant-scoped customer journey read model with stable event deduplication.

CREATE TABLE IF NOT EXISTS mg_merchant_canvas_journey_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  event_key VARCHAR(190) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  customer_user_id BIGINT UNSIGNED NOT NULL,
  store_session_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  event_label VARCHAR(180) NULL,
  source_kind VARCHAR(48) NOT NULL DEFAULT 'store_session',
  source_public_id VARCHAR(190) NULL,
  event_at DATETIME NOT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_canvas_journey_public (public_id),
  UNIQUE KEY uq_mg_canvas_journey_event_key (merchant_user_id, event_key),
  KEY idx_mg_canvas_journey_customer (merchant_user_id, customer_user_id, event_at, id),
  KEY idx_mg_canvas_journey_session (store_session_id, event_at, id),
  KEY idx_mg_canvas_journey_type (merchant_user_id, event_type, event_at),
  KEY idx_mg_canvas_journey_source (merchant_user_id, source_kind, source_public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('merchant_canvas_real_customer_analytics_journey_v1','Canonical merchant-scoped Store Canvas customer analytics and journey event read model.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);