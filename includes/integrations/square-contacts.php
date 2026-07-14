<?php
declare(strict_types=1);

require_once __DIR__ . '/providers/square.php';

function mg_square_provider(): MgSquareProvider
{
    static $provider;
    if (!$provider instanceof MgSquareProvider) $provider = new MgSquareProvider();
    return $provider;
}

function mg_square_provider_catalog(array $catalog): array
{
    $provider = mg_square_provider();
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
        if ((string)($item['key'] ?? '') !== 'square') continue;
        $catalog[$index] = $entry;
        $replaced = true;
        break;
    }
    if (!$replaced) $catalog[] = $entry;
    return array_values($catalog);
}

function mg_square_datetime(mixed $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $timestamp = strtotime($value);
    return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
}

function mg_square_normalize_contact(array $customer): array
{
    $email = strtolower(trim((string)($customer['email_address'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
    $firstName = trim((string)($customer['given_name'] ?? ''));
    $lastName = trim((string)($customer['family_name'] ?? ''));
    $name = trim($firstName . ' ' . $lastName);
    if ($name === '') $name = trim((string)($customer['nickname'] ?? ''));
    if ($name === '') $name = trim((string)($customer['company_name'] ?? ''));
    $preferences = is_array($customer['preferences'] ?? null) ? $customer['preferences'] : [];
    $emailUnsubscribed = (bool)($preferences['email_unsubscribed'] ?? false);
    $normalized = [
        'external_id' => trim((string)($customer['id'] ?? '')),
        'email' => $email,
        'display_name' => $name !== '' ? mb_substr($name, 0, 180) : ($email !== '' ? mb_substr($email, 0, 180) : 'Square customer'),
        'first_name' => mb_substr($firstName, 0, 100),
        'last_name' => mb_substr($lastName, 0, 100),
        'nickname' => mb_substr(trim((string)($customer['nickname'] ?? '')), 0, 100),
        'company_name' => mb_substr(trim((string)($customer['company_name'] ?? '')), 0, 180),
        'created_on' => mg_square_datetime($customer['created_at'] ?? null),
        'updated_on' => mg_square_datetime($customer['updated_at'] ?? null),
        'reference_id' => mb_substr(trim((string)($customer['reference_id'] ?? '')), 0, 255),
        'creation_source' => mb_substr(trim((string)($customer['creation_source'] ?? '')), 0, 80),
        'version' => max(0, (int)($customer['version'] ?? 0)),
        'group_ids' => array_values(array_slice(array_filter(array_map('strval', (array)($customer['group_ids'] ?? []))), 0, 100)),
        'segment_ids' => array_values(array_slice(array_filter(array_map('strval', (array)($customer['segment_ids'] ?? []))), 0, 100)),
        'marketing' => [
            'accepts_marketing' => false,
            'status' => $emailUnsubscribed ? 'UNSUBSCRIBED' : 'UNKNOWN',
            'email_unsubscribed' => $emailUnsubscribed,
            'source' => 'square',
            'inferred' => false,
        ],
        'addresses_excluded' => true,
        'phone_numbers_excluded' => true,
        'birthdays_excluded' => true,
        'notes_excluded' => true,
    ];
    $normalized['sync_hash'] = hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return $normalized;
}

function mg_square_begin_oauth(PDO $pdo, int $merchantUserId): array
{
    if (!mg_integration_schema_ready($pdo)) throw new RuntimeException('Merchant integrations schema is not installed.');
    if (mg_integration_credential_master_key() === null) throw new MgIntegrationCredentialException('Integration credential encryption is not configured.');
    $provider = mg_square_provider();
    if (!$provider->isConfigured()) throw new RuntimeException('Square OAuth is not configured.');
    $state = bin2hex(random_bytes(32));
    $stateHash = hash('sha256', $state);

    $pdo->beginTransaction();
    try {
        $connection = mg_integration_connection_row($pdo, $merchantUserId, 'square', true);
        if (!$connection || (string)($connection['status'] ?? '') === 'disconnected') {
            $pdo->prepare("INSERT INTO merchant_integration_connections (public_id,merchant_user_id,provider_key,auth_type,status,sync_direction,scopes_json,settings_json,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,'oauth2','pending','import_only',?,?,?,?,NOW(),NOW())")
                ->execute([
                    mg_integration_uuid(),
                    $merchantUserId,
                    'square',
                    json_encode($provider->scopes(), JSON_UNESCAPED_SLASHES),
                    json_encode(['environment' => $provider->environment()], JSON_UNESCAPED_SLASHES),
                    $merchantUserId,
                    $merchantUserId,
                ]);
            $connectionId = (int)$pdo->lastInsertId();
        } else {
            $connectionId = (int)$connection['id'];
            $pdo->prepare("UPDATE merchant_integration_connections SET status='pending',scopes_json=?,settings_json=JSON_SET(COALESCE(settings_json,'{}'),'$.environment',?),last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_by_user_id=?,updated_at=NOW() WHERE id=?")
                ->execute([json_encode($provider->scopes(), JSON_UNESCAPED_SLASHES), $provider->environment(), $merchantUserId, $connectionId]);
        }
        $pdo->prepare("INSERT INTO merchant_integration_credentials (connection_id,oauth_state_hash,oauth_state_expires_at,metadata_json,created_at,updated_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE),?,NOW(),NOW()) ON DUPLICATE KEY UPDATE oauth_state_hash=VALUES(oauth_state_hash),oauth_state_expires_at=VALUES(oauth_state_expires_at),metadata_json=JSON_MERGE_PATCH(COALESCE(metadata_json,'{}'),VALUES(metadata_json)),updated_at=NOW()")
            ->execute([$connectionId, $stateHash, json_encode(['environment' => $provider->environment(), 'oauth_started_at' => gmdate('c')], JSON_UNESCAPED_SLASHES)]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    return [
        'provider' => 'square',
        'environment' => $provider->environment(),
        'authorization_url' => $provider->buildAuthorizationUrl($state),
        'state_expires_in' => 600,
    ];
}

function mg_square_pending_oauth(PDO $pdo, int $merchantUserId, string $stateHash, bool $forUpdate = false): ?array
{
    $sql = "SELECT c.*,cr.oauth_state_hash,cr.oauth_state_expires_at,cr.metadata_json credential_metadata_json
            FROM merchant_integration_connections c
            INNER JOIN merchant_integration_credentials cr ON cr.connection_id=c.id
            WHERE c.merchant_user_id=? AND c.provider_key='square' AND cr.oauth_state_hash=? AND cr.oauth_state_expires_at>=NOW()
            ORDER BY c.id DESC LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$merchantUserId, $stateHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_square_complete_oauth(PDO $pdo, int $merchantUserId, string $state, string $code): array
{
    $state = trim($state);
    $code = trim($code);
    if ($state === '' || $code === '') throw new RuntimeException('Square did not return a valid authorization response.');
    $stateHash = hash('sha256', $state);
    if (!mg_square_pending_oauth($pdo, $merchantUserId, $stateHash, false)) throw new RuntimeException('The Square authorization request expired or does not match this account.');

    $provider = mg_square_provider();
    $tokenResponse = $provider->exchangeAuthorizationCode($code);
    $accessToken = trim((string)($tokenResponse['access_token'] ?? ''));
    $refreshToken = trim((string)($tokenResponse['refresh_token'] ?? ''));
    $merchantId = trim((string)($tokenResponse['merchant_id'] ?? ''));
    if ($accessToken === '' || $refreshToken === '' || $merchantId === '') throw new RuntimeException('Square did not return complete OAuth credentials.');
    $merchant = $provider->fetchMerchant($merchantId, $accessToken);
    $accessExpiresAt = mg_square_datetime($tokenResponse['expires_at'] ?? null);
    $settings = [
        'merchant' => $merchant,
        'environment' => $provider->environment(),
        'contact_sync' => 'import_only',
        'addresses_imported' => false,
        'phone_numbers_imported' => false,
        'birthdays_imported' => false,
        'notes_imported' => false,
        'marketing_consent_mode' => 'preserve_unsubscribe_only',
    ];

    $pdo->beginTransaction();
    try {
        $locked = mg_square_pending_oauth($pdo, $merchantUserId, $stateHash, true);
        if (!$locked) throw new RuntimeException('The Square authorization request was already used or expired.');
        $connectionId = (int)$locked['id'];
        $pdo->prepare("UPDATE merchant_integration_connections SET status='active',external_account_id=?,external_account_name=?,external_account_url=NULL,scopes_json=?,settings_json=?,connected_at=COALESCE(connected_at,NOW()),disconnected_at=NULL,last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_by_user_id=?,updated_at=NOW() WHERE id=?")
            ->execute([
                $merchantId,
                trim((string)($merchant['business_name'] ?? $merchant['country'] ?? 'Square merchant')),
                json_encode($provider->scopes(), JSON_UNESCAPED_SLASHES),
                json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $merchantUserId,
                $connectionId,
            ]);
        $pdo->prepare("UPDATE merchant_integration_credentials SET access_token_ciphertext=?,refresh_token_ciphertext=?,access_expires_at=?,refresh_expires_at=NULL,oauth_state_hash=NULL,oauth_state_expires_at=NULL,refresh_lock_token=NULL,refresh_lock_expires_at=NULL,metadata_json=?,updated_at=NOW() WHERE connection_id=?")
            ->execute([
                mg_integration_encrypt_secret($accessToken),
                mg_integration_encrypt_secret($refreshToken),
                $accessExpiresAt,
                json_encode(['environment' => $provider->environment(), 'token_type' => (string)($tokenResponse['token_type'] ?? 'bearer'), 'jwt_requested' => true], JSON_UNESCAPED_SLASHES),
                $connectionId,
            ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    $row = mg_integration_connection_row($pdo, $merchantUserId, 'square', false);
    return $row ? mg_integration_connection_public($row) : [];
}

function mg_square_connection_credentials_row(PDO $pdo, int $merchantUserId): array
{
    $stmt = $pdo->prepare("SELECT c.*,cr.access_token_ciphertext,cr.refresh_token_ciphertext,cr.access_expires_at,cr.refresh_lock_token,cr.refresh_lock_expires_at,cr.metadata_json credential_metadata_json FROM merchant_integration_connections c INNER JOIN merchant_integration_credentials cr ON cr.connection_id=c.id WHERE c.merchant_user_id=? AND c.provider_key='square' AND c.status IN ('active','error','reauthorization_required') ORDER BY (c.status='active') DESC,c.id DESC LIMIT 1");
    $stmt->execute([$merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('An active Square connection is required.');
    return $row;
}

function mg_square_access_credentials(PDO $pdo, int $merchantUserId): array
{
    $row = mg_square_connection_credentials_row($pdo, $merchantUserId);
    $expiresAt = strtotime((string)($row['access_expires_at'] ?? '')) ?: 0;
    if ($expiresAt > time() + 300) {
        $token = mg_integration_decrypt_secret($row['access_token_ciphertext'] ?? null);
        if ($token !== '') return ['connection' => $row, 'token' => $token];
    }
    return mg_square_refresh_credentials($pdo, $row);
}

function mg_square_refresh_credentials(PDO $pdo, array $connection): array
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
        if (!$credential) throw new RuntimeException('Square credentials are unavailable.');
        $expiresAt = strtotime((string)($credential['access_expires_at'] ?? '')) ?: 0;
        if ($expiresAt > time() + 300) {
            $token = mg_integration_decrypt_secret($credential['access_token_ciphertext'] ?? null);
            $pdo->commit();
            if ($token !== '') return ['connection' => $connection, 'token' => $token];
        }
        $existingLockExpires = strtotime((string)($credential['refresh_lock_expires_at'] ?? '')) ?: 0;
        if (trim((string)($credential['refresh_lock_token'] ?? '')) !== '' && $existingLockExpires > time()) {
            throw new RuntimeException('Square token refresh is already in progress.');
        }
        $refreshToken = mg_integration_decrypt_secret($credential['refresh_token_ciphertext'] ?? null);
        if ($refreshToken === '') throw new MgIntegrationCredentialException('Stored Square refresh credentials are unavailable.');
        $pdo->prepare('UPDATE merchant_integration_credentials SET refresh_lock_token=?,refresh_lock_expires_at=DATE_ADD(NOW(),INTERVAL 90 SECOND),updated_at=NOW() WHERE connection_id=?')
            ->execute([$lockToken, $connectionId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    try {
        $response = mg_square_provider()->refreshAccessToken($refreshToken);
        $accessToken = trim((string)($response['access_token'] ?? ''));
        $newRefreshToken = trim((string)($response['refresh_token'] ?? '')) ?: $refreshToken;
        if ($accessToken === '') throw new RuntimeException('Square did not return a refreshed access token.');
        $accessExpiresAt = mg_square_datetime($response['expires_at'] ?? null);

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT refresh_lock_token FROM merchant_integration_credentials WHERE connection_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$connectionId]);
        $locked = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$locked || !hash_equals($lockToken, (string)($locked['refresh_lock_token'] ?? ''))) throw new RuntimeException('Square token refresh lock was lost.');
        $pdo->prepare('UPDATE merchant_integration_credentials SET access_token_ciphertext=?,refresh_token_ciphertext=?,access_expires_at=?,refresh_lock_token=NULL,refresh_lock_expires_at=NULL,updated_at=NOW() WHERE connection_id=?')
            ->execute([
                mg_integration_encrypt_secret($accessToken),
                mg_integration_encrypt_secret($newRefreshToken),
                $accessExpiresAt,
                $connectionId,
            ]);
        $pdo->prepare("UPDATE merchant_integration_connections SET status='active',last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_at=NOW() WHERE id=?")
            ->execute([$connectionId]);
        $pdo->commit();
        $fresh = mg_square_connection_credentials_row($pdo, $merchantUserId);
        return ['connection' => $fresh, 'token' => $accessToken];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $pdo->prepare('UPDATE merchant_integration_credentials SET refresh_lock_token=NULL,refresh_lock_expires_at=NULL,updated_at=NOW() WHERE connection_id=? AND refresh_lock_token=?')
            ->execute([$connectionId, $lockToken]);
        $pdo->prepare("UPDATE merchant_integration_connections SET status='reauthorization_required',last_error_at=NOW(),last_error_code=?,last_error_message=?,updated_at=NOW() WHERE id=?")
            ->execute([mb_substr($error::class, 0, 120), mb_substr($error->getMessage(), 0, 1000), $connectionId]);
        throw $error;
    }
}

function mg_square_contact_match(PDO $pdo, int $merchantUserId, string $email): ?array
{
    if ($email === '') return null;
    $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE merchant_user_id=? AND primary_email=? LIMIT 1');
    $stmt->execute([$merchantUserId, $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return function_exists('mg_crm_identity_resolve_contact') ? mg_crm_identity_resolve_contact($pdo, $merchantUserId, $row, false) : $row;
    return function_exists('mg_crm_identity_alias_contact') ? mg_crm_identity_alias_contact($pdo, $merchantUserId, null, $email, null, false) : null;
}

function mg_square_contact_link(PDO $pdo, int $connectionId, string $externalId, bool $forUpdate = false): ?array
{
    $sql = "SELECT * FROM merchant_integration_entity_links WHERE connection_id=? AND entity_type='contact' AND external_entity_id=? LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$connectionId, $externalId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_square_contact_preview(PDO $pdo, int $merchantUserId, ?string $cursor = null, int $pageSize = 50): array
{
    $auth = mg_square_access_credentials($pdo, $merchantUserId);
    $page = mg_square_provider()->listCustomers($auth['token'], $cursor, max(1, min(100, $pageSize)));
    $connection = $auth['connection'];
    $items = [];
    foreach ((array)($page['customers'] ?? []) as $raw) {
        if (!is_array($raw)) continue;
        $contact = mg_square_normalize_contact($raw);
        $link = $contact['external_id'] !== '' ? mg_square_contact_link($pdo, (int)$connection['id'], $contact['external_id']) : null;
        $matched = $contact['email'] !== '' ? mg_square_contact_match($pdo, $merchantUserId, $contact['email']) : null;
        $action = 'create';
        $reason = 'New CRM contact';
        if ($contact['external_id'] === '' || $contact['email'] === '') {
            $action = 'review';
            $reason = 'Missing a valid customer ID or email';
        } elseif ($link) {
            $action = hash_equals((string)($link['sync_hash'] ?? ''), (string)$contact['sync_hash']) ? 'unchanged' : 'update';
            $reason = $action === 'unchanged' ? 'Already synchronized' : 'Linked Square customer changed';
        } elseif ($matched) {
            $action = 'link';
            $reason = 'Exact CRM email match';
        }
        $items[] = [
            'external_id' => $contact['external_id'],
            'email' => $contact['email'],
            'name' => $contact['display_name'],
            'accepts_marketing' => false,
            'marketing_status' => $contact['marketing']['status'],
            'action' => $action,
            'reason' => $reason,
            'addresses_excluded' => true,
            'phone_numbers_excluded' => true,
        ];
    }
    return [
        'provider' => 'square',
        'connection_id' => (string)$connection['public_id'],
        'items' => $items,
        'page_count' => count($items),
        'pagination' => is_array($page['pagination'] ?? null) ? $page['pagination'] : [],
        'policy' => [
            'addresses_imported' => false,
            'phone_numbers_imported' => false,
            'birthdays_imported' => false,
            'notes_imported' => false,
            'marketing_unsubscribe_preserved' => true,
            'marketing_consent_inferred' => false,
        ],
    ];
}

function mg_square_contact_metadata(array $existing, array $contact, string $connectionPublicId): array
{
    $metadata = mg_integration_json($existing['metadata_json'] ?? null);
    $integrations = is_array($metadata['integrations'] ?? null) ? $metadata['integrations'] : [];
    $integrations['square'] = [
        'connection_id' => $connectionPublicId,
        'external_customer_id' => $contact['external_id'],
        'created_on' => $contact['created_on'],
        'updated_on' => $contact['updated_on'],
        'reference_id' => $contact['reference_id'],
        'creation_source' => $contact['creation_source'],
        'version' => $contact['version'],
        'company_name' => $contact['company_name'],
        'marketing' => $contact['marketing'],
        'group_ids' => $contact['group_ids'],
        'segment_ids' => $contact['segment_ids'],
        'addresses_excluded' => true,
        'phone_numbers_excluded' => true,
        'birthdays_excluded' => true,
        'notes_excluded' => true,
        'verified_at' => gmdate('Y-m-d H:i:s'),
    ];
    $metadata['integrations'] = $integrations;
    return $metadata;
}

function mg_square_upsert_link(PDO $pdo, int $connectionId, array $contact, ?int $localContactId, string $status, array $metadata): void
{
    $pdo->prepare("INSERT INTO merchant_integration_entity_links (public_id,connection_id,entity_type,external_entity_id,local_entity_type,local_entity_id,external_updated_at,last_synced_at,sync_hash,status,metadata_json,created_at,updated_at) VALUES (?,?,'contact',?,'merchant_crm_contact',?,?,NOW(),?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE local_entity_id=VALUES(local_entity_id),external_updated_at=VALUES(external_updated_at),last_synced_at=NOW(),sync_hash=VALUES(sync_hash),status=VALUES(status),metadata_json=VALUES(metadata_json),updated_at=NOW()")
        ->execute([
            mg_integration_uuid(),
            $connectionId,
            $contact['external_id'],
            $localContactId,
            $contact['updated_on'] ?? $contact['created_on'],
            $contact['sync_hash'],
            $status,
            json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
}

function mg_square_import_contact(PDO $pdo, array $connection, array $rawCustomer, string $triggerType = 'manual'): string
{
    $merchantUserId = (int)$connection['merchant_user_id'];
    $connectionId = (int)$connection['id'];
    $contact = mg_square_normalize_contact($rawCustomer);
    if ($contact['external_id'] === '') return 'failed';

    $pdo->beginTransaction();
    try {
        $link = mg_square_contact_link($pdo, $connectionId, $contact['external_id'], true);
        if ($link && (string)($link['sync_hash'] ?? '') === (string)$contact['sync_hash'] && (string)($link['status'] ?? '') === 'linked') {
            $pdo->commit();
            return 'skipped';
        }
        if ($contact['email'] === '') {
            mg_square_upsert_link($pdo, $connectionId, $contact, null, 'pending_review', ['review_reason' => 'missing_valid_email', 'snapshot' => $contact]);
            $pdo->commit();
            return 'review';
        }

        $local = null;
        if ($link && (int)($link['local_entity_id'] ?? 0) > 0) {
            $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE id=? AND merchant_user_id=? LIMIT 1 FOR UPDATE');
            $stmt->execute([(int)$link['local_entity_id'], $merchantUserId]);
            $local = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($local && function_exists('mg_crm_identity_resolve_contact')) $local = mg_crm_identity_resolve_contact($pdo, $merchantUserId, $local, true);
        }
        if (!$local) $local = mg_merchant_crm_contact($pdo, $merchantUserId, null, $contact['email'], null);

        if ($local) {
            $localId = (int)$local['id'];
            $localEmail = strtolower(trim((string)($local['primary_email'] ?? '')));
            $emailOwner = mg_square_contact_match($pdo, $merchantUserId, $contact['email']);
            if ($emailOwner && (int)$emailOwner['id'] !== $localId) {
                mg_square_upsert_link($pdo, $connectionId, $contact, $localId, 'conflict', ['review_reason' => 'email_owned_by_another_contact', 'email_owner_contact_id' => (int)$emailOwner['id'], 'snapshot' => $contact]);
                $pdo->commit();
                return 'review';
            }
            if ($localEmail !== '' && $localEmail !== $contact['email']) {
                mg_square_upsert_link($pdo, $connectionId, $contact, $localId, 'conflict', ['review_reason' => 'linked_email_changed', 'local_email' => $localEmail, 'external_email' => $contact['email'], 'snapshot' => $contact]);
                $pdo->commit();
                return 'review';
            }
            $metadata = mg_square_contact_metadata($local, $contact, (string)$connection['public_id']);
            $updateName = trim((string)($local['display_name'] ?? '')) === '' || (string)($local['last_source_type'] ?? '') === 'square_customer';
            $pdo->prepare('UPDATE merchant_crm_contacts SET primary_email=COALESCE(primary_email,?),display_name=IF(?, ?, display_name),last_source_type=?,last_seen_at=GREATEST(last_seen_at,COALESCE(?,NOW())),source_summary_json=?,metadata_json=?,updated_at=NOW() WHERE id=?')
                ->execute([
                    $contact['email'],
                    $updateName ? 1 : 0,
                    $contact['display_name'],
                    'square_customer',
                    $contact['updated_on'] ?? $contact['created_on'],
                    json_encode(['last_event_type' => 'square_customer_synced', 'provider' => 'square'], JSON_UNESCAPED_SLASHES),
                    json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $localId,
                ]);
            $action = $link ? 'updated' : 'linked';
        } else {
            $metadata = mg_square_contact_metadata([], $contact, (string)$connection['public_id']);
            $firstSeen = $contact['created_on'] ?: gmdate('Y-m-d H:i:s');
            $lastSeen = $contact['updated_on'] ?: $firstSeen;
            $pdo->prepare("INSERT INTO merchant_crm_contacts (public_id,merchant_user_id,user_id,primary_email,primary_phone,display_name,lifecycle_stage,crm_status,last_campaign_type,last_source_type,first_seen_at,last_seen_at,last_engaged_at,source_summary_json,tags_json,metadata_json,created_at,updated_at) VALUES (?,?,NULL,?,NULL,?,'lead','active','non_campaign','square_customer',?,?,NULL,?,'[]',?,NOW(),NOW())")
                ->execute([
                    mg_merchant_crm_uuid(),
                    $merchantUserId,
                    $contact['email'],
                    $contact['display_name'],
                    $firstSeen,
                    $lastSeen,
                    json_encode(['last_event_type' => 'square_customer_imported', 'provider' => 'square'], JSON_UNESCAPED_SLASHES),
                    json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]);
            $localId = (int)$pdo->lastInsertId();
            $action = 'created';
        }

        mg_square_upsert_link($pdo, $connectionId, $contact, $localId, 'linked', ['snapshot' => $contact, 'match' => $action, 'trigger' => $triggerType]);
        $pdo->prepare('INSERT INTO merchant_crm_contact_events (public_id,merchant_user_id,crm_contact_id,campaign_id,campaign_type,event_type,source_type,source_public_id,user_id,email,phone,name,value_cents,metadata_json,created_at) VALUES (?,?,?,NULL,?,?,?,?,NULL,?,NULL,?,NULL,?,NOW())')
            ->execute([
                mg_merchant_crm_uuid(),
                $merchantUserId,
                $localId,
                'non_campaign',
                'square_customer_' . $action,
                'square_customer',
                mb_substr($contact['external_id'], 0, 80),
                $contact['email'],
                $contact['display_name'],
                json_encode(['trigger' => $triggerType, 'connection_id' => (string)$connection['public_id'], 'marketing' => $contact['marketing'], 'addresses_excluded' => true, 'phone_numbers_excluded' => true], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        $pdo->commit();
        return $action;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_square_sync_contacts(PDO $pdo, int $merchantUserId, bool $reset = false, int $pageSize = 100, int $maxPages = 5): array
{
    $auth = mg_square_access_credentials($pdo, $merchantUserId);
    $connection = $auth['connection'];
    $connectionId = (int)$connection['id'];
    $pageSize = max(1, min(100, $pageSize));
    $maxPages = max(1, min(10, $maxPages));
    $stateStmt = $pdo->prepare("SELECT * FROM merchant_integration_sync_state WHERE connection_id=? AND resource_key='contacts' LIMIT 1");
    $stateStmt->execute([$connectionId]);
    $state = $stateStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $cursor = $reset ? null : (trim((string)($state['cursor_value'] ?? '')) ?: null);
    $runPublicId = mg_integration_uuid();
    $pdo->prepare("INSERT INTO merchant_integration_sync_runs (public_id,connection_id,resource_key,direction,trigger_type,status,cursor_value,started_at,created_at,updated_at) VALUES (?,?,'contacts','import','manual','running',?,NOW(),NOW(),NOW())")
        ->execute([$runPublicId, $connectionId, $cursor]);
    $runId = (int)$pdo->lastInsertId();
    $counts = ['processed' => 0, 'created' => 0, 'updated' => 0, 'linked' => 0, 'review' => 0, 'skipped' => 0, 'failed' => 0];
    $hasNext = false;

    try {
        for ($pageIndex = 0; $pageIndex < $maxPages; $pageIndex++) {
            $page = mg_square_provider()->listCustomers($auth['token'], $cursor, $pageSize);
            foreach ((array)($page['customers'] ?? []) as $raw) {
                if (!is_array($raw)) continue;
                $counts['processed']++;
                try {
                    $result = mg_square_import_contact($pdo, $connection, $raw, 'manual');
                    if (isset($counts[$result])) $counts[$result]++; else $counts['updated']++;
                } catch (Throwable $contactError) {
                    $counts['failed']++;
                    mg_security_log('warning', 'merchant.integration.square_customer_failed', 'Square customer import failed.', ['exception_class' => $contactError::class], $merchantUserId);
                }
            }
            $pagination = is_array($page['pagination'] ?? null) ? $page['pagination'] : [];
            $hasNext = (bool)($pagination['has_next_page'] ?? false);
            $cursor = trim((string)($pagination['next_cursor'] ?? '')) ?: null;
            $pdo->prepare("INSERT INTO merchant_integration_sync_state (connection_id,resource_key,cursor_value,last_attempt_at,metadata_json,created_at,updated_at) VALUES (?,'contacts',?,NOW(),?,NOW(),NOW()) ON DUPLICATE KEY UPDATE cursor_value=VALUES(cursor_value),last_attempt_at=NOW(),metadata_json=VALUES(metadata_json),updated_at=NOW()")
                ->execute([$connectionId, $hasNext ? $cursor : null, json_encode(['has_next_page' => $hasNext, 'addresses_excluded' => true, 'marketing_unsubscribe_preserved' => true], JSON_UNESCAPED_SLASHES)]);
            if (!$hasNext || $cursor === null) break;
        }
        $status = $counts['failed'] > 0 ? 'partial' : 'completed';
        $pdo->prepare('UPDATE merchant_integration_sync_runs SET status=?,cursor_value=?,processed_count=?,created_count=?,updated_count=?,skipped_count=?,failed_count=?,finished_at=NOW(),updated_at=NOW() WHERE id=?')
            ->execute([$status, $hasNext ? $cursor : null, $counts['processed'], $counts['created'], $counts['updated'] + $counts['linked'], $counts['skipped'] + $counts['review'], $counts['failed'], $runId]);
        $pdo->prepare("UPDATE merchant_integration_sync_state SET last_success_at=NOW(),last_error_code=NULL,last_error_message=NULL,updated_at=NOW() WHERE connection_id=? AND resource_key='contacts'")->execute([$connectionId]);
        $pdo->prepare('UPDATE merchant_integration_connections SET last_sync_at=NOW(),last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_at=NOW() WHERE id=?')->execute([$connectionId]);
        return ['run_id' => $runPublicId, 'status' => $status, 'counts' => $counts, 'has_more' => $hasNext, 'next_cursor_saved' => $hasNext, 'addresses_imported' => false, 'marketing_unsubscribe_preserved' => true, 'marketing_consent_inferred' => false];
    } catch (Throwable $error) {
        $pdo->prepare("UPDATE merchant_integration_sync_runs SET status='failed',error_code=?,error_message=?,processed_count=?,created_count=?,updated_count=?,skipped_count=?,failed_count=?,finished_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([mb_substr($error::class, 0, 120), mb_substr($error->getMessage(), 0, 1000), $counts['processed'], $counts['created'], $counts['updated'] + $counts['linked'], $counts['skipped'] + $counts['review'], $counts['failed'], $runId]);
        throw $error;
    }
}

function mg_square_contacts_status(PDO $pdo, int $merchantUserId): array
{
    if (!mg_integration_schema_ready($pdo)) return ['connected' => false, 'counts' => []];
    $connection = mg_integration_connection_row($pdo, $merchantUserId, 'square', false);
    if (!$connection) return ['connected' => false, 'counts' => []];
    $connectionId = (int)$connection['id'];
    $stmt = $pdo->prepare("SELECT status,COUNT(*) total FROM merchant_integration_entity_links WHERE connection_id=? AND entity_type='contact' GROUP BY status");
    $stmt->execute([$connectionId]);
    $counts = ['linked' => 0, 'pending_review' => 0, 'conflict' => 0, 'deleted_external' => 0, 'error' => 0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $counts[(string)$row['status']] = (int)$row['total'];
    $runStmt = $pdo->prepare("SELECT public_id,status,processed_count,created_count,updated_count,skipped_count,failed_count,started_at,finished_at FROM merchant_integration_sync_runs WHERE connection_id=? AND resource_key='contacts' ORDER BY id DESC LIMIT 1");
    $runStmt->execute([$connectionId]);
    return [
        'connected' => (string)$connection['status'] === 'active',
        'connection_status' => (string)$connection['status'],
        'counts' => $counts,
        'total_contacts' => array_sum($counts),
        'last_sync_at' => $connection['last_sync_at'] ?? null,
        'last_run' => $runStmt->fetch(PDO::FETCH_ASSOC) ?: null,
        'policy' => [
            'addresses_imported' => false,
            'phone_numbers_imported' => false,
            'birthdays_imported' => false,
            'notes_imported' => false,
            'marketing_unsubscribe_preserved' => true,
            'marketing_consent_inferred' => false,
        ],
    ];
}
