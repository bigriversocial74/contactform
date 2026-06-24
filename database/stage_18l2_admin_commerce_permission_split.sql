-- Stage 18L2: Admin Commerce Permission Split
START TRANSACTION;

INSERT IGNORE INTO permissions (slug,name) VALUES
  ('admin.commerce.orders.view','View commerce order operations'),
  ('admin.commerce.refunds.view','View commerce refund operations'),
  ('admin.commerce.disputes.view','View commerce dispute operations'),
  ('admin.commerce.subscriptions.view','View commerce subscription operations'),
  ('admin.commerce.tips.view','View commerce tip operations'),
  ('admin.commerce.microgifts.view','View commerce microgift operations'),
  ('admin.commerce.cases.view','View commerce operation cases'),
  ('admin.commerce.cases.manage','Manage commerce operation cases'),
  ('admin.commerce.tips.reverse','Reverse posted commerce tips');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.slug IN (
  'admin.commerce.view',
  'admin.commerce.manage',
  'admin.commerce.orders.view',
  'admin.commerce.refunds.view',
  'admin.commerce.disputes.view',
  'admin.commerce.subscriptions.view',
  'admin.commerce.tips.view',
  'admin.commerce.microgifts.view',
  'admin.commerce.cases.view',
  'admin.commerce.cases.manage',
  'admin.commerce.tips.reverse'
)
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_18l2_admin_commerce_permission_split','Adds domain-specific admin commerce read permissions and action-specific commerce case/tip permissions.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
