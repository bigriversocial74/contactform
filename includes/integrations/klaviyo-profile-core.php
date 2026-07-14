<?php
declare(strict_types=1);

require_once __DIR__ . '/providers/klaviyo.php';

function mg_klaviyo_provider(): MgKlaviyoProvider
{
    static $provider;
    if (!$provider instanceof MgKlaviyoProvider) $provider = new MgKlaviyoProvider();
    return $provider;
}

function mg_klaviyo_provider_catalog(array $catalog): array
{
    $provider = mg_klaviyo_provider();
    $entry = [
        'key' => $provider->key(),
        'label' => $provider->label(),
        'description' => $provider->description(),
        'auth_type' => $provider->authType(),
        'capabilities' => $provider->capabilities(),
        'available' => true,
        'configuration' => $provider->configurationStatus(),
    ];
    $replaced = false;
    foreach ($catalog as $index => $item) {
        if ((string)($item['key'] ?? '') !== 'klaviyo') continue;
        $catalog[$index] = $entry;
        $replaced = true;
        break;
    }
    if (!$replaced) $catalog[] = $entry;
    return array_values($catalog);
}

function mg_klaviyo_datetime(mixed $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $timestamp = strtotime($value);
    return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
}

function mg_klaviyo_expiry_from_seconds(mixed $seconds): ?string
{
    if (!is_numeric($seconds)) return null;
    return gmdate('Y-m-d H:i:s', time() + max(60, (int)$seconds));
}

function mg_klaviyo_base64url(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function mg_klaviyo_pkce_pair(): array
{
    $verifier = mg_klaviyo_base64url(random_bytes(32));
    $challenge = mg_klaviyo_base64url(hash('sha256', $verifier, true));
    if (strlen($verifier) < 43 || strlen($verifier) > 128) throw new RuntimeException('Unable to generate a valid Klaviyo PKCE verifier.');
    return ['verifier' => $verifier, 'challenge' => $challenge];
}

function mg_klaviyo_marketing(array $attributes): array
{
    $subscriptions = is_array($attributes['subscriptions'] ?? null) ? $attributes['subscriptions'] : [];
    $email = is_array($subscriptions['email'] ?? null) ? $subscriptions['email'] : [];
    $marketing = is_array($email['marketing'] ?? null) ? $email['marketing'] : [];
    $consent = strtoupper(trim((string)($marketing['consent'] ?? 'UNKNOWN')));
    if ($consent === '') $consent = 'UNKNOWN';
    $canReceive = (bool)($marketing['can_receive_email_marketing'] ?? false);
    $suppressions = is_array($marketing['suppression'] ?? null) ? array_values($marketing['suppression']) : [];
    $listSuppressions = is_array($marketing['list_suppressions'] ?? null) ? array_values($marketing['list_suppressions']) : [];
    return [
        'accepts_marketing' => $consent === 'SUBSCRIBED' && $canReceive,
        'status' => $consent,
        'can_receive_email_marketing' => $canReceive,
        'consent_timestamp' => mg_klaviyo_datetime($marketing['consent_timestamp'] ?? null),
        'last_updated' => mg_klaviyo_datetime($marketing['last_updated'] ?? null),
        'method' => mb_substr(trim((string)($marketing['method'] ?? '')), 0, 80),
        'method_detail' => mb_substr(trim((string)($marketing['method_detail'] ?? '')), 0, 255),
        'custom_method_detail' => mb_substr(trim((string)($marketing['custom_method_detail'] ?? '')), 0, 255),
        'double_optin' => array_key_exists('double_optin', $marketing) ? (bool)$marketing['double_optin'] : null,
        'suppressions' => $suppressions,
        'list_suppressions' => $listSuppressions,
        'source' => 'klaviyo_profile_subscriptions',
        'inferred' => false,
    ];
}

function mg_klaviyo_normalize_profile(array $resource): array
{
    $attributes = is_array($resource['attributes'] ?? null) ? $resource['attributes'] : [];
    $email = strtolower(trim((string)($attributes['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
    $firstName = trim((string)($attributes['first_name'] ?? ''));
    $lastName = trim((string)($attributes['last_name'] ?? ''));
    $name = trim($firstName . ' ' . $lastName);
    $normalized = [
        'external_id' => trim((string)($resource['id'] ?? '')),
        'external_reference' => mb_substr(trim((string)($attributes['external_id'] ?? '')), 0, 190),
        'email' => $email,
        'display_name' => $name !== '' ? mb_substr($name, 0, 180) : ($email !== '' ? mb_substr($email, 0, 180) : 'Klaviyo profile'),
        'first_name' => mb_substr($firstName, 0, 100),
        'last_name' => mb_substr($lastName, 0, 100),
        'created_on' => mg_klaviyo_datetime($attributes['created'] ?? null),
        'updated_on' => mg_klaviyo_datetime($attributes['updated'] ?? null),
        'marketing' => mg_klaviyo_marketing($attributes),
        'addresses_excluded' => true,
        'phone_numbers_excluded' => true,
        'location_excluded' => true,
        'custom_properties_excluded' => true,
    ];
    $normalized['sync_hash'] = hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return $normalized;
}

function mg_klaviyo_begin_oauth(PDO $pdo, int $merchantUserId): array
{
    if (!mg_integration_schema_ready($pdo)) throw new RuntimeException('Merchant integrations schema is not installed.');
    if (mg_integration_credential_master_key() === null) throw new MgIntegrationCredentialException('Integration credential encryption is not configured.');
    $provider = mg_klaviyo_provider();
    if (!$provider->isConfigured()) throw new RuntimeException('Klaviyo OAuth is not configured.');
    $state = bin2hex(random_bytes(32));
    $stateHash = hash('sha256', $state);
    $pkce = mg_klaviyo_pkce_pair();

    $pdo->beginTransaction();
    try {
        $connection = mg_integration_connection_row($pdo, $merchantUserId, 'klaviyo', true);
        if (!$connection || (string)($connection['status'] ?? '') === 'disconnected') {
            $pdo->prepare("INSERT INTO merchant_integration_connections (public_id,merchant_user_id,provider_key,auth_type,status,sync_direction,scopes_json,settings_json,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,'oauth2','pending','import_only',?,?,?,?,NOW(),NOW())")
                ->execute([
                    mg_integration_uuid(),
                    $merchantUserId,
                    'klaviyo',
                    json_encode($provider->scopes(), JSON_UNESCAPED_SLASHES),
                    json_encode(['profile_sync' => 'import_only', 'marketing_status_mode' => 'preserve_profile_subscription'], JSON_UNESCAPED_SLASHES),
                    $merchantUserId,
                    $merchantUserId,
                ]);
            $connectionId = (int)$pdo->lastInsertId();
        } else {
            $connectionId = (int)$connection['id'];
            $pdo->prepare("UPDATE merchant_integration_connections SET status='pending',scopes_json=?,last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_by_user_id=?,updated_at=NOW() WHERE id=?")
                ->execute([json_encode($provider->scopes(), JSON_UNESCAPED_SLASHES), $merchantUserId, $connectionId]);
        }
        $pdo->prepare("INSERT INTO merchant_integration_credentials (connection_id,api_key_ciphertext,oauth_state_hash,oauth_state_expires_at,metadata_json,created_at,updated_at) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL 5 MINUTE),?,NOW(),NOW()) ON DUPLICATE KEY UPDATE api_key_ciphertext=VALUES(api_key_ciphertext),oauth_state_hash=VALUES(oauth_state_hash),oauth_state_expires_at=VALUES(oauth_state_expires_at),metadata_json=JSON_MERGE_PATCH(COALESCE(metadata_json,'{}'),VALUES(metadata_json)),updated_at=NOW()")
            ->execute([
                $connectionId,
                mg_integration_encrypt_secret($pkce['verifier']),
                $stateHash,
                json_encode(['pkce' => ['method' => 'S256', 'created_at' => gmdate('c')]], JSON_UNESCAPED_SLASHES),
            ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    return [
        'provider' => 'klaviyo',
        'authorization_url' => $provider->buildAuthorizationUrl($state, $pkce['challenge']),
        'state_expires_in' => 300,
        'pkce_method' => 'S256',
    ];
}

function mg_klaviyo_pending_oauth(PDO $pdo, int $merchantUserId, string $stateHash, bool $forUpdate = false): ?array
{
    $sql = "SELECT c.*,cr.api_key_ciphertext,cr.oauth_state_hash,cr.oauth_state_expires_at FROM merchant_integration_connections c INNER JOIN merchant_integration_credentials cr ON cr.connection_id=c.id WHERE c.merchant_user_id=? AND c.provider_key='klaviyo' AND cr.oauth_state_hash=? AND cr.oauth_state_expires_at>=NOW() ORDER BY c.id DESC LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$merchantUserId, $stateHash]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function mg_klaviyo_complete_oauth(PDO $pdo, int $merchantUserId, string $state, string $code): array
{
    $state = trim($state);
    $code = trim($code);
    if ($state === '' || $code === '') throw new RuntimeException('Klaviyo did not return a valid authorization response.');
    $stateHash = hash('sha256', $state);
    $pending = mg_klaviyo_pending_oauth($pdo, $merchantUserId, $stateHash);
    if (!$pending) throw new RuntimeException('The Klaviyo authorization request expired or does not match this account.');
    $verifier = mg_integration_decrypt_secret($pending['api_key_ciphertext'] ?? null);
    if ($verifier === '') throw new RuntimeException('The Klaviyo PKCE verifier is unavailable. Restart authorization.');

    $provider = mg_klaviyo_provider();
    $tokens = $provider->exchangeAuthorizationCode($code, $verifier);
    $accessToken = trim((string)($tokens['access_token'] ?? ''));
    $refreshToken = trim((string)($tokens['refresh_token'] ?? ''));
    if ($accessToken === '' || $refreshToken === '') throw new RuntimeException('Klaviyo did not return complete OAuth credentials.');
    $account = $provider->fetchAccount($accessToken);
    $accountId = trim((string)($account['id'] ?? ''));
    $attributes = is_array($account['attributes'] ?? null) ? $account['attributes'] : [];
    $publicKey = trim((string)($attributes['public_api_key'] ?? ''));
    $accountName = $publicKey !== '' ? 'Klaviyo account ' . $publicKey : 'Klaviyo account ' . mb_substr($accountId, -8);
    $settings = [
        'account' => [
            'id' => $accountId,
            'public_api_key' => $publicKey,
            'timezone' => trim((string)($attributes['timezone'] ?? '')),
            'locale' => trim((string)($attributes['locale'] ?? '')),
            'test_account' => (bool)($attributes['test_account'] ?? false),
        ],
        'profile_sync' => 'import_only',
        'revision' => $provider->revision(),
        'marketing_status_mode' => 'preserve_profile_subscription',
        'addresses_imported' => false,
        'phone_numbers_imported' => false,
        'custom_properties_imported' => false,
    ];

    $pdo->beginTransaction();
    try {
        $locked = mg_klaviyo_pending_oauth($pdo, $merchantUserId, $stateHash, true);
        if (!$locked) throw new RuntimeException('The Klaviyo authorization request was already used or expired.');
        $connectionId = (int)$locked['id'];
        $pdo->prepare("UPDATE merchant_integration_connections SET status='active',external_account_id=?,external_account_name=?,external_account_url=?,scopes_json=?,settings_json=?,connected_at=COALESCE(connected_at,NOW()),disconnected_at=NULL,last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_by_user_id=?,updated_at=NOW() WHERE id=?")
            ->execute([
                $accountId,
                $accountName,
                'https://www.klaviyo.com/account',
                json_encode($provider->scopes(), JSON_UNESCAPED_SLASHES),
                json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $merchantUserId,
                $connectionId,
            ]);
        $pdo->prepare("UPDATE merchant_integration_credentials SET access_token_ciphertext=?,refresh_token_ciphertext=?,api_key_ciphertext=NULL,token_type=?,access_expires_at=?,refresh_expires_at=NULL,oauth_state_hash=NULL,oauth_state_expires_at=NULL,refresh_lock_token=NULL,refresh_lock_expires_at=NULL,metadata_json=?,updated_at=NOW() WHERE connection_id=?")
            ->execute([
                mg_integration_encrypt_secret($accessToken),
                mg_integration_encrypt_secret($refreshToken),
                trim((string)($tokens['token_type'] ?? 'bearer')) ?: 'bearer',
                mg_klaviyo_expiry_from_seconds($tokens['expires_in'] ?? null),
                json_encode(['scope' => trim((string)($tokens['scope'] ?? '')), 'revision' => $provider->revision(), 'pkce_verifier_cleared' => true], JSON_UNESCAPED_SLASHES),
                $connectionId,
            ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    $row = mg_integration_connection_row($pdo, $merchantUserId, 'klaviyo', false);
    return $row ? mg_integration_connection_public($row) : [];
}

function mg_klaviyo_connection_credentials(PDO $pdo, int $merchantUserId): array
{
    $stmt = $pdo->prepare("SELECT c.*,cr.access_token_ciphertext,cr.refresh_token_ciphertext,cr.access_expires_at,cr.refresh_lock_token,cr.refresh_lock_expires_at FROM merchant_integration_connections c INNER JOIN merchant_integration_credentials cr ON cr.connection_id=c.id WHERE c.merchant_user_id=? AND c.provider_key='klaviyo' AND c.status IN ('active','error','reauthorization_required') ORDER BY (c.status='active') DESC,c.id DESC LIMIT 1");
    $stmt->execute([$merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('An active Klaviyo connection is required.');
    return $row;
}

function mg_klaviyo_access_credentials(PDO $pdo, int $merchantUserId): array
{
    $row = mg_klaviyo_connection_credentials($pdo, $merchantUserId);
    $expiresAt = strtotime((string)($row['access_expires_at'] ?? '')) ?: 0;
    if ($expiresAt > time() + 120) {
        $token = mg_integration_decrypt_secret($row['access_token_ciphertext'] ?? null);
        if ($token !== '') return ['connection' => $row, 'token' => $token];
    }
    return mg_klaviyo_refresh_credentials($pdo, $row);
}

function mg_klaviyo_refresh_credentials(PDO $pdo, array $connection): array
{
    $connectionId = (int)$connection['id'];
    $merchantUserId = (int)$connection['merchant_user_id'];
    $lockToken = bin2hex(random_bytes(20));
    $refreshToken = '';

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM merchant_integration_credentials WHERE connection_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$connectionId]);
        $credential = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$credential) throw new RuntimeException('Klaviyo credentials are unavailable.');
        $expiresAt = strtotime((string)($credential['access_expires_at'] ?? '')) ?: 0;
        if ($expiresAt > time() + 120) {
            $token = mg_integration_decrypt_secret($credential['access_token_ciphertext'] ?? null);
            $pdo->commit();
            if ($token !== '') return ['connection' => $connection, 'token' => $token];
        }
        $existingLockExpires = strtotime((string)($credential['refresh_lock_expires_at'] ?? '')) ?: 0;
        if (trim((string)($credential['refresh_lock_token'] ?? '')) !== '' && $existingLockExpires > time()) throw new RuntimeException('Klaviyo token refresh is already in progress.');
        $refreshToken = mg_integration_decrypt_secret($credential['refresh_token_ciphertext'] ?? null);
        if ($refreshToken === '') throw new MgIntegrationCredentialException('Stored Klaviyo refresh credentials are unavailable.');
        $pdo->prepare('UPDATE merchant_integration_credentials SET refresh_lock_token=?,refresh_lock_expires_at=DATE_ADD(NOW(),INTERVAL 60 SECOND),updated_at=NOW() WHERE connection_id=?')
            ->execute([$lockToken, $connectionId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    try {
        $tokens = mg_klaviyo_provider()->refreshAccessToken($refreshToken);
        $accessToken = trim((string)($tokens['access_token'] ?? ''));
        $newRefreshToken = trim((string)($tokens['refresh_token'] ?? '')) ?: $refreshToken;
        if ($accessToken === '') throw new RuntimeException('Klaviyo did not return a refreshed access token.');
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT refresh_lock_token FROM merchant_integration_credentials WHERE connection_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$connectionId]);
        $locked = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$locked || !hash_equals($lockToken, (string)($locked['refresh_lock_token'] ?? ''))) throw new RuntimeException('Klaviyo token refresh lock was lost.');
        $pdo->prepare('UPDATE merchant_integration_credentials SET access_token_ciphertext=?,refresh_token_ciphertext=?,access_expires_at=?,refresh_lock_token=NULL,refresh_lock_expires_at=NULL,updated_at=NOW() WHERE connection_id=?')
            ->execute([
                mg_integration_encrypt_secret($accessToken),
                mg_integration_encrypt_secret($newRefreshToken),
                mg_klaviyo_expiry_from_seconds($tokens['expires_in'] ?? null),
                $connectionId,
            ]);
        $pdo->prepare("UPDATE merchant_integration_connections SET status='active',last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_at=NOW() WHERE id=?")
            ->execute([$connectionId]);
        $pdo->commit();
        return ['connection' => mg_klaviyo_connection_credentials($pdo, $merchantUserId), 'token' => $accessToken];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $pdo->prepare('UPDATE merchant_integration_credentials SET refresh_lock_token=NULL,refresh_lock_expires_at=NULL,updated_at=NOW() WHERE connection_id=? AND refresh_lock_token=?')
            ->execute([$connectionId, $lockToken]);
        $pdo->prepare("UPDATE merchant_integration_connections SET status='reauthorization_required',last_error_at=NOW(),last_error_code=?,last_error_message=?,updated_at=NOW() WHERE id=?")
            ->execute([mb_substr($error::class, 0, 120), mb_substr($error->getMessage(), 0, 1000), $connectionId]);
        throw $error;
    }
}
