-- 03Z Microgifter owner/admin bootstrap
-- Purpose: promote user #1 to super_admin only when no super_admin exists yet.
-- Run once through phpMyAdmin after confirming user #1 is the intended platform owner.

START TRANSACTION;

INSERT INTO user_roles (user_id, role_id, created_at)
SELECT 1, r.id, NOW()
FROM roles r
WHERE r.slug = 'super_admin'
  AND EXISTS (SELECT 1 FROM users u WHERE u.id = 1)
  AND NOT EXISTS (
      SELECT 1
      FROM user_roles ur
      INNER JOIN roles existing_role ON existing_role.id = ur.role_id
      WHERE existing_role.slug = 'super_admin'
  )
  AND NOT EXISTS (
      SELECT 1
      FROM user_roles already
      WHERE already.user_id = 1
        AND already.role_id = r.id
  );

INSERT INTO audit_logs (user_id, action, entity_type, metadata_json, ip_address, user_agent, created_at)
SELECT 1,
       'system.bootstrap_super_admin',
       'user',
       JSON_OBJECT('bootstrap_user_id', 1, 'role', 'super_admin', 'source', '03Z_bootstrap_super_admin_user1.sql'),
       'system',
       'database-bootstrap',
       NOW()
WHERE EXISTS (
    SELECT 1
    FROM user_roles ur
    INNER JOIN roles r ON r.id = ur.role_id
    WHERE ur.user_id = 1 AND r.slug = 'super_admin'
);

COMMIT;
