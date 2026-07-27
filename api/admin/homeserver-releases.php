<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/homeserver-releases.php';

function mg_admin_homeserver_release_user_can_manage(array $user): bool
{
    return in_array('super_admin', (array)($user['roles'] ?? []), true)
        || (function_exists('mg_api_user_has_permission') && mg_api_user_has_permission($user, 'admin.settings.manage'))
        || in_array('admin.settings.manage', (array)($user['permissions'] ?? []), true);
}

function mg_admin_homeserver_release_storage_status(): array
{
    try {
        $status = mg_storage_assert_ready(true, false);
        return [
            'ready' => true,
            'persistent' => (bool)($status['persistent'] ?? false),
            'writable' => (bool)($status['writable'] ?? false),
            'free_bytes' => $status['free_bytes'] ?? null,
            'message' => 'Protected persistent storage is ready.',
        ];
    } catch (Throwable $error) {
        return [
            'ready' => false,
            'persistent' => false,
            'writable' => false,
            'free_bytes' => null,
            'message' => $error->getMessage(),
        ];
    }
}

function mg_admin_homeserver_release_payload(PDO $pdo, array $user): array
{
    $schemaReady = mg_homeserver_release_schema_ready($pdo);
    $base = [
        'schema_ready' => $schemaReady,
        'can_manage' => mg_admin_homeserver_release_user_can_manage($user),
        'storage' => mg_admin_homeserver_release_storage_status(),
        'limits' => [
            'max_upload_bytes' => MG_HOMESERVER_RELEASE_MAX_BYTES,
            'allowed_channels' => ['stable', 'beta', 'preview'],
            'allowed_architectures' => ['x64', 'arm64'],
        ],
        'stats' => [
            'release_count' => 0,
            'published_count' => 0,
            'download_count' => 0,
            'latest_version' => null,
        ],
        'releases' => [],
        'recent_downloads' => [],
    ];
    if (!$schemaReady) return $base;

    $stats = $pdo->query(
        "SELECT COUNT(*) AS release_count,
                SUM(CASE WHEN status='published' THEN 1 ELSE 0 END) AS published_count,
                COALESCE(SUM(download_count),0) AS download_count
         FROM homeserver_releases"
    )->fetch(PDO::FETCH_ASSOC) ?: [];
    $latest = mg_homeserver_release_latest($pdo);
    $base['stats'] = [
        'release_count' => (int)($stats['release_count'] ?? 0),
        'published_count' => (int)($stats['published_count'] ?? 0),
        'download_count' => (int)($stats['download_count'] ?? 0),
        'latest_version' => $latest['version'] ?? null,
    ];

    $releaseRows = $pdo->query(
        "SELECT r.*,u.email AS created_by_email
         FROM homeserver_releases r
         LEFT JOIN users u ON u.id=r.created_by_user_id
         ORDER BY r.is_latest DESC,r.published_at DESC,r.created_at DESC,r.id DESC
         LIMIT 100"
    )->fetchAll(PDO::FETCH_ASSOC);
    $base['releases'] = array_map(static function (array $row): array {
        $payload = mg_homeserver_release_row_payload($row);
        $payload['created_by_email'] = $row['created_by_email'] ?? null;
        return $payload;
    }, $releaseRows ?: []);

    $downloadRows = $pdo->query(
        "SELECT d.public_id,d.downloaded_at,d.user_agent,
                r.public_id AS release_public_id,r.version,r.release_channel,r.architecture,
                u.email AS user_email
         FROM homeserver_release_downloads d
         INNER JOIN homeserver_releases r ON r.id=d.release_id
         LEFT JOIN users u ON u.id=d.user_id
         ORDER BY d.downloaded_at DESC,d.id DESC
         LIMIT 50"
    )->fetchAll(PDO::FETCH_ASSOC);
    $base['recent_downloads'] = array_map(static fn(array $row): array => [
        'download_id' => (string)$row['public_id'],
        'release_id' => (string)$row['release_public_id'],
        'version' => (string)$row['version'],
        'channel' => (string)$row['release_channel'],
        'architecture' => (string)$row['architecture'],
        'user_email' => $row['user_email'] ?? null,
        'user_agent' => (string)($row['user_agent'] ?? ''),
        'downloaded_at' => $row['downloaded_at'] ?? null,
    ], $downloadRows ?: []);

    return $base;
}

function mg_admin_homeserver_release_require_row(PDO $pdo, string $publicId): array
{
    $publicId = strtolower(trim($publicId));
    if (preg_match('/^[a-f0-9-]{36}$/', $publicId) !== 1) mg_fail('HomeServer release not found.', 404);
    $stmt = $pdo->prepare('SELECT * FROM homeserver_releases WHERE public_id=? LIMIT 1');
    $stmt->execute([$publicId]);
    $release = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$release) mg_fail('HomeServer release not found.', 404);
    return $release;
}

$user = mg_require_permission('admin.settings.manage');
$pdo = mg_db();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    mg_ok(mg_admin_homeserver_release_payload($pdo, $user));
}
if ($method !== 'POST') mg_fail('Method not allowed.', 405);

mg_require_csrf_for_write($_POST ?: mg_input());
if (function_exists('mg_rate_limit')) {
    mg_rate_limit('admin.homeserver_releases', 'user:' . (int)$user['id'], 40, 3600);
}
if (!mg_homeserver_release_schema_ready($pdo)) {
    mg_fail('Run the HomeServer release distribution migration before uploading installers.', 409);
}

$input = $_POST ?: mg_input();
$action = strtolower(trim((string)($input['action'] ?? '')));

if ($action === 'upload') {
    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) mg_fail('Choose a HomeServer .exe installer to upload.', 422);

    $version = trim((string)($input['version'] ?? ''));
    if (!mg_homeserver_release_valid_version($version)) {
        mg_fail('Use a valid version such as 1.0.0 or 1.0.0-beta.1.', 422);
    }
    $channel = mg_homeserver_release_normalize_channel((string)($input['channel'] ?? 'stable'));
    $architecture = mg_homeserver_release_normalize_architecture((string)($input['architecture'] ?? 'x64'));
    $notes = trim((string)($input['release_notes'] ?? ''));
    if (mb_strlen($notes) > MG_HOMESERVER_RELEASE_MAX_NOTES_LENGTH) {
        mg_fail('Release notes are too long.', 422);
    }
    $minimumVersion = trim((string)($input['minimum_supported_version'] ?? ''));
    if ($minimumVersion !== '' && !mg_homeserver_release_valid_version($minimumVersion)) {
        mg_fail('Minimum supported version is invalid.', 422);
    }
    $publishNow = filter_var($input['publish_now'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    $publishNow = $publishNow !== false;
    $mandatory = filter_var($input['mandatory_update'] ?? false, FILTER_VALIDATE_BOOL);

    try {
        $validated = mg_homeserver_release_validate_upload($_FILES['file']);
    } catch (DomainException $error) {
        $status = str_contains(strtolower($error->getMessage()), 'limit') || str_contains(strtolower($error->getMessage()), 'large') ? 413 : 422;
        mg_fail($error->getMessage(), $status);
    } catch (Throwable $error) {
        mg_fail_unexpected($error, 'homeserver.release_upload_validation_failed', 'The HomeServer installer could not be validated.', 500, [], (int)$user['id']);
    }

    $publicId = mg_homeserver_release_uuid();
    $storageKey = mg_homeserver_release_storage_key($publicId, $channel);
    $storedPath = null;
    try {
        $storedPath = mg_storage_store_uploaded_file((string)$validated['temporary_path'], $storageKey);
        $storedChecksum = hash_file('sha256', $storedPath);
        if (!is_string($storedChecksum) || !hash_equals((string)$validated['checksum_sha256'], $storedChecksum)) {
            throw new RuntimeException('Stored installer checksum verification failed.');
        }

        $pdo->beginTransaction();
        if ($publishNow) {
            $pdo->prepare(
                "UPDATE homeserver_releases SET is_latest=0,updated_at=UTC_TIMESTAMP()
                 WHERE release_channel=? AND platform='windows' AND architecture=?"
            )->execute([$channel, $architecture]);
        }
        $stmt = $pdo->prepare(
            'INSERT INTO homeserver_releases
             (public_id,version,release_channel,platform,architecture,status,is_latest,mandatory_update,
              minimum_supported_version,original_filename,storage_provider,storage_key,mime_type,byte_size,
              checksum_sha256,release_notes,download_count,created_by_user_id,published_at,created_at,updated_at)
             VALUES (?,?,?,\'windows\',?,?,?,?,?,?,?,\'persistent_local\',?,?,?,?,?,0,?,IF(?=1,UTC_TIMESTAMP(),NULL),UTC_TIMESTAMP(),UTC_TIMESTAMP())'
        );
        $stmt->execute([
            $publicId,
            $version,
            $channel,
            $architecture,
            $publishNow ? 'published' : 'draft',
            $publishNow ? 1 : 0,
            $mandatory ? 1 : 0,
            $minimumVersion !== '' ? $minimumVersion : null,
            mg_homeserver_release_safe_filename((string)$validated['original_filename'], $version),
            $storageKey,
            (string)$validated['mime_type'],
            (int)$validated['byte_size'],
            (string)$validated['checksum_sha256'],
            $notes !== '' ? $notes : null,
            (int)$user['id'],
            $publishNow ? 1 : 0,
        ]);
        $pdo->commit();
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($storedPath && is_file($storedPath)) @unlink($storedPath);
        if ((string)$error->getCode() === '23000') {
            mg_fail('That HomeServer version already exists for this channel and architecture.', 409);
        }
        mg_fail_unexpected($error, 'homeserver.release_upload_database_failed', 'The HomeServer release could not be registered.', 500, [
            'version' => $version,
            'channel' => $channel,
            'architecture' => $architecture,
        ], (int)$user['id']);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($storedPath && is_file($storedPath)) @unlink($storedPath);
        mg_fail_unexpected($error, 'homeserver.release_upload_failed', 'The HomeServer installer could not be stored.', 500, [
            'version' => $version,
            'channel' => $channel,
            'architecture' => $architecture,
        ], (int)$user['id']);
    }

    mg_audit('homeserver.release_uploaded', 'homeserver_release', [
        'release_id' => $publicId,
        'version' => $version,
        'channel' => $channel,
        'architecture' => $architecture,
        'published' => $publishNow,
        'mandatory_update' => $mandatory,
        'byte_size' => (int)$validated['byte_size'],
        'checksum_sha256' => (string)$validated['checksum_sha256'],
    ], (int)$user['id']);
    mg_ok(mg_admin_homeserver_release_payload($pdo, $user), 'HomeServer release uploaded.', 201);
}

if ($action === 'set_latest') {
    $release = mg_admin_homeserver_release_require_row($pdo, (string)($input['release_id'] ?? ''));
    if ((string)$release['status'] === 'retired') mg_fail('A retired HomeServer release cannot be published.', 409);
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            "UPDATE homeserver_releases SET is_latest=0,updated_at=UTC_TIMESTAMP()
             WHERE release_channel=? AND platform=? AND architecture=?"
        )->execute([(string)$release['release_channel'], (string)$release['platform'], (string)$release['architecture']]);
        $pdo->prepare(
            "UPDATE homeserver_releases
             SET status='published',is_latest=1,published_at=COALESCE(published_at,UTC_TIMESTAMP()),updated_at=UTC_TIMESTAMP()
             WHERE id=?"
        )->execute([(int)$release['id']]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_fail_unexpected($error, 'homeserver.release_publish_failed', 'The HomeServer release could not be published.', 500, [
            'release_id' => (string)$release['public_id'],
        ], (int)$user['id']);
    }
    mg_audit('homeserver.release_published', 'homeserver_release', [
        'release_id' => (string)$release['public_id'],
        'version' => (string)$release['version'],
    ], (int)$user['id']);
    mg_ok(mg_admin_homeserver_release_payload($pdo, $user), 'Latest HomeServer version updated.');
}

if ($action === 'retire') {
    $release = mg_admin_homeserver_release_require_row($pdo, (string)($input['release_id'] ?? ''));
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE homeserver_releases SET status='retired',is_latest=0,updated_at=UTC_TIMESTAMP() WHERE id=?")
            ->execute([(int)$release['id']]);
        if ((int)$release['is_latest'] === 1) {
            $fallback = $pdo->prepare(
                "SELECT id FROM homeserver_releases
                 WHERE release_channel=? AND platform=? AND architecture=? AND status='published' AND id<>?
                 ORDER BY published_at DESC,id DESC LIMIT 1"
            );
            $fallback->execute([
                (string)$release['release_channel'],
                (string)$release['platform'],
                (string)$release['architecture'],
                (int)$release['id'],
            ]);
            $fallbackId = (int)($fallback->fetchColumn() ?: 0);
            if ($fallbackId > 0) {
                $pdo->prepare('UPDATE homeserver_releases SET is_latest=1,updated_at=UTC_TIMESTAMP() WHERE id=?')
                    ->execute([$fallbackId]);
            }
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_fail_unexpected($error, 'homeserver.release_retire_failed', 'The HomeServer release could not be retired.', 500, [
            'release_id' => (string)$release['public_id'],
        ], (int)$user['id']);
    }
    mg_audit('homeserver.release_retired', 'homeserver_release', [
        'release_id' => (string)$release['public_id'],
        'version' => (string)$release['version'],
    ], (int)$user['id']);
    mg_ok(mg_admin_homeserver_release_payload($pdo, $user), 'HomeServer release retired.');
}

mg_fail('Unsupported HomeServer release action.', 422);
