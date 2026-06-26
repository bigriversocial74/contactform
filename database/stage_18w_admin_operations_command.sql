-- Stage 18W Admin Operations Command Center
-- Adds command center permissions for unified admin operations visibility.

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.operations_command.view','View operations command center','View unified admin operations command center, queue health, automation, reporting, notifications, workload, and critical work.',NOW()),
('admin.operations_command.manage','Manage operations command center','Run command-center operations such as automation and linked operational actions.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.operations_command.view','admin.operations_command.manage')
WHERE r.slug IN ('admin','super_admin');
