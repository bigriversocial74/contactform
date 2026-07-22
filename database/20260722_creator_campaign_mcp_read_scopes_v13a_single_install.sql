-- Creator Campaign MCP Read Tools v13A
-- Adds only grantable read scopes for the canonical Creator Campaign MCP projections.
-- No write, approval, financial execution, publication, or automation authority is enabled.

START TRANSACTION;

INSERT INTO mcp_scope_catalog
(scope_key,display_name,description,operation_class,active,grantable,created_at,updated_at)
VALUES
('creator_campaigns:read','Read Creator Campaigns','List and read Creator Campaigns visible to the authorized merchant workspace or Creator account.','read',1,1,NOW(),NOW()),
('creator_campaigns_analytics:read','Read Creator Campaign analytics','Read privacy-safe Creator Campaign performance and aggregate finance summaries.','read',1,1,NOW(),NOW()),
('creator_campaign_applications:read','Read Creator Campaign applications','Read campaign applications within the authorized merchant workspace or the Creator account that submitted them.','read',1,1,NOW(),NOW()),
('creator_campaign_participants:read','Read Creator Campaign participants','Read campaign participant records without private account contact fields.','read',1,1,NOW(),NOW()),
('creator_campaign_deliverables:read','Read Creator Campaign deliverables','Read campaign deliverable definitions or the authenticated Creator account assignments.','read',1,1,NOW(),NOW()),
('creator_campaign_submissions:read','Read Creator Campaign submissions','Read authorized content submissions and review state without storage internals.','read',1,1,NOW(),NOW()),
('creator_campaign_tracking:read','Read Creator Campaign tracking','Read authorized tracking sources and aggregate accepted activity without anonymous tracking hashes.','read',1,1,NOW(),NOW()),
('creator_campaign_attributions:read','Read Creator Campaign attribution','Read canonical attribution decisions without customer identity or anonymous tracking hashes.','read',1,1,NOW(),NOW()),
('creator_campaign_earnings:read','Read Creator Campaign earnings','Read authorized append-only earning events in integer minor currency units.','read',1,1,NOW(),NOW()),
('creator_campaign_payouts:read','Read Creator Campaign payouts','Read authorized payout records without provider references, banking details, or execution authority.','read',1,1,NOW(),NOW()),
('creator_campaign_disputes:read','Read Creator Campaign disputes','Read disputes within the authorized merchant workspace or the Creator account records.','read',1,1,NOW(),NOW())
ON DUPLICATE KEY UPDATE
  display_name=VALUES(display_name),
  description=VALUES(description),
  operation_class='read',
  active=1,
  grantable=1,
  updated_at=NOW();

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  '20260722_creator_campaign_mcp_read_scopes_v13a_single_install',
  'Creator Campaign MCP Phase 13A grantable read scopes; no canonical write or automation authority.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
