-- Stage 18J unified content moderation and account restrictions

ALTER TABLE social_reports
  MODIFY subject_type ENUM('profile','post','comment','media','message','user') NOT NULL,
  ADD COLUMN source ENUM('user','admin','automated') NOT NULL DEFAULT 'user' AFTER reporter_user_id,
  ADD COLUMN severity ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal' AFTER reason_code,
  ADD COLUMN subject_user_id BIGINT UNSIGNED NULL AFTER subject_reference,
  ADD COLUMN feed_post_id BIGINT UNSIGNED NULL AFTER subject_user_id,
  ADD COLUMN comment_id BIGINT UNSIGNED NULL AFTER feed_post_id,
  ADD COLUMN asset_id BIGINT UNSIGNED NULL AFTER comment_id,
  ADD COLUMN message_id BIGINT UNSIGNED NULL AFTER asset_id,
  ADD COLUMN assigned_user_id BIGINT UNSIGNED NULL AFTER reviewed_by_user_id,
  ADD COLUMN subject_snapshot_json JSON NULL AFTER resolution_note,
  ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER reviewed_at,
  ADD KEY idx_social_reports_queue (status,severity,created_at,id),
  ADD KEY idx_social_reports_subject_user (subject_user_id,status,created_at),
  ADD KEY idx_social_reports_assignee (assigned_user_id,status,updated_at),
  ADD KEY idx_social_reports_post (feed_post_id,status),
  ADD KEY idx_social_reports_asset (asset_id,status),
  ADD KEY idx_social_reports_message (message_id,status),
  ADD CONSTRAINT fk_social_reports_subject_user FOREIGN KEY (subject_user_id) REFERENCES users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_social_reports_post FOREIGN KEY (feed_post_id) REFERENCES feed_posts(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_social_reports_comment FOREIGN KEY (comment_id) REFERENCES feed_post_comments(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_social_reports_asset FOREIGN KEY (asset_id) REFERENCES catalog_assets(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_social_reports_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_social_reports_assignee FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE catalog_assets
  ADD COLUMN moderation_status ENUM('clear','flagged','quarantined','removed') NOT NULL DEFAULT 'clear' AFTER status,
  ADD KEY idx_catalog_assets_moderation (moderation_status,updated_at,id);

ALTER TABLE messages
  ADD COLUMN moderation_status ENUM('clear','flagged','hidden','removed') NOT NULL DEFAULT 'clear' AFTER body,
  ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD KEY idx_messages_moderation (moderation_status,created_at,id);

CREATE TABLE IF NOT EXISTS content_moderation_actions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  report_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  action_type ENUM('report_opened','claim','note','dismiss','resolve','hide_content','restore_content','quarantine_media','warn_user','restrict_posting','suspend_user','reactivate_user') NOT NULL,
  reason TEXT NULL,
  previous_state VARCHAR(80) NULL,
  resulting_state VARCHAR(80) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_content_moderation_actions_public_id (public_id),
  KEY idx_content_moderation_actions_report (report_id,created_at,id),
  KEY idx_content_moderation_actions_actor (actor_user_id,created_at),
  CONSTRAINT fk_content_moderation_actions_report FOREIGN KEY (report_id) REFERENCES social_reports(id) ON DELETE CASCADE,
  CONSTRAINT fk_content_moderation_actions_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_moderation_restrictions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  restriction_type ENUM('posting','messaging','uploading','following','all') NOT NULL,
  status ENUM('active','lifted','expired') NOT NULL DEFAULT 'active',
  reason VARCHAR(1000) NOT NULL,
  starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ends_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  lifted_by_user_id BIGINT UNSIGNED NULL,
  lifted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_moderation_restrictions_public_id (public_id),
  KEY idx_user_moderation_restrictions_user (user_id,status,restriction_type,ends_at),
  KEY idx_user_moderation_restrictions_creator (created_by_user_id,created_at),
  CONSTRAINT fk_user_moderation_restrictions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_moderation_restrictions_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_moderation_restrictions_lifter FOREIGN KEY (lifted_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.moderation.view','View moderation center','Review content reports and moderation history.',NOW()),
('admin.moderation.manage','Manage moderation center','Apply content and account moderation actions.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.moderation.view','admin.moderation.manage')
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_18j_content_moderation',
  'Unified profile post comment media message and account moderation reports actions and restrictions.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);
