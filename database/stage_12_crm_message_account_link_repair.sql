-- Stage 12 CRM message account link repair
-- Purpose: backfill campaign contact account links and canonical message thread participants
-- for CRM contacts that were originally captured as no-account contacts but now have
-- a Microgifter user account with the same email address.

UPDATE campaign_contacts cc
JOIN users u ON LOWER(u.email)=LOWER(cc.email)
SET cc.user_id=u.id, cc.updated_at=NOW()
WHERE cc.user_id IS NULL;

INSERT IGNORE INTO message_thread_participants (thread_id,user_id,joined_at,last_read_at)
SELECT mt.id, cc.user_id, NOW(), NULL
FROM message_threads mt
JOIN campaign_contacts cc ON cc.public_id=SUBSTRING_INDEX(SUBSTRING(mt.conversation_key,5),':',1)
WHERE mt.conversation_key LIKE 'crm:%'
  AND cc.user_id IS NOT NULL;

UPDATE messages m
JOIN message_threads mt ON mt.id=m.thread_id
JOIN campaign_contacts cc ON cc.public_id=SUBSTRING_INDEX(SUBSTRING(mt.conversation_key,5),':',1)
SET m.recipient_user_id=cc.user_id
WHERE mt.conversation_key LIKE 'crm:%'
  AND m.recipient_user_id IS NULL
  AND cc.user_id IS NOT NULL
  AND m.sender_user_id<>cc.user_id;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_12_crm_message_account_link_repair','Backfill CRM contact account links and message participants for customer-visible merchant CRM messaging.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
