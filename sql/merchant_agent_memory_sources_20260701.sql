-- Merchant Agent Memory Sources
-- Apply after deploy when enabling document / website memory sources.

CREATE TABLE IF NOT EXISTS merchant_agent_memory_sources (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  source_type ENUM('manual_note','pdf','doc','docx','txt','md','csv','json','website','other') NOT NULL DEFAULT 'other',
  source_status ENUM('uploaded','queued','processing','ready','failed','archived') NOT NULL DEFAULT 'uploaded',
  title VARCHAR(180) NOT NULL,
  original_filename VARCHAR(255) NULL,
  source_url VARCHAR(2048) NULL,
  storage_provider VARCHAR(40) NULL,
  storage_key VARCHAR(500) NULL,
  mime_type VARCHAR(160) NULL,
  byte_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  checksum_sha256 CHAR(64) NULL,
  summary TEXT NULL,
  error_message VARCHAR(500) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_agent_memory_sources_public_id (public_id),
  KEY idx_merchant_agent_memory_sources_merchant_status (merchant_user_id, source_status, created_at),
  KEY idx_merchant_agent_memory_sources_type (source_type, source_status),
  CONSTRAINT fk_merchant_agent_memory_sources_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_agent_memory_sources_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_agent_memory_chunks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  source_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  chunk_index INT UNSIGNED NOT NULL DEFAULT 0,
  heading VARCHAR(220) NULL,
  page_number INT UNSIGNED NULL,
  section_label VARCHAR(120) NULL,
  chunk_text MEDIUMTEXT NOT NULL,
  token_estimate INT UNSIGNED NOT NULL DEFAULT 0,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_agent_memory_chunks_public_id (public_id),
  UNIQUE KEY uq_merchant_agent_memory_chunks_source_index (source_id, chunk_index),
  KEY idx_merchant_agent_memory_chunks_merchant_source (merchant_user_id, source_id),
  FULLTEXT KEY ft_merchant_agent_memory_chunks_text (chunk_text),
  CONSTRAINT fk_merchant_agent_memory_chunks_source FOREIGN KEY (source_id) REFERENCES merchant_agent_memory_sources(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_agent_memory_chunks_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
