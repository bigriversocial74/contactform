-- Creator Campaign Merchant Builder v2
-- Adds typed builder fields and normalized application questions.

START TRANSACTION;

ALTER TABLE creator_campaigns
  MODIFY COLUMN access_mode ENUM('open','invite_only','approved_creators','selected_creators','hybrid') NOT NULL DEFAULT 'open',
  ADD COLUMN campaign_focus ENUM('merchant_profile','single_product','multiple_products','product_collection','microgift_offer','reward','event','service','experience','general_brand_campaign') NOT NULL DEFAULT 'general_brand_campaign' AFTER category,
  ADD COLUMN campaign_manager_user_id BIGINT UNSIGNED NULL AFTER workspace_id,
  ADD COLUMN featured_reward_template_id BIGINT UNSIGNED NULL AFTER cover_asset_id,
  ADD COLUMN creator_product_access ENUM('none','purchase_required','reimbursed','provided','loaned','digital_access') NOT NULL DEFAULT 'none' AFTER featured_reward_template_id,
  ADD COLUMN creator_landing_url VARCHAR(500) NULL AFTER creator_product_access,
  ADD COLUMN maximum_approved_creators INT UNSIGNED NULL AFTER creator_landing_url,
  ADD COLUMN maximum_applications INT UNSIGNED NULL AFTER maximum_approved_creators,
  ADD COLUMN automatic_acceptance TINYINT(1) NOT NULL DEFAULT 0 AFTER maximum_applications,
  ADD COLUMN existing_creator_preference ENUM('none','preferred','required') NOT NULL DEFAULT 'none' AFTER automatic_acceptance,
  ADD COLUMN builder_step TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER existing_creator_preference,
  ADD COLUMN builder_completed_steps_json JSON NULL AFTER builder_step,
  ADD COLUMN builder_validation_json JSON NULL AFTER builder_completed_steps_json,
  ADD COLUMN builder_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER builder_validation_json,
  ADD KEY idx_creator_campaign_builder (workspace_id,status,builder_step,updated_at),
  ADD KEY idx_creator_campaign_manager (campaign_manager_user_id,status),
  ADD KEY idx_creator_campaign_reward (featured_reward_template_id,status),
  ADD CONSTRAINT fk_creator_campaign_manager FOREIGN KEY (campaign_manager_user_id) REFERENCES users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_creator_campaign_reward FOREIGN KEY (featured_reward_template_id) REFERENCES reward_templates(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS creator_campaign_application_questions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  prompt VARCHAR(500) NOT NULL,
  helper_text VARCHAR(500) NULL,
  question_type ENUM('short_text','long_text','single_choice','multiple_choice','boolean','number','url','portfolio_link') NOT NULL DEFAULT 'short_text',
  options_json JSON NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_question_public (public_id),
  KEY idx_creator_campaign_questions_order (campaign_id,sort_order,id),
  CONSTRAINT fk_creator_campaign_question_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_creator_campaign_question_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_question_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
