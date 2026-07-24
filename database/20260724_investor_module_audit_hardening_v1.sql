-- Microgifter Investor Module Audit Hardening v1
-- Additive migration applied after Investor Governance v5.
-- Adds explicit portal visibility for executed governance consents.

SET @mg_schema := DATABASE();

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

INSERT IGNORE INTO schema_migrations (migration_key,description,applied_at)
VALUES (
  '20260724_investor_module_audit_hardening_v1',
  'Adds explicit investor-portal visibility control for executed written consents and supports the investor module 10/10 audit hardening layer.',
  NOW()
);
