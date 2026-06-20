-- V1 release hardening: make the catalog moderation trigger portable in mysqldump restores.
-- The migration runner records this file after execution. The CREATE TRIGGER
-- statement is intentionally the final statement and has no trailing semicolon,
-- preventing MySQL from retaining a terminator inside SHOW CREATE TRIGGER.

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
)