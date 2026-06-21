-- Microgifter Stage 19B AI permission guard
-- Keep agent model choice broad, but reserve AI provider administration for admin roles.

DELETE rp FROM role_permissions rp
INNER JOIN permissions p ON p.id = rp.permission_id AND p.slug = 'admin.ai.manage'
INNER JOIN roles r ON r.id = rp.role_id
WHERE r.slug NOT IN ('admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
JOIN permissions p ON p.slug = 'admin.ai.manage'
WHERE r.slug IN ('admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
JOIN permissions p ON p.slug = 'agent.ai.configure'
WHERE r.slug IN ('customer','merchant','admin','super_admin');
