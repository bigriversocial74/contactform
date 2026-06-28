-- ------------------------------------------------------------
-- Stage 20C Store Canvas Trigger Analytics + Stamp Ledger Hook
-- ------------------------------------------------------------
-- Purpose:
--   Registers the stamp debit action used by automated Store Canvas trigger
--   messages and marks the analytics/stamp-ledger phase in schema migrations.
-- ------------------------------------------------------------

INSERT IGNORE INTO stamp_debit_actions
(public_id,action_key,label,channel,scope,stamp_value,description,status,created_at,updated_at)
VALUES
(UUID(),'store_canvas_auto_message_send','Store Canvas automated message','Store Canvas','Automation',1,'Automated merchant message sent when a customer avatar crosses a Store Canvas trigger zone.','active',NOW(),NOW());

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_20c_store_canvas_trigger_analytics_stamps','Store Canvas trigger analytics stamp debit action for automated trigger messaging.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
