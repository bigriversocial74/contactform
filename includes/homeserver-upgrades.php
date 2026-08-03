<?php
declare(strict_types=1);

require_once __DIR__ . '/homeserver-releases.php';

const MG_HOMESERVER_UPGRADE_MANIFEST_SCHEMA_VERSION = 1;
const MG_HOMESERVER_UPGRADE_DEFAULT_KEY_ID = 'homeserver-release-2026-01';
const MG_HOMESERVER_UPGRADE_MAX_REASON_LENGTH = 500;

function mg_homeserver_upgrade_schema_ready(?PDO $pdo = null): bool
{
    $pdo ??= mg_db();
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN ('homeserver_release_controls_v2','homeserver_release_control_events_v2')"
        );
        $stmt->execute();
        return (int)$stmt->fetchColumn() === 2;
    } catch (Throwable) {
        return false;
    }
}

function mg_homeserver_upgrade_normalize_class(string $value): string
{
    $value = strtolower(trim($value));
    return in_array($value, ['bootstrap','security','maintenance','feature','preview','recovery'], true)
        ? $value
        : 'feature';
}

function mg_homeserver_upgrade_rollout_percentage(mixed $value): int
{
    return max(0, min(100, (int)$value));
}

function mg_homeserver_upgrade_public_base_url(): string
{
    $value = trim((string)(getenv('MG_HOMESERVER_UPDATE_PUBLIC_BASE_URL') ?: 'https://microgifter.com'));
    if (!preg_match('#^https://[A-Za-z0-9.-]+(?::[0-9]+)?$#', $value)) {
        throw new RuntimeException('The public HomeServer update base URL is invalid.');
    }
    return rtrim($value, '/');
}

function mg_homeserver_upgrade_public_key_base64(): string
{
    return trim((string)(getenv('MG_HOMESERVER_RELEASE_PUBLIC_KEY_BASE64') ?: ''));
}

function mg_homeserver_upgrade_key_configured(): bool
{
    $decoded = base64_decode(mg_homeserver_upgrade_public_key_base64(), true);
    return is_string($decoded) && strlen($decoded) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES;
}

function mg_homeserver_upgrade_base64url_decode(string $value): string|false
{
    if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) return false;
    $padding = (4 - (strlen($value) % 4)) % 4;
    return base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
}

function mg_homeserver_upgrade_utc(?string $value): string
{
    $timestamp = $value ? strtotime($value . (str_contains($value, 'T') ? '' : ' UTC')) : false;
    if ($timestamp === false) $timestamp = time();
    return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
}

function mg_homeserver_upgrade_manifest_payload(array $release, array $control): array
{
    $publicId = strtolower(trim((string)($release['public_id'] ?? '')));
    if (preg_match('/^[a-f0-9-]{36}$/', $publicId) !== 1) {
        throw new DomainException('The HomeServer release identifier is invalid.');
    }
    $version = trim((string)($release['version'] ?? ''));
    if (!mg_homeserver_release_valid_version($version)) {
        throw new DomainException('The HomeServer release version is invalid.');
    }
    $checksum = strtolower(trim((string)($release['checksum_sha256'] ?? '')));
    if (preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1) {
        throw new DomainException('The HomeServer release checksum is invalid.');
    }
    $thumbprint = strtoupper(trim((string)($control['authenticode_thumbprint'] ?? '')));
    if (preg_match('/^(?:[A-F0-9]{40}|[A-F0-9]{64})$/', $thumbprint) !== 1) {
        throw new DomainException('The HomeServer Authenticode thumbprint is invalid.');
    }
    $size = (int)($release['byte_size'] ?? 0);
    if ($size < 1000000 || $size > MG_HOMESERVER_RELEASE_MAX_BYTES) {
        throw new DomainException('The HomeServer installer size is outside the updater contract.');
    }

    return [
        'schema_version' => MG_HOMESERVER_UPGRADE_MANIFEST_SCHEMA_VERSION,
        'product' => 'Microgifter HomeServer',
        'channel' => 'stable',
        'version' => $version,
        'minimum_version' => ($release['minimum_supported_version'] ?? null) ?: null,
        'published_at_utc' => mg_homeserver_upgrade_utc($release['published_at'] ?? null),
        'release_notes' => (string)($release['release_notes'] ?? ''),
        'installer' => [
            'url' => mg_homeserver_upgrade_public_base_url() . '/api/homeserver/update-download.php?release=' . rawurlencode($publicId),
            'file_name' => 'Microgifter-HomeServer-Setup.exe',
            'size_bytes' => $size,
            'sha256' => $checksum,
            'authenticode_thumbprint' => $thumbprint,
        ],
    ];
}

function mg_homeserver_upgrade_canonical_payload_json(array $release, array $control): string
{
    return json_encode(
        mg_homeserver_upgrade_manifest_payload($release, $control),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
}

function mg_homeserver_upgrade_payload_sha256(array $release, array $control): string
{
    return hash('sha256', mg_homeserver_upgrade_canonical_payload_json($release, $control));
}

function mg_homeserver_upgrade_verify_signature(array $release, array $control, string $signature): bool
{
    if (!extension_loaded('sodium')) return false;
    $publicKey = base64_decode(mg_homeserver_upgrade_public_key_base64(), true);
    $signatureBytes = mg_homeserver_upgrade_base64url_decode(trim($signature));
    if (!is_string($publicKey) || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) return false;
    if (!is_string($signatureBytes) || strlen($signatureBytes) !== SODIUM_CRYPTO_SIGN_BYTES) return false;
    return sodium_crypto_sign_verify_detached(
        $signatureBytes,
        mg_homeserver_upgrade_canonical_payload_json($release, $control),
        $publicKey
    );
}

function mg_homeserver_upgrade_manifest(array $release, array $control): array
{
    $signature = trim((string)($control['manifest_signature'] ?? ''));
    if (!mg_homeserver_upgrade_verify_signature($release, $control, $signature)) {
        throw new RuntimeException('The stored HomeServer release manifest signature is invalid.');
    }
    $expectedHash = strtolower(trim((string)($control['manifest_payload_sha256'] ?? '')));
    $actualHash = mg_homeserver_upgrade_payload_sha256($release, $control);
    if ($expectedHash === '' || !hash_equals($expectedHash, $actualHash)) {
        throw new RuntimeException('The HomeServer release manifest payload changed after signing.');
    }
    return [
        'key_id' => (string)($control['manifest_key_id'] ?? MG_HOMESERVER_UPGRADE_DEFAULT_KEY_ID),
        'payload' => mg_homeserver_upgrade_manifest_payload($release, $control),
        'signature' => $signature,
    ];
}

function mg_homeserver_upgrade_release_control(PDO $pdo, int $releaseId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM homeserver_release_controls_v2 WHERE release_id=? LIMIT 1');
    $stmt->execute([$releaseId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function mg_homeserver_upgrade_release_bundle(PDO $pdo, string $publicId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT r.*,c.id AS control_id,c.public_id AS control_public_id,c.update_class,c.control_state,
                c.rollout_percentage,c.manifest_schema_version,c.manifest_key_id,c.manifest_signature,
                c.manifest_payload_sha256,c.authenticode_thumbprint,c.rollback_release_id,
                c.revocation_reason,c.activated_at,c.paused_at,c.revoked_at
         FROM homeserver_releases r
         LEFT JOIN homeserver_release_controls_v2 c ON c.release_id=r.id
         WHERE r.public_id=? LIMIT 1'
    );
    $stmt->execute([strtolower(trim($publicId))]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function mg_homeserver_upgrade_rollout_bucket(array $release): int
{
    $installation = trim((string)($_SERVER['HTTP_X_MICROGIFTER_HOMESERVER_INSTALLATION'] ?? ''));
    if ($installation === '') {
        $installation = function_exists('mg_client_ip')
            ? (string)mg_client_ip()
            : (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
    $digest = hash('sha256', (string)$release['public_id'] . '|' . $installation);
    return (int)(hexdec(substr($digest, 0, 8)) % 100) + 1;
}

function mg_homeserver_upgrade_manifest_candidate(PDO $pdo): ?array
{
    $rows = $pdo->query(
        "SELECT r.*,c.id AS control_id,c.public_id AS control_public_id,c.update_class,c.control_state,
                c.rollout_percentage,c.manifest_schema_version,c.manifest_key_id,c.manifest_signature,
                c.manifest_payload_sha256,c.authenticode_thumbprint,c.rollback_release_id,
                c.revocation_reason,c.activated_at,c.paused_at,c.revoked_at
         FROM homeserver_releases r
         INNER JOIN homeserver_release_controls_v2 c ON c.release_id=r.id
         WHERE r.release_channel='stable' AND r.platform='windows' AND r.architecture='x64'
           AND r.status='published' AND c.control_state='active'
           AND c.manifest_signature IS NOT NULL AND c.manifest_payload_sha256 IS NOT NULL
         ORDER BY r.is_latest DESC,r.published_at DESC,r.id DESC
         LIMIT 20"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows ?: [] as $row) {
        if (mg_homeserver_upgrade_rollout_bucket($row) <= (int)$row['rollout_percentage']) return $row;
    }
    return null;
}

function mg_homeserver_upgrade_control_payload(array $row): array
{
    return [
        'control_id' => $row['control_public_id'] ?? null,
        'release_id' => $row['public_id'] ?? null,
        'version' => $row['version'] ?? null,
        'update_class' => $row['update_class'] ?? 'feature',
        'state' => $row['control_state'] ?? 'unconfigured',
        'rollout_percentage' => isset($row['rollout_percentage']) ? (int)$row['rollout_percentage'] : 0,
        'manifest_schema_version' => isset($row['manifest_schema_version']) ? (int)$row['manifest_schema_version'] : 1,
        'manifest_key_id' => $row['manifest_key_id'] ?? MG_HOMESERVER_UPGRADE_DEFAULT_KEY_ID,
        'manifest_payload_sha256' => $row['manifest_payload_sha256'] ?? null,
        'signature_present' => !empty($row['manifest_signature']),
        'authenticode_thumbprint' => $row['authenticode_thumbprint'] ?? null,
        'rollback_release_id' => $row['rollback_release_id'] ?? null,
        'revocation_reason' => $row['revocation_reason'] ?? null,
        'activated_at' => $row['activated_at'] ?? null,
        'paused_at' => $row['paused_at'] ?? null,
        'revoked_at' => $row['revoked_at'] ?? null,
    ];
}

function mg_homeserver_upgrade_record_event(
    PDO $pdo,
    array $control,
    string $eventType,
    ?string $previousState,
    ?string $newState,
    array $metadata,
    ?int $actorUserId
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO homeserver_release_control_events_v2
         (public_id,release_control_id,release_id,event_type,previous_state,new_state,metadata_json,actor_user_id,created_at)
         VALUES (?,?,?,?,?,?,?, ?,UTC_TIMESTAMP())'
    );
    $stmt->execute([
        mg_homeserver_release_uuid(),
        (int)$control['id'],
        (int)$control['release_id'],
        $eventType,
        $previousState,
        $newState,
        json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        $actorUserId,
    ]);
}
