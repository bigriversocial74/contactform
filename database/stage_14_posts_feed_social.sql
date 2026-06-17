ALTER TABLE feed_posts
  MODIFY catalog_product_id BIGINT UNSIGNED NULL,
  MODIFY visibility ENUM('private','recipient','unlisted','public','followers','subscribers','premium') NOT NULL DEFAULT 'public',
  ADD COLUMN linked_microgift_instance_id BIGINT UNSIGNED NULL AFTER catalog_product_id,
  ADD COLUMN subscription_plan_id BIGINT UNSIGNED NULL AFTER linked_microgift_instance_id,
  ADD COLUMN moderation_status ENUM('clear','flagged','hidden','removed') NOT NULL DEFAULT 'clear' AFTER status,
  ADD COLUMN comment_count BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER moderation_status,
  ADD COLUMN reaction_count BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER comment_count,
  ADD COLUMN share_count BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER reaction_count,
  ADD COLUMN save_count BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER share_count,
  ADD KEY idx_feed_posts_visibility_status (visibility,status,moderation_status,updated_at),
  ADD CONSTRAINT fk_feed_posts_microgift FOREIGN KEY (linked_microgift_instance_id) REFERENCES microgift_instances(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_feed_posts_subscription_plan FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS social_follows (
  follower_user_id BIGINT UNSIGNED NOT NULL,
  followed_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('active','pending','blocked') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (follower_user_id,followed_user_id),
  KEY idx_social_follows_followed (followed_user_id,status,created_at),
  CONSTRAINT fk_social_follow_follower FOREIGN KEY (follower_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_social_follow_followed FOREIGN KEY (followed_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT chk_social_follow_distinct CHECK (follower_user_id <> followed_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_mutes (
  muting_user_id BIGINT UNSIGNED NOT NULL,
  muted_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (muting_user_id,muted_user_id),
  CONSTRAINT fk_social_mute_actor FOREIGN KEY (muting_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_social_mute_target FOREIGN KEY (muted_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_blocks (
  blocking_user_id BIGINT UNSIGNED NOT NULL,
  blocked_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (blocking_user_id,blocked_user_id),
  CONSTRAINT fk_social_block_actor FOREIGN KEY (blocking_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_social_block_target FOREIGN KEY (blocked_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_post_reactions (
  feed_post_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  reaction_type ENUM('like','love','celebrate','support') NOT NULL DEFAULT 'like',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (feed_post_id,user_id),
  CONSTRAINT fk_feed_reaction_post FOREIGN KEY (feed_post_id) REFERENCES feed_posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_feed_reaction_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_post_comments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  feed_post_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  parent_comment_id BIGINT UNSIGNED NULL,
  body TEXT NOT NULL,
  status ENUM('visible','flagged','hidden','removed') NOT NULL DEFAULT 'visible',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_feed_comments_public_id (public_id),
  KEY idx_feed_comments_post (feed_post_id,status,created_at),
  CONSTRAINT fk_feed_comment_post FOREIGN KEY (feed_post_id) REFERENCES feed_posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_feed_comment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_feed_comment_parent FOREIGN KEY (parent_comment_id) REFERENCES feed_post_comments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_post_saves (
  feed_post_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (feed_post_id,user_id),
  CONSTRAINT fk_feed_save_post FOREIGN KEY (feed_post_id) REFERENCES feed_posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_feed_save_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_post_shares (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  feed_post_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  channel ENUM('internal','copy_link','email','sms','external') NOT NULL DEFAULT 'internal',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_feed_shares_public_id (public_id),
  KEY idx_feed_shares_post (feed_post_id,created_at),
  CONSTRAINT fk_feed_share_post FOREIGN KEY (feed_post_id) REFERENCES feed_posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_feed_share_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  reporter_user_id BIGINT UNSIGNED NOT NULL,
  subject_type ENUM('post','comment','user') NOT NULL,
  subject_reference VARCHAR(190) NOT NULL,
  reason_code VARCHAR(100) NOT NULL,
  details VARCHAR(1000) NULL,
  status ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  resolution_note VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_social_reports_public_id (public_id),
  KEY idx_social_reports_status (status,created_at),
  CONSTRAINT fk_social_reporter FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_social_report_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('social.posts.create','Create social posts','Create and publish social feed posts.',NOW()),
('social.engage','Engage with social posts','React, comment, save, share, follow, mute, block, and report.',NOW()),
('social.moderate','Moderate social content','Review reports and moderate posts and comments.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN ('social.posts.create','social.engage')
WHERE r.slug IN ('customer','member','merchant','creator','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='social.moderate'
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_14_posts_feed_social','Posts, social graph, engagement, moderation, visibility, and entitlement-aware feed delivery.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
