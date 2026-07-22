-- Creator Campaign MCP Draft and Proposal Tools v13B
-- Adds only grantable draft scopes for review-only Creator Campaign proposals.
-- Approval does not create native records, publish, send, schedule, approve, pay, or execute external effects.

START TRANSACTION;

INSERT INTO mcp_scope_catalog
(scope_key,display_name,description,operation_class,active,grantable,created_at,updated_at)
VALUES
('creator_campaigns:draft','Draft Creator Campaigns','Create or update review-only Creator Campaign proposals without creating or changing a native campaign.','draft',1,1,NOW(),NOW()),
('creator_campaign_products:draft','Propose Creator Campaign products','Propose campaign product relationships without changing native catalog links.','draft',1,1,NOW(),NOW()),
('creator_campaign_eligibility:draft','Propose Creator eligibility','Propose Creator eligibility rules without accepting or declining participants.','draft',1,1,NOW(),NOW()),
('creator_campaign_deliverables:draft','Propose Creator deliverables','Propose campaign deliverables without assigning work or changing submissions.','draft',1,1,NOW(),NOW()),
('creator_campaign_compensation:draft','Propose Creator compensation','Propose compensation terms without activating rules, creating earnings, or moving money.','draft',1,1,NOW(),NOW()),
('creator_campaign_attribution:draft','Propose Creator attribution','Propose attribution settings without changing sources, events, or attribution decisions.','draft',1,1,NOW(),NOW()),
('creator_campaign_budget:draft','Propose Creator Campaign budgets','Propose campaign budgets without funding, reserving, committing, or spending money.','draft',1,1,NOW(),NOW()),
('creator_campaign_rights:draft','Propose Creator content rights','Propose content-rights language without creating or changing an agreement.','draft',1,1,NOW(),NOW()),
('creator_campaign_terms:draft','Propose Creator Campaign terms','Propose campaign terms without creating an agreement version or changing accepted terms.','draft',1,1,NOW(),NOW()),
('creator_campaign_invitations:draft','Draft Creator invitations','Draft Creator invitations without sending them or creating participants.','draft',1,1,NOW(),NOW()),
('creator_campaign_messages:draft','Draft Creator Campaign messages','Draft campaign messages without sending or scheduling them.','draft',1,1,NOW(),NOW()),
('creator_campaign_submission_feedback:draft','Draft Creator submission feedback','Draft submission feedback without approving, rejecting, or requesting a revision.','draft',1,1,NOW(),NOW())
ON DUPLICATE KEY UPDATE
  display_name=VALUES(display_name),
  description=VALUES(description),
  operation_class='draft',
  active=1,
  grantable=1,
  updated_at=NOW();

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  '20260722_creator_campaign_mcp_draft_scopes_v13b_single_install',
  'Creator Campaign MCP Phase 13B grantable review-only draft scopes; no native conversion or canonical action authority.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
