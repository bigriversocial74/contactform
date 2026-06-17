-- Stage 4F Future Demand Intelligence, Forecasting, and Merchant Analytics

CREATE TABLE IF NOT EXISTS demand_fact_daily (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  metric_date DATE NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  program_id BIGINT UNSIGNED NULL,
  source_type VARCHAR(40) NOT NULL DEFAULT 'unknown',
  impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
  opens BIGINT UNSIGNED NOT NULL DEFAULT 0,
  media_starts BIGINT UNSIGNED NOT NULL DEFAULT 0,
  media_completions BIGINT UNSIGNED NOT NULL DEFAULT 0,
  cta_clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
  claim_opens BIGINT UNSIGNED NOT NULL DEFAULT 0,
  claims BIGINT UNSIGNED NOT NULL DEFAULT 0,
  redemptions BIGINT UNSIGNED NOT NULL DEFAULT 0,
  items_issued BIGINT UNSIGNED NOT NULL DEFAULT 0,
  issued_value_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  unique_recipients BIGINT UNSIGNED NOT NULL DEFAULT 0,
  unique_viewers BIGINT UNSIGNED NOT NULL DEFAULT 0,
  avg_time_to_claim_seconds BIGINT UNSIGNED NULL,
  avg_time_to_redeem_seconds BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_demand_fact_daily (metric_date,merchant_user_id,product_id,program_id,source_type),
  KEY idx_demand_fact_merchant_date (merchant_user_id,metric_date),
  KEY idx_demand_fact_product_date (product_id,metric_date),
  CONSTRAINT fk_demand_fact_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_demand_fact_product FOREIGN KEY (product_id) REFERENCES catalog_products(id) ON DELETE SET NULL,
  CONSTRAINT fk_demand_fact_program FOREIGN KEY (program_id) REFERENCES distribution_programs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demand_feature_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  as_of_date DATE NOT NULL,
  horizon_days SMALLINT UNSIGNED NOT NULL,
  feature_version VARCHAR(40) NOT NULL,
  features_json JSON NOT NULL,
  source_window_start DATE NOT NULL,
  source_window_end DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_demand_feature_public_id (public_id),
  UNIQUE KEY uq_demand_feature_scope (merchant_user_id,product_id,as_of_date,horizon_days,feature_version),
  CONSTRAINT fk_demand_feature_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_demand_feature_product FOREIGN KEY (product_id) REFERENCES catalog_products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demand_forecast_models (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  model_key VARCHAR(100) NOT NULL,
  model_version VARCHAR(40) NOT NULL,
  model_type ENUM('seasonal_naive','moving_average','exponential_smoothing','regression','external') NOT NULL,
  target_metric ENUM('items_issued','issued_value_cents','claims','redemptions','engagements') NOT NULL,
  parameters_json JSON NOT NULL,
  training_window_days SMALLINT UNSIGNED NOT NULL DEFAULT 90,
  status ENUM('active','inactive','retired') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_demand_forecast_models_public_id (public_id),
  UNIQUE KEY uq_demand_forecast_models_key (merchant_user_id,model_key,model_version),
  CONSTRAINT fk_demand_model_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demand_forecast_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  model_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  as_of_date DATE NOT NULL,
  horizon_days SMALLINT UNSIGNED NOT NULL,
  status ENUM('queued','running','completed','failed') NOT NULL DEFAULT 'queued',
  training_window_start DATE NOT NULL,
  training_window_end DATE NOT NULL,
  input_checksum CHAR(64) NOT NULL,
  metrics_json JSON NULL,
  failure_message VARCHAR(500) NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_demand_forecast_runs_public_id (public_id),
  UNIQUE KEY uq_demand_forecast_runs_scope (model_id,merchant_user_id,product_id,as_of_date,horizon_days,input_checksum),
  KEY idx_demand_forecast_runs_queue (status,created_at),
  CONSTRAINT fk_demand_run_model FOREIGN KEY (model_id) REFERENCES demand_forecast_models(id) ON DELETE RESTRICT,
  CONSTRAINT fk_demand_run_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_demand_run_product FOREIGN KEY (product_id) REFERENCES catalog_products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demand_forecast_points (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  forecast_run_id BIGINT UNSIGNED NOT NULL,
  forecast_date DATE NOT NULL,
  predicted_value DECIMAL(18,4) NOT NULL,
  lower_bound DECIMAL(18,4) NULL,
  upper_bound DECIMAL(18,4) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_demand_forecast_point (forecast_run_id,forecast_date),
  CONSTRAINT fk_demand_point_run FOREIGN KEY (forecast_run_id) REFERENCES demand_forecast_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_intelligence_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  snapshot_date DATE NOT NULL,
  period_days SMALLINT UNSIGNED NOT NULL,
  score_version VARCHAR(40) NOT NULL,
  demand_score DECIMAL(8,4) NOT NULL DEFAULT 0,
  engagement_score DECIMAL(8,4) NOT NULL DEFAULT 0,
  fulfillment_score DECIMAL(8,4) NOT NULL DEFAULT 0,
  redemption_score DECIMAL(8,4) NOT NULL DEFAULT 0,
  growth_rate DECIMAL(12,6) NULL,
  forecast_value_cents BIGINT UNSIGNED NULL,
  insights_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_intelligence_public_id (public_id),
  UNIQUE KEY uq_merchant_intelligence_scope (merchant_user_id,snapshot_date,period_days,score_version),
  CONSTRAINT fk_merchant_intelligence_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demand_alert_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  metric_key VARCHAR(80) NOT NULL,
  comparison ENUM('gt','gte','lt','lte','change_gt','change_lt') NOT NULL,
  threshold_value DECIMAL(18,4) NOT NULL,
  lookback_days SMALLINT UNSIGNED NOT NULL DEFAULT 7,
  status ENUM('active','paused','archived') NOT NULL DEFAULT 'active',
  channels_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_demand_alert_rules_public_id (public_id),
  KEY idx_demand_alert_rules_merchant_status (merchant_user_id,status),
  CONSTRAINT fk_demand_alert_rules_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demand_alert_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  rule_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  observed_value DECIMAL(18,4) NOT NULL,
  baseline_value DECIMAL(18,4) NULL,
  status ENUM('open','acknowledged','resolved') NOT NULL DEFAULT 'open',
  context_json JSON NULL,
  triggered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  acknowledged_at DATETIME NULL,
  resolved_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_demand_alert_events_public_id (public_id),
  KEY idx_demand_alert_events_merchant_status (merchant_user_id,status,triggered_at),
  CONSTRAINT fk_demand_alert_events_rule FOREIGN KEY (rule_id) REFERENCES demand_alert_rules(id) ON DELETE CASCADE,
  CONSTRAINT fk_demand_alert_events_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS intelligence_export_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  requested_by_user_id BIGINT UNSIGNED NOT NULL,
  export_type ENUM('summary','daily_facts','forecasts','campaigns','products') NOT NULL,
  format ENUM('csv','json') NOT NULL DEFAULT 'csv',
  date_from DATE NOT NULL,
  date_to DATE NOT NULL,
  privacy_mode ENUM('aggregate','k_anonymous') NOT NULL DEFAULT 'aggregate',
  minimum_cohort_size SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  status ENUM('queued','processing','ready','failed','expired') NOT NULL DEFAULT 'queued',
  storage_provider VARCHAR(80) NULL,
  storage_key VARCHAR(500) NULL,
  checksum_sha256 CHAR(64) NULL,
  row_count BIGINT UNSIGNED NULL,
  expires_at DATETIME NULL,
  failure_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_intelligence_export_jobs_public_id (public_id),
  KEY idx_intelligence_export_jobs_queue (status,created_at),
  CONSTRAINT fk_intelligence_export_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_intelligence_export_requester FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO demand_forecast_models (public_id,merchant_user_id,model_key,model_version,model_type,target_metric,parameters_json,training_window_days,status,created_at,updated_at) VALUES
(UUID(),NULL,'global_items_seasonal','1.0','seasonal_naive','items_issued',JSON_OBJECT('season_days',7,'confidence_multiplier',1.96),90,'active',NOW(),NOW()),
(UUID(),NULL,'global_value_moving_average','1.0','moving_average','issued_value_cents',JSON_OBJECT('window_days',28,'confidence_multiplier',1.96),90,'active',NOW(),NOW());

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('intelligence.dashboard.view','View demand intelligence dashboard','View merchant demand intelligence and forecasts.',NOW()),
('intelligence.forecasts.manage','Manage demand forecasts','Run and configure merchant forecasting.',NOW()),
('intelligence.alerts.manage','Manage demand alerts','Configure demand and performance alerts.',NOW()),
('intelligence.exports.create','Create intelligence exports','Create privacy-safe merchant intelligence exports.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('intelligence.dashboard.view','intelligence.forecasts.manage','intelligence.alerts.manage','intelligence.exports.create')
WHERE r.slug IN ('merchant','admin','super_admin');
