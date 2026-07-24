-- Microgifter Public Donations + Community Role v1
-- Master additive single-install foundation for Community Donations phases.
-- Safe to import once or reimport on supported MySQL 8 installations.
-- No user receives Community automatically and no financial execution is enabled.

START TRANSACTION;

INSERT INTO roles (slug, name, created_at)
VALUES ('community', 'Community', NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Community is an additional role on the canonical account. Copy the current
-- Customer baseline so a Community-only account keeps ordinary account,
-- wallet, Inbox, send, and claim access without gaining merchant/admin powers.
INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT community_role.id, customer_permissions.permission_id, NOW()
FROM roles community_role
INNER JOIN roles customer_role ON customer_role.slug = 'customer'
INNER JOIN role_permissions customer_permissions ON customer_permissions.role_id = customer_role.id
WHERE community_role.slug = 'community';

COMMIT;

-- Append one ENUM value while preserving all values already installed.
DROP PROCEDURE IF EXISTS mg_public_donations_append_enum_value;
DELIMITER $$
CREATE PROCEDURE mg_public_donations_append_enum_value(
    IN p_table_name VARCHAR(64),
    IN p_column_name VARCHAR(64),
    IN p_enum_value VARCHAR(80)
)
BEGIN
    DECLARE v_data_type VARCHAR(64) DEFAULT NULL;
    DECLARE v_column_type LONGTEXT DEFAULT NULL;
    DECLARE v_is_nullable VARCHAR(3) DEFAULT NULL;
    DECLARE v_default_value TEXT DEFAULT NULL;
    DECLARE v_extra VARCHAR(255) DEFAULT NULL;
    DECLARE v_has_value INT DEFAULT 0;
    DECLARE v_sql LONGTEXT DEFAULT NULL;
    DECLARE v_null_clause VARCHAR(16) DEFAULT '';
    DECLARE v_default_clause LONGTEXT DEFAULT '';
    DECLARE v_extra_clause VARCHAR(255) DEFAULT '';

    SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA,
           CASE WHEN LOCATE(CONCAT('''', p_enum_value, ''''), COLUMN_TYPE) > 0 THEN 1 ELSE 0 END
      INTO v_data_type, v_column_type, v_is_nullable, v_default_value, v_extra, v_has_value
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = p_table_name
       AND COLUMN_NAME = p_column_name
     LIMIT 1;

    IF v_data_type = 'enum' AND v_has_value = 0 THEN
        SET v_column_type = CONCAT(
            LEFT(v_column_type, CHAR_LENGTH(v_column_type) - 1),
            ',''', REPLACE(p_enum_value, '''', ''''''), ''')'
        );
        SET v_null_clause = IF(v_is_nullable = 'YES', 'NULL', 'NOT NULL');
        SET v_default_clause = IF(
            v_default_value IS NULL,
            '',
            CONCAT(' DEFAULT ''', REPLACE(v_default_value, '''', ''''''), '''')
        );
        SET v_extra_clause = IF(v_extra IS NULL OR v_extra = '', '', CONCAT(' ', v_extra));
        SET v_sql = CONCAT(
            'ALTER TABLE `', REPLACE(p_table_name, '`', '``'),
            '` MODIFY COLUMN `', REPLACE(p_column_name, '`', '``'),
            '` ', v_column_type, ' ', v_null_clause, v_default_clause, v_extra_clause
        );
        SET @mg_public_donations_sql = v_sql;
        PREPARE mg_public_donations_stmt FROM @mg_public_donations_sql;
        EXECUTE mg_public_donations_stmt;
        DEALLOCATE PREPARE mg_public_donations_stmt;
        SET @mg_public_donations_sql = NULL;
    END IF;
END$$
DELIMITER ;

CALL mg_public_donations_append_enum_value('campaigns', 'campaign_type', 'public_donation');
CALL mg_public_donations_append_enum_value('wallet_items', 'source_type', 'public_donation');

DROP PROCEDURE IF EXISTS mg_public_donations_append_enum_value;

CREATE TABLE IF NOT EXISTS campaign_community_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  community_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('active','paused','removed') NOT NULL DEFAULT 'active',
  public_display_status ENUM('pending','approved','declined','revoked') NOT NULL DEFAULT 'pending',
  public_display_decided_at DATETIME NULL,
  public_display_decided_by_user_id BIGINT UNSIGNED NULL,
  added_by_user_id BIGINT UNSIGNED NOT NULL,
  added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reactivated_at DATETIME NULL,
  paused_at DATETIME NULL,
  removed_at DATETIME NULL,
  last_allocated_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campaign_community_assignments_public_id (public_id),
  UNIQUE KEY uq_campaign_community_assignments_campaign_user (campaign_id, community_user_id),
  KEY idx_campaign_community_assignments_merchant_status (merchant_user_id, status, updated_at),
  KEY idx_campaign_community_assignments_campaign_status (campaign_id, status, updated_at),
  KEY idx_campaign_community_assignments_user_status (community_user_id, status, updated_at),
  KEY idx_campaign_community_assignments_public_display (campaign_id, public_display_status, status),
  CONSTRAINT fk_campaign_community_assignments_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_community_assignments_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_community_assignments_user FOREIGN KEY (community_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_community_assignments_added_by FOREIGN KEY (added_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_community_assignments_display_actor FOREIGN KEY (public_display_decided_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_donation_operations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  reward_template_id BIGINT UNSIGNED NOT NULL,
  operation_kind ENUM('allocation','recall') NOT NULL,
  operation_mode ENUM('single','same_quantity','custom_quantity','partial_recall') NOT NULL,
  status ENUM('processing','completed','failed') NOT NULL DEFAULT 'processing',
  idempotency_key VARCHAR(190) NOT NULL,
  request_hash CHAR(64) NOT NULL,
  recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
  requested_quantity INT UNSIGNED NOT NULL DEFAULT 0,
  completed_quantity INT UNSIGNED NOT NULL DEFAULT 0,
  inventory_before INT UNSIGNED NULL,
  inventory_after INT UNSIGNED NULL,
  total_stated_value_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  confirmation_level ENUM('standard','large_operation') NOT NULL DEFAULT 'standard',
  message VARCHAR(1000) NULL,
  internal_note VARCHAR(2000) NULL,
  error_code VARCHAR(120) NULL,
  error_message VARCHAR(1000) NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campaign_donation_operations_public_id (public_id),
  UNIQUE KEY uq_campaign_donation_operations_idempotency (merchant_user_id, idempotency_key),
  KEY idx_campaign_donation_operations_campaign_kind (campaign_id, operation_kind, created_at),
  KEY idx_campaign_donation_operations_reward_date (reward_template_id, created_at),
  KEY idx_campaign_donation_operations_merchant_status (merchant_user_id, status, created_at),
  CONSTRAINT chk_campaign_donation_operations_recipient_count CHECK (recipient_count <= 50),
  CONSTRAINT chk_campaign_donation_operations_requested_quantity CHECK (requested_quantity <= 1000),
  CONSTRAINT chk_campaign_donation_operations_completed_quantity CHECK (completed_quantity <= requested_quantity),
  CONSTRAINT fk_campaign_donation_operations_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_donation_operations_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_donation_operations_reward FOREIGN KEY (reward_template_id) REFERENCES reward_templates(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_donation_operations_actor FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_donation_batches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  operation_id BIGINT UNSIGNED NOT NULL,
  assignment_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  reward_template_id BIGINT UNSIGNED NOT NULL,
  community_user_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  recalled_quantity INT UNSIGNED NOT NULL DEFAULT 0,
  stated_value_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  status ENUM('allocated','partially_recalled','recalled') NOT NULL DEFAULT 'allocated',
  message VARCHAR(1000) NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campaign_donation_batches_public_id (public_id),
  UNIQUE KEY uq_campaign_donation_batches_operation_user (operation_id, community_user_id),
  KEY idx_campaign_donation_batches_campaign_user (campaign_id, community_user_id, created_at),
  KEY idx_campaign_donation_batches_user_status (community_user_id, status, created_at),
  KEY idx_campaign_donation_batches_assignment_date (assignment_id, created_at),
  CONSTRAINT chk_campaign_donation_batches_quantity CHECK (quantity > 0),
  CONSTRAINT chk_campaign_donation_batches_recalled CHECK (recalled_quantity <= quantity),
  CONSTRAINT fk_campaign_donation_batches_operation FOREIGN KEY (operation_id) REFERENCES campaign_donation_operations(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_donation_batches_assignment FOREIGN KEY (assignment_id) REFERENCES campaign_community_assignments(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_donation_batches_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_donation_batches_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_donation_batches_reward FOREIGN KEY (reward_template_id) REFERENCES reward_templates(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_donation_batches_user FOREIGN KEY (community_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_donation_batches_actor FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_donation_rewards (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  operation_id BIGINT UNSIGNED NOT NULL,
  batch_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  reward_template_id BIGINT UNSIGNED NOT NULL,
  original_community_user_id BIGINT UNSIGNED NOT NULL,
  wallet_item_id BIGINT UNSIGNED NOT NULL,
  pppm_item_id BIGINT UNSIGNED NOT NULL,
  microgift_instance_id BIGINT UNSIGNED NOT NULL,
  allocation_sequence INT UNSIGNED NOT NULL,
  reward_title_snapshot VARCHAR(180) NOT NULL,
  value_cents_snapshot INT UNSIGNED NOT NULL DEFAULT 0,
  currency_snapshot CHAR(3) NOT NULL DEFAULT 'USD',
  status ENUM('allocated','recalled') NOT NULL DEFAULT 'allocated',
  allocated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  recalled_at DATETIME NULL,
  recalled_by_user_id BIGINT UNSIGNED NULL,
  recall_reason VARCHAR(500) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campaign_donation_rewards_public_id (public_id),
  UNIQUE KEY uq_campaign_donation_rewards_wallet_item (wallet_item_id),
  UNIQUE KEY uq_campaign_donation_rewards_pppm_item (pppm_item_id),
  UNIQUE KEY uq_campaign_donation_rewards_microgift_instance (microgift_instance_id),
  UNIQUE KEY uq_campaign_donation_rewards_batch_sequence (batch_id, allocation_sequence),
  KEY idx_campaign_donation_rewards_campaign_status (campaign_id, status, allocated_at),
  KEY idx_campaign_donation_rewards_community_status (original_community_user_id, status, allocated_at),
  KEY idx_campaign_donation_rewards_batch_status (batch_id, status),
  KEY idx_campaign_donation_rewards_operation_status (operation_id, status),
  CONSTRAINT fk_campaign_donation_rewards_operation FOREIGN KEY (operation_id) REFERENCES campaign_donation_operations(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_donation_rewards_batch FOREIGN KEY (batch_id) REFERENCES campaign_donation_batches(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_donation_rewards_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_donation_rewards_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_donation_rewards_reward FOREIGN KEY (reward_template_id) REFERENCES reward_templates(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_donation_rewards_community FOREIGN KEY (original_community_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_donation_rewards_wallet FOREIGN KEY (wallet_item_id) REFERENCES wallet_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_donation_rewards_pppm FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_donation_rewards_microgift FOREIGN KEY (microgift_instance_id) REFERENCES microgift_instances(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_donation_rewards_recall_actor FOREIGN KEY (recalled_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
