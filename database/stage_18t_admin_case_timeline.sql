-- Stage 18T Admin Case Timeline
-- Adds internal timeline comments and permissions for queue case activity streams.

CREATE TABLE IF NOT EXISTS admin_queue_case_comments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  note_id BIGINT UNSIGNED NOT NULL,
  target_user_id BIGINT UNSIGNED NOT NULL,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  comment_text TEXT NOT NULL,
  is_pinned TINYINT(1) NOT NULL DEFAULT 0,
  internal_only TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_queue_case_comments_public_id (public_id),
  KEY idx_admin_queue_case_comments_note (note_id,is_pinned,created_at),
  KEY idx_admin_queue_case_comments_admin (admin_user_id,created_at),
  CONSTRAINT fk_admin_queue_case_comments_note FOREIGN KEY (note_id) REFERENCES admin_user_notes(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_queue_case_comments_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_queue_case_comments_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE admin_queue_notifications
  MODIFY notification_type ENUM('assigned','overdue','due_soon','escalated','reopened','review_flag','digest','auto_routed','sla_breach','auto_escalated','workload_balance','playbook_applied','template_used','checklist_completed','case_comment','case_comment_pinned','timeline_viewed') NOT NULL;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.queue_timeline.view','View admin queue timeline','View follow-up queue case timeline, comments, and internal activity stream.',NOW()),
('admin.queue_timeline.manage','Manage admin queue timeline','Add and pin internal comments on follow-up queue case timelines.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.queue_timeline.view','admin.queue_timeline.manage')
WHERE r.slug IN ('admin','super_admin');
