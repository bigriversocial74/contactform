-- V1 release hardening: make the catalog moderation trigger portable in mysqldump restores.
-- The original CASE expression was serialized by MySQL as `END; */;;`, which
-- produces invalid SQL when a full backup is restored. Recreate the trigger as
-- one parenthesized IF expression so SHOW CREATE TRIGGER and mysqldump do not
-- retain an internal statement terminator.

DROP TRIGGER IF EXISTS trg_catalog_assets_review_state;

CREATE TRIGGER trg_catalog_assets_review_state
BEFORE UPDATE ON catalog_assets
FOR EACH ROW
SET NEW.status = IF(
  NEW.moderation_status IN ('quarantined','blocked','takedown','removed'),
  'quarantined',
  IF(
    OLD.moderation_status IN ('quarantined','blocked','takedown','removed')
      AND NEW.moderation_status IN ('approved','unreviewed','clear'),
    'ready',
    NEW.status
  )
);

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_v1_release_trigger_portability',
  'Recreate catalog asset moderation trigger with mysqldump-portable SQL.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);
