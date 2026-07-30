-- Creator Campaign Phases 1–15 Production Audit & Repair v1
-- Repairs canonical Creator-model permission coverage and Phase 15 smoke receipt identity.
-- No campaign action, payment-provider action, payout execution, MCP scope, grant, or automation authority is added.

START TRANSACTION;

-- Creator is a user model layered on the canonical customer role. Runtime services still require
-- an active user, active Creator model assignment, active Creator profile, active Creator model
-- context, and object ownership. This backfill only makes the earlier Phase 3–8 permission rows
-- consistent with the Phase 9+ production identity model.
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
INNER JOIN permissions p ON p.slug IN (
  'creator.campaigns.discover',
  'creator.campaign_applications.manage_own',
  'creator.campaign_invitations.respond_own',
  'creator.campaign_participants.view_own',
  'creator.campaign_agreements.view_own',
  'creator.campaign_agreements.respond_own',
  'creator.campaign_deliverables.view_own',
  'creator.campaign_submissions.manage_own',
  'creator.campaign_tracking.view_own',
  'creator.campaign_tracking.manage_own',
  'creator.campaign_earnings.view_own',
  'creator.campaign_payouts.view_own',
  'creator.campaign_disputes.manage_own'
)
WHERE r.slug='customer';

-- A failed and passing smoke test for the same launch-state fingerprint are separate evidence.
-- Replace the original three-column uniqueness rule with a status-aware four-column rule.
SET @cc_audit_receipt_index_columns := (
  SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',')
  FROM information_schema.statistics
  WHERE table_schema=DATABASE()
    AND table_name='creator_campaign_onboarding_receipts'
    AND index_name='uq_creator_campaign_onboarding_receipt_snapshot'
);
SET @cc_audit_drop_receipt_index_sql := IF(
  @cc_audit_receipt_index_columns IS NOT NULL
  AND @cc_audit_receipt_index_columns <> 'onboarding_id,receipt_type,snapshot_hash,status',
  'ALTER TABLE creator_campaign_onboarding_receipts DROP INDEX uq_creator_campaign_onboarding_receipt_snapshot',
  'SELECT 1'
);
PREPARE cc_audit_drop_receipt_index_stmt FROM @cc_audit_drop_receipt_index_sql;
EXECUTE cc_audit_drop_receipt_index_stmt;
DEALLOCATE PREPARE cc_audit_drop_receipt_index_stmt;

SET @cc_audit_receipt_index_exists := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema=DATABASE()
    AND table_name='creator_campaign_onboarding_receipts'
    AND index_name='uq_creator_campaign_onboarding_receipt_snapshot'
);
SET @cc_audit_add_receipt_index_sql := IF(
  @cc_audit_receipt_index_exists=0,
  'ALTER TABLE creator_campaign_onboarding_receipts ADD UNIQUE KEY uq_creator_campaign_onboarding_receipt_snapshot (onboarding_id,receipt_type,snapshot_hash,status)',
  'SELECT 1'
);
PREPARE cc_audit_add_receipt_index_stmt FROM @cc_audit_add_receipt_index_sql;
EXECUTE cc_audit_add_receipt_index_stmt;
DEALLOCATE PREPARE cc_audit_add_receipt_index_stmt;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  '20260723_creator_campaign_phases_1_15_production_audit_repair_v1',
  'Backfill canonical customer-role Creator permissions and make Phase 15 smoke-test receipt identity status-aware.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
