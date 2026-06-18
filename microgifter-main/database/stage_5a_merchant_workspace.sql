-- Stage 5A Merchant Workspace Shell, Onboarding Foundation, and Navigation

CREATE TABLE IF NOT EXISTS merchant_workspaces (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  display_name VARCHAR(180) NOT NULL,
  legal_name VARCHAR(220) NULL,
  business_type VARCHAR(80) NULL,
  website_url VARCHAR(500) NULL,
  support_email VARCHAR(255) NULL,
  support_phone VARCHAR(40) NULL,
  default_currency CHAR(3) NOT NULL DEFAULT 'USD',
  timezone VARCHAR(80) NOT NULL DEFAULT 'UTC',
  status ENUM('draft','pending_review','active','suspended','archived') NOT NULL DEFAULT 'draft',
  eligibility_status ENUM('not_started','pending','eligible','ineligible','manual_review') NOT NULL DEFAULT 'not_started',
  onboarding_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  metadata_json JSON NULL,
  activated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_workspaces_public_id (public_id),
  UNIQUE KEY uq_merchant_workspaces_user (merchant_user_id),
  CONSTRAINT fk_merchant_workspaces_user FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_onboarding_steps (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  workspace_id BIGINT UNSIGNED NOT NULL,
  step_key VARCHAR(80) NOT NULL,
  step_order SMALLINT UNSIGNED NOT NULL,
  status ENUM('locked','available','in_progress','completed','skipped','needs_attention') NOT NULL DEFAULT 'available',
  completed_at DATETIME NULL,
  completed_by_user_id BIGINT UNSIGNED NULL,
  state_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_onboarding_step (workspace_id,step_key),
  KEY idx_merchant_onboarding_status (workspace_id,status,step_order),
  CONSTRAINT fk_merchant_onboarding_workspace FOREIGN KEY (workspace_id) REFERENCES merchant_workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_onboarding_user FOREIGN KEY (completed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_locations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  location_code VARCHAR(80) NOT NULL,
  address_line1 VARCHAR(220) NULL,
  address_line2 VARCHAR(220) NULL,
  city VARCHAR(120) NULL,
  region VARCHAR(120) NULL,
  postal_code VARCHAR(30) NULL,
  country_code CHAR(2) NOT NULL DEFAULT 'US',
  timezone VARCHAR(80) NOT NULL DEFAULT 'UTC',
  phone VARCHAR(40) NULL,
  status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_locations_public_id (public_id),
  UNIQUE KEY uq_merchant_locations_code (workspace_id,location_code),
  KEY idx_merchant_locations_status (workspace_id,status,is_primary),
  CONSTRAINT fk_merchant_locations_workspace FOREIGN KEY (workspace_id) REFERENCES merchant_workspaces(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_team_members (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  invited_email_hash CHAR(64) NULL,
  display_name VARCHAR(180) NULL,
  role_key ENUM('owner','admin','manager','location_staff','claims_staff','analyst','viewer') NOT NULL DEFAULT 'viewer',
  location_scope_json JSON NULL,
  status ENUM('invited','active','disabled','removed') NOT NULL DEFAULT 'invited',
  invited_by_user_id BIGINT UNSIGNED NOT NULL,
  invited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  accepted_at DATETIME NULL,
  removed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_team_public_id (public_id),
  UNIQUE KEY uq_merchant_team_user (workspace_id,user_id),
  UNIQUE KEY uq_merchant_team_invite (workspace_id,invited_email_hash),
  CONSTRAINT fk_merchant_team_workspace FOREIGN KEY (workspace_id) REFERENCES merchant_workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_team_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_team_inviter FOREIGN KEY (invited_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_payment_readiness (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  workspace_id BIGINT UNSIGNED NOT NULL,
  provider_key VARCHAR(80) NULL,
  mode ENUM('not_configured','test','live') NOT NULL DEFAULT 'not_configured',
  account_connected TINYINT(1) NOT NULL DEFAULT 0,
  identity_verified TINYINT(1) NOT NULL DEFAULT 0,
  charges_enabled TINYINT(1) NOT NULL DEFAULT 0,
  payouts_enabled TINYINT(1) NOT NULL DEFAULT 0,
  tax_setup_complete TINYINT(1) NOT NULL DEFAULT 0,
  test_payment_complete TINYINT(1) NOT NULL DEFAULT 0,
  live_approved TINYINT(1) NOT NULL DEFAULT 0,
  state_json JSON NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_payment_workspace (workspace_id),
  CONSTRAINT fk_merchant_payment_workspace FOREIGN KEY (workspace_id) REFERENCES merchant_workspaces(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.workspace.view','View merchant workspace','Open the merchant operating workspace.',NOW()),
('merchant.workspace.manage','Manage merchant workspace','Update merchant workspace settings and onboarding.',NOW()),
('merchant.locations.manage','Manage merchant locations','Create and update merchant locations.',NOW()),
('merchant.team.manage','Manage merchant team','Invite and manage merchant team members.',NOW()),
('merchant.payments.view_readiness','View payment readiness','View payment onboarding and launch readiness without processing payments.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('merchant.workspace.view','merchant.workspace.manage','merchant.locations.manage','merchant.team.manage','merchant.payments.view_readiness')
WHERE r.slug IN ('merchant','admin','super_admin');
