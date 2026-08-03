-- Microgifter HomeServer signed upgrade control v2.
-- Additive control plane layered over the existing protected release catalog.
-- The website stores only public verification material and externally produced
-- signatures. The Ed25519 release private key must never be stored in MySQL or
-- the application repository.

CREATE TABLE IF NOT EXISTS homeserver_release_controls_v2 (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    release_id BIGINT UNSIGNED NOT NULL,
    update_class ENUM('bootstrap','security','maintenance','feature','preview','recovery') NOT NULL DEFAULT 'feature',
    control_state ENUM('draft','active','paused','revoked','superseded') NOT NULL DEFAULT 'draft',
    rollout_percentage TINYINT UNSIGNED NOT NULL DEFAULT 100,
    manifest_schema_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    manifest_key_id VARCHAR(120) NOT NULL DEFAULT 'homeserver-release-2026-01',
    manifest_signature VARCHAR(160) NULL,
    manifest_payload_sha256 CHAR(64) NULL,
    authenticode_thumbprint VARCHAR(64) NULL,
    rollback_release_id BIGINT UNSIGNED NULL,
    revocation_reason VARCHAR(500) NULL,
    activated_at DATETIME NULL,
    paused_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_homeserver_release_controls_public (public_id),
    UNIQUE KEY uq_homeserver_release_controls_release (release_id),
    KEY idx_homeserver_release_controls_state (control_state,update_class,updated_at),
    KEY idx_homeserver_release_controls_rollback (rollback_release_id),
    CONSTRAINT fk_homeserver_release_controls_release
        FOREIGN KEY (release_id) REFERENCES homeserver_releases(id) ON DELETE CASCADE,
    CONSTRAINT fk_homeserver_release_controls_rollback
        FOREIGN KEY (rollback_release_id) REFERENCES homeserver_releases(id) ON DELETE SET NULL,
    CONSTRAINT fk_homeserver_release_controls_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_homeserver_release_controls_updated_by
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homeserver_release_control_events_v2 (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    release_control_id BIGINT UNSIGNED NOT NULL,
    release_id BIGINT UNSIGNED NOT NULL,
    event_type ENUM('configured','activated','paused','resumed','rollout_changed','revoked','rollback_selected','rollback_activated') NOT NULL,
    previous_state VARCHAR(40) NULL,
    new_state VARCHAR(40) NULL,
    metadata_json JSON NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_homeserver_release_control_events_public (public_id),
    KEY idx_homeserver_release_control_events_control (release_control_id,created_at),
    KEY idx_homeserver_release_control_events_release (release_id,created_at),
    CONSTRAINT fk_homeserver_release_control_events_control
        FOREIGN KEY (release_control_id) REFERENCES homeserver_release_controls_v2(id) ON DELETE CASCADE,
    CONSTRAINT fk_homeserver_release_control_events_release
        FOREIGN KEY (release_id) REFERENCES homeserver_releases(id) ON DELETE CASCADE,
    CONSTRAINT fk_homeserver_release_control_events_actor
        FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
