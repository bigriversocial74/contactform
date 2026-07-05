-- Microgifter Public Blog + Admin Content Studio
-- Public blog, admin publishing workflow, categories, tags, SEO metadata, CTA routing, settings, media library

CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  migration_key VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_schema_migrations_key (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_blog_categories_slug (slug),
  KEY idx_blog_categories_active_sort (is_active, sort_order, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_posts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  excerpt VARCHAR(500) NULL,
  body LONGTEXT NOT NULL,
  featured_image VARCHAR(700) NULL,
  featured_image_alt VARCHAR(255) NULL,
  status ENUM('draft','published','scheduled','archived') NOT NULL DEFAULT 'draft',
  author_id BIGINT UNSIGNED NULL,
  category_id BIGINT UNSIGNED NULL,
  seo_title VARCHAR(180) NULL,
  seo_description VARCHAR(255) NULL,
  canonical_url VARCHAR(255) NULL,
  cta_type ENUM('none','merchant_signup','investor_page','demo_request','campaign_builder','contact') NOT NULL DEFAULT 'none',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  published_at DATETIME NULL,
  scheduled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_blog_posts_slug (slug),
  KEY idx_blog_posts_public (status, published_at, deleted_at),
  KEY idx_blog_posts_category (category_id, status, published_at),
  KEY idx_blog_posts_featured (is_featured, status, published_at),
  KEY idx_blog_posts_author (author_id),
  KEY idx_blog_posts_deleted (deleted_at),
  CONSTRAINT fk_blog_posts_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_blog_posts_category FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_tags (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_blog_tags_slug (slug),
  KEY idx_blog_tags_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_post_tags (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id BIGINT UNSIGNED NOT NULL,
  tag_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_blog_post_tags_pair (post_id, tag_id),
  KEY idx_blog_post_tags_tag (tag_id),
  CONSTRAINT fk_blog_post_tags_post FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_blog_post_tags_tag FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(120) NOT NULL,
  setting_value TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_blog_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_media (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  file_path VARCHAR(700) NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  alt_text VARCHAR(255) NULL,
  uploaded_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_blog_media_created (created_at),
  KEY idx_blog_media_uploader (uploaded_by),
  CONSTRAINT fk_blog_media_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO blog_categories (name, slug, description, sort_order, is_active) VALUES
('Pre Sale Revenue', 'pre-sale-revenue', 'Category-building articles about turning future demand into present-day revenue.', 10, 1),
('Hospitality Marketing', 'hospitality-marketing', 'Growth content for restaurants, bars, events, travel, fitness, salons, and local service merchants.', 20, 1),
('Loyalty CRM', 'loyalty-crm', 'Customer engagement, rewards, recovery, referral, claim tracking, and retention strategy.', 30, 1),
('Agentic Commerce', 'agentic-commerce', 'Managed gifting agents, scheduled rewards, automated commerce, and demand intelligence.', 40, 1),
('Product Updates', 'product-updates', 'Microgifter platform releases, feature notes, and operational product updates.', 50, 1),
('Founder Notes', 'founder-notes', 'Founder perspective, investor notes, and Microgifter category thinking.', 60, 1);

INSERT IGNORE INTO blog_settings (setting_key, setting_value) VALUES
('blog_title', 'Microgifter Blog'),
('blog_description', 'Insights on hospitality marketing, loyalty CRM, social gifting, automated commerce, and Pre Sale Revenue.'),
('default_social_image', ''),
('posts_per_page', '9'),
('default_cta', 'demo_request'),
('rss_enabled', '1'),
('sitemap_enabled', '1'),
('coming_soon_title', 'Microgifter is preparing public articles.'),
('coming_soon_message', 'The Content Studio is ready for founder notes, merchant education, product updates, and Pre Sale Revenue category-building posts. Draft starter posts stay private until they are reviewed and published.');

INSERT IGNORE INTO blog_posts (title, slug, excerpt, body, status, category_id, seo_title, seo_description, cta_type, is_featured) VALUES
('What Is Pre Sale Revenue?', 'what-is-pre-sale-revenue', 'A founder-friendly explanation of how Microgifter turns future demand into present-day revenue for local merchants.', '<h2>Pre Sale Revenue is committed future demand</h2><p>Microgifter views every gift, reward, referral, and enterprise-sponsored purchase as a measurable future transaction. The merchant receives demand before the customer arrives, and the platform tracks the claim path after purchase.</p><h3>Why it matters</h3><p>Local businesses need more than promotion. They need committed demand, claim tracking, loyalty loops, and a clear view of which offers create future visits.</p><h3>Microgifter angle</h3><p>The platform connects prepaid local commerce, social gifting, loyalty CRM, and automated follow-up into one revenue event pipeline.</p>', 'draft', (SELECT id FROM blog_categories WHERE slug = 'pre-sale-revenue' LIMIT 1), 'What Is Pre Sale Revenue? | Microgifter', 'Learn how Microgifter defines Pre Sale Revenue as committed future demand for local merchants.', 'investor_page', 1),
('How Social Gifting Helps Local Businesses', 'how-social-gifting-helps-local-businesses', 'Social gifting gives customers a simple way to buy now, send later, and support local businesses with measurable claim activity.', '<h2>Social gifting turns customer intent into local sales</h2><p>A gift purchase is more than a transaction. It is a referral, a future visit, and a trackable customer engagement event.</p><h3>Buy now, send later</h3><p>Microgifter helps customers plan gifts for family, friends, coworkers, and community moments while giving merchants a cleaner way to manage offers and claims.</p><h3>Why merchants benefit</h3><p>Every social gift can create prepaid revenue, a new customer relationship, and a loyalty moment after redemption.</p>', 'draft', (SELECT id FROM blog_categories WHERE slug = 'hospitality-marketing' LIMIT 1), 'How Social Gifting Helps Local Businesses | Microgifter', 'Explore how social gifting can create prepaid revenue, referrals, and customer engagement for local merchants.', 'merchant_signup', 0),
('Loyalty CRM for Hospitality Merchants', 'loyalty-crm-for-hospitality-merchants', 'Hospitality merchants need simple tools to reward customers, recover relationships, and track engagement after purchase.', '<h2>Loyalty needs operational follow-through</h2><p>Restaurants, bars, salons, fitness studios, and local service brands often run promotions without a clean system for claims, recovery, and repeat engagement.</p><h3>From offer to relationship</h3><p>A loyalty CRM should help the merchant create rewards, send offers, track claims, and follow up when customers need attention.</p><h3>Microgifter approach</h3><p>Microgifter combines rewards, gift certificates, campaigns, claim tracking, and customer messaging into a focused merchant workflow.</p>', 'draft', (SELECT id FROM blog_categories WHERE slug = 'loyalty-crm' LIMIT 1), 'Loyalty CRM for Hospitality Merchants | Microgifter', 'A practical look at loyalty CRM, rewards, customer recovery, and claim tracking for hospitality merchants.', 'demo_request', 0),
('Why Claim Tracking Matters', 'why-claim-tracking-matters', 'Claim tracking helps merchants understand what was sold, what was redeemed, and where customer engagement should happen next.', '<h2>Redemption is part of the revenue story</h2><p>A gift or reward is not complete when it is purchased. The important operational moment is the claim, because that is where customer experience, redemption proof, and merchant follow-up come together.</p><h3>Better visibility</h3><p>Claim tracking helps merchants know which offers moved, which customers engaged, and which rewards still need action.</p><h3>Microgifter workflow</h3><p>Microgifter connects purchase, send, claim, redemption, and follow-up so merchants can manage the full post-purchase product lifecycle.</p>', 'draft', (SELECT id FROM blog_categories WHERE slug = 'loyalty-crm' LIMIT 1), 'Why Claim Tracking Matters | Microgifter', 'Understand why claim tracking is critical for gift certificates, rewards, customer recovery, and merchant operations.', 'campaign_builder', 0),
('The Agentic Gifting CRM', 'the-agentic-gifting-crm', 'Agentic gifting connects planned purchases, recurring rewards, and managed commerce paths into one customer engagement system.', '<h2>Gifting can become managed commerce</h2><p>Agentic gifting means customers and merchants can use guided workflows to plan gifts, schedule recurring rewards, and automate meaningful local purchases.</p><h3>From manual campaigns to managed programs</h3><p>Instead of one-off promotions, merchants can build repeatable engagement flows for family gifting, coworker recognition, community prizes, customer recovery, and loyalty programs.</p><h3>Microgifter direction</h3><p>Microgifter is building toward an agentic gifting CRM where rewards, claims, referrals, and future demand intelligence work together.</p>', 'draft', (SELECT id FROM blog_categories WHERE slug = 'agentic-commerce' LIMIT 1), 'The Agentic Gifting CRM | Microgifter', 'Learn how agentic gifting can support recurring rewards, managed local commerce, and demand intelligence.', 'merchant_signup', 0),
('Founder Note: Building Future Demand Infrastructure', 'founder-note-building-future-demand-infrastructure', 'A founder note on the Microgifter vision for social gifting, prepaid demand, loyalty CRM, and local commerce intelligence.', '<h2>Microgifter is more than a gift certificate tool</h2><p>The vision is to build infrastructure for local merchants that connects promotions, prepaid revenue, rewards, claims, and future demand intelligence.</p><h3>Why now</h3><p>Local businesses need simpler technology that works across customer acquisition, loyalty, redemption tracking, and automated commerce.</p><h3>Category direction</h3><p>Microgifter is positioning around hospitality marketing, loyalty CRM, automated commerce, and the broader idea of turning future demand into present-day revenue.</p>', 'draft', (SELECT id FROM blog_categories WHERE slug = 'founder-notes' LIMIT 1), 'Founder Note: Building Future Demand Infrastructure | Microgifter', 'A Microgifter founder note about the platform vision for social gifting, loyalty CRM, and future demand infrastructure.', 'investor_page', 0);

INSERT IGNORE INTO permissions (slug, name) VALUES
('admin.blog.view', 'View Content Studio and blog posts'),
('admin.blog.manage', 'Create, edit, publish, archive, configure, and manage blog content');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.slug IN ('admin.blog.view', 'admin.blog.manage')
WHERE r.slug IN ('admin','super_admin');

INSERT IGNORE INTO schema_migrations (migration_key, description) VALUES
('microgifter_blog_module_foundation', 'Adds public blog, admin Content Studio, categories, tags, SEO fields, CTA routing, seed draft posts, settings, media library, RSS, sitemap, and blog permissions.');
