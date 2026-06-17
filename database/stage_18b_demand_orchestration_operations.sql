-- Stage 18B: read-only operational monitoring and administrator observability for Stage 17B demand orchestration.

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('operations.orchestrations.view','View demand orchestration operations','Inspect demand-signal orchestration readiness, workflow and swarm outcomes, and operational event history.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug='operations.orchestrations.view'
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_18b_demand_orchestration_operations',
  'Stage 18 readiness and read-only administrator observability for Stage 17B demand orchestration.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);
