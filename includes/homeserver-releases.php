<?php
declare(strict_types=1);

require_once __DIR__ . '/storage.php';

const MG_HOMESERVER_RELEASE_MAX_BYTES = 1073741824;
const MG_HOMESERVER_RELEASE_MAX_NOTES_LENGTH = 12000;
const MG_HOMESERVER_RELEASE_MAX_FILENAME_LENGTH = 180;

function mg_homeserver_release_schema_ready(?PDO $pdo = null): bool
{
    $pdo ??= mg_db();
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name IN ('homeserver_releases','homeserver_release_downloads')"
        );
        $stmt->execute();
        return (int)$stmt->fetchColumn() === 2;
    } catch (Throwable) {
        return false;
    }
}

function mg_homeserver_release_uuid(): string
{
    if (function_exists('mg_public_uuid')) {
        return strtolower((string)mg_public_uuid());
    }

    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function mg_homeserver_release_valid_version(string $version): bool
{
    $version = trim($version);
    return $version !== ''
        && strlen($version) <= 64
        && preg_match('/^[0-9]+(?:\.[0-9]+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/', $version) === 1;
}

function mg_homeserver_release_normalize_channel(string $channel): string
{
    $channel = strtolower(trim($channel));
    return in_array($channel, ['stable', 'beta', 'preview'], true) ? $channel : 'stable';
}

function mg_homeserver_release_normalize_architecture(string $architecture): string
{
    $architecture = strtolower(trim($architecture));
    return in_array($architecture, ['x64', 'arm64'], true) ? $architecture : 'x64';
}

function mg_homeserver_release_safe_filename(string $name, string $version): string
{
    $name = trim(basename($name));
    $name = preg_replace('/[\x00-\x1F\x7F]+/', '', $name) ?? '';
    $name = preg_replace('/[^A-Za-z0-9._() +\-]+/', '-', $name) ?? '';
    $name = trim($name, " .-\t\n\r\0\x0B");

    if ($name === '' || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'exe') {
        $name = 'Microgifter-HomeServer-' . $version . '.exe';
    }
    if (strlen($name) > MG_HOMESERVER_RELEASE_MAX_FILENAME_LENGTH) {
        $base = pathinfo($name, PATHINFO_FILENAME) ?: 'Microgifter-HomeServer-' . $version;
        $name = substr($base, 0, 170) . '.exe';
    }
    return $name;
}

function mg_homeserver_release_storage_key(string $publicId, string $channel): string
{
    if (preg_match('/^[a-f0-9-]{36}$/', $publicId) !== 1) {
        throw new InvalidArgumentException('Invalid HomeServer release identifier.');
    }
    $channel = mg_homeserver_release_normalize_channel($channel);
    return mg_storage_normalize_key(
        'homeserver/releases/' . $channel . '/' . gmdate('Y/m') . '/' . str_replace('-', '', $publicId) . '.exe'
    );
}

function mg_homeserver_release_upload_error(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The HomeServer installer exceeds the server upload limit.',
        UPLOAD_ERR_PARTIAL => 'The HomeServer installer upload was incomplete. Please try again.',
        UPLOAD_ERR_NO_FILE => 'Choose a HomeServer .exe installer to upload.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The server could not store the uploaded installer.',
        default => 'The HomeServer installer upload failed.',
    };
}

function mg_homeserver_release_validate_upload(array $file): array
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new DomainException(mg_homeserver_release_upload_error($error));
    }

    $temporaryPath = (string)($file['tmp_name'] ?? '');
    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath) || !is_file($temporaryPath)) {
        throw new DomainException('The uploaded HomeServer installer is invalid.');
    }

    $reportedSize = (int)($file['size'] ?? 0);
    $actualSize = filesize($temporaryPath);
    if ($actualSize === false) {
        throw new DomainException('The HomeServer installer size could not be verified.');
    }
    $actualSize = (int)$actualSize;
    if ($actualSize < 2 || $actualSize > MG_HOMESERVER_RELEASE_MAX_BYTES || ($reportedSize > 0 && $reportedSize !== $actualSize)) {
        throw new DomainException('The HomeServer installer is empty, too large, or incomplete.');
    }

    $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($extension !== 'exe') {
        throw new DomainException('Only a Windows .exe installer can be uploaded.');
    }

    $handle = fopen($temporaryPath, 'rb');
    $signature = is_resource($handle) ? fread($handle, 2) : false;
    if (is_resource($handle)) fclose($handle);
    if ($signature !== 'MZ') {
        throw new DomainException('The uploaded file is not a valid Windows executable.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string)$finfo->file($temporaryPath);
    $acceptedMimes = [
        'application/vnd.microsoft.portable-executable',
        'application/x-dosexec',
        'application/x-msdownload',
        'application/octet-stream',
    ];
    if (!in_array($mimeType, $acceptedMimes, true)) {
        throw new DomainException('The uploaded file type is not a supported Windows executable.');
    }

    $checksum = hash_file('sha256', $temporaryPath);
    if (!is_string($checksum) || preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1) {
        throw new RuntimeException('The HomeServer installer checksum could not be created.');
    }

    return [
        'temporary_path' => $temporaryPath,
        'byte_size' => $actualSize,
        'mime_type' => $mimeType,
        'checksum_sha256' => $checksum,
        'original_filename' => (string)($file['name'] ?? 'Microgifter-HomeServer.exe'),
    ];
}

function mg_homeserver_release_row_payload(array $release): array
{
    $publicId = (string)($release['public_id'] ?? '');
    return [
        'release_id' => $publicId,
        'version' => (string)($release['version'] ?? ''),
        'channel' => (string)($release['release_channel'] ?? 'stable'),
        'platform' => (string)($release['platform'] ?? 'windows'),
        'architecture' => (string)($release['architecture'] ?? 'x64'),
        'status' => (string)($release['status'] ?? 'draft'),
        'is_latest' => (bool)($release['is_latest'] ?? false),
        'mandatory' => (bool)($release['mandatory_update'] ?? false),
        'minimum_supported_version' => $release['minimum_supported_version'] ?? null,
        'filename' => (string)($release['original_filename'] ?? ''),
        'mime_type' => (string)($release['mime_type'] ?? 'application/octet-stream'),
        'byte_size' => (int)($release['byte_size'] ?? 0),
        'checksum_sha256' => (string)($release['checksum_sha256'] ?? ''),
        'release_notes' => (string)($release['release_notes'] ?? ''),
        'download_count' => (int)($release['download_count'] ?? 0),
        'published_at' => $release['published_at'] ?? null,
        'created_at' => $release['created_at'] ?? null,
        'updated_at' => $release['updated_at'] ?? null,
        'download_url' => $publicId !== '' ? '/api/homeserver/download.php?release=' . rawurlencode($publicId) : null,
    ];
}

function mg_homeserver_release_latest(?PDO $pdo = null, string $channel = 'stable', string $architecture = 'x64'): ?array
{
    $pdo ??= mg_db();
    $channel = mg_homeserver_release_normalize_channel($channel);
    $architecture = mg_homeserver_release_normalize_architecture($architecture);
    $stmt = $pdo->prepare(
        "SELECT * FROM homeserver_releases
         WHERE release_channel=? AND platform='windows' AND architecture=?
           AND status='published' AND is_latest=1
         ORDER BY published_at DESC,id DESC LIMIT 1"
    );
    $stmt->execute([$channel, $architecture]);
    $release = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($release) ? $release : null;
}

function mg_homeserver_release_find_published(PDO $pdo, string $publicId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM homeserver_releases
         WHERE public_id=? AND status='published' LIMIT 1"
    );
    $stmt->execute([strtolower(trim($publicId))]);
    $release = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($release) ? $release : null;
}

function mg_homeserver_release_file_path(array $release): string
{
    if ((string)($release['storage_provider'] ?? '') !== 'persistent_local') {
        throw new RuntimeException('Unsupported HomeServer release storage provider.');
    }
    $key = (string)($release['storage_key'] ?? '');
    $path = mg_storage_resolve_asset_path('persistent_local', $key);
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('The HomeServer installer file is unavailable.');
    }
    $expectedSize = (int)($release['byte_size'] ?? 0);
    $actualSize = filesize($path);
    if ($actualSize === false || $expectedSize < 1 || (int)$actualSize !== $expectedSize) {
        throw new RuntimeException('The HomeServer installer file failed its size check.');
    }
    return $path;
}

function mg_homeserver_release_ip_hash(): string
{
    $ip = function_exists('mg_client_ip') ? (string)mg_client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return hash('sha256', gmdate('Y-m-d') . '|homeserver-download|' . $ip);
}

function mg_homeserver_release_record_download(PDO $pdo, array $release, int $userId): string
{
    $requestId = mg_homeserver_release_uuid();
    $userAgent = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    $referer = mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 500);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO homeserver_release_downloads
             (public_id,release_id,user_id,ip_hash,user_agent,referer,downloaded_at,created_at)
             VALUES (?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
        );
        $stmt->execute([
            $requestId,
            (int)$release['id'],
            $userId > 0 ? $userId : null,
            mg_homeserver_release_ip_hash(),
            $userAgent,
            $referer,
        ]);
        $pdo->prepare('UPDATE homeserver_releases SET download_count=download_count+1,updated_at=UTC_TIMESTAMP() WHERE id=?')
            ->execute([(int)$release['id']]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    return $requestId;
}
