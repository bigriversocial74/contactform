-- 02C Microgifter model default role seed
-- Safe to rerun. Inserts mappings only when matching role slugs already exist.

START TRANSACTION;

INSERT IGNORE INTO model_default_roles (user_model_id, role_id, is_required, created_at)
SELECT um.id, r.id, 1, NOW()
FROM user_models um
INNER JOIN roles r ON r.slug = um.code
WHERE um.code IN (
  'customer',
  'creator',
  'merchant',
  'moderator',
  'vendor_manager',
  'marketing_affiliate',
  'trader',
  'admin',
  'super_admin'
);

COMMIT;
