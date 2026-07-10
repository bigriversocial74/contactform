-- Store Canvas Merchant-Customer Behavior Memory & Predictive Intelligence v1
-- Durable, merchant-scoped behavioral profile snapshots derived from canonical CRM,
-- Store Canvas journey, campaign, Wallet, claim, redemption, and interaction history.
-- This table stores transparent derived memory only; source events remain authoritative.

CREATE TABLE IF NOT EXISTS mg_merchant_customer_behavior_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  customer_user_id BIGINT UNSIGNED NOT NULL,
  relationship_stage VARCHAR(32) NOT NULL DEFAULT 'new',
  dominant_pattern VARCHAR(48) NOT NULL DEFAULT 'early_signal',
  greeting_mode VARCHAR(48) NOT NULL DEFAULT 'first_visit',
  movement_mode VARCHAR(48) NOT NULL DEFAULT 'explore',
  follow_state VARCHAR(32) NOT NULL DEFAULT 'observe',
  release_state VARCHAR(32) NOT NULL DEFAULT 'hold',
  return_7d_probability DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  campaign_engagement_probability DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  reward_claim_probability DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  reward_redeem_probability DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  inactivity_risk_probability DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  confidence_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  sample_size INT UNSIGNED NOT NULL DEFAULT 0,
  memory_summary VARCHAR(500) NOT NULL DEFAULT '',
  evidence_json LONGTEXT NULL,
  last_event_at DATETIME NULL,
  last_calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_behavior_profile_public (public_id),
  UNIQUE KEY uq_mg_behavior_profile_pair (merchant_user_id, customer_user_id),
  KEY idx_mg_behavior_profile_stage (merchant_user_id, relationship_stage, updated_at),
  KEY idx_mg_behavior_profile_follow (merchant_user_id, follow_state, updated_at),
  KEY idx_mg_behavior_profile_risk (merchant_user_id, inactivity_risk_probability, updated_at),
  KEY idx_mg_behavior_profile_customer (customer_user_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
