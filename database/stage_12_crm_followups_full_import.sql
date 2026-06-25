-- Stage 12 CRM follow-ups full import placeholder
--
-- This file is intentionally present because the canonical migration manifest
-- lists it under manual_only for operators who want a single-file phpMyAdmin
-- import. The automated release/recovery workflows require every manifest entry
-- to exist, but manual_only files are not part of the canonical ordered apply.
--
-- Canonical split migrations for automated installs:
--   database/stage_12_merchant_crm.sql
--   database/stage_12_campaign_followups.sql
--   database/stage_12_message_delivery_campaign_suppression.sql
--
-- Use those split migrations for CI, staging, and production release automation.
-- This placeholder keeps the manifest contract valid without duplicating schema
-- that is already applied by the canonical ordered files.

SELECT 'stage_12_crm_followups_full_import is manual-only; use split Stage 12 migrations for automated installs.' AS stage_12_manual_import_note;
