-- Stage 12J Campaign Feature SQL Hardening
--
-- Canonical migration marker for the Stage 12 campaign feature SQL pass.
-- The operator-facing one-file phpMyAdmin import is:
-- database/stage_12_campaign_features_full_import.sql
--
-- The required campaign event nullability is already covered by:
-- database/stage_12b_campaign_events_agent_context.sql
--
-- Keep this ordered file intentionally simple so the canonical PDO migration
-- runner remains safe while the one-file import remains available for operators.
-- This statement intentionally returns no result set.

SET @stage_12j_campaign_feature_sql_hardening := 1;
