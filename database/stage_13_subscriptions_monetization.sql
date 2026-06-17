CREATE TABLE IF NOT EXISTS subscription_plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  target_type ENUM('profile','creator','merchant','location','product','post') NOT NULL,
  target_reference VARCHAR(190) NOT NULL,
  name VARCHAR(160) NOT NULL,
  description VARCHAR(1000) NULL,
  amount_cents BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  interval_unit ENUM('week','month','year') NOT NULL DEFAULT 'month',
  interval_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  trial_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  funding_type ENUM('wallet','stripe') NOT NULL DEFAULT 'stripe',
  status ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'draft',
  provider_price_id VARCHAR(190) NULL,
  policy_json JSON NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subscription_plans_public_id (public_id),
  KEY idx_subscription_plans_owner_status (owner_user_id,status,updated_at),
  KEY idx_subscription_plans_target (target_type,target_reference,status),
  CONSTRAINT fk_subscription_plans_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT chk_subscription_plan_amount CHECK (amount_cents > 0),
  CONSTRAINT chk_subscription_plan_interval CHECK (interval_count > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  subscriber_user_id BIGINT UNSIGNED NOT NULL,
  recipient_user_id BIGINT UNSIGNED NOT NULL,
  target_type VARCHAR(40) NOT NULL,
  target_reference VARCHAR(190) NOT NULL,
  amount_cents BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  funding_type ENUM('wallet','stripe') NOT NULL,
  status ENUM('pending_payment','trialing','active','past_due','paused','cancel_pending','canceled','expired') NOT NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  provider_subscription_id VARCHAR(190) NULL,
  provider_customer_id VARCHAR(190) NULL,
  provider_payment_method_ref VARCHAR(190) NULL,
  current_period_start DATETIME NOT NULL,
  current_period_end DATETIME NOT NULL,
  next_billing_at DATETIME NULL,
  trial_ends_at DATETIME NULL,
  initial_payment_required TINYINT(1) NOT NULL DEFAULT 0,
  funded_at DATETIME NULL,
  activated_at DATETIME NULL,
  cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
  canceled_at DATETIME NULL,
  paused_at DATETIME NULL,
  resumed_at DATETIME NULL,
  retry_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_failure_message VARCHAR(500) NULL,
  recovery_status ENUM('clear','disputed','refunded','chargeback') NOT NULL DEFAULT 'clear',
  recovery_attempt_id BIGINT UNSIGNED NULL,
  recovery_reference VARCHAR(190) NULL,
  pre_recovery_status VARCHAR(40) NULL,
  pre_recovery_next_billing_at DATETIME NULL,
  recovery_started_at DATETIME NULL,
  recovery_resolved_at DATETIME NULL,
  access_suspended_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subscriptions_public_id (public_id),
  UNIQUE KEY uq_subscriptions_subscriber_idempotency (subscriber_user_id,idempotency_key),
  UNIQUE KEY uq_subscriptions_provider_subscription (provider_subscription_id),
  KEY idx_subscriptions_due (status,next_billing_at,id),
  KEY idx_subscriptions_subscriber (subscriber_user_id,status,updated_at),
  KEY idx_subscriptions_recipient (recipient_user_id,status,updated_at),
  KEY idx_subscriptions_recovery (recovery_status,recovery_attempt_id,updated_at),
  CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE RESTRICT,
  CONSTRAINT fk_subscriptions_subscriber FOREIGN KEY (subscriber_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_subscriptions_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  subscription_id BIGINT UNSIGNED NOT NULL,
  cycle_key VARCHAR(80) NOT NULL,
  attempt_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('pending','processing','succeeded','failed','abandoned') NOT NULL DEFAULT 'pending',
  tip_id BIGINT UNSIGNED NULL,
  provider_payment_id VARCHAR(190) NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  amount_cents BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL,
  failure_code VARCHAR(100) NULL,
  failure_message VARCHAR(500) NULL,
  recovery_status ENUM('clear','disputed','partial_refund','refunded','chargeback') NOT NULL DEFAULT 'clear',
  recovered_amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  recovery_reference VARCHAR(190) NULL,
  recovery_started_at DATETIME NULL,
  recovery_resolved_at DATETIME NULL,
  scheduled_at DATETIME NOT NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  next_retry_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subscription_attempts_public_id (public_id),
  UNIQUE KEY uq_subscription_attempts_cycle_attempt (subscription_id,cycle_key,attempt_number),
  UNIQUE KEY uq_subscription_attempts_idempotency (idempotency_key),
  UNIQUE KEY uq_subscription_attempts_provider_payment (provider_payment_id),
  KEY idx_subscription_attempts_retry (status,next_retry_at,id),
  KEY idx_subscription_attempts_recovery (recovery_status,recovery_reference,updated_at),
  CONSTRAINT fk_subscription_attempts_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
  CONSTRAINT fk_subscription_attempts_tip FOREIGN KEY (tip_id) REFERENCES tips(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  subscription_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  from_status VARCHAR(40) NULL,
  to_status VARCHAR(40) NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  reason_code VARCHAR(100) NULL,
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subscription_events_public_id (public_id),
  KEY idx_subscription_events_subscription (subscription_id,created_at,id),
  CONSTRAINT fk_subscription_events_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
  CONSTRAINT fk_subscription_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_payment_recoveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  subscription_id BIGINT UNSIGNED NOT NULL,
  subscription_attempt_id BIGINT UNSIGNED NOT NULL,
  tip_recovery_id BIGINT UNSIGNED NOT NULL,
  recovery_type ENUM('refund','dispute_opened','dispute_won','dispute_lost','chargeback') NOT NULL,
  provider_reference VARCHAR(190) NOT NULL,
  amount_cents BIGINT UNSIGNED NOT NULL,
  recovered_amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  previous_subscription_status VARCHAR(40) NOT NULL,
  resulting_subscription_status VARCHAR(40) NOT NULL,
  previous_recovery_status VARCHAR(40) NOT NULL,
  resulting_recovery_status VARCHAR(40) NOT NULL,
  access_action ENUM('unchanged','suspended','restored','revoked') NOT NULL,
  payload_json JSON NULL,
  processed_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subscription_recoveries_public_id (public_id),
  UNIQUE KEY uq_subscription_recoveries_tip_recovery (tip_recovery_id),
  KEY idx_subscription_recoveries_subscription (subscription_id,created_at,id),
  KEY idx_subscription_recoveries_attempt (subscription_attempt_id,recovery_type,created_at),
  KEY idx_subscription_recoveries_provider (provider_reference,recovery_type),
  CONSTRAINT fk_subscription_recoveries_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
  CONSTRAINT fk_subscription_recoveries_attempt FOREIGN KEY (subscription_attempt_id) REFERENCES subscription_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_subscription_recoveries_tip_recovery FOREIGN KEY (tip_recovery_id) REFERENCES tip_payment_recoveries(id) ON DELETE RESTRICT,
  CONSTRAINT chk_subscription_recoveries_amount CHECK (amount_cents > 0),
  CONSTRAINT chk_subscription_recoveries_accumulated CHECK (recovered_amount_cents >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('subscriptions.create','Create subscriptions','Subscribe to recurring support plans.',NOW()),
('subscriptions.manage_own','Manage own subscriptions','Pause, resume, and cancel owned subscriptions.',NOW()),
('subscription_plans.manage','Manage subscription plans','Create and manage monetization plans.',NOW()),
('subscriptions.admin','Administer subscriptions','Inspect and administratively manage subscriptions.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN ('subscriptions.create','subscriptions.manage_own')
WHERE r.slug IN ('customer','member','merchant','creator','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='subscription_plans.manage'
WHERE r.slug IN ('merchant','creator','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='subscriptions.admin'
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_13_subscriptions_monetization','Recurring support plans, funded activation, renewal attempts, retries, dunning, and payment recovery access reconciliation.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
