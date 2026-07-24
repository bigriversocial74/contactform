-- Microgifter Investor Module Audit Hardening v1
-- Additive migration applied after Investor Governance v5.
-- Adds explicit consent visibility, maker/checker funding provenance,
-- and separate investor-relations publishing authority.

SET @mg_schema := DATABASE();

INSERT INTO permissions (slug,name,created_at) VALUES
('admin.investment.relations.publish','Publish approved post-investment reports and investor-visible actuals',NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug='admin.investment.relations.publish'
WHERE r.slug IN ('admin','super_admin');

SET @mg_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=@mg_schema
     AND TABLE_NAME='investment_written_consents'
     AND COLUMN_NAME='investor_visible') = 0,
  'ALTER TABLE investment_written_consents ADD COLUMN investor_visible TINYINT(1) NOT NULL DEFAULT 0 AFTER internal_notes',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA=@mg_schema
     AND TABLE_NAME='investment_written_consents'
     AND INDEX_NAME='idx_investment_written_consent_portal') = 0,
  'ALTER TABLE investment_written_consents ADD KEY idx_investment_written_consent_portal (round_id,status,investor_visible,effective_at)',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=@mg_schema
     AND TABLE_NAME='investor_closing_records'
     AND COLUMN_NAME='funding_verification_source') = 0,
  'ALTER TABLE investor_closing_records ADD COLUMN funding_verification_source ENUM(''unverified'',''maker_checker'') NOT NULL DEFAULT ''unverified'' AFTER verified_funded_cents',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA=@mg_schema
     AND TABLE_NAME='investor_closing_records'
     AND INDEX_NAME='idx_investor_closing_verified_source') = 0,
  'ALTER TABLE investor_closing_records ADD KEY idx_investor_closing_verified_source (round_id,investor_user_id,funding_verification_source,verified_funded_cents)',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

-- Backfill provenance only when an approved maker/checker funded decision exists.
UPDATE investor_closing_records cr
INNER JOIN investment_financial_verification_requests vr
  ON vr.closing_record_id=cr.id
 AND vr.status='approved'
 AND vr.verification_type IN ('funded_amount','funded_reversal')
INNER JOIN investment_financial_verification_decisions vd
  ON vd.request_id=vr.id
 AND vd.decision='approved'
SET cr.funding_verification_source='maker_checker'
WHERE cr.verified_funded_cents=vr.requested_amount_cents;

INSERT IGNORE INTO schema_migrations (migration_key,description,applied_at)
VALUES (
  '20260724_investor_module_audit_hardening_v1',
  'Adds explicit investor-portal consent visibility, maker-checker funding provenance, relations publish separation, and investor module 10/10 audit hardening support.',
  NOW()
);
