-- Production engagement mutations foundation

CREATE TABLE IF NOT EXISTS social_mutation_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(80) NOT NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  request_fingerprint CHAR(64) NOT NULL,
  response_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_social_mutation_public_id (public_id),
  UNIQUE KEY uq_social_mutation_actor_key (actor_user_id,idempotency_key),
  KEY idx_social_mutation_action_created (action,created_at),
  CONSTRAINT fk_social_mutation_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_18e_engagement_mutations','Idempotent follow, reaction, comment, and card-tip confirmation mutation authority.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
