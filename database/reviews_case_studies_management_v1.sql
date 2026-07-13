-- Reviews & Case Studies Management v1
-- Import after database/customer_review_campaign_v1.sql.

ALTER TABLE customer_reviews
  ADD COLUMN featured_on_profile TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN featured_in_case_study TINYINT(1) NOT NULL DEFAULT 0 AFTER featured_on_profile,
  ADD COLUMN moderation_notes TEXT NULL AFTER featured_in_case_study,
  ADD COLUMN moderated_by_user_id BIGINT UNSIGNED NULL AFTER moderation_notes,
  ADD COLUMN moderated_at DATETIME NULL AFTER moderated_by_user_id,
  ADD KEY idx_customer_reviews_featured_profile (merchant_user_id,featured_on_profile,status,submitted_at),
  ADD KEY idx_customer_reviews_featured_case (merchant_user_id,featured_in_case_study,status,submitted_at);

CREATE TABLE IF NOT EXISTS customer_review_replies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  review_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  author_user_id BIGINT UNSIGNED NOT NULL,
  reply_body TEXT NOT NULL,
  status ENUM('published','hidden','removed') NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customer_review_replies_public_id (public_id),
  UNIQUE KEY uq_customer_review_replies_review (review_id),
  KEY idx_customer_review_replies_merchant_status (merchant_user_id,status,updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS featured_case_studies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  profile_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  selected_review_id BIGINT UNSIGNED NULL,
  status ENUM('draft','published','hidden','archived') NOT NULL DEFAULT 'draft',
  display_order INT NOT NULL DEFAULT 100,
  hero_featured TINYINT(1) NOT NULL DEFAULT 0,
  title VARCHAR(220) NULL,
  subtitle VARCHAR(320) NULL,
  challenge_text TEXT NULL,
  solution_text TEXT NULL,
  outcomes_json JSON NULL,
  testimonial_text TEXT NULL,
  testimonial_name VARCHAR(180) NULL,
  testimonial_role VARCHAR(180) NULL,
  internal_notes TEXT NULL,
  published_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_featured_case_studies_public_id (public_id),
  UNIQUE KEY uq_featured_case_studies_profile (profile_id),
  KEY idx_featured_case_studies_public_order (status,hero_featured,display_order,published_at),
  KEY idx_featured_case_studies_merchant (merchant_user_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_case_study_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  review_id BIGINT UNSIGNED NULL,
  case_study_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  before_json JSON NULL,
  after_json JSON NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_review_case_study_audit_public_id (public_id),
  KEY idx_review_case_study_audit_review (review_id,created_at),
  KEY idx_review_case_study_audit_case (case_study_id,created_at),
  KEY idx_review_case_study_audit_merchant (merchant_user_id,created_at),
  KEY idx_review_case_study_audit_actor (actor_user_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;