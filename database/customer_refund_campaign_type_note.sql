-- Add Customer Refund as an internal campaign type.
-- Required because campaigns.campaign_type is an ENUM.

ALTER TABLE campaigns
  CHANGE campaign_type campaign_type ENUM('newsletter_signup','contest_giveaway','qr_reward_drop','referral_reward','birthday_vip','agent_offer','customer_refund') NOT NULL;
