CREATE TABLE IF NOT EXISTS platform_subscription_packages (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 package_id VARCHAR(80) NOT NULL,
 name VARCHAR(160) NOT NULL,
 billing_cycle ENUM('month','year') NOT NULL DEFAULT 'month',
 monthly_amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
 yearly_amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
 currency CHAR(3) NOT NULL DEFAULT 'USD',
 stripe_price_id_test VARCHAR(190) NULL,
 stripe_price_id_live VARCHAR(190) NULL,
 stripe_product_id_test VARCHAR(190) NULL,
 stripe_product_id_live VARCHAR(190) NULL,
 is_self_serve TINYINT(1) NOT NULL DEFAULT 1,
 requires_admin_review TINYINT(1) NOT NULL DEFAULT 0,
 features_json JSON NULL,
 limits_json JSON NULL,
 metadata_json JSON NULL,
 status ENUM('active','archived') NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id), UNIQUE KEY uq_platform_subscription_packages_package (package_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_account_subscriptions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 public_id CHAR(36) NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 package_id VARCHAR(80) NOT NULL,
 billing_cycle ENUM('month','year') NOT NULL DEFAULT 'month',
 status ENUM('incomplete','pending_admin_review','active','trialing','past_due','paused','cancel_pending','canceled','expired') NOT NULL DEFAULT 'active',
 amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
 currency CHAR(3) NOT NULL DEFAULT 'USD',
 provider_key VARCHAR(40) NULL,
 provider_customer_id VARCHAR(190) NULL,
 provider_subscription_id VARCHAR(190) NULL,
 provider_session_reference VARCHAR(190) NULL,
 provider_price_id VARCHAR(190) NULL,
 current_period_start DATETIME NULL,
 current_period_end DATETIME NULL,
 next_billing_at DATETIME NULL,
 cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
 package_change_request_public_id CHAR(36) NULL,
 metadata_json JSON NULL,
 activated_at DATETIME NULL,
 canceled_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id), UNIQUE KEY uq_platform_account_subscriptions_public (public_id), UNIQUE KEY uq_platform_account_subscriptions_user (user_id), KEY idx_platform_account_subscriptions_provider (provider_key,provider_subscription_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_subscription_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 public_id CHAR(36) NOT NULL,
 account_subscription_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(120) NOT NULL,
 from_status VARCHAR(40) NULL,
 to_status VARCHAR(40) NULL,
 actor_user_id BIGINT UNSIGNED NULL,
 provider_key VARCHAR(40) NULL,
 provider_event_id VARCHAR(190) NULL,
 payload_json JSON NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id), UNIQUE KEY uq_platform_subscription_events_public (public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO platform_subscription_packages (package_id,name,billing_cycle,monthly_amount_cents,yearly_amount_cents,currency,is_self_serve,requires_admin_review,status,created_at,updated_at) VALUES
('starter','Starter','month',2900,27840,'USD',1,0,'active',NOW(),NOW()),
('growth','Growth','month',7900,75840,'USD',1,0,'active',NOW(),NOW()),
('pro','Pro','month',19900,191040,'USD',1,0,'active',NOW(),NOW()),
('enterprise','Enterprise','month',49900,479040,'USD',0,1,'active',NOW(),NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name),monthly_amount_cents=VALUES(monthly_amount_cents),yearly_amount_cents=VALUES(yearly_amount_cents),currency=VALUES(currency),is_self_serve=VALUES(is_self_serve),requires_admin_review=VALUES(requires_admin_review),status='active',updated_at=NOW();

SET @t:='redemption_settlement_ledger';
SET @c:='value_type'; SET @s:=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,"ALTER TABLE redemption_settlement_ledger ADD COLUMN value_type ENUM('promotional_reward','merchant_direct_paid_product','platform_checkout','sponsored_campaign') NOT NULL DEFAULT 'promotional_reward' AFTER receipt_type",'SELECT 1'); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c:='cash_movement'; SET @s:=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,"ALTER TABLE redemption_settlement_ledger ADD COLUMN cash_movement ENUM('none','merchant_direct','stripe_connect','platform_collected') NOT NULL DEFAULT 'none' AFTER value_type",'SELECT 1'); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c:='face_value_cents'; SET @s:=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,'ALTER TABLE redemption_settlement_ledger ADD COLUMN face_value_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER amount_cents','SELECT 1'); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c:='customer_paid_cents'; SET @s:=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,'ALTER TABLE redemption_settlement_ledger ADD COLUMN customer_paid_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER face_value_cents','SELECT 1'); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c:='merchant_collected_cents'; SET @s:=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,'ALTER TABLE redemption_settlement_ledger ADD COLUMN merchant_collected_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER customer_paid_cents','SELECT 1'); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c:='microgifter_collected_cents'; SET @s:=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,'ALTER TABLE redemption_settlement_ledger ADD COLUMN microgifter_collected_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER merchant_collected_cents','SELECT 1'); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c:='payout_due_cents'; SET @s:=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,'ALTER TABLE redemption_settlement_ledger ADD COLUMN payout_due_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER merchant_net_cents','SELECT 1'); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c:='reconciliation_status'; SET @s:=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,"ALTER TABLE redemption_settlement_ledger ADD COLUMN reconciliation_status ENUM('pending','not_applicable','reconciled','in_review','disputed','voided','reversed') NOT NULL DEFAULT 'not_applicable' AFTER settlement_status",'SELECT 1'); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c:='campaign_id'; SET @s:=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,'ALTER TABLE redemption_settlement_ledger ADD COLUMN campaign_id BIGINT UNSIGNED NULL AFTER reconciliation_status','SELECT 1'); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c:='source_campaign_id'; SET @s:=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,'ALTER TABLE redemption_settlement_ledger ADD COLUMN source_campaign_id BIGINT UNSIGNED NULL AFTER campaign_id','SELECT 1'); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c:='order_id'; SET @s:=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,'ALTER TABLE redemption_settlement_ledger ADD COLUMN order_id BIGINT UNSIGNED NULL AFTER source_campaign_id','SELECT 1'); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c:='payment_intent_id'; SET @s:=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,'ALTER TABLE redemption_settlement_ledger ADD COLUMN payment_intent_id VARCHAR(190) NULL AFTER order_id','SELECT 1'); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c:='receipt_id'; SET @s:=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,'ALTER TABLE redemption_settlement_ledger ADD COLUMN receipt_id BIGINT UNSIGNED NULL AFTER payment_intent_id','SELECT 1'); PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE redemption_settlement_ledger
SET value_type=IF(value_type IS NULL OR value_type='','promotional_reward',value_type), cash_movement=IF(cash_movement IS NULL OR cash_movement='','none',cash_movement), face_value_cents=IF(face_value_cents=0,amount_cents,face_value_cents), customer_paid_cents=IF(cash_movement='none',0,customer_paid_cents), payout_due_cents=IF(cash_movement='platform_collected',GREATEST(customer_paid_cents-platform_fee_cents,0),0), merchant_net_cents=IF(cash_movement='platform_collected',GREATEST(customer_paid_cents-platform_fee_cents,0),0), settlement_status=IF(cash_movement='platform_collected' AND GREATEST(customer_paid_cents-platform_fee_cents,0)>0,settlement_status,IF(settlement_status='pending','settled',settlement_status)), reconciliation_status=IF(cash_movement='platform_collected' AND GREATEST(customer_paid_cents-platform_fee_cents,0)>0,'pending','not_applicable')
WHERE id>0;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at) VALUES ('stage_18ag_subscription_billing_value_reconciliation','Canonical package billing and redemption value reconciliation.',NULL,NOW()) ON DUPLICATE KEY UPDATE description=VALUES(description);
