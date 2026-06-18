CREATE TABLE IF NOT EXISTS entitlements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  entitlement_type ENUM('download','stream','view','redeem','access','license','other') NOT NULL DEFAULT 'download',
  status ENUM('active','suspended','revoked','expired','consumed') NOT NULL DEFAULT 'active',
  pppm_item_id BIGINT UNSIGNED NOT NULL,
  commerce_order_item_id BIGINT UNSIGNED NULL,
  product_version_id BIGINT UNSIGNED NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  entitled_user_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  source_type VARCHAR(80) NOT NULL,
  source_reference VARCHAR(190) NOT NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  suspended_at DATETIME NULL,
  revoked_at DATETIME NULL,
  revocation_reason VARCHAR(240) NULL,
  policy_json JSON NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_entitlements_public_id (public_id),
  UNIQUE KEY uq_entitlements_idempotency (idempotency_key),
  UNIQUE KEY uq_entitlements_active_grant (pppm_item_id,entitled_user_id,asset_id,entitlement_type),
  KEY idx_entitlements_user_status (entitled_user_id,status,updated_at),
  KEY idx_entitlements_pppm (pppm_item_id,status),
  KEY idx_entitlements_asset (asset_id,status),
  KEY idx_entitlements_merchant (merchant_user_id,status,updated_at),
  CONSTRAINT fk_entitlements_pppm_item FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_entitlements_order_item FOREIGN KEY (commerce_order_item_id) REFERENCES commerce_order_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_entitlements_product_version FOREIGN KEY (product_version_id) REFERENCES catalog_product_versions(id) ON DELETE SET NULL,
  CONSTRAINT fk_entitlements_asset FOREIGN KEY (asset_id) REFERENCES catalog_assets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_entitlements_user FOREIGN KEY (entitled_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_entitlements_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entitlement_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  entitlement_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  from_status VARCHAR(40) NULL,
  to_status VARCHAR(40) NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  reason_code VARCHAR(100) NULL,
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_entitlement_events_public_id (public_id),
  KEY idx_entitlement_events_entitlement (entitlement_id,created_at,id),
  CONSTRAINT fk_entitlement_events_entitlement FOREIGN KEY (entitlement_id) REFERENCES entitlements(id) ON DELETE CASCADE,
  CONSTRAINT fk_entitlement_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entitlement_access_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  entitlement_id BIGINT UNSIGNED NULL,
  asset_id BIGINT UNSIGNED NULL,
  pppm_item_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  event_type ENUM('authorized','denied','download_started','download_completed') NOT NULL,
  decision_reason VARCHAR(160) NULL,
  request_context_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_entitlement_access_events_public_id (public_id),
  KEY idx_entitlement_access_user (user_id,created_at),
  KEY idx_entitlement_access_asset (asset_id,created_at),
  KEY idx_entitlement_access_entitlement (entitlement_id,created_at),
  CONSTRAINT fk_entitlement_access_entitlement FOREIGN KEY (entitlement_id) REFERENCES entitlements(id) ON DELETE SET NULL,
  CONSTRAINT fk_entitlement_access_asset FOREIGN KEY (asset_id) REFERENCES catalog_assets(id) ON DELETE SET NULL,
  CONSTRAINT fk_entitlement_access_pppm FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_entitlement_access_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asset_delivery_grants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  entitlement_id BIGINT UNSIGNED NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  delivery_mode ENUM('redirect','signed_url','proxy','metadata_only') NOT NULL DEFAULT 'metadata_only',
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_asset_delivery_grants_public_id (public_id),
  UNIQUE KEY uq_asset_delivery_grants_token (token_hash),
  KEY idx_asset_delivery_grants_user (user_id,expires_at),
  CONSTRAINT fk_asset_delivery_entitlement FOREIGN KEY (entitlement_id) REFERENCES entitlements(id) ON DELETE CASCADE,
  CONSTRAINT fk_asset_delivery_asset FOREIGN KEY (asset_id) REFERENCES catalog_assets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_asset_delivery_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entitlement_review_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  review_type ENUM('partial_refund','dispute','asset_removed','policy_exception','other') NOT NULL,
  status ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
  user_id BIGINT UNSIGNED NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  commerce_order_id BIGINT UNSIGNED NULL,
  pppm_item_id BIGINT UNSIGNED NULL,
  entitlement_id BIGINT UNSIGNED NULL,
  reason VARCHAR(240) NOT NULL,
  payload_json JSON NULL,
  resolved_by_user_id BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_entitlement_review_items_public_id (public_id),
  KEY idx_entitlement_review_status (status,review_type,created_at),
  CONSTRAINT fk_entitlement_review_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_entitlement_review_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_entitlement_review_order FOREIGN KEY (commerce_order_id) REFERENCES commerce_orders(id) ON DELETE SET NULL,
  CONSTRAINT fk_entitlement_review_pppm FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_entitlement_review_entitlement FOREIGN KEY (entitlement_id) REFERENCES entitlements(id) ON DELETE SET NULL,
  CONSTRAINT fk_entitlement_review_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('entitlements.view','View entitlements','View owned entitlements and protected access status.',NOW()),
('entitlements.manage','Manage entitlements','Suspend, revoke, restore, and inspect entitlements.',NOW()),
('entitlements.review','Review entitlement exceptions','Review refund, dispute, and policy exception entitlement items.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='entitlements.view'
WHERE r.slug IN ('customer','member','merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN ('entitlements.manage','entitlements.review')
WHERE r.slug IN ('admin','super_admin');
