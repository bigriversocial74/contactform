ALTER TABLE feed_posts
  ADD COLUMN headline VARCHAR(240) NULL AFTER post_type,
  ADD COLUMN body TEXT NULL AFTER headline,
  ADD COLUMN media_json JSON NULL AFTER body;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_14b_social_content','Direct social post content fields.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
