-- Merchant and Bundle Commission Authority v1
-- Canonical configurable platform starting rate, per-merchant effective-dated terms,
-- bundle rate modes, participant acceptance, and immutable checkout/order snapshots.

CREATE TABLE IF NOT EXISTS commission_platform_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  settings_key VARCHAR(40) NOT NULL DEFAULT 'default',
  starting_commission_bps INT UNSIGNED NOT NULL DEFAULT 1500,
  rule_version VARCHAR(80) NOT NULL DEFAULT 'merchant-bundle-commission-v1',
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_commission_platform_settings_public (public_id),
  UNIQUE KEY uq_commission_platform_settings_key (settings_key),
  CONSTRAINT fk_commission_platform_settings_actor FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commission_platform_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  settings_id BIGINT UNSIGNED NOT NULL,
  previous_commission_bps INT UNSIGNED NULL,
  new_commission_bps INT UNSIGNED NOT NULL,
  change_reason VARCHAR(500) NOT NULL,
  changed_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_commission_platform_history_public (public_id),
  KEY idx_commission_platform_history_settings (settings_id,created_at,id),
  CONSTRAINT fk_commission_platform_history_settings FOREIGN KEY (settings_id) REFERENCES commission_platform_settings(id) ON DELETE CASCADE,
  CONSTRAINT fk_commission_platform_history_actor FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_commission_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL DEFAULT 1,
  rate_mode ENUM('fixed_merchant_rate','follow_platform_default','promotional_rate','contract_rate') NOT NULL DEFAULT 'fixed_merchant_rate',
  default_commission_bps INT UNSIGNED NULL,
  initialized_from_platform_bps INT UNSIGNED NOT NULL,
  effective_from DATETIME NOT NULL,
  effective_until DATETIME NULL,
  status ENUM('active','scheduled','retired','cancelled') NOT NULL DEFAULT 'active',
  reason VARCHAR(500) NOT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_commission_profiles_public (public_id),
  UNIQUE KEY uq_merchant_commission_profiles_version (merchant_user_id,version_number),
  KEY idx_merchant_commission_profiles_effective (merchant_user_id,status,effective_from,effective_until),
  CONSTRAINT fk_merchant_commission_profiles_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_commission_profiles_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_commission_profiles_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_commission_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  previous_profile_id BIGINT UNSIGNED NULL,
  new_profile_id BIGINT UNSIGNED NOT NULL,
  previous_commission_bps INT UNSIGNED NULL,
  new_commission_bps INT UNSIGNED NULL,
  previous_rate_mode VARCHAR(40) NULL,
  new_rate_mode VARCHAR(40) NOT NULL,
  effective_from DATETIME NOT NULL,
  effective_until DATETIME NULL,
  change_reason VARCHAR(500) NOT NULL,
  changed_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_commission_history_public (public_id),
  KEY idx_merchant_commission_history_merchant (merchant_user_id,created_at,id),
  CONSTRAINT fk_merchant_commission_history_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_commission_history_previous FOREIGN KEY (previous_profile_id) REFERENCES merchant_commission_profiles(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_commission_history_new FOREIGN KEY (new_profile_id) REFERENCES merchant_commission_profiles(id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_commission_history_actor FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bundle_commission_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  bundle_reference VARCHAR(190) NOT NULL,
  version_number INT UNSIGNED NOT NULL DEFAULT 1,
  commission_mode ENUM('merchant_default','bundle_starting_rate','custom_participant_rates') NOT NULL DEFAULT 'merchant_default',
  starting_commission_bps INT UNSIGNED NULL,
  status ENUM('draft','locked','superseded','cancelled') NOT NULL DEFAULT 'draft',
  reason VARCHAR(500) NOT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bundle_commission_profiles_public (public_id),
  UNIQUE KEY uq_bundle_commission_profiles_version (bundle_reference,version_number),
  KEY idx_bundle_commission_profiles_active (bundle_reference,status,version_number),
  CONSTRAINT fk_bundle_commission_profiles_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_bundle_commission_profiles_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bundle_commission_participant_terms (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  bundle_profile_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  proposed_commission_bps INT UNSIGNED NOT NULL,
  accepted_commission_bps INT UNSIGNED NULL,
  terms_status ENUM('proposed','countered','accepted','declined','revoked') NOT NULL DEFAULT 'proposed',
  terms_source VARCHAR(80) NOT NULL DEFAULT 'bundle_participant_terms',
  reason VARCHAR(500) NOT NULL,
  accepted_by_user_id BIGINT UNSIGNED NULL,
  accepted_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bundle_commission_participant_public (public_id),
  UNIQUE KEY uq_bundle_commission_participant_merchant (bundle_profile_id,merchant_user_id),
  KEY idx_bundle_commission_participant_status (merchant_user_id,terms_status,updated_at),
  CONSTRAINT fk_bundle_commission_participant_profile FOREIGN KEY (bundle_profile_id) REFERENCES bundle_commission_profiles(id) ON DELETE CASCADE,
  CONSTRAINT fk_bundle_commission_participant_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_bundle_commission_participant_accepted_by FOREIGN KEY (accepted_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_bundle_commission_participant_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_bundle_commission_participant_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS checkout_draft_commission_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  checkout_draft_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  merchant_profile_id BIGINT UNSIGNED NULL,
  bundle_profile_id BIGINT UNSIGNED NULL,
  bundle_terms_id BIGINT UNSIGNED NULL,
  commissionable_amount_cents BIGINT UNSIGNED NOT NULL,
  commission_rate_bps INT UNSIGNED NOT NULL,
  percentage_commission_cents BIGINT UNSIGNED NOT NULL,
  fixed_fee_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  commission_amount_cents BIGINT UNSIGNED NOT NULL,
  merchant_net_amount_cents BIGINT UNSIGNED NOT NULL,
  rate_mode VARCHAR(50) NOT NULL,
  rate_source VARCHAR(80) NOT NULL,
  rule_version VARCHAR(80) NOT NULL,
  bundle_reference VARCHAR(190) NULL,
  bundle_terms_version INT UNSIGNED NULL,
  inputs_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_checkout_draft_commission_public (public_id),
  UNIQUE KEY uq_checkout_draft_commission_draft (checkout_draft_id),
  KEY idx_checkout_draft_commission_merchant (merchant_user_id,created_at),
  CONSTRAINT fk_checkout_draft_commission_draft FOREIGN KEY (checkout_draft_id) REFERENCES checkout_drafts(id) ON DELETE CASCADE,
  CONSTRAINT fk_checkout_draft_commission_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_checkout_draft_commission_profile FOREIGN KEY (merchant_profile_id) REFERENCES merchant_commission_profiles(id) ON DELETE SET NULL,
  CONSTRAINT fk_checkout_draft_commission_bundle FOREIGN KEY (bundle_profile_id) REFERENCES bundle_commission_profiles(id) ON DELETE SET NULL,
  CONSTRAINT fk_checkout_draft_commission_terms FOREIGN KEY (bundle_terms_id) REFERENCES bundle_commission_participant_terms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commerce_order_commission_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  checkout_draft_snapshot_id BIGINT UNSIGNED NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  merchant_profile_id BIGINT UNSIGNED NULL,
  bundle_profile_id BIGINT UNSIGNED NULL,
  bundle_terms_id BIGINT UNSIGNED NULL,
  commissionable_amount_cents BIGINT UNSIGNED NOT NULL,
  commission_rate_bps INT UNSIGNED NOT NULL,
  percentage_commission_cents BIGINT UNSIGNED NOT NULL,
  fixed_fee_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  commission_amount_cents BIGINT UNSIGNED NOT NULL,
  merchant_net_amount_cents BIGINT UNSIGNED NOT NULL,
  rate_mode VARCHAR(50) NOT NULL,
  rate_source VARCHAR(80) NOT NULL,
  rule_version VARCHAR(80) NOT NULL,
  bundle_reference VARCHAR(190) NULL,
  bundle_terms_version INT UNSIGNED NULL,
  inputs_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_commerce_order_commission_public (public_id),
  UNIQUE KEY uq_commerce_order_commission_order (order_id),
  KEY idx_commerce_order_commission_merchant (merchant_user_id,created_at),
  CONSTRAINT fk_commerce_order_commission_order FOREIGN KEY (order_id) REFERENCES commerce_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_commerce_order_commission_draft FOREIGN KEY (checkout_draft_snapshot_id) REFERENCES checkout_draft_commission_snapshots(id) ON DELETE SET NULL,
  CONSTRAINT fk_commerce_order_commission_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_commerce_order_commission_profile FOREIGN KEY (merchant_profile_id) REFERENCES merchant_commission_profiles(id) ON DELETE SET NULL,
  CONSTRAINT fk_commerce_order_commission_bundle FOREIGN KEY (bundle_profile_id) REFERENCES bundle_commission_profiles(id) ON DELETE SET NULL,
  CONSTRAINT fk_commerce_order_commission_terms FOREIGN KEY (bundle_terms_id) REFERENCES bundle_commission_participant_terms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commerce_order_item_commission_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  order_item_id BIGINT UNSIGNED NOT NULL,
  order_commission_snapshot_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  merchant_profile_id BIGINT UNSIGNED NULL,
  bundle_profile_id BIGINT UNSIGNED NULL,
  bundle_terms_id BIGINT UNSIGNED NULL,
  commissionable_amount_cents BIGINT UNSIGNED NOT NULL,
  commission_rate_bps INT UNSIGNED NOT NULL,
  commission_amount_cents BIGINT UNSIGNED NOT NULL,
  merchant_net_amount_cents BIGINT UNSIGNED NOT NULL,
  rate_mode VARCHAR(50) NOT NULL,
  rate_source VARCHAR(80) NOT NULL,
  rule_version VARCHAR(80) NOT NULL,
  bundle_reference VARCHAR(190) NULL,
  bundle_terms_version INT UNSIGNED NULL,
  inputs_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_order_item_commission_public (public_id),
  UNIQUE KEY uq_order_item_commission_item (order_item_id),
  KEY idx_order_item_commission_order (order_id,id),
  KEY idx_order_item_commission_merchant (merchant_user_id,created_at),
  CONSTRAINT fk_order_item_commission_order FOREIGN KEY (order_id) REFERENCES commerce_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_item_commission_item FOREIGN KEY (order_item_id) REFERENCES commerce_order_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_item_commission_snapshot FOREIGN KEY (order_commission_snapshot_id) REFERENCES commerce_order_commission_snapshots(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_item_commission_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_order_item_commission_profile FOREIGN KEY (merchant_profile_id) REFERENCES merchant_commission_profiles(id) ON DELETE SET NULL,
  CONSTRAINT fk_order_item_commission_bundle FOREIGN KEY (bundle_profile_id) REFERENCES bundle_commission_profiles(id) ON DELETE SET NULL,
  CONSTRAINT fk_order_item_commission_terms FOREIGN KEY (bundle_terms_id) REFERENCES bundle_commission_participant_terms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO commission_platform_settings
(public_id,settings_key,starting_commission_bps,rule_version,created_at,updated_at)
SELECT UUID(),'default',COALESCE((SELECT platform_fee_bps FROM payment_platform_credentials WHERE provider_key='stripe' ORDER BY CASE mode WHEN 'live' THEN 0 ELSE 1 END,updated_at DESC,id DESC LIMIT 1),1500),'merchant-bundle-commission-v1',NOW(),NOW();

INSERT IGNORE INTO merchant_commission_profiles
(public_id,merchant_user_id,version_number,rate_mode,default_commission_bps,initialized_from_platform_bps,effective_from,effective_until,status,reason,created_by_user_id,updated_by_user_id,created_at,updated_at)
SELECT UUID(),merchants.merchant_user_id,1,'fixed_merchant_rate',cps.starting_commission_bps,cps.starting_commission_bps,NOW(),NULL,'active','Initialized from the platform starting commission during commission-authority migration.',NULL,NULL,NOW(),NOW()
FROM (
  SELECT merchant_user_id FROM merchant_storefronts
  UNION SELECT merchant_user_id FROM payment_provider_accounts
  UNION SELECT ur.user_id AS merchant_user_id FROM user_roles ur INNER JOIN roles r ON r.id=ur.role_id WHERE r.slug='merchant'
) merchants
CROSS JOIN commission_platform_settings cps
LEFT JOIN merchant_commission_profiles mcp ON mcp.merchant_user_id=merchants.merchant_user_id
WHERE cps.settings_key='default' AND mcp.id IS NULL;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.payments.commissions.manage','Manage merchant commission terms','Manage the platform starting commission, merchant effective-dated commission profiles, and bundle participant commission terms.',NOW()),
('merchant.payments.commissions.view','View merchant commission terms','View the authenticated merchant commission rate, source, effective period, and sale preview.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='admin.payments.commissions.manage' WHERE r.slug IN ('admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='merchant.payments.commissions.view' WHERE r.slug IN ('merchant','admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('20260719_merchant_bundle_commission_authority_v1','Configurable platform starting commission, effective-dated merchant terms, bundle commission profiles, participant acceptance, and immutable checkout/order snapshots.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
