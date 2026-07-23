-- Creator Campaign MCP Bounded Automation Playbooks v13D
-- Adds six grantable draft scopes for manual, review-artifact-only playbook runs.
-- No scope is granted automatically. No scheduler, canonical action, payment, or external effect is enabled.

START TRANSACTION;

INSERT INTO mcp_scope_catalog
(scope_key,display_name,description,operation_class,active,grantable,created_at,updated_at)
VALUES
('creator_campaign_playbooks:campaign_preparation','Run Campaign Preparation Playbook','Run an owner-configured campaign-preparation playbook that creates a non-convertible review artifact.','draft',1,1,NOW(),NOW()),
('creator_campaign_playbooks:application_review','Run Application Review Playbook','Run an owner-configured Creator application review playbook that drafts a recommendation without deciding the application.','draft',1,1,NOW(),NOW()),
('creator_campaign_playbooks:content_review','Run Content Review Playbook','Run an owner-configured content review playbook that drafts feedback without approving, rejecting, or requesting revision.','draft',1,1,NOW(),NOW()),
('creator_campaign_playbooks:campaign_health','Run Campaign Health Playbook','Run an owner-configured campaign health playbook that creates a reviewable risk and recommendation report.','draft',1,1,NOW(),NOW()),
('creator_campaign_playbooks:earnings_review','Run Earnings Review Playbook','Run an owner-configured earnings review playbook that drafts a recommendation without changing earnings, payouts, or disputes.','draft',1,1,NOW(),NOW()),
('creator_campaign_playbooks:creator_outreach','Run Creator Outreach Playbook','Run an owner-configured Creator outreach playbook that drafts an eligible invitation list and messages without sending them.','draft',1,1,NOW(),NOW())
ON DUPLICATE KEY UPDATE
  display_name=VALUES(display_name),
  description=VALUES(description),
  operation_class='draft',
  active=1,
  grantable=1,
  updated_at=NOW();

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  '20260722_creator_campaign_mcp_bounded_playbooks_v13d_single_install',
  'Creator Campaign MCP Phase 13D six manual bounded playbook scopes; review artifacts only and no autonomous execution.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
