DROP DATABASE IF EXISTS microgifter_community_test;
CREATE DATABASE microgifter_community_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE microgifter_community_test;

CREATE TABLE users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  status ENUM('active','disabled','pending') NOT NULL DEFAULT 'active',
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE roles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(80) NOT NULL,
  name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE permissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(120) NOT NULL,
  name VARCHAR(160) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissions_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE user_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, role_id)
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_id, permission_id)
) ENGINE=InnoDB;

INSERT INTO roles (slug,name) VALUES
('customer','Customer'),('merchant','Merchant'),('admin','Admin'),('super_admin','Super Admin');
INSERT INTO permissions (slug,name) VALUES
('agent.test','Create test agent'),('wallet.view','View wallet');
INSERT INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.slug='customer';

CREATE TABLE reward_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  campaign_type ENUM('newsletter_signup','contest_giveaway','qr_reward_drop','referral_reward','birthday_vip','agent_offer','customer_refund') NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE pppm_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE microgift_instances (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE wallet_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_type ENUM('purchase','manual_send','newsletter_signup','contest_entry','contest_winner','qr_scan','referral','birthday_vip','agent_discovery','customer_refund','api_issue') NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
