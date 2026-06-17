CREATE TABLE IF NOT EXISTS microgift_demand_commitment_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  microgift_instance_id BIGINT UNSIGNED NOT NULL,
  purchase_signal_id BIGINT UNSIGNED NOT NULL,
  lifecycle_state ENUM('purchased','sent','claimed','redeemed','cancelled','refunded','expired','replaced') NOT NULL DEFAULT 'purchased',
  expected_from_source VARCHAR(80) NOT NULL DEFAULT 'issued_at',
  reconciled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgift_demand_link_public_id (public_id),
  UNIQUE KEY uq_microgift_demand_link_instance (microgift_instance_id),
  UNIQUE KEY uq_microgift_demand_link_signal (purchase_signal_id),
  KEY idx_microgift_demand_link_state (lifecycle_state,reconciled_at),
  CONSTRAINT fk_microgift_demand_link_instance FOREIGN KEY (microgift_instance_id) REFERENCES microgift_instances(id) ON DELETE RESTRICT,
  CONSTRAINT fk_microgift_demand_link_signal FOREIGN KEY (purchase_signal_id) REFERENCES purchase_signal_records(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @mg_has_psr_source_index := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='purchase_signal_records' AND INDEX_NAME='idx_psr_source_reference');
SET @mg_sql := IF(@mg_has_psr_source_index=0,'ALTER TABLE purchase_signal_records ADD KEY idx_psr_source_reference (source_type,source_reference)','SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_psr_merchant_type_index := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='purchase_signal_records' AND INDEX_NAME='idx_psr_merchant_type_status_window');
SET @mg_sql := IF(@mg_has_psr_merchant_type_index=0,'ALTER TABLE purchase_signal_records ADD KEY idx_psr_merchant_type_status_window (merchant_user_id,signal_type,status,expected_from,expected_to)','SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('demand.commitments.view_own','View prepaid demand commitments','View demand commitments derived from purchased and received Microgifts.',NOW()),
('demand.commitments.reconcile','Reconcile prepaid demand commitments','Reconcile Microgift lifecycle state into canonical committed-demand records.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='demand.commitments.view_own'
WHERE r.slug IN ('customer','member','merchant','creator','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='demand.commitments.reconcile'
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_15c_prepaid_demand_commitments','Derive one canonical committed-demand PSR from each eligible prepaid Microgift lifecycle.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
