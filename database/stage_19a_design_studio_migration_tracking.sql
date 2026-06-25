-- Stage 19a: Design Studio migration tracking
-- Purpose: add an application-level migration ledger so Stage 19 can be imported in smaller, auditable steps.
-- Import first.

CREATE TABLE IF NOT EXISTS microgifter_schema_migrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  migration_key VARCHAR(160) NOT NULL,
  migration_group VARCHAR(80) NOT NULL DEFAULT 'core',
  description VARCHAR(500) NULL,
  checksum CHAR(64) NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgifter_schema_migrations_key (migration_key),
  KEY idx_microgifter_schema_migrations_group (migration_group,applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO microgifter_schema_migrations (migration_key,migration_group,description,checksum,applied_at,created_at,updated_at)
VALUES ('stage_19a_design_studio_migration_tracking','design_studio','Create migration tracking ledger for split Stage 19 imports.',SHA2('stage_19a_design_studio_migration_tracking',256),NOW(),NOW(),NOW())
ON DUPLICATE KEY UPDATE updated_at=NOW();
