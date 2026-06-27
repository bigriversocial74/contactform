-- Stage 12 CRM Reward Invites

CREATE TABLE IF NOT EXISTS crm_reward_invites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  contact_id BIGINT UNSIGNED NOT NULL,
  reward_template_id BIGINT UNSIGNED NOT NULL,
  wallet_item_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  email VARCHAR(255) NOT NULL,
  name VARCHAR(180) NULL,
  status ENUM('sent','linked','delivered','revoked','expired') NOT NULL DEFAULT 'sent',
  note TEXT NULL,
  idempotency_key VARCHAR(190) NULL,
  invite_url VARCHAR(500) NULL,
  sent_at DATETIME NULL,
  linked_at DATETIME NULL,
  delivered_at DATETIME NULL,
  expires_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_crm_reward_invites_public_id (public_id),
  UNIQUE KEY uq_crm_reward_invites_idem (merchant_user_id,idempotency_key),
  KEY idx_crm_reward_invites_email_status (email,status,expires_at),
  KEY idx_crm_reward_invites_contact_status (contact_id,status,created_at),
  KEY idx_crm_reward_invites_template_status (reward_template_id,status,created_at),
  KEY idx_crm_reward_invites_wallet (wallet_item_id),
  CONSTRAINT fk_crm_reward_invites_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_crm_reward_invites_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_crm_reward_invites_contact FOREIGN KEY (contact_id) REFERENCES campaign_contacts(id) ON DELETE CASCADE,
  CONSTRAINT fk_crm_reward_invites_template FOREIGN KEY (reward_template_id) REFERENCES reward_templates(id) ON DELETE RESTRICT,
  CONSTRAINT fk_crm_reward_invites_wallet FOREIGN KEY (wallet_item_id) REFERENCES wallet_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_crm_reward_invites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_12_crm_reward_invites','Adds CRM reward invites.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);