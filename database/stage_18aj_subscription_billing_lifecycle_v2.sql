-- Subscription Checkout, Stripe Lifecycle, and Billing Portal v2
-- Safe to re-run.

SET @t := 'platform_subscription_packages';
SET @c := 'stripe_monthly_price_id_test';
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,
  'ALTER TABLE platform_subscription_packages ADD COLUMN stripe_monthly_price_id_test VARCHAR(190) NULL AFTER stripe_price_id_live', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c := 'stripe_monthly_price_id_live';
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,
  'ALTER TABLE platform_subscription_packages ADD COLUMN stripe_monthly_price_id_live VARCHAR(190) NULL AFTER stripe_monthly_price_id_test', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c := 'stripe_yearly_price_id_test';
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,
  'ALTER TABLE platform_subscription_packages ADD COLUMN stripe_yearly_price_id_test VARCHAR(190) NULL AFTER stripe_monthly_price_id_live', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c := 'stripe_yearly_price_id_live';
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,
  'ALTER TABLE platform_subscription_packages ADD COLUMN stripe_yearly_price_id_live VARCHAR(190) NULL AFTER stripe_yearly_price_id_test', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE platform_subscription_packages
SET stripe_monthly_price_id_test = COALESCE(NULLIF(stripe_monthly_price_id_test,''), NULLIF(stripe_price_id_test,'')),
    stripe_monthly_price_id_live = COALESCE(NULLIF(stripe_monthly_price_id_live,''), NULLIF(stripe_price_id_live,''))
WHERE id > 0;

SET @t := 'platform_account_subscriptions';
SET @c := 'provider_schedule_id';
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,
  'ALTER TABLE platform_account_subscriptions ADD COLUMN provider_schedule_id VARCHAR(190) NULL AFTER provider_subscription_id', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c := 'scheduled_package_id';
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,
  'ALTER TABLE platform_account_subscriptions ADD COLUMN scheduled_package_id VARCHAR(80) NULL AFTER cancel_at_period_end', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c := 'scheduled_billing_cycle';
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,
  "ALTER TABLE platform_account_subscriptions ADD COLUMN scheduled_billing_cycle ENUM('month','year') NULL AFTER scheduled_package_id", 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c := 'scheduled_effective_at';
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,
  'ALTER TABLE platform_account_subscriptions ADD COLUMN scheduled_effective_at DATETIME NULL AFTER scheduled_billing_cycle', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c := 'provider_latest_invoice_id';
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,
  'ALTER TABLE platform_account_subscriptions ADD COLUMN provider_latest_invoice_id VARCHAR(190) NULL AFTER provider_price_id', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c := 'provider_latest_invoice_status';
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,
  'ALTER TABLE platform_account_subscriptions ADD COLUMN provider_latest_invoice_status VARCHAR(80) NULL AFTER provider_latest_invoice_id', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c := 'provider_latest_invoice_url';
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,
  'ALTER TABLE platform_account_subscriptions ADD COLUMN provider_latest_invoice_url VARCHAR(600) NULL AFTER provider_latest_invoice_status', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c := 'provider_latest_invoice_pdf';
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,
  'ALTER TABLE platform_account_subscriptions ADD COLUMN provider_latest_invoice_pdf VARCHAR(600) NULL AFTER provider_latest_invoice_url', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c := 'provider_latest_payment_intent_id';
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,
  'ALTER TABLE platform_account_subscriptions ADD COLUMN provider_latest_payment_intent_id VARCHAR(190) NULL AFTER provider_latest_invoice_pdf', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c := 'last_payment_at';
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,
  'ALTER TABLE platform_account_subscriptions ADD COLUMN last_payment_at DATETIME NULL AFTER next_billing_at', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c := 'last_payment_failed_at';
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,
  'ALTER TABLE platform_account_subscriptions ADD COLUMN last_payment_failed_at DATETIME NULL AFTER last_payment_at', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c := 'reactivated_at';
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@t AND COLUMN_NAME=@c)=0,
  'ALTER TABLE platform_account_subscriptions ADD COLUMN reactivated_at DATETIME NULL AFTER canceled_at', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_18aj_subscription_billing_lifecycle_v2','Monthly/yearly Stripe prices, billing portal, subscription schedules, canonical invoice state, and subscription lifecycle v2.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
