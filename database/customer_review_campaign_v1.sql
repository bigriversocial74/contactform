-- Customer Review Campaign + Profile Review Module v1
-- Import after database/campaign_type_enum_foundation_20260709.sql.
-- Adds the CUSTOMER REVIEW campaign type, review storage, and wallet/PPPM reward source values.

ALTER TABLE campaigns
  MODIFY campaign_type ENUM(
    'newsletter_signup',
    'contest_giveaway',
    'qr_reward_drop',
    'referral_reward',
    'birthday_vip',
    'agent_offer',
    'survey_feedback_reward',
    'check_in_reward',
    'instant_win_reward',
    'stamp_card_reward',
    'rsvp_event_reward',
    'watch_video_reward',
    'listen_music_reward',
    'customer_review',
    'customer_refund'
  ) NOT NULL;

ALTER TABLE campaign_contacts
  MODIFY source ENUM(
    'newsletter_signup',
    'contest_entry',
    'qr_scan',
    'referral',
    'birthday_vip',
    'agent_discovery',
    'survey_feedback',
    'check_in_reward',
    'instant_win_reward',
    'stamp_card_reward',
    'rsvp_event_reward',
    'watch_video_reward',
    'listen_music_reward',
    'customer_review',
    'customer_refund',
    'manual',
    'api_issue'
  ) NOT NULL DEFAULT 'newsletter_signup';

ALTER TABLE wallet_items
  MODIFY source_type ENUM(
    'purchase',
    'manual_send',
    'newsletter_signup',
    'contest_entry',
    'contest_winner',
    'qr_scan',
    'referral',
    'birthday_vip',
    'agent_discovery',
    'survey_feedback',
    'survey_feedback_reward',
    'check_in_reward',
    'instant_win_reward',
    'stamp_card_reward',
    'rsvp_event_reward',
    'watch_video_reward',
    'listen_music_reward',
    'customer_review',
    'customer_refund',
    'api_issue'
  ) NOT NULL;

CREATE TABLE IF NOT EXISTS customer_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  profile_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  reviewer_user_id BIGINT UNSIGNED NOT NULL,
  contact_id BIGINT UNSIGNED NULL,
  wallet_item_id BIGINT UNSIGNED NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  reviewer_name VARCHAR(180) NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  review_title VARCHAR(180) NULL,
  review_body TEXT NOT NULL,
  status ENUM('pending','published','hidden','removed') NOT NULL DEFAULT 'published',
  metadata_json JSON NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customer_reviews_public_id (public_id),
  UNIQUE KEY uq_customer_reviews_idempotency (reviewer_user_id,idempotency_key),
  KEY idx_customer_reviews_profile_status_time (profile_id,status,submitted_at),
  KEY idx_customer_reviews_merchant_status_time (merchant_user_id,status,submitted_at),
  KEY idx_customer_reviews_campaign_reviewer_time (campaign_id,reviewer_user_id,submitted_at),
  KEY idx_customer_reviews_contact (contact_id),
  KEY idx_customer_reviews_wallet (wallet_item_id),
  CONSTRAINT chk_customer_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
