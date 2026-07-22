-- Creator Campaign Messaging and Notifications v9
-- Reuses canonical message_threads, message_thread_participants, messages, notifications,
-- notification_preferences, notification_delivery_jobs, and message_thread_settings.
START TRANSACTION;

CREATE TABLE IF NOT EXISTS creator_campaign_message_contexts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  thread_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  merchant_workspace_id BIGINT UNSIGNED NOT NULL,
  creator_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_message_context_public (public_id),
  UNIQUE KEY uq_cc_message_context_thread (thread_id),
  UNIQUE KEY uq_cc_message_context_participant (campaign_id,participant_id),
  KEY idx_cc_message_context_workspace (merchant_workspace_id,status,updated_at,id),
  KEY idx_cc_message_context_creator (creator_user_id,status,updated_at,id),
  CONSTRAINT fk_cc_message_context_thread FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_message_context_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_message_context_participant FOREIGN KEY (participant_id) REFERENCES creator_campaign_participants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_message_context_workspace FOREIGN KEY (merchant_workspace_id) REFERENCES merchant_workspaces(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_message_context_creator FOREIGN KEY (creator_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_message_context_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_message_context_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_message_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  message_context_id BIGINT UNSIGNED NOT NULL,
  message_id BIGINT UNSIGNED NOT NULL,
  context_type ENUM('campaign','deliverable','submission','earning','payout','dispute') NOT NULL DEFAULT 'campaign',
  context_public_id VARCHAR(80) NULL,
  message_kind ENUM('participant','system') NOT NULL DEFAULT 'participant',
  system_event_type VARCHAR(100) NULL,
  asset_public_ids_json JSON NULL,
  metadata_json JSON NULL,
  idempotency_hash CHAR(64) NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_message_link_public (public_id),
  UNIQUE KEY uq_cc_message_link_message (message_id),
  UNIQUE KEY uq_cc_message_link_idempotency (message_context_id,idempotency_hash),
  KEY idx_cc_message_link_context (message_context_id,created_at,id),
  KEY idx_cc_message_link_object (context_type,context_public_id,created_at,id),
  CONSTRAINT fk_cc_message_link_context FOREIGN KEY (message_context_id) REFERENCES creator_campaign_message_contexts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_message_link_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_message_link_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_internal_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NULL,
  merchant_workspace_id BIGINT UNSIGNED NOT NULL,
  context_type ENUM('campaign','participant','deliverable','submission','earning','payout','dispute') NOT NULL DEFAULT 'campaign',
  context_public_id VARCHAR(80) NULL,
  body TEXT NOT NULL,
  moderation_status ENUM('clear','hidden','removed') NOT NULL DEFAULT 'clear',
  idempotency_hash CHAR(64) NOT NULL,
  author_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_internal_note_public (public_id),
  UNIQUE KEY uq_cc_internal_note_idempotency (merchant_workspace_id,idempotency_hash),
  KEY idx_cc_internal_note_campaign (campaign_id,created_at,id),
  KEY idx_cc_internal_note_participant (participant_id,created_at,id),
  CONSTRAINT fk_cc_internal_note_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_internal_note_participant FOREIGN KEY (participant_id) REFERENCES creator_campaign_participants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_internal_note_workspace FOREIGN KEY (merchant_workspace_id) REFERENCES merchant_workspaces(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_internal_note_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT chk_cc_internal_note_body CHECK (CHAR_LENGTH(TRIM(body)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.creator_messages.view','View Creator Campaign messages','View Creator Campaign canonical message threads and delivery context.',NOW()),
('merchant.creator_messages.manage','Manage Creator Campaign messages','Open, send, close, and reopen Creator Campaign canonical message threads.',NOW()),
('merchant.creator_notes.manage','Manage Creator Campaign internal notes','Create merchant-only Creator Campaign operational notes.',NOW()),
('creator.campaign_messages.view_own','View own Creator Campaign messages','View canonical message threads for the authenticated Creator.',NOW()),
('creator.campaign_messages.send_own','Send own Creator Campaign messages','Send messages in canonical threads owned by the authenticated Creator.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('merchant.creator_messages.view','merchant.creator_messages.manage','merchant.creator_notes.manage')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('creator.campaign_messages.view_own','creator.campaign_messages.send_own')
WHERE r.slug IN ('creator','admin','super_admin');

-- Creator Campaign participants use the canonical Messages and Notifications centers.
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('gift.message.send','notification.view')
WHERE r.slug='creator';

COMMIT;
