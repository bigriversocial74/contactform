CREATE TABLE IF NOT EXISTS stamp_purchases (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  account_user_id BIGINT UNSIGNED NOT NULL,
  bundle_id BIGINT UNSIGNED NOT NULL,
  bundle_key VARCHAR(120) NOT NULL,
  label_snapshot VARCHAR(190) NOT NULL,
  stamps_snapshot INT UNSIGNED NOT NULL,
  price_cents_snapshot INT UNSIGNED NOT NULL,
  currency_snapshot CHAR(3) NOT NULL DEFAULT 'USD',
  status ENUM('pending','checkout_created','paid','credited','cancelled','failed') NOT NULL DEFAULT 'pending',
  checkout_reference VARCHAR(190) NULL,
  credited_ledger_entry_public_id CHAR(36) NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  paid_at DATETIME NULL,
  credited_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stamp_purchases_public_id (public_id),
  UNIQUE KEY uq_stamp_purchases_account_idempotency (account_user_id,idempotency_key),
  KEY idx_stamp_purchases_account_created (account_user_id,created_at,id),
  KEY idx_stamp_purchases_bundle (bundle_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_17b_stamp_purchases','Stamp bundle purchase tracking and post-payment credit lifecycle.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
