-- Campaign Type Enum Foundation Repair — 2026-07-09
-- Aligns database ENUM values with includes/campaign-types.php so all registered
-- merchant campaign types can be saved and issued without falling into the generic
-- "Unable to save campaign" catch block.
--
-- Import after database/listen_music_reward_v1_5.sql.

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
    'customer_refund',
    'api_issue'
  ) NOT NULL;
