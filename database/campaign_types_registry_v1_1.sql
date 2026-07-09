-- Campaign Types v1.1 — Registry + UI Cleanup
-- Adds the internal customer_refund campaign type and aligns CRM / wallet source enums
-- with the centralized campaign type registry.

ALTER TABLE campaigns
  MODIFY campaign_type ENUM(
    'newsletter_signup',
    'contest_giveaway',
    'qr_reward_drop',
    'referral_reward',
    'birthday_vip',
    'agent_offer',
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
    'customer_refund',
    'api_issue'
  ) NOT NULL;
