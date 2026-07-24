-- Microgifter Investor Module Audit Hardening v1
-- Additive migration applied after Investor Governance v5.
-- Adds explicit consent visibility, maker/checker financial provenance,
-- publication revision history, and separate investor-relations publishing authority.

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
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@mg_schema AND TABLE_NAME='investment_written_consents' AND COLUMN_NAME='investor_visible') = 0,
  'ALTER TABLE investment_written_consents ADD COLUMN investor_visible TINYINT(1) NOT NULL DEFAULT 0 AFTER internal_notes',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@mg_schema AND TABLE_NAME='investment_written_consents' AND INDEX_NAME='idx_investment_written_consent_portal') = 0,
  'ALTER TABLE investment_written_consents ADD KEY idx_investment_written_consent_portal (round_id,status,investor_visible,effective_at)',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@mg_schema AND TABLE_NAME='investor_closing_records' AND COLUMN_NAME='signed_verification_source') = 0,
  'ALTER TABLE investor_closing_records ADD COLUMN signed_verification_source ENUM(''unverified'',''maker_checker'') NOT NULL DEFAULT ''unverified'' AFTER signed_amount_cents',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@mg_schema AND TABLE_NAME='investor_closing_records' AND COLUMN_NAME='funding_verification_source') = 0,
  'ALTER TABLE investor_closing_records ADD COLUMN funding_verification_source ENUM(''unverified'',''maker_checker'') NOT NULL DEFAULT ''unverified'' AFTER verified_funded_cents',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@mg_schema AND TABLE_NAME='investor_closing_records' AND INDEX_NAME='idx_investor_closing_verified_source') = 0,
  'ALTER TABLE investor_closing_records ADD KEY idx_investor_closing_verified_source (round_id,investor_user_id,signed_verification_source,funding_verification_source,verified_funded_cents)',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@mg_schema AND TABLE_NAME='investment_round_publication' AND COLUMN_NAME='current_version_number') = 0,
  'ALTER TABLE investment_round_publication ADD COLUMN current_version_number INT UNSIGNED NOT NULL DEFAULT 0 AFTER publication_status',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

CREATE TABLE IF NOT EXISTS investment_round_publication_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  round_id BIGINT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  publication_status ENUM('draft','internal_preview','private_preview','published','paused','archived') NOT NULL,
  sections_json JSON NOT NULL,
  founder_update TEXT NULL,
  important_notice TEXT NULL,
  change_reason VARCHAR(500) NOT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_round_publication_version_public (public_id),
  UNIQUE KEY uq_investment_round_publication_version (round_id,version_number),
  KEY idx_investment_round_publication_version_status (round_id,publication_status,created_at),
  CONSTRAINT fk_investment_round_publication_version_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_round_publication_version_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed one immutable baseline version for existing publication records.
INSERT INTO investment_round_publication_versions
(public_id,round_id,version_number,publication_status,sections_json,founder_update,important_notice,change_reason,created_by_user_id,created_at)
SELECT UUID(),p.round_id,1,p.publication_status,p.sections_json,p.founder_update,p.important_notice,'Audit baseline of existing publication state',p.updated_by_user_id,COALESCE(p.updated_at,NOW())
FROM investment_round_publication p
LEFT JOIN investment_round_publication_versions v ON v.round_id=p.round_id
WHERE v.id IS NULL;

UPDATE investment_round_publication p
SET p.current_version_number=(SELECT COALESCE(MAX(v.version_number),0) FROM investment_round_publication_versions v WHERE v.round_id=p.round_id)
WHERE p.current_version_number=0;

-- Backfill financial provenance only when an approved maker/checker decision exists.
UPDATE investor_closing_records cr
INNER JOIN investment_financial_verification_requests vr ON vr.closing_record_id=cr.id AND vr.status='approved' AND vr.verification_type IN ('signed_amount','signed_reversal')
INNER JOIN investment_financial_verification_decisions vd ON vd.request_id=vr.id AND vd.decision='approved'
SET cr.signed_verification_source='maker_checker'
WHERE cr.signed_amount_cents=vr.requested_amount_cents;

UPDATE investor_closing_records cr
INNER JOIN investment_financial_verification_requests vr ON vr.closing_record_id=cr.id AND vr.status='approved' AND vr.verification_type IN ('funded_amount','funded_reversal')
INNER JOIN investment_financial_verification_decisions vd ON vd.request_id=vr.id AND vd.decision='approved'
SET cr.funding_verification_source='maker_checker'
WHERE cr.verified_funded_cents=vr.requested_amount_cents;

-- Reconcile official totals from proven closing records rather than historical manual values.
UPDATE investment_rounds r
SET r.signed_cents=(SELECT COALESCE(SUM(cr.signed_amount_cents),0) FROM investor_closing_records cr WHERE cr.round_id=r.id AND cr.signed_verification_source='maker_checker' AND cr.status NOT IN ('withdrawn','declined')),
    r.funded_cents=(SELECT COALESCE(SUM(cr.verified_funded_cents),0) FROM investor_closing_records cr WHERE cr.round_id=r.id AND cr.funding_verification_source='maker_checker' AND cr.status NOT IN ('withdrawn','declined'));

INSERT IGNORE INTO schema_migrations (migration_key,description,applied_at)
VALUES (
  '20260724_investor_module_audit_hardening_v1',
  'Adds consent visibility, maker-checker signed/funded provenance, publication versions, relations publish separation, and investor module 10/10 audit hardening support.',
  NOW()
);
