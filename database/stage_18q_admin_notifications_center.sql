-- Stage 18Q Admin Notification Center
-- Adds permission seeds for the admin notification center that reads admin_queue_notifications.

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.notifications.view','View admin notifications','View admin queue notifications and notification center records.',NOW()),
('admin.notifications.manage','Manage admin notifications','Mark admin notifications read, unread, opened, and clear notification state.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.notifications.view','admin.notifications.manage')
WHERE r.slug IN ('admin','super_admin');
