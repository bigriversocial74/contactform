-- Stage 18K Admin Account Management

START TRANSACTION;

INSERT INTO permissions (slug, name, description, created_at) VALUES
('admin.user_models.manage', 'Manage user models', 'Allow an administrator to manage user-model assignments.', NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
INNER JOIN permissions p ON p.slug = 'admin.user_models.manage'
WHERE r.slug IN ('admin','super_admin');

COMMIT;
