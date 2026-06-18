-- Stage 18K: Admin account management permissions
START TRANSACTION;

INSERT IGNORE INTO permissions (slug, name) VALUES
  ('admin.users.manage', 'Manage user account status'),
  ('admin.roles.manage', 'Manage user roles'),
  ('admin.user_models.manage', 'Manage user model assignments'),
  ('admin.sessions.view', 'View user sessions'),
  ('admin.sessions.revoke', 'Revoke user sessions');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.slug IN (
  'admin.users.manage',
  'admin.roles.manage',
  'admin.user_models.manage',
  'admin.sessions.view',
  'admin.sessions.revoke'
)
WHERE r.slug IN ('admin', 'super_admin');

COMMIT;
