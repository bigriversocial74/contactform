-- Merchant Canvas Manual Operations Stabilization v1
-- Durable merchant/customer CRM safeguards and action idempotency receipts.

CREATE TABLE IF NOT EXISTS mg_merchant_customer_crm (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  customer_user_id BIGINT UNSIGNED NOT NULL,
  notes TEXT NULL,
  tags_json LONGTEXT NULL,
  do_not_message TINYINT(1) NOT NULL DEFAULT 0,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_merchant_customer_crm_public (public_id),
  UNIQUE KEY uq_mg_merchant_customer_crm_pair (merchant_user_id, customer_user_id),
  KEY idx_mg_merchant_customer_crm_customer (customer_user_id),
  KEY idx_mg_merchant_customer_crm_dnm (merchant_user_id, do_not_message)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_merchant_canvas_action_receipts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  customer_user_id BIGINT UNSIGNED NOT NULL,
  store_session_id BIGINT UNSIGNED NULL,
  action_type VARCHAR(48) NOT NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  request_hash CHAR(64) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'processing',
  result_public_id CHAR(36) NULL,
  response_json LONGTEXT NULL,
  initiated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_canvas_action_receipt_public (public_id),
  UNIQUE KEY uq_mg_canvas_action_receipt_key (merchant_user_id, action_type, idempotency_key),
  KEY idx_mg_canvas_action_receipt_customer (merchant_user_id, customer_user_id, created_at),
  KEY idx_mg_canvas_action_receipt_session (store_session_id),
  KEY idx_mg_canvas_action_receipt_status (status, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
