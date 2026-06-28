-- Stage 12K Reward Media Packs
-- Adds audio/media pack reward template types. Media pack assets are stored in reward_templates.metadata_json.

ALTER TABLE reward_templates
  MODIFY reward_type ENUM('dollar_credit','free_item','discount','perk_upgrade','event_reward','audio_pack','media_pack','custom') NOT NULL DEFAULT 'custom';
