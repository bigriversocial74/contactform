CREATE TABLE IF NOT EXISTS distribution_eligibility_decisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  program_id BIGINT UNSIGNED NOT NULL,
  recipient_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(32) NULL,
  to_status VARCHAR(32) NOT NULL,
  reason VARCHAR(255) NULL,
  rule_snapshot_json JSON NULL,
  decided_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_distribution_eligibility_decisions_public_id (public_id),
  KEY idx_distribution_eligibility_decisions_program (program_id,created_at,id),
  CONSTRAINT fk_distribution_eligibility_decisions_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_distribution_eligibility_decisions_program FOREIGN KEY (program_id) REFERENCES distribution_programs(id) ON DELETE RESTRICT,
  CONSTRAINT fk_distribution_eligibility_decisions_recipient FOREIGN KEY (recipient_id) REFERENCES distribution_recipients(id) ON DELETE RESTRICT,
  CONSTRAINT fk_distribution_eligibility_decisions_user FOREIGN KEY (decided_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS distribution_assignment_batches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  program_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  allocation_method VARCHAR(32) NOT NULL DEFAULT 'batch',
  status VARCHAR(32) NOT NULL DEFAULT 'draft',
  requested_count INT UNSIGNED NOT NULL DEFAULT 0,
  allocated_count INT UNSIGNED NOT NULL DEFAULT 0,
  failed_count INT UNSIGNED NOT NULL DEFAULT 0,
  selection_proof_json JSON NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_distribution_assignment_batches_public_id (public_id),
  KEY idx_distribution_assignment_batches_program (program_id,status,created_at),
  CONSTRAINT fk_distribution_assignment_batches_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_distribution_assignment_batches_program FOREIGN KEY (program_id) REFERENCES distribution_programs(id) ON DELETE RESTRICT,
  CONSTRAINT fk_distribution_assignment_batches_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS distribution_assignment_batch_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  batch_id BIGINT UNSIGNED NOT NULL,
  recipient_id BIGINT UNSIGNED NOT NULL,
  program_product_id BIGINT UNSIGNED NOT NULL,
  allocation_id BIGINT UNSIGNED NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  status VARCHAR(32) NOT NULL DEFAULT 'pending',
  failure_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_distribution_assignment_batch_item (batch_id,recipient_id,program_product_id),
  CONSTRAINT fk_distribution_assignment_batch_items_batch FOREIGN KEY (batch_id) REFERENCES distribution_assignment_batches(id) ON DELETE CASCADE,
  CONSTRAINT fk_distribution_assignment_batch_items_recipient FOREIGN KEY (recipient_id) REFERENCES distribution_recipients(id) ON DELETE RESTRICT,
  CONSTRAINT fk_distribution_assignment_batch_items_product FOREIGN KEY (program_product_id) REFERENCES distribution_program_products(id) ON DELETE RESTRICT,
  CONSTRAINT fk_distribution_assignment_batch_items_allocation FOREIGN KEY (allocation_id) REFERENCES distribution_allocations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.distribution.view','View merchant distribution workspace','View merchant campaign, eligibility, allocation, and source operations.',NOW()),
('merchant.distribution.eligibility.manage','Manage distribution eligibility','Approve, reject, and disqualify campaign recipients.',NOW()),
('merchant.distribution.assignments.manage','Manage distribution assignments','Create and operate assignment batches.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('merchant.distribution.view','merchant.distribution.eligibility.manage','merchant.distribution.assignments.manage')
WHERE r.slug IN ('merchant','admin','super_admin');
