-- 02G Microgifter sales model and CRM schema
-- Purpose: add sales identity model, basic CRM lead capture tables, lead assignment foundation,
-- and website visitor analytics by region.
-- HostGator/MySQL compatible. Safe to rerun.
-- NOTE: roles and permissions in the current live schema use a minimal column shape.

START TRANSACTION;

-- 1) Sales user model
-- Avoid ON DUPLICATE KEY UPDATE VALUES(...) because newer MySQL versions deprecate VALUES().
INSERT INTO user_models (public_id, code, name, description, is_system, is_assignable, requires_approval, default_status, sort_order)
SELECT 'um_sales', 'sales', 'Sales', 'Sales identity model for lead follow-up, CRM assignment, and customer acquisition workflows.', 1, 0, 1, 'pending', 65
WHERE NOT EXISTS (SELECT 1 FROM user_models WHERE code = 'sales');

UPDATE user_models
SET name = 'Sales',
    description = 'Sales identity model for lead follow-up, CRM assignment, and customer acquisition workflows.',
    is_system = 1,
    is_assignable = 0,
    requires_approval = 1,
    default_status = 'pending',
    sort_order = 65,
    updated_at = NOW()
WHERE code = 'sales';

-- 2) Sales role and permissions
-- Live roles table confirmed columns: id, slug, name, created_at.
-- Use only slug/name so created_at can use its DEFAULT CURRENT_TIMESTAMP value.
INSERT INTO roles (slug, name)
SELECT 'sales', 'Sales'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE slug = 'sales');

-- Keep permission inserts minimal too, so they work with the same Stage 1 table shape.
INSERT INTO permissions (slug, name)
SELECT p.slug, p.name
FROM (
  SELECT 'sales.leads.view_own' AS slug, 'View own sales leads' AS name
  UNION ALL SELECT 'sales.leads.view_all', 'View all sales leads'
  UNION ALL SELECT 'sales.leads.assign', 'Assign sales leads'
  UNION ALL SELECT 'sales.leads.update_status', 'Update sales lead status'
  UNION ALL SELECT 'sales.roster.view', 'View sales roster'
  UNION ALL SELECT 'sales.roster.manage', 'Manage sales roster'
  UNION ALL SELECT 'crm.analytics.view', 'View CRM analytics'
) p
WHERE NOT EXISTS (SELECT 1 FROM permissions existing WHERE existing.slug = p.slug);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.slug IN (
  'sales.leads.view_own',
  'sales.leads.update_status',
  'sales.roster.view'
)
WHERE r.slug = 'sales';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.slug IN (
  'sales.leads.view_own',
  'sales.leads.view_all',
  'sales.leads.assign',
  'sales.leads.update_status',
  'sales.roster.view',
  'sales.roster.manage',
  'crm.analytics.view'
)
WHERE r.slug IN ('admin', 'super_admin');

INSERT IGNORE INTO model_default_roles (user_model_id, role_id, is_required, created_at)
SELECT um.id, r.id, 1, NOW()
FROM user_models um
JOIN roles r ON r.slug = 'sales'
WHERE um.code = 'sales';

-- 3) Sales profile and roster
CREATE TABLE IF NOT EXISTS sales_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  display_name VARCHAR(160) NULL,
  title VARCHAR(120) NULL,
  territory VARCHAR(180) NULL,
  status ENUM('pending','active','inactive','suspended') NOT NULL DEFAULT 'pending',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sales_profiles_public_id (public_id),
  UNIQUE KEY uq_sales_profiles_user (user_id),
  KEY idx_sales_profiles_status (status),
  CONSTRAINT fk_sales_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_roster (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('active','inactive','paused','suspended') NOT NULL DEFAULT 'active',
  territory VARCHAR(180) NULL,
  region_code VARCHAR(80) NULL,
  lead_weight INT UNSIGNED NOT NULL DEFAULT 100,
  max_open_leads INT UNSIGNED NOT NULL DEFAULT 50,
  open_lead_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_assigned_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sales_roster_public_id (public_id),
  UNIQUE KEY uq_sales_roster_user (user_id),
  KEY idx_sales_roster_status_region (status, region_code),
  KEY idx_sales_roster_capacity (status, open_lead_count, max_open_leads, last_assigned_at),
  CONSTRAINT fk_sales_roster_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) CRM leads and assignment history
CREATE TABLE IF NOT EXISTS crm_leads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  lead_type ENUM('merchant','workplace','creator','affiliate','partner','general') NOT NULL DEFAULT 'general',
  source_page VARCHAR(180) NOT NULL DEFAULT 'learn-more',
  source_url VARCHAR(600) NULL,
  source_utm_json JSON NULL,
  name VARCHAR(180) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(80) NULL,
  business_name VARCHAR(220) NULL,
  website_url VARCHAR(600) NULL,
  zip_code VARCHAR(20) NULL,
  category VARCHAR(120) NULL,
  message TEXT NULL,
  status ENUM('new','assigned','contacted','qualified','nurture','converted','closed_lost','spam') NOT NULL DEFAULT 'new',
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  assigned_user_id BIGINT UNSIGNED NULL,
  assigned_at DATETIME NULL,
  region_country VARCHAR(80) NULL,
  region_state VARCHAR(120) NULL,
  region_city VARCHAR(120) NULL,
  region_postal VARCHAR(40) NULL,
  ip_hash CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_crm_leads_public_id (public_id),
  KEY idx_crm_leads_status_created (status, created_at),
  KEY idx_crm_leads_assigned_status (assigned_user_id, status),
  KEY idx_crm_leads_email (email),
  KEY idx_crm_leads_region (region_country, region_state, region_city),
  KEY idx_crm_leads_zip (zip_code),
  CONSTRAINT fk_crm_leads_assigned_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_lead_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  lead_id BIGINT UNSIGNED NOT NULL,
  assigned_to_user_id BIGINT UNSIGNED NOT NULL,
  assigned_by_user_id BIGINT UNSIGNED NULL,
  assignment_method ENUM('auto_least_open','manual','system') NOT NULL DEFAULT 'auto_least_open',
  reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_crm_lead_assignments_public_id (public_id),
  KEY idx_crm_lead_assignments_lead (lead_id, created_at),
  KEY idx_crm_lead_assignments_user (assigned_to_user_id, created_at),
  CONSTRAINT fk_crm_lead_assignments_lead FOREIGN KEY (lead_id) REFERENCES crm_leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_crm_lead_assignments_assigned_to FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_crm_lead_assignments_assigned_by FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_lead_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  lead_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  from_status VARCHAR(40) NULL,
  to_status VARCHAR(40) NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  note TEXT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_crm_lead_events_public_id (public_id),
  KEY idx_crm_lead_events_lead_created (lead_id, created_at),
  KEY idx_crm_lead_events_type_created (event_type, created_at),
  KEY idx_crm_lead_events_actor (actor_user_id, created_at),
  CONSTRAINT fk_crm_lead_events_lead FOREIGN KEY (lead_id) REFERENCES crm_leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_crm_lead_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_lead_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  lead_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  note TEXT NOT NULL,
  visibility ENUM('internal','sales','admin') NOT NULL DEFAULT 'internal',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_crm_lead_notes_public_id (public_id),
  KEY idx_crm_lead_notes_lead_created (lead_id, created_at),
  CONSTRAINT fk_crm_lead_notes_lead FOREIGN KEY (lead_id) REFERENCES crm_leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_crm_lead_notes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) Website analytics foundation for visitor region reporting.
-- Do not store raw IP addresses here. The application should hash IP/user-agent values before insert.
CREATE TABLE IF NOT EXISTS website_analytics_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  event_type VARCHAR(80) NOT NULL DEFAULT 'page_view',
  source_page VARCHAR(180) NULL,
  path VARCHAR(500) NULL,
  referrer VARCHAR(700) NULL,
  utm_source VARCHAR(120) NULL,
  utm_medium VARCHAR(120) NULL,
  utm_campaign VARCHAR(160) NULL,
  utm_term VARCHAR(160) NULL,
  utm_content VARCHAR(160) NULL,
  region_country VARCHAR(80) NULL,
  region_state VARCHAR(120) NULL,
  region_city VARCHAR(120) NULL,
  region_postal VARCHAR(40) NULL,
  timezone_label VARCHAR(120) NULL,
  ip_hash CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  session_key_hash CHAR(64) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_website_analytics_events_public_id (public_id),
  KEY idx_website_analytics_created (created_at),
  KEY idx_website_analytics_page_created (source_page, created_at),
  KEY idx_website_analytics_region_created (region_country, region_state, region_city, created_at),
  KEY idx_website_analytics_event_created (event_type, created_at),
  KEY idx_website_analytics_session (session_key_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_daily_region_stats (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  stat_date DATE NOT NULL,
  source_page VARCHAR(180) NOT NULL DEFAULT 'learn-more',
  region_country VARCHAR(80) NOT NULL DEFAULT 'unknown',
  region_state VARCHAR(120) NOT NULL DEFAULT 'unknown',
  region_city VARCHAR(120) NOT NULL DEFAULT 'unknown',
  visitors_count INT UNSIGNED NOT NULL DEFAULT 0,
  page_views_count INT UNSIGNED NOT NULL DEFAULT 0,
  leads_count INT UNSIGNED NOT NULL DEFAULT 0,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_crm_daily_region_stats (stat_date, source_page, region_country, region_state, region_city),
  KEY idx_crm_daily_region_stats_date (stat_date),
  KEY idx_crm_daily_region_stats_region (region_country, region_state, region_city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
