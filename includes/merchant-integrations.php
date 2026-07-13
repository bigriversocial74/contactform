<?php
declare(strict_types=1);

require_once __DIR__ . '/integrations/providers/squarespace.php';

final class MgIntegrationCredentialException extends RuntimeException {}

function mg_integration_uuid(): string
{
    if (function_exists('mg_public_uuid')) return mg_public_uuid();
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
    $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_integration_json(mixed $value): array
{
    if (is_array($value)) return $value;
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_integration_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute([$table]);
        return $cache[$table] = (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return $cache[$table] = false;
    }
}

function mg_integration_schema_ready(PDO $pdo): bool
{
    foreach ([
        'merchant_integration_connections',
        'merchant_integration_credentials',
        'merchant_integration_entity_links',
        'merchant_integration_sync_runs',
        'merchant_integration_sync_state',
        'merchant_integration_webhook_events',
    ] as $table) {
        if (!mg_integration_table_exists($pdo, $table)) return false;
    }
    return true;
}

function mg_integration_sodium_available(): bool
{
    return function_exists('sodium_crypto_secretbox')
        && function_exists('sodium_crypto_secretbox_open')
        && function_exists('sodium_crypto_generichash')
        && defined('SODIUM_CRYPTO_SECRETBOX_KEYBYTES')
        && defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES');
}

function mg_integration_decode_key(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') return null;
    $decoded = base64_decode($raw, true);
    if (is_string($decoded) && strlen($decoded) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) return $decoded;
    return strlen($raw) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES ? $raw : null;
}

function mg_integration_credential_master_key(): ?string
{
    if (!mg_integration_sodium_available()) return null;
    $direct = mg_integration_decode_key((string)(getenv('MG_INTEGRATION_CREDENTIAL_KEY') ?: ''));
    if ($direct !== null) return $direct;
    $payment = mg_integration_decode_key((string)(getenv('MG_PAYMENT_CREDENTIAL_KEY') ?: ''));
    if ($payment === null) return null;
    return sodium_crypto_generichash('microgifter:merchant-integrations:v1', $payment, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
}

function mg_integration_encrypt_secret(string $plaintext): string
{
    if (!mg_integration_sodium_available()) throw new MgIntegrationCredentialException('The PHP Sodium extension is required before integration credentials can be saved.');
    $key = mg_integration_credential_master_key();
    if ($key === null) throw new MgIntegrationCredentialException('MG_INTEGRATION_CREDENTIAL_KEY or MG_PAYMENT_CREDENTIAL_KEY must be configured before integration credentials can be saved.');
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce . sodium_crypto_secretbox($plaintext, $nonce, $key));
}

function mg_integration_decrypt_secret(?string $encoded): string
{
    $encoded = trim((string)$encoded);
    if ($encoded === '') return '';
    if (!mg_integration_sodium_available()) throw new MgIntegrationCredentialException('Encrypted integration credentials are present but PHP Sodium is unavailable.');
    $key = mg_integration_credential_master_key();
    if ($key === null) throw new MgIntegrationCredentialException('Encrypted integration credentials are present but the integration credential key is unavailable.');
    $raw = base64_decode($encoded, true);
    if (!is_string($raw) || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) throw new MgIntegrationCredentialException('Stored integration credential is invalid.');
    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
    if (!is_string($plaintext)) throw new MgIntegrationCredentialException('Stored integration credential could not be decrypted.');
    return $plaintext;
}

function mg_integration_provider_registry(): array
{
    static $providers;
    if (!is_array($providers)) $providers = ['squarespace' => new MgSquarespaceProvider()];
    return $providers;
}

function mg_integration_provider(string $key): MgMerchantIntegrationProvider
{
    $key = strtolower(trim($key));
    $providers = mg_integration_provider_registry();
    if (!isset($providers[$key])) throw new InvalidArgumentException('Unsupported integration provider.');
    return $providers[$key];
}

function mg_integration_provider_catalog(): array
{
    $catalog = [];
    foreach (mg_integration_provider_registry() as $provider) {
        $catalog[] = [
            'key' => $provider->key(),
            'label' => $provider->label(),
            'description' => $provider->description(),
            'auth_type' => $provider->authType(),
            'capabilities' => $provider->capabilities(),
            'available' => true,
            'configuration' => $provider->configurationStatus(),
        ];
    }
    foreach ([
        ['shopify', 'Shopify', 'Customer, order, product, and inventory synchronization.'],
        ['square', 'Square', 'Customer directory, catalog, order, and loyalty synchronization.'],
        ['woocommerce', 'WooCommerce', 'Customer, product, coupon, and order synchronization.'],
        ['hubspot', 'HubSpot', 'Contacts, lists, lifecycle stages, and engagement synchronization.'],
        ['salesforce', 'Salesforce', 'Lead, contact, account, campaign, and opportunity synchronization.'],
        ['mailchimp', 'Mailchimp', 'Audience, subscriber, tag, and consent synchronization.'],
    ] as [$key, $label, $description]) {
        $catalog[] = [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'auth_type' => 'oauth2',
            'capabilities' => [],
            'available' => false,
            'configuration' => ['configured' => false],
        ];
    }
    return $catalog;
}

function mg_integration_connection_row(PDO $pdo, int $merchantUserId, string $providerKey, bool $forUpdate = false): ?array
{
    $sql = 'SELECT * FROM merchant_integration_connections WHERE merchant_user_id=? AND provider_key=? ORDER BY (status=\'active\') DESC,id DESC LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$merchantUserId, $providerKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_integration_connection_public(array $row): array
{
    return [
        'id' => (string)$row['public_id'],
        'provider' => (string)$row['provider_key'],
        'auth_type' => (string)$row['auth_type'],
        'status' => (string)$row['status'],
        'sync_direction' => (string)$row['sync_direction'],
        'external_account_id' => $row['external_account_id'] ?? null,
        'external_account_name' => $row['external_account_name'] ?? null,
        'external_account_url' => $row['external_account_url'] ?? null,
        'scopes' => mg_integration_json($row['scopes_json'] ?? null),
        'settings' => mg_integration_json($row['settings_json'] ?? null),
        'last_sync_at' => $row['last_sync_at'] ?? null,
        'last_error_at' => $row['last_error_at'] ?? null,
        'last_error_code' => $row['last_error_code'] ?? null,
        'last_error_message' => $row['last_error_message'] ?? null,
        'connected_at' => $row['connected_at'] ?? null,
        'disconnected_at' => $row['disconnected_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_integration_connections(PDO $pdo, int $merchantUserId): array
{
    if (!mg_integration_schema_ready($pdo)) return [];
    $stmt = $pdo->prepare('SELECT * FROM merchant_integration_connections WHERE merchant_user_id=? ORDER BY updated_at DESC,id DESC');
    $stmt->execute([$merchantUserId]);
    return array_map('mg_integration_connection_public', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_integration_begin_oauth(PDO $pdo, int $merchantUserId, string $providerKey, ?string $externalAccountHint = null): array
{
    if (!mg_integration_schema_ready($pdo)) throw new RuntimeException('Merchant integrations schema is not installed.');
    $provider = mg_integration_provider($providerKey);
    if (!$provider instanceof MgMerchantIntegrationOAuthProvider) throw new RuntimeException('This integration does not support OAuth.');
    if (!$provider->isConfigured()) throw new RuntimeException($provider->label() . ' OAuth is not configured.');
    if (mg_integration_credential_master_key() === null) throw new MgIntegrationCredentialException('Integration credential encryption is not configured.');

    $state = bin2hex(random_bytes(32));
    $stateHash = hash('sha256', $state);
    $pdo->beginTransaction();
    try {
        $connection = mg_integration_connection_row($pdo, $merchantUserId, $provider->key(), true);
        if (!$connection || (string)$connection['status'] === 'disconnected') {
            $publicId = mg_integration_uuid();
            $pdo->prepare("INSERT INTO merchant_integration_connections (public_id,merchant_user_id,provider_key,auth_type,status,sync_direction,scopes_json,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,'oauth2','pending','import_only',?,?,?,NOW(),NOW())")
                ->execute([$publicId, $merchantUserId, $provider->key(), json_encode($provider->scopes(), JSON_UNESCAPED_SLASHES), $merchantUserId, $merchantUserId]);
            $connectionId = (int)$pdo->lastInsertId();
            $connection = ['id' => $connectionId, 'public_id' => $publicId];
        } else {
            $connectionId = (int)$connection['id'];
            $pdo->prepare("UPDATE merchant_integration_connections SET status='pending',last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_by_user_id=?,updated_at=NOW() WHERE id=?")
                ->execute([$merchantUserId, $connectionId]);
        }
        $pdo->prepare("INSERT INTO merchant_integration_credentials (connection_id,oauth_state_hash,oauth_state_expires_at,created_at,updated_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE),NOW(),NOW()) ON DUPLICATE KEY UPDATE oauth_state_hash=VALUES(oauth_state_hash),oauth_state_expires_at=VALUES(oauth_state_expires_at),updated_at=NOW()")
            ->execute([$connectionId, $stateHash]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    return [
        'connection_id' => (string)$connection['public_id'],
        'provider' => $provider->key(),
        'authorization_url' => $provider->buildAuthorizationUrl($state, $externalAccountHint),
        'state_expires_in' => 600,
    ];
}

function mg_integration_find_oauth_connection(PDO $pdo, int $merchantUserId, string $providerKey, string $stateHash, bool $forUpdate = false): ?array
{
    $sql = "SELECT c.*,cr.id credential_id,cr.oauth_state_hash,cr.oauth_state_expires_at
            FROM merchant_integration_connections c
            INNER JOIN merchant_integration_credentials cr ON cr.connection_id=c.id
            WHERE c.merchant_user_id=? AND c.provider_key=? AND cr.oauth_state_hash=? AND cr.oauth_state_expires_at>=NOW()
            ORDER BY c.id DESC LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$merchantUserId, $providerKey, $stateHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_integration_datetime_from_epoch(mixed $value): ?string
{
    if (!is_numeric($value)) return null;
    $timestamp = (int)floor((float)$value);
    return $timestamp > 0 ? gmdate('Y-m-d H:i:s', $timestamp) : null;
}

function mg_integration_complete_oauth(PDO $pdo, int $merchantUserId, string $providerKey, string $state, string $code): array
{
    if (!mg_integration_schema_ready($pdo)) throw new RuntimeException('Merchant integrations schema is not installed.');
    $provider = mg_integration_provider($providerKey);
    if (!$provider instanceof MgMerchantIntegrationOAuthProvider) throw new RuntimeException('This integration does not support OAuth.');
    $stateHash = hash('sha256', trim($state));
    $pending = mg_integration_find_oauth_connection($pdo, $merchantUserId, $provider->key(), $stateHash, false);
    if (!$pending) throw new RuntimeException('The integration authorization request expired or does not match this account.');

    $tokens = $provider->exchangeAuthorizationCode($code);
    $accessToken = trim((string)($tokens['access_token'] ?? $tokens['token'] ?? ''));
    $refreshToken = trim((string)($tokens['refresh_token'] ?? ''));
    if ($accessToken === '') throw new RuntimeException($provider->label() . ' did not return an access token.');
    $account = $provider->fetchExternalAccount($accessToken);
    $externalId = trim((string)($account['id'] ?? ''));
    if ($externalId === '') throw new RuntimeException($provider->label() . ' did not return a website identifier.');

    $accessExpiresAt = mg_integration_datetime_from_epoch($tokens['access_token_expires_at'] ?? null);
    $refreshExpiresAt = mg_integration_datetime_from_epoch($tokens['refresh_token_expires_at'] ?? null);
    $accessCipher = mg_integration_encrypt_secret($accessToken);
    $refreshCipher = $refreshToken !== '' ? mg_integration_encrypt_secret($refreshToken) : '';
    $scopes = $provider->scopes();
    $settings = ['website' => $account, 'consent_mode' => 'preserve_external', 'contact_sync' => 'import_only'];

    $pdo->beginTransaction();
    try {
        $locked = mg_integration_find_oauth_connection($pdo, $merchantUserId, $provider->key(), $stateHash, true);
        if (!$locked) throw new RuntimeException('The integration authorization request was already used or expired.');
        $connectionId = (int)$locked['id'];
        $pdo->prepare("UPDATE merchant_integration_connections SET status='active',external_account_id=?,external_account_name=?,external_account_url=?,scopes_json=?,settings_json=?,connected_at=COALESCE(connected_at,NOW()),disconnected_at=NULL,last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_by_user_id=?,updated_at=NOW() WHERE id=?")
            ->execute([
                $externalId,
                trim((string)($account['title'] ?? $account['siteId'] ?? 'Squarespace site')) ?: 'Squarespace site',
                trim((string)($account['url'] ?? '')) ?: null,
                json_encode($scopes, JSON_UNESCAPED_SLASHES),
                json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $merchantUserId,
                $connectionId,
            ]);
        $pdo->prepare("UPDATE merchant_integration_credentials SET access_token_ciphertext=?,refresh_token_ciphertext=?,token_type=?,access_expires_at=?,refresh_expires_at=?,oauth_state_hash=NULL,oauth_state_expires_at=NULL,metadata_json=?,updated_at=NOW() WHERE connection_id=?")
            ->execute([
                $accessCipher,
                $refreshCipher !== '' ? $refreshCipher : null,
                trim((string)($tokens['token_type'] ?? 'bearer')) ?: 'bearer',
                $accessExpiresAt,
                $refreshExpiresAt,
                json_encode(['token_fields' => array_values(array_keys($tokens))], JSON_UNESCAPED_SLASHES),
                $connectionId,
            ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    $row = mg_integration_connection_row($pdo, $merchantUserId, $provider->key(), false);
    return $row ? mg_integration_connection_public($row) : [];
}

function mg_integration_mark_error(PDO $pdo, int $merchantUserId, string $providerKey, string $code, string $message): void
{
    if (!mg_integration_schema_ready($pdo)) return;
    $stmt = $pdo->prepare("UPDATE merchant_integration_connections SET status='error',last_error_at=NOW(),last_error_code=?,last_error_message=?,updated_by_user_id=?,updated_at=NOW() WHERE merchant_user_id=? AND provider_key=? ORDER BY id DESC LIMIT 1");
    $stmt->execute([mb_substr($code, 0, 120), mb_substr($message, 0, 1000), $merchantUserId, $merchantUserId, $providerKey]);
}

function mg_integration_disconnect(PDO $pdo, int $merchantUserId, string $providerKey): array
{
    if (!mg_integration_schema_ready($pdo)) throw new RuntimeException('Merchant integrations schema is not installed.');
    $pdo->beginTransaction();
    try {
        $connection = mg_integration_connection_row($pdo, $merchantUserId, $providerKey, true);
        if (!$connection) throw new RuntimeException('Integration connection was not found.');
        $connectionId = (int)$connection['id'];
        $pdo->prepare("UPDATE merchant_integration_connections SET status='disconnected',disconnected_at=NOW(),updated_by_user_id=?,updated_at=NOW() WHERE id=?")
            ->execute([$merchantUserId, $connectionId]);
        $pdo->prepare('UPDATE merchant_integration_credentials SET access_token_ciphertext=NULL,refresh_token_ciphertext=NULL,api_key_ciphertext=NULL,webhook_secret_ciphertext=NULL,access_expires_at=NULL,refresh_expires_at=NULL,oauth_state_hash=NULL,oauth_state_expires_at=NULL,updated_at=NOW() WHERE connection_id=?')
            ->execute([$connectionId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    $row = mg_integration_connection_row($pdo, $merchantUserId, $providerKey, false);
    return $row ? mg_integration_connection_public($row) : [];
}
