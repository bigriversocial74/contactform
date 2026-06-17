-- Microgifter Stage 1 Security Hardening 03N-3
-- Ensure the baseline permissions table supports descriptions before inserting
-- descriptive permission records.

SET @mg_permissions_description_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'permissions'
    AND COLUMN_NAME = 'description'
);

SET @mg_add_permissions_description_sql := IF(
  @mg_permissions_description_exists = 0,
  'ALTER TABLE permissions ADD COLUMN description VARCHAR(255) NULL AFTER name',
  'SELECT 1'
);

PREPARE mg_add_permissions_description_stmt FROM @mg_add_permissions_description_sql;
EXECUTE mg_add_permissions_description_stmt;
DEALLOCATE PREPARE mg_add_permissions_description_stmt;

INSERT INTO permissions (slug, name, description, created_at) VALUES
('user.sessions.manage', 'Manage own sessions', 'Allow a user to list and manage their own sessions.', NOW()),
('admin.sessions.view', 'View user sessions', 'Allow an admin to view user session records.', NOW()),
('admin.sessions.revoke', 'Manage user sessions', 'Allow an admin to manage user session records.', NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r JOIN permissions p ON p.slug = 'user.sessions.manage'
WHERE r.slug IN ('customer', 'merchant', 'admin', 'super_admin');

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r JOIN permissions p ON p.slug IN ('admin.sessions.view', 'admin.sessions.revoke')
WHERE r.slug IN ('admin', 'super_admin');