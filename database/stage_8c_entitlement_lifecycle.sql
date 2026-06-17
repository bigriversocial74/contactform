CREATE TABLE IF NOT EXISTS entitlement_transfers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  pppm_item_id BIGINT UNSIGNED NOT NULL,
  from_user_id BIGINT UNSIGNED NULL,
  to_user_id BIGINT UNSIGNED NOT NULL,
  source_type VARCHAR(80) NOT NULL,
  source_reference VARCHAR(190) NOT NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  status ENUM('completed','failed','reversed') NOT NULL DEFAULT 'completed',
  transferred_by_user_id BIGINT UNSIGNED NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_entitlement_transfers_public_id (public_id),
  UNIQUE KEY uq_entitlement_transfers_idempotency (idempotency_key),
  KEY idx_entitlement_transfers_pppm (pppm_item_id,created_at),
  CONSTRAINT fk_entitlement_transfers_pppm FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_entitlement_transfers_from_user FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_entitlement_transfers_to_user FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_entitlement_transfers_actor FOREIGN KEY (transferred_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entitlement_policy_actions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  action_type ENUM('dispute_opened','dispute_won','dispute_lost','asset_removed','asset_restored','owner_sync','expiration_sweep') NOT NULL,
  source_type VARCHAR(80) NOT NULL,
  source_reference VARCHAR(190) NOT NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  commerce_order_id BIGINT UNSIGNED NULL,
  pppm_item_id BIGINT UNSIGNED NULL,
  asset_id BIGINT UNSIGNED NULL,
  affected_count INT UNSIGNED NOT NULL DEFAULT 0,
  actor_user_id BIGINT UNSIGNED NULL,
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_entitlement_policy_actions_public_id (public_id),
  UNIQUE KEY uq_entitlement_policy_actions_idempotency (idempotency_key),
  KEY idx_entitlement_policy_actions_source (source_type,source_reference),
  CONSTRAINT fk_entitlement_policy_order FOREIGN KEY (commerce_order_id) REFERENCES commerce_orders(id) ON DELETE SET NULL,
  CONSTRAINT fk_entitlement_policy_pppm FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_entitlement_policy_asset FOREIGN KEY (asset_id) REFERENCES catalog_assets(id) ON DELETE SET NULL,
  CONSTRAINT fk_entitlement_policy_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.entitlements.view','View merchant entitlements','View entitlement and protected-access summaries for owned merchant products.',NOW()),
('entitlements.lifecycle','Run entitlement lifecycle policies','Synchronize ownership, disputes, asset removal, and expiration policies.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='merchant.entitlements.view'
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='entitlements.lifecycle'
WHERE r.slug IN ('admin','super_admin');
