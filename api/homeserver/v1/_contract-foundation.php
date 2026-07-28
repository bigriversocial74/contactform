<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_homeserver.php';
require_once dirname(__DIR__, 3) . '/includes/homeserver-device-identity.php';
require_once dirname(__DIR__, 3) . '/includes/homeserver-entitlements.php';

const MG_HOMESERVER_CLOUD_CONTRACT_VERSION = 'v1';
const MG_HOMESERVER_ENTITLEMENT_SCHEMA_VERSION = 1;
const MG_HOMESERVER_PAIRING_RECOVERY_TTL_SECONDS = 900;
const MG_HOMESERVER_ROTATION_RECOVERY_TTL_SECONDS = 600;
const MG_HOMESERVER_PREVIOUS_CREDENTIAL_TTL_SECONDS = 600;
const MG_HOMESERVER_V1_MAX_RECEIPTS = 100;

function mg_hs_v1_capabilities(): array
{
    return [
        'pairing.v1',
        'device-registration.v1',
        'device-heartbeat.v1',
        'entitlement-lease.v1',
        'credential-rotation.v1',
        'merchant-assignments.v1',
        'site-assignments.v1',
        'dataset-grants.v1',
        'sync.incremental.v1',
        'operational-data.v1',
        'campaign-actions.v1',
        'signed-updates.v1',
        'update-authorization.v1',
        'update-receipts.v1',
        'device-replacement.v1',
    ];
}

function mg_hs_v1_ok(array $data, string $message = 'OK', int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode([
        'ok' => true,
        'message' => $message,
        'data' => $data,
        'error_code' => null,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function mg_hs_v1_fail(string $errorCode, string $message, int $status = 400, array $details = []): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode([
        'ok' => false,
        'message' => $message,
        'data' => null,
        'error_code' => $errorCode,
        'details' => $details,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function mg_hs_v1_internal(Throwable $error, string $auditCode, string $publicMessage): never
{
    if (function_exists('mg_log_exception')) {
        try { mg_log_exception($error, $auditCode); } catch (Throwable) {}
    }
    mg_hs_v1_fail($auditCode, $publicMessage, 500);
}

function mg_hs_v1_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $stmt->execute([$table]);
        return $cache[$table] = ((int)$stmt->fetchColumn() > 0);
    } catch (Throwable) {
        return $cache[$table] = false;
    }
}

function mg_hs_v1_require_schema(PDO $pdo): void
{
    foreach ([
        'homeserver_provider_connections',
        'homeserver_pairing_exchanges_v1',
        'homeserver_device_credentials',
        'homeserver_credential_rotations',
        'homeserver_entitlement_leases_v1',
        'homeserver_update_authorizations_v1',
        'homeserver_update_receipts_v1',
        'homeserver_device_replacements_v1',
        'homeserver_connection_receipts_v1',
    ] as $table) {
        if (!mg_hs_v1_table_exists($pdo, $table)) {
            mg_hs_v1_fail('microgifter_contract_schema_unavailable', 'The HomeServer v1 cloud contract migration has not been applied.', 503);
        }
    }
}

function mg_hs_v1_require_route(string $expectedPath): void
{
    $actual = rtrim(mg_homeserver_request_path(), '/');
    if ($actual !== rtrim($expectedPath, '/')) {
        mg_hs_v1_fail('microgifter_route_not_found', 'The HomeServer cloud contract route was not found.', 404);
    }
}

function mg_hs_v1_string(mixed $value, int $max, string $label, bool $allowEmpty = false): string
{
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', trim((string)$value)) ?? '';
    if (!$allowEmpty && $value === '') mg_hs_v1_fail('microgifter_request_invalid', ucfirst($label) . ' is required.', 422);
    if (mb_strlen($value) > $max) mg_hs_v1_fail('microgifter_request_invalid', ucfirst($label) . ' is too long.', 422);
    return $value;
}

function mg_hs_v1_optional_string(mixed $value, int $max, string $label): ?string
{
    $value = mg_hs_v1_string($value, $max, $label, true);
    return $value === '' ? null : $value;
}

function mg_hs_v1_uuid(mixed $value, string $label): string
{
    $value = strtolower(trim((string)$value));
    if (!mg_homeserver_is_uuid($value)) mg_hs_v1_fail('microgifter_request_invalid', ucfirst($label) . ' is invalid.', 422);
    return $value;
}

function mg_hs_v1_base64_decode(string $value): string|false
{
    if ($value === '') return false;
    if (preg_match('/^[A-Za-z0-9_-]+$/', $value) === 1) return mg_homeserver_base64url_decode($value);
    return base64_decode($value, true);
}

function mg_hs_v1_secret_seed(): string
{
    if (!function_exists('sodium_crypto_sign_seed_keypair')) {
        mg_hs_v1_fail('microgifter_signing_unavailable', 'HomeServer entitlement signing is unavailable.', 503);
    }
    $configured = trim((string)getenv('MG_HOMESERVER_ENTITLEMENT_SIGNING_SEED'));
    if ($configured === '' && function_exists('mg_config_value')) {
        $configured = trim((string)mg_config_value('homeserver', 'entitlement_signing_seed', ''));
    }
    $decoded = false;
    if (preg_match('/^[a-f0-9]{64}$/i', $configured) === 1) $decoded = hex2bin($configured);
    if ($decoded === false) $decoded = mg_hs_v1_base64_decode($configured);
    if (!is_string($decoded) || strlen($decoded) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
        mg_hs_v1_fail('microgifter_signing_key_unconfigured', 'The HomeServer entitlement signing key is not configured.', 503);
    }
    return $decoded;
}

function mg_hs_v1_signing_material(): array
{
    static $material = null;
    if (is_array($material)) return $material;
    $seed = mg_hs_v1_secret_seed();
    $pair = sodium_crypto_sign_seed_keypair($seed);
    $secretKey = sodium_crypto_sign_secretkey($pair);
    $publicKey = sodium_crypto_sign_publickey($pair);
    $configuredKeyId = trim((string)getenv('MG_HOMESERVER_ENTITLEMENT_SIGNING_KEY_ID'));
    if ($configuredKeyId === '' && function_exists('mg_config_value')) {
        $configuredKeyId = trim((string)mg_config_value('homeserver', 'entitlement_signing_key_id', ''));
    }
    $keyId = $configuredKeyId !== ''
        ? preg_replace('/[^A-Za-z0-9_.:-]/', '-', mb_substr($configuredKeyId, 0, 120))
        : 'ed25519-' . substr(hash('sha256', $publicKey), 0, 16);
    $recoveryConfigured = trim((string)getenv('MG_HOMESERVER_PAIRING_RECOVERY_KEY'));
    if ($recoveryConfigured === '' && function_exists('mg_config_value')) {
        $recoveryConfigured = trim((string)mg_config_value('homeserver', 'pairing_recovery_key', ''));
    }
    $recoveryKey = $recoveryConfigured !== '' ? mg_hs_v1_base64_decode($recoveryConfigured) : false;
    if (!is_string($recoveryKey) || strlen($recoveryKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        $recoveryKey = hash_hmac('sha256', 'microgifter-homeserver-pairing-recovery-v1', $seed, true);
    }
    sodium_memzero($seed);
    return $material = [
        'key_id' => (string)$keyId,
        'public_key' => $publicKey,
        'secret_key' => $secretKey,
        'recovery_key' => $recoveryKey,
    ];
}

function mg_hs_v1_encrypt_response(array $payload): array
{
    $material = mg_hs_v1_signing_material();
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $cipher = sodium_crypto_secretbox($plain, $nonce, $material['recovery_key']);
    return [
        'ciphertext' => mg_homeserver_base64url_encode($cipher),
        'nonce' => mg_homeserver_base64url_encode($nonce),
    ];
}

function mg_hs_v1_decrypt_response(string $ciphertext, string $nonce): array
{
    $material = mg_hs_v1_signing_material();
    $cipher = mg_hs_v1_base64_decode($ciphertext);
    $nonceBytes = mg_hs_v1_base64_decode($nonce);
    if (!is_string($cipher) || !is_string($nonceBytes) || strlen($nonceBytes) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        throw new RuntimeException('Encrypted recovery response is invalid.');
    }
    $plain = sodium_crypto_secretbox_open($cipher, $nonceBytes, $material['recovery_key']);
    if (!is_string($plain)) throw new RuntimeException('Encrypted recovery response could not be opened.');
    $decoded = json_decode($plain, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new RuntimeException('Encrypted recovery response payload is invalid.');
    return $decoded;
}

function mg_hs_v1_user(PDO $pdo, int $userId): array
{
    if ($userId < 1) {
        mg_hs_v1_fail('microgifter_account_not_found', 'The owning Microgifter account was not found.', 404);
    }
    if (function_exists('mg_load_user_auth')) {
        $user = mg_load_user_auth($userId);
        if (is_array($user)) return $user;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) mg_hs_v1_fail('microgifter_account_not_found', 'The owning Microgifter account was not found.', 404);
    $user['id'] = $userId;
    $user['roles'] = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    $user['permissions'] = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
    return $user;
}
