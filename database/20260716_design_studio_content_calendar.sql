-- Microgifter Design Studio 30-day content calendar
-- Manual publishing plan only. This does not auto-publish to third-party networks.

CREATE TABLE IF NOT EXISTS design_content_schedule (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    merchant_user_id BIGINT UNSIGNED NOT NULL,
    catalog_product_id BIGINT UNSIGNED NOT NULL,
    scheduled_date DATE NOT NULL,
    scheduled_time TIME NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
    post_format VARCHAR(24) NOT NULL DEFAULT 'square',
    layout_key VARCHAR(32) NOT NULL DEFAULT 'spotlight',
    status VARCHAR(24) NOT NULL DEFAULT 'planned',
    notes VARCHAR(500) NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_design_content_schedule_public_id (public_id),
    KEY idx_design_content_schedule_merchant_date (merchant_user_id, scheduled_date, status),
    KEY idx_design_content_schedule_product_date (catalog_product_id, scheduled_date),
    CONSTRAINT fk_design_content_schedule_merchant
        FOREIGN KEY (merchant_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_design_content_schedule_product
        FOREIGN KEY (catalog_product_id) REFERENCES catalog_products (id) ON DELETE CASCADE,
    CONSTRAINT fk_design_content_schedule_creator
        FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
