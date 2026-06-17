CREATE TABLE IF NOT EXISTS merchant_saved_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  report_type ENUM('overview','campaigns','products','locations','pppm_funnel','engagement','forecast') NOT NULL DEFAULT 'overview',
  date_range_key VARCHAR(40) NOT NULL DEFAULT 'last_30_days',
  filters_json JSON NULL,
  columns_json JSON NULL,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_saved_reports_public_id (public_id),
  KEY idx_merchant_saved_reports_merchant (merchant_user_id,status,updated_at),
  CONSTRAINT fk_merchant_saved_reports_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_saved_reports_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_report_schedules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  saved_report_id BIGINT UNSIGNED NOT NULL,
  frequency ENUM('daily','weekly','monthly') NOT NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
  recipient_email_hash CHAR(64) NOT NULL,
  format ENUM('csv','json') NOT NULL DEFAULT 'csv',
  status ENUM('active','paused','archived') NOT NULL DEFAULT 'active',
  next_run_at DATETIME NULL,
  last_run_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_report_schedules_public_id (public_id),
  KEY idx_merchant_report_schedules_queue (status,next_run_at),
  CONSTRAINT fk_merchant_report_schedules_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_report_schedules_report FOREIGN KEY (saved_report_id) REFERENCES merchant_saved_reports(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_report_schedules_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.intelligence.view','View merchant intelligence','View merchant analytics, forecasts, demand signals, and reporting.',NOW()),
('merchant.reports.manage','Manage merchant reports','Create saved reports, exports, and reporting schedules.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('merchant.intelligence.view','merchant.reports.manage')
WHERE r.slug IN ('merchant','admin','super_admin');
