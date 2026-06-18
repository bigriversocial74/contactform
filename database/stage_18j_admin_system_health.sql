-- Stage 18J admin system health

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.health.manage','Manage system health','Run administrative system health checks and bounded recovery actions.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='admin.health.manage'
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_18j_admin_system_health','Administrative system health controls.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
