-- Stage 18N Admin User Notes

CREATE TABLE IF NOT EXISTS admin_user_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  target_user_id BIGINT UNSIGNED NOT NULL,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  category ENUM('support','risk','billing','merchant_onboarding','product_catalog','crm_campaigns','general') NOT NULL DEFAULT 'general',
  priority ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  status ENUM('open','waiting_on_merchant','waiting_on_customer','resolved','escalated') NOT NULL DEFAULT 'open',
  flag_state ENUM('none','flagged','cleared','review') NOT NULL DEFAULT 'none',
  note TEXT NOT NULL,
  reason VARCHAR(240) NOT NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_user_notes_public_id (public_id),
  KEY idx_admin_user_notes_target_status (target_user_id,status,updated_at),
  KEY idx_admin_user_notes_target_flag (target_user_id,flag_state,updated_at),
  KEY idx_admin_user_notes_admin_created (admin_user_id,created_at),
  CONSTRAINT fk_admin_user_notes_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_user_notes_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.user_notes.view','View admin user notes','View internal admin notes on user records.',NOW()),
('admin.user_notes.manage','Manage admin user notes','Create internal admin notes on user records.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.user_notes.view','admin.user_notes.manage')
WHERE r.slug IN ('admin','super_admin');
