<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/feed/_feed.php';
require_once dirname(__DIR__) . '/pppm/_pppm.php';
require_once __DIR__ . '/storage.php';

function mg_media_secret(): string
{
    $secret = trim((string) getenv('MG_MEDIA_SIGNING_SECRET'));
    if ($secret === '') {
        mg_fail('Media signing is not configured.', 503);
    }
    return $secret;
}

function mg_media_issue_token(PDO $pdo, array $claims, int $ttlSeconds = 300): array
{
    $ttlSeconds = max(30, min(3600, $ttlSeconds));
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash_hmac('sha256', $rawToken, mg_media_secret());
    $publicId = mg_feed_uuid();
    $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
    $pdo->prepare(
        'INSERT INTO media_delivery_tokens
         (public_id, token_hash, asset_id, variant_id, entitlement_id, user_id, purpose,
          disposition, expires_at, max_uses, use_count, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())'
    )->execute([
        $publicId,
        $tokenHash,
        $claims['asset_id'] ?? null,
        $claims['variant_id'] ?? null,
        $claims['entitlement_id'] ?? null,
        $claims['user_id'] ?? null,
        $claims['purpose'] ?? 'preview',
        $claims['disposition'] ?? 'inline',
        $expiresAt,
        $claims['max_uses'] ?? null,
    ]);
    return ['token' => $rawToken, 'public_id' => $publicId, 'expires_at' => $expiresAt];
}

function mg_media_resolve_token(PDO $pdo, string $rawToken): array
{
    if ($rawToken === '' || strlen($rawToken) !== 64 || !ctype_xdigit($rawToken)) {
        mg_fail('Invalid media token.', 403);
    }
    $hash = hash_hmac('sha256', $rawToken, mg_media_secret());
    $stmt = $pdo->prepare(
        'SELECT t.*, a.storage_provider AS asset_provider, a.storage_key AS asset_key,
                a.original_filename AS asset_filename, a.mime_type AS asset_mime,
                a.byte_size AS asset_bytes, a.status AS asset_status, a.moderation_status,
                v.storage_provider AS variant_provider, v.storage_key AS variant_key,
                v.mime_type AS variant_mime, v.byte_size AS variant_bytes, v.status AS variant_status
         FROM media_delivery_tokens t
         LEFT JOIN catalog_asset_variants v ON v.id = t.variant_id
         LEFT JOIN catalog_assets a ON a.id = COALESCE(t.asset_id, v.source_asset_id)
         WHERE t.token_hash = ? LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([$hash]);
    $token = $stmt->fetch();
    if (!$token || !empty($token['revoked_at']) || strtotime((string) $token['expires_at']) < time()) {
        mg_fail('Media token has expired.', 403);
    }
    if ($token['max_uses'] !== null && (int) $token['use_count'] >= (int) $token['max_uses']) {
        mg_fail('Media token usage limit reached.', 403);
    }
    if ((string) ($token['asset_status'] ?? '') !== 'ready' || in_array((string) ($token['moderation_status'] ?? 'approved'), ['quarantined','blocked','takedown'], true)) {
        mg_fail('Media is unavailable.', 451);
    }
    if ($token['variant_id'] && (string) ($token['variant_status'] ?? '') !== 'ready') {
        mg_fail('Media variant is unavailable.', 404);
    }
    $pdo->prepare('UPDATE media_delivery_tokens SET use_count = use_count + 1 WHERE id = ?')->execute([(int) $token['id']]);
    return $token;
}

function mg_media_file_from_token(array $token): array
{
    $providerName = $token['variant_id'] ? $token['variant_provider'] : $token['asset_provider'];
    $key = $token['variant_id'] ? $token['variant_key'] : $token['asset_key'];
    $mime = $token['variant_id'] ? $token['variant_mime'] : $token['asset_mime'];
    $filename = $token['asset_filename'] ?: 'media';
    try {
        $provider = mg_media_storage((string) $providerName);
        $path = $provider->resolve((string) $key);
    } catch (Throwable) {
        mg_fail('Media file is unavailable.', 404);
    }
    if (!is_file($path)) mg_fail('Media file is unavailable.', 404);
    return ['path' => $path, 'mime' => $mime ?: 'application/octet-stream', 'filename' => $filename];
}

function mg_media_stream_file(string $path, string $mime, string $filename, string $disposition = 'inline'): int
{
    $size = filesize($path);
    if ($size === false) mg_fail('Media file is unavailable.', 404);
    $start = 0;
    $end = $size - 1;
    $status = 200;
    $range = trim((string) ($_SERVER['HTTP_RANGE'] ?? ''));
    if ($range !== '' && preg_match('/bytes=(\d*)-(\d*)/', $range, $match)) {
        if ($match[1] !== '') $start = max(0, (int) $match[1]);
        if ($match[2] !== '') $end = min($end, (int) $match[2]);
        if ($start > $end || $start >= $size) {
            header('Content-Range: bytes */' . $size);
            http_response_code(416);
            exit;
        }
        $status = 206;
    }
    $length = $end - $start + 1;
    http_response_code($status);
    header('Content-Type: ' . $mime);
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . $length);
    header('Cache-Control: private, max-age=60, no-transform');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: ' . ($disposition === 'attachment' ? 'attachment' : 'inline') . '; filename="' . rawurlencode($filename) . '"');
    if ($status === 206) header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    $handle = fopen($path, 'rb');
    if (!$handle) mg_fail('Media file is unavailable.', 404);
    fseek($handle, $start);
    $remaining = $length;
    while ($remaining > 0 && !feof($handle)) {
        $chunk = fread($handle, min(1048576, $remaining));
        if ($chunk === false) break;
        echo $chunk;
        $remaining -= strlen($chunk);
        flush();
    }
    fclose($handle);
    return $length - $remaining;
}

function mg_media_hash_context(string $value): ?string
{
    $value = trim($value);
    if ($value === '') return null;
    return hash_hmac('sha256', $value, mg_media_secret());
}
