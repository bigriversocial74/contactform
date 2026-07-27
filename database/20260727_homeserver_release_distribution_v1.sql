-- Microgifter HomeServer release distribution v1
-- Additive schema for protected Windows installer releases, version metadata,
-- latest-release selection, and authenticated download-request tracking.

CREATE TABLE IF NOT EXISTS homeserver_releases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    version VARCHAR(64) NOT NULL,
    release_channel VARCHAR(24) NOT NULL DEFAULT 'stable',
    platform VARCHAR(24) NOT NULL DEFAULT 'windows',
    architecture VARCHAR(24) NOT NULL DEFAULT 'x64',
    status VARCHAR(24) NOT NULL DEFAULT 'draft',
    is_latest TINYINT(1) NOT NULL DEFAULT 0,
    mandatory_update TINYINT(1) NOT NULL DEFAULT 0,
    minimum_supported_version VARCHAR(64) NULL,
    original_filename VARCHAR(180) NOT NULL,
    storage_provider VARCHAR(32) NOT NULL DEFAULT 'persistent_local',
    storage_key VARCHAR(700) NOT NULL,
    mime_type VARCHAR(128) NOT NULL DEFAULT 'application/octet-stream',
    byte_size BIGINT UNSIGNED NOT NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    release_notes TEXT NULL,
    download_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_by_user_id BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_homeserver_releases_public_id (public_id),
    UNIQUE KEY uq_homeserver_releases_version_target (release_channel, platform, architecture, version),
    KEY idx_homeserver_releases_latest (release_channel, platform, architecture, status, is_latest, published_at),
    KEY idx_homeserver_releases_created_by (created_by_user_id, created_at),
    CONSTRAINT fk_homeserver_releases_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homeserver_release_downloads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    release_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    ip_hash CHAR(64) NOT NULL,
    user_agent VARCHAR(500) NULL,
    referer VARCHAR(500) NULL,
    downloaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_homeserver_release_downloads_public_id (public_id),
    KEY idx_homeserver_release_downloads_release (release_id, downloaded_at),
    KEY idx_homeserver_release_downloads_user (user_id, downloaded_at),
    KEY idx_homeserver_release_downloads_ip_hash (ip_hash, downloaded_at),
    CONSTRAINT fk_homeserver_release_downloads_release
        FOREIGN KEY (release_id) REFERENCES homeserver_releases(id) ON DELETE CASCADE,
    CONSTRAINT fk_homeserver_release_downloads_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
