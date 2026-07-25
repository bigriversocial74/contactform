<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

const MG_HOMESERVER_PAIRING_TTL_SECONDS = 600;
const MG_HOMESERVER_SIGNATURE_WINDOW_SECONDS = 300;
const MG_HOMESERVER_NONCE_TTL_SECONDS = 900;
const MG_HOMESERVER_MAX_SYNC_OPERATIONS = 50;
const MG_HOMESERVER_MAX_BODY_BYTES = 524288;

function mg_homeserver_base64url_encode(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function mg_homeserver_base64url_decode(string $value): string|false
{
    if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) return false;
    $padding = (4 - (strlen($value) % 4)) % 4;
    return base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
}

function mg_homeserver_raw_body(): string
{
    static $body = null;
    if ($body === null) {
        $body = (string)(file_get_contents('php://input') ?: '');
        if (strlen($body) > MG_HOMESERVER_MAX_BODY_BYTES) mg_fail('HomeServer request body is too large.', 413);
    }
    return $body;
}

function mg_homeserver_input(): array
{
    $body = mg_homeserver_raw_body();
    if ($body === '') return $_POST ?: [];
    try {
        $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        mg_fail('Request body must be valid JSON.', 400);
    }
    return is_array($decoded) ? $decoded : [];
}

function mg_homeserver_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$key] ?? ''));
}

function mg_homeserver_request_path(): string
{
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    return is_string($path) && $path !== '' ? $path : '/';
}

function mg_homeserver_require_secure_transport(): void
{
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    $trustProxy = (bool)mg_config_value('app', 'trust_proxy', false);
    $forwardedValues = explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $forwarded = strtolower(trim((string)($forwardedValues[0] ?? '')));
    $forwardedHttps = $trustProxy && $forwarded === 'https';
    $host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')) ?? '');
    $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    $allowLocal = in_array(strtolower((string)getenv('MG_HOMESERVER_ALLOW_INSECURE_LOCAL')), ['1', 'true', 'yes', 'on'], true);
    if (!in_array($https, ['on', '1'], true) && !$forwardedHttps && !($local && $allowLocal)) {
        mg_fail('Secure HTTPS transport is required.', 426);
    }
}

function mg_homeserver_public_uuid(): string
{
    return function_exists('mg_public_uuid') ? mg_public_uuid() : sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff),
        random_int(0, 0x0fff) | 0x4000, random_int(0, 0x3fff) | 0x8000,
        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
    );
}

function mg_homeserver_is_uuid(string $value): bool
{
    return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value) === 1;
}

function mg_homeserver_sanitize_server_name(string $value): string
{
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    $value = trim(mb_substr($value, 0, 128));
    return $value !== '' ? $value : 'Microgifter HomeServer';
}

function mg_homeserver_pairing_code(): string
{
    return mg_homeserver_base64url_encode(random_bytes(18));
}

function mg_homeserver_device_token(): string
{
    return mg_homeserver_base64url_encode(random_bytes(32));
}

function mg_homeserver_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function mg_homeserver_scopes(): array
{
    return ['homeserver.status', 'homeserver.sync.write'];
}

function mg_homeserver_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function mg_homeserver_device_payload(array $device): array
{
    $scopes = json_decode((string)($device['scopes_json'] ?? '[]'), true);
    return [
        'device_id' => (string)$device['public_id'],
        'installation_id' => (string)$device['installation_id'],
        'server_name' => (string)$device['server_name'],
        'version' => (string)$device['version'],
        'status' => (string)$device['status'],
        'scopes' => is_array($scopes) ? array_values($scopes) : [],
        'paired_at' => $device['paired_at'] ?? null,
        'last_seen_at' => $device['last_seen_at'] ?? null,
        'revoked_at' => $device['revoked_at'] ?? null,
        'token_last_four' => (string)($device['token_last_four'] ?? ''),
    ];
}

function mg_homeserver_authorization_header(): string
{
    $authorization = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    if ($authorization !== '') return $authorization;
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strcasecmp((string)$name, 'Authorization') === 0) return trim((string)$value);
            }
        }
    }
    return '';
}

function mg_homeserver_bearer_token(): string
{
    if (preg_match('/^Bearer\s+([A-Za-z0-9_-]{32,200})$/i', mg_homeserver_authorization_header(), $matches) !== 1) {
        mg_fail('HomeServer device authentication is required.', 401);
    }
    return $matches[1];
}

function mg_homeserver_require_device(string $requiredScope): array
{
    mg_homeserver_require_secure_transport();
    if (!function_exists('sodium_crypto_sign_verify_detached')) {
        mg_fail('HomeServer request verification is unavailable.', 503);
    }

    $publicId = strtolower(mg_homeserver_header('X-MG-Homeserver-ID'));
    $timestampRaw = mg_homeserver_header('X-MG-Timestamp');
    $nonce = mg_homeserver_header('X-MG-Nonce');
    $signatureEncoded = mg_homeserver_header('X-MG-Signature');
    $version = mb_substr(mg_homeserver_header('X-MG-Homeserver-Version'), 0, 32);
    $token = mg_homeserver_bearer_token();

    if (!mg_homeserver_is_uuid($publicId) || !ctype_digit($timestampRaw)) mg_fail('Invalid HomeServer request identity.', 401);
    $timestamp = (int)$timestampRaw;
    if (abs(time() - $timestamp) > MG_HOMESERVER_SIGNATURE_WINDOW_SECONDS) mg_fail('HomeServer request timestamp is outside the allowed window.', 401);
    if (preg_match('/^[A-Za-z0-9_-]{16,80}$/', $nonce) !== 1) mg_fail('Invalid HomeServer request nonce.', 401);

    $pdo = mg_db();
    $stmt = $pdo->prepare("SELECT * FROM homeserver_devices WHERE public_id=? AND token_hash=? AND status='active' LIMIT 1");
    $stmt->execute([$publicId, mg_homeserver_token_hash($token)]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$device) mg_fail('HomeServer device credentials are invalid or revoked.', 401);

    $scopes = json_decode((string)$device['scopes_json'], true);
    if (!is_array($scopes) || !in_array($requiredScope, $scopes, true)) mg_fail('HomeServer device scope is not permitted.', 403);

    $signature = mg_homeserver_base64url_decode($signatureEncoded);
    $publicKey = mg_homeserver_base64url_decode((string)$device['public_key_base64']);
    if (!is_string($signature) || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES || !is_string($publicKey) || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
        mg_fail('HomeServer request signature is invalid.', 401);
    }

    $canonical = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) . "\n"
        . mg_homeserver_request_path() . "\n"
        . $timestampRaw . "\n"
        . $nonce . "\n"
        . hash('sha256', mg_homeserver_raw_body());
    if (!sodium_crypto_sign_verify_detached($signature, $canonical, $publicKey)) mg_fail('HomeServer request signature verification failed.', 401);

    try {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM homeserver_request_nonces WHERE expires_at < UTC_TIMESTAMP()')->execute();
        $pdo->prepare('INSERT INTO homeserver_request_nonces (device_id,nonce,requested_at,expires_at,created_at) VALUES (?,?,FROM_UNIXTIME(?),DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),UTC_TIMESTAMP())')
            ->execute([(int)$device['id'], $nonce, $timestamp, MG_HOMESERVER_NONCE_TTL_SECONDS]);
        $pdo->prepare('UPDATE homeserver_devices SET last_seen_at=UTC_TIMESTAMP(),version=COALESCE(NULLIF(?,\'\'),version),updated_at=UTC_TIMESTAMP() WHERE id=?')
            ->execute([$version, (int)$device['id']]);
        $pdo->commit();
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ((string)$error->getCode() === '23000') mg_fail('HomeServer request replay was rejected.', 409);
        throw $error;
    }

    $device['version'] = $version !== '' ? $version : $device['version'];
    $device['last_seen_at'] = gmdate('Y-m-d H:i:s');
    return $device;
}

function mg_homeserver_sync_disposition(string $operationType, array $payload): array
{
    if (str_starts_with($operationType, 'commerce.') || str_starts_with($operationType, 'payment.') || str_starts_with($operationType, 'claim.') || str_starts_with($operationType, 'redemption.') || str_starts_with($operationType, 'ownership.')) {
        return ['disposition' => 'rejected', 'reason_code' => 'cloud_authority_required', 'response' => ['accepted' => false, 'cloud_authoritative' => true]];
    }
    return match ($operationType) {
        'device.heartbeat' => ['disposition' => 'accepted', 'reason_code' => null, 'response' => ['accepted' => true, 'cloud_time_utc' => gmdate(DATE_ATOM)]],
        'local.settings.snapshot' => ['disposition' => 'accepted', 'reason_code' => null, 'response' => ['accepted' => true, 'authority' => 'local', 'stored_as_receipt' => true]],
        'cache.refresh.request' => ['disposition' => 'accepted', 'reason_code' => null, 'response' => ['accepted' => true, 'next_poll_seconds' => 300]],
        default => ['disposition' => 'review', 'reason_code' => 'operation_not_enabled', 'response' => ['accepted' => false, 'review_required' => true]],
    };
}
