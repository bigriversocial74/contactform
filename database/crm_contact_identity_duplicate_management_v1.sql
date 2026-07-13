-- CRM Contact Identity & Duplicate Management v1
-- Adds non-destructive merge lineage and immutable audit records.

SET @db := DATABASE();

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='merchant_crm_contacts' AND COLUMN_NAME='merged_into_contact_id') = 0,
  'ALTER TABLE merchant_crm_contacts ADD COLUMN merged_into_contact_id BIGINT UNSIGNED NULL AFTER crm_status',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='merchant_crm_contacts' AND COLUMN_NAME='merged_at') = 0,
  'ALTER TABLE merchant_crm_contacts ADD COLUMN merged_at DATETIME NULL AFTER merged_into_contact_id',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='merchant_crm_contacts' AND COLUMN_NAME='merge_reason') = 0,
  'ALTER TABLE merchant_crm_contacts ADD COLUMN merge_reason VARCHAR(500) NULL AFTER merged_at',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='merchant_crm_contacts' AND INDEX_NAME='idx_merchant_crm_contacts_merge_state') = 0,
  'ALTER TABLE merchant_crm_contacts ADD KEY idx_merchant_crm_contacts_merge_state (merchant_user_id,merged_into_contact_id,crm_status,updated_at)',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='merchant_crm_contacts' AND CONSTRAINT_NAME='fk_merchant_crm_contacts_merged_into') = 0,
  'ALTER TABLE merchant_crm_contacts ADD CONSTRAINT fk_merchant_crm_contacts_merged_into FOREIGN KEY (merged_into_contact_id) REFERENCES merchant_crm_contacts(id) ON DELETE SET NULL',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS merchant_crm_contact_merges (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merge_batch_public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  canonical_contact_id BIGINT UNSIGNED NOT NULL,
  source_contact_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  match_type VARCHAR(80) NOT NULL,
  confidence_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  reason VARCHAR(500) NULL,
  canonical_before_json JSON NULL,
  source_before_json JSON NULL,
  moved_counts_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_crm_contact_merges_public_id (public_id),
  UNIQUE KEY uq_merchant_crm_contact_merges_source (source_contact_id),
  KEY idx_merchant_crm_contact_merges_batch (merchant_user_id,merge_batch_public_id,created_at),
  KEY idx_merchant_crm_contact_merges_canonical (canonical_contact_id,created_at),
  CONSTRAINT fk_merchant_crm_contact_merges_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_crm_contact_merges_canonical FOREIGN KEY (canonical_contact_id) REFERENCES merchant_crm_contacts(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_crm_contact_merges_source FOREIGN KEY (source_contact_id) REFERENCES merchant_crm_contacts(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_crm_contact_merges_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
