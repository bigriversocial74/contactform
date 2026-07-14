<?php
declare(strict_types=1);

require_once __DIR__ . '/providers/hubspot.php';

function mg_hubspot_provider(): MgHubSpotProvider
{
    static $provider;
    if (!$provider instanceof MgHubSpotProvider) $provider = new MgHubSpotProvider();
    return $provider;
}

function mg_hubspot_provider_catalog(array $catalog): array
{
    $provider = mg_hubspot_provider();
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
        if ((string)($item['key'] ?? '') !== 'hubspot') continue;
        $catalog[$index] = $entry;
        $replaced = true;
        break;
    }
    if (!$replaced) $catalog[] = $entry;
    return array_values($catalog);
}

function mg_hubspot_datetime(mixed $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    if (ctype_digit($value) && strlen($value) >= 10) {
        $timestamp = (int)$value;
        if ($timestamp > 9999999999) $timestamp = (int)floor($timestamp / 1000);
        return $timestamp > 0 ? gmdate('Y-m-d H:i:s', $timestamp) : null;
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
}

function mg_hubspot_expiry_from_seconds(mixed $seconds): ?string
{
    if (!is_numeric($seconds)) return null;
    $ttl = max(60, (int)$seconds);
    return gmdate('Y-m-d H:i:s', time() + $ttl);
}

function mg_hubspot_normalize_contact(array $record): array
{
    $properties = is_array($record['properties'] ?? null) ? $record['properties'] : [];
    $email = strtolower(trim((string)($properties['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
    $firstName = trim((string)($properties['firstname'] ?? ''));
    $lastName = trim((string)($properties['lastname'] ?? ''));
    $name = trim($firstName . ' ' . $lastName);
    $created = mg_hubspot_datetime($properties['createdate'] ?? $record['createdAt'] ?? null);
    $updated = mg_hubspot_datetime($properties['lastmodifieddate'] ?? $record['updatedAt'] ?? null);
    $normalized = [
        'external_id' => trim((string)($record['id'] ?? $properties['hs_object_id'] ?? '')),
        'email' => $email,
        'display_name' => $name !== '' ? mb_substr($name, 0, 180) : ($email !== '' ? mb_substr($email, 0, 180) : 'HubSpot contact'),
        'first_name' => mb_substr($firstName, 0, 100),
        'last_name' => mb_substr($lastName, 0, 100),
        'lifecycle_stage' => mb_substr(trim((string)($properties['lifecyclestage'] ?? '')), 0, 80),
        'created_on' => $created,
        'updated_on' => $updated,
        'archived' => (bool)($record['archived'] ?? false),
        'marketing' => [
            'accepts_marketing' => false,
            'status' => 'UNKNOWN',
            'source' => 'hubspot_contact_record',
            'inferred' => false,
            'subscription_preferences_imported' => false,
        ],
        'addresses_excluded' => true,
        'phone_numbers_excluded' => true,
    ];
    $normalized['sync_hash'] = hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return $normalized;
}

function mg_hubspot_begin_oauth(PDO $pdo, int $merchantUserId): array
{
    if (!mg_integration_schema_ready($pdo)) throw new RuntimeException('Merchant integrations schema is not installed.');
    if (mg_integration_credential_master_key() === null) throw new MgIntegrationCredentialException('Integration credential encryption is not configured.');
    $provider = mg_hubspot_provider();
    if (!$provider->isConfigured()) throw new RuntimeException('HubSpot OAuth is not configured.');

    $state = bin2hex(random_bytes(32));
    $stateHash = hash('sha256', $state);
    $pdo->beginTransaction();
    try {
        $connection = mg_integration_connection_row($pdo, $merchantUserId, 'hubspot', true);
        if (!$connection || (string)($connection['status'] ?? '') === 'disconnected') {
            $pdo->prepare("INSERT INTO merchant_integration_connections (public_id,merchant_user_id,provider_key,auth_type,status,sync_direction,scopes_json,settings_json,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,'oauth2','pending','import_only',?,?,?,?,NOW(),NOW())")
                ->execute([
                    mg_integration_uuid(),
                    $merchantUserId,
                    'hubspot',
                    json_encode($provider->scopes(), JSON_UNESCAPED_SLASHES),
                    json_encode(['contact_sync' => 'import_only', 'marketing_consent_mode' => 'unknown'], JSON_UNESCAPED_SLASHES),
                    $merchantUserId,
                    $merchantUserId,
                ]);
            $connectionId = (int)$pdo->lastInsertId();
        } else {
            $connectionId = (int)$connection['id'];
            $pdo->prepare("UPDATE merchant_integration_connections SET status='pending',scopes_json=?,last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_by_user_id=?,updated_at=NOW() WHERE id=?")
                ->execute([json_encode($provider->scopes(), JSON_UNESCAPED_SLASHES), $merchantUserId, $connectionId]);
        }
        $pdo->prepare("INSERT INTO merchant_integration_credentials (connection_id,oauth_state_hash,oauth_state_expires_at,metadata_json,created_at,updated_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE),?,NOW(),NOW()) ON DUPLICATE KEY UPDATE oauth_state_hash=VALUES(oauth_state_hash),oauth_state_expires_at=VALUES(oauth_state_expires_at),metadata_json=JSON_MERGE_PATCH(COALESCE(metadata_json,'{}'),VALUES(metadata_json)),updated_at=NOW()")
            ->execute([$connectionId, $stateHash, json_encode(['oauth_started_at' => gmdate('c')], JSON_UNESCAPED_SLASHES)]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    return [
        'provider' => 'hubspot',
        'authorization_url' => $provider->buildAuthorizationUrl($state),
        'state_expires_in' => 600,
    ];
}

function mg_hubspot_pending_oauth(PDO $pdo, int $merchantUserId, string $stateHash, bool $forUpdate = false): ?array
{
    $sql = "SELECT c.*,cr.oauth_state_hash,cr.oauth_state_expires_at FROM merchant_integration_connections c INNER JOIN merchant_integration_credentials cr ON cr.connection_id=c.id WHERE c.merchant_user_id=? AND c.provider_key='hubspot' AND cr.oauth_state_hash=? AND cr.oauth_state_expires_at>=NOW() ORDER BY c.id DESC LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$merchantUserId, $stateHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_hubspot_complete_oauth(PDO $pdo, int $merchantUserId, string $state, string $code): array
{
    $state = trim($state);
    $code = trim($code);
    if ($state === '' || $code === '') throw new RuntimeException('HubSpot did not return a valid authorization response.');
    $stateHash = hash('sha256', $state);
    if (!mg_hubspot_pending_oauth($pdo, $merchantUserId, $stateHash)) throw new RuntimeException('The HubSpot authorization request expired or does not match this account.');

    $provider = mg_hubspot_provider();
    $tokens = $provider->exchangeAuthorizationCode($code);
    $accessToken = trim((string)($tokens['access_token'] ?? ''));
    $refreshToken = trim((string)($tokens['refresh_token'] ?? ''));
    if ($accessToken === '' || $refreshToken === '') throw new RuntimeException('HubSpot did not return complete OAuth credentials.');
    $tokenInfo = $provider->fetchAccessTokenInfo($accessToken);
    $portalId = trim((string)($tokenInfo['hub_id'] ?? $tokenInfo['hubId'] ?? ''));
    if ($portalId === '') throw new RuntimeException('HubSpot did not return a portal identifier.');
    $portalDomain = trim((string)($tokenInfo['hub_domain'] ?? $tokenInfo['hubDomain'] ?? ''));
    $portalName = $portalDomain !== '' ? $portalDomain : 'HubSpot portal ' . $portalId;
    $portalUrl = 'https://app.hubspot.com/contacts/' . rawurlencode($portalId);
    $settings = [
        'portal' => [
            'id' => $portalId,
            'domain' => $portalDomain,
            'user' => trim((string)($tokenInfo['user'] ?? '')),
        ],
        'contact_sync' => 'import_only',
        'addresses_imported' => false,
        'phone_numbers_imported' => false,
        'marketing_consent_mode' => 'unknown',
        'subscription_preferences_imported' => false,
    ];

    $pdo->beginTransaction();
    try {
        $locked = mg_hubspot_pending_oauth($pdo, $merchantUserId, $stateHash, true);
        if (!$locked) throw new RuntimeException('The HubSpot authorization request was already used or expired.');
        $connectionId = (int)$locked['id'];
        $pdo->prepare("UPDATE merchant_integration_connections SET status='active',external_account_id=?,external_account_name=?,external_account_url=?,scopes_json=?,settings_json=?,connected_at=COALESCE(connected_at,NOW()),disconnected_at=NULL,last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_by_user_id=?,updated_at=NOW() WHERE id=?")
            ->execute([
                $portalId,
                $portalName,
                $portalUrl,
                json_encode($provider->scopes(), JSON_UNESCAPED_SLASHES),
                json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $merchantUserId,
                $connectionId,
            ]);
        $pdo->prepare("UPDATE merchant_integration_credentials SET access_token_ciphertext=?,refresh_token_ciphertext=?,token_type=?,access_expires_at=?,refresh_expires_at=NULL,oauth_state_hash=NULL,oauth_state_expires_at=NULL,refresh_lock_token=NULL,refresh_lock_expires_at=NULL,metadata_json=?,updated_at=NOW() WHERE connection_id=?")
            ->execute([
                mg_integration_encrypt_secret($accessToken),
                mg_integration_encrypt_secret($refreshToken),
                trim((string)($tokens['token_type'] ?? 'bearer')) ?: 'bearer',
                mg_hubspot_expiry_from_seconds($tokens['expires_in'] ?? null),
                json_encode(['token_fields' => array_values(array_keys($tokens)), 'portal_id' => $portalId], JSON_UNESCAPED_SLASHES),
                $connectionId,
            ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    $row = mg_integration_connection_row($pdo, $merchantUserId, 'hubspot', false);
    return $row ? mg_integration_connection_public($row) : [];
}

function mg_hubspot_connection_credentials_row(PDO $pdo, int $merchantUserId): array
{
    $stmt = $pdo->prepare("SELECT c.*,cr.access_token_ciphertext,cr.refresh_token_ciphertext,cr.access_expires_at,cr.refresh_lock_token,cr.refresh_lock_expires_at FROM merchant_integration_connections c INNER JOIN merchant_integration_credentials cr ON cr.connection_id=c.id WHERE c.merchant_user_id=? AND c.provider_key='hubspot' AND c.status IN ('active','error','reauthorization_required') ORDER BY (c.status='active') DESC,c.id DESC LIMIT 1");
    $stmt->execute([$merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('An active HubSpot connection is required.');
    return $row;
}

function mg_hubspot_access_credentials(PDO $pdo, int $merchantUserId): array
{
    $row = mg_hubspot_connection_credentials_row($pdo, $merchantUserId);
    $expiresAt = strtotime((string)($row['access_expires_at'] ?? '')) ?: 0;
    if ($expiresAt > time() + 120) {
        $token = mg_integration_decrypt_secret($row['access_token_ciphertext'] ?? null);
        if ($token !== '') return ['connection' => $row, 'token' => $token];
    }
    return mg_hubspot_refresh_credentials($pdo, $row);
}

function mg_hubspot_refresh_credentials(PDO $pdo, array $connection): array
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
        if (!$credential) throw new RuntimeException('HubSpot credentials are unavailable.');
        $expiresAt = strtotime((string)($credential['access_expires_at'] ?? '')) ?: 0;
        if ($expiresAt > time() + 120) {
            $token = mg_integration_decrypt_secret($credential['access_token_ciphertext'] ?? null);
            $pdo->commit();
            if ($token !== '') return ['connection' => $connection, 'token' => $token];
        }
        $existingLockExpires = strtotime((string)($credential['refresh_lock_expires_at'] ?? '')) ?: 0;
        if (trim((string)($credential['refresh_lock_token'] ?? '')) !== '' && $existingLockExpires > time()) throw new RuntimeException('HubSpot token refresh is already in progress.');
        $refreshToken = mg_integration_decrypt_secret($credential['refresh_token_ciphertext'] ?? null);
        if ($refreshToken === '') throw new MgIntegrationCredentialException('Stored HubSpot refresh credentials are unavailable.');
        $pdo->prepare('UPDATE merchant_integration_credentials SET refresh_lock_token=?,refresh_lock_expires_at=DATE_ADD(NOW(),INTERVAL 60 SECOND),updated_at=NOW() WHERE connection_id=?')
            ->execute([$lockToken, $connectionId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    try {
        $tokens = mg_hubspot_provider()->refreshAccessToken($refreshToken);
        $accessToken = trim((string)($tokens['access_token'] ?? ''));
        $newRefreshToken = trim((string)($tokens['refresh_token'] ?? '')) ?: $refreshToken;
        if ($accessToken === '') throw new RuntimeException('HubSpot did not return a refreshed access token.');

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT refresh_lock_token FROM merchant_integration_credentials WHERE connection_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$connectionId]);
        $locked = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$locked || !hash_equals($lockToken, (string)($locked['refresh_lock_token'] ?? ''))) throw new RuntimeException('HubSpot token refresh lock was lost.');
        $pdo->prepare('UPDATE merchant_integration_credentials SET access_token_ciphertext=?,refresh_token_ciphertext=?,access_expires_at=?,refresh_lock_token=NULL,refresh_lock_expires_at=NULL,updated_at=NOW() WHERE connection_id=?')
            ->execute([
                mg_integration_encrypt_secret($accessToken),
                mg_integration_encrypt_secret($newRefreshToken),
                mg_hubspot_expiry_from_seconds($tokens['expires_in'] ?? null),
                $connectionId,
            ]);
        $pdo->prepare("UPDATE merchant_integration_connections SET status='active',last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_at=NOW() WHERE id=?")
            ->execute([$connectionId]);
        $pdo->commit();
        return ['connection' => mg_hubspot_connection_credentials_row($pdo, $merchantUserId), 'token' => $accessToken];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $pdo->prepare('UPDATE merchant_integration_credentials SET refresh_lock_token=NULL,refresh_lock_expires_at=NULL,updated_at=NOW() WHERE connection_id=? AND refresh_lock_token=?')
            ->execute([$connectionId, $lockToken]);
        $pdo->prepare("UPDATE merchant_integration_connections SET status='reauthorization_required',last_error_at=NOW(),last_error_code=?,last_error_message=?,updated_at=NOW() WHERE id=?")
            ->execute([mb_substr($error::class, 0, 120), mb_substr($error->getMessage(), 0, 1000), $connectionId]);
        throw $error;
    }
}

function mg_hubspot_contact_match(PDO $pdo, int $merchantUserId, string $email): ?array
{
    if ($email === '') return null;
    $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE merchant_user_id=? AND primary_email=? LIMIT 1');
    $stmt->execute([$merchantUserId, $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return function_exists('mg_crm_identity_resolve_contact') ? mg_crm_identity_resolve_contact($pdo, $merchantUserId, $row, false) : $row;
    return function_exists('mg_crm_identity_alias_contact') ? mg_crm_identity_alias_contact($pdo, $merchantUserId, null, $email, null, false) : null;
}

function mg_hubspot_contact_link(PDO $pdo, int $connectionId, string $externalId, bool $forUpdate = false): ?array
{
    $sql = "SELECT * FROM merchant_integration_entity_links WHERE connection_id=? AND entity_type='contact' AND external_entity_id=? LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$connectionId, $externalId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_hubspot_contact_preview(PDO $pdo, int $merchantUserId, ?string $cursor = null, int $pageSize = 50): array
{
    $auth = mg_hubspot_access_credentials($pdo, $merchantUserId);
    $page = mg_hubspot_provider()->listContacts($auth['token'], $cursor, max(1, min(100, $pageSize)));
    $connection = $auth['connection'];
    $items = [];
    foreach ((array)($page['contacts'] ?? []) as $raw) {
        if (!is_array($raw)) continue;
        $contact = mg_hubspot_normalize_contact($raw);
        $link = $contact['external_id'] !== '' ? mg_hubspot_contact_link($pdo, (int)$connection['id'], $contact['external_id']) : null;
        $matched = $contact['email'] !== '' ? mg_hubspot_contact_match($pdo, $merchantUserId, $contact['email']) : null;
        $action = 'create';
        $reason = 'New CRM contact';
        if ($contact['external_id'] === '' || $contact['email'] === '') {
            $action = 'review';
            $reason = 'Missing a valid HubSpot contact ID or email';
        } elseif ($link) {
            $action = hash_equals((string)($link['sync_hash'] ?? ''), (string)$contact['sync_hash']) ? 'unchanged' : 'update';
            $reason = $action === 'unchanged' ? 'Already synchronized' : 'Linked HubSpot contact changed';
        } elseif ($matched) {
            $action = 'link';
            $reason = 'Exact CRM email match';
        }
        $items[] = [
            'external_id' => $contact['external_id'],
            'email' => $contact['email'],
            'name' => $contact['display_name'],
            'lifecycle_stage' => $contact['lifecycle_stage'],
            'accepts_marketing' => false,
            'marketing_status' => 'UNKNOWN',
            'action' => $action,
            'reason' => $reason,
            'addresses_excluded' => true,
            'phone_numbers_excluded' => true,
        ];
    }
    return [
        'provider' => 'hubspot',
        'connection_id' => (string)$connection['public_id'],
        'items' => $items,
        'page_count' => count($items),
        'pagination' => is_array($page['pagination'] ?? null) ? $page['pagination'] : [],
        'policy' => [
            'addresses_imported' => false,
            'phone_numbers_imported' => false,
            'marketing_consent_imported' => false,
            'marketing_consent_inferred' => false,
            'subscription_preferences_imported' => false,
        ],
    ];
}

function mg_hubspot_contact_metadata(array $existing, array $contact, string $connectionPublicId): array
{
    $metadata = mg_integration_json($existing['metadata_json'] ?? null);
    $integrations = is_array($metadata['integrations'] ?? null) ? $metadata['integrations'] : [];
    $integrations['hubspot'] = [
        'connection_id' => $connectionPublicId,
        'external_contact_id' => $contact['external_id'],
        'lifecycle_stage' => $contact['lifecycle_stage'],
        'created_on' => $contact['created_on'],
        'updated_on' => $contact['updated_on'],
        'marketing' => $contact['marketing'],
        'addresses_excluded' => true,
        'phone_numbers_excluded' => true,
        'verified_at' => gmdate('Y-m-d H:i:s'),
    ];
    $metadata['integrations'] = $integrations;
    return $metadata;
}

function mg_hubspot_upsert_link(PDO $pdo, int $connectionId, array $contact, ?int $localContactId, string $status, array $metadata): void
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

function mg_hubspot_record_contact_event(PDO $pdo, int $merchantUserId, int $contactId, array $contact, string $eventType, array $metadata = []): void
{
    $pdo->prepare('INSERT INTO merchant_crm_contact_events (public_id,merchant_user_id,crm_contact_id,campaign_id,campaign_type,event_type,source_type,source_public_id,user_id,email,phone,name,value_cents,metadata_json,created_at) VALUES (?,?,?,NULL,?,?,?,?,NULL,?,NULL,?,NULL,?,NOW())')
        ->execute([
            mg_merchant_crm_uuid(),
            $merchantUserId,
            $contactId,
            'non_campaign',
            $eventType,
            'hubspot_contact',
            mb_substr((string)$contact['external_id'], 0, 80),
            $contact['email'] !== '' ? $contact['email'] : null,
            $contact['display_name'],
            json_encode($metadata + ['lifecycle_stage' => $contact['lifecycle_stage'], 'marketing_status' => 'UNKNOWN'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
}

function mg_hubspot_import_contact(PDO $pdo, array $connection, array $rawRecord, string $triggerType = 'manual'): string
{
    $merchantUserId = (int)$connection['merchant_user_id'];
    $connectionId = (int)$connection['id'];
    $contact = mg_hubspot_normalize_contact($rawRecord);
    if ($contact['external_id'] === '') return 'failed';

    $pdo->beginTransaction();
    try {
        $link = mg_hubspot_contact_link($pdo, $connectionId, $contact['external_id'], true);
        if ($link && (string)($link['sync_hash'] ?? '') === (string)$contact['sync_hash'] && (string)($link['status'] ?? '') === 'linked') {
            $pdo->commit();
            return 'skipped';
        }
        if ($contact['email'] === '') {
            mg_hubspot_upsert_link($pdo, $connectionId, $contact, null, 'pending_review', ['review_reason' => 'missing_valid_email', 'snapshot' => $contact]);
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
            $emailOwner = mg_hubspot_contact_match($pdo, $merchantUserId, $contact['email']);
            if ($emailOwner && (int)$emailOwner['id'] !== $localId) {
                mg_hubspot_upsert_link($pdo, $connectionId, $contact, $localId, 'conflict', ['review_reason' => 'email_owned_by_another_contact', 'email_owner_contact_id' => (int)$emailOwner['id'], 'snapshot' => $contact]);
                $pdo->commit();
                return 'review';
            }
            if ($localEmail !== '' && $localEmail !== $contact['email']) {
                mg_hubspot_upsert_link($pdo, $connectionId, $contact, $localId, 'conflict', ['review_reason' => 'linked_email_changed', 'local_email' => $localEmail, 'external_email' => $contact['email'], 'snapshot' => $contact]);
                $pdo->commit();
                return 'review';
            }
            $metadata = mg_hubspot_contact_metadata($local, $contact, (string)$connection['public_id']);
            $updateName = trim((string)($local['display_name'] ?? '')) === '' || (string)($local['last_source_type'] ?? '') === 'hubspot_contact';
            $pdo->prepare('UPDATE merchant_crm_contacts SET primary_email=COALESCE(primary_email,?),display_name=IF(?, ?, display_name),last_source_type=?,last_seen_at=GREATEST(last_seen_at,COALESCE(?,NOW())),source_summary_json=?,metadata_json=?,updated_at=NOW() WHERE id=?')
                ->execute([
                    $contact['email'],
                    $updateName ? 1 : 0,
                    $contact['display_name'],
                    'hubspot_contact',
                    $contact['updated_on'] ?? $contact['created_on'],
                    json_encode(['last_event_type' => 'hubspot_contact_synced', 'provider' => 'hubspot', 'lifecycle_stage' => $contact['lifecycle_stage']], JSON_UNESCAPED_SLASHES),
                    json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $localId,
                ]);
            $action = $link ? 'updated' : 'linked';
        } else {
            $metadata = mg_hubspot_contact_metadata([], $contact, (string)$connection['public_id']);
            $firstSeen = $contact['created_on'] ?: gmdate('Y-m-d H:i:s');
            $lastSeen = $contact['updated_on'] ?: $firstSeen;
            $pdo->prepare("INSERT INTO merchant_crm_contacts (public_id,merchant_user_id,user_id,primary_email,primary_phone,display_name,lifecycle_stage,crm_status,last_campaign_type,last_source_type,first_seen_at,last_seen_at,last_engaged_at,source_summary_json,tags_json,metadata_json,created_at,updated_at) VALUES (?,?,NULL,?,NULL,?,'lead','active','non_campaign','hubspot_contact',?,?,NULL,?,'[]',?,NOW(),NOW())")
                ->execute([
                    mg_merchant_crm_uuid(),
                    $merchantUserId,
                    $contact['email'],
                    $contact['display_name'],
                    $firstSeen,
                    $lastSeen,
                    json_encode(['last_event_type' => 'hubspot_contact_imported', 'provider' => 'hubspot', 'lifecycle_stage' => $contact['lifecycle_stage']], JSON_UNESCAPED_SLASHES),
                    json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]);
            $localId = (int)$pdo->lastInsertId();
            $action = 'created';
        }

        mg_hubspot_upsert_link($pdo, $connectionId, $contact, $localId, 'linked', ['snapshot' => $contact, 'match' => $action, 'trigger' => $triggerType]);
        mg_hubspot_record_contact_event($pdo, $merchantUserId, $localId, $contact, 'hubspot_contact_' . $action, ['trigger' => $triggerType, 'connection_id' => (string)$connection['public_id']]);
        $pdo->commit();
        return $action;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_hubspot_sync_contacts(PDO $pdo, int $merchantUserId, bool $reset = false, int $pageSize = 100, int $maxPages = 5): array
{
    $auth = mg_hubspot_access_credentials($pdo, $merchantUserId);
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
        for ($pageNumber = 0; $pageNumber < $maxPages; $pageNumber++) {
            $page = mg_hubspot_provider()->listContacts($auth['token'], $cursor, $pageSize);
            foreach ((array)($page['contacts'] ?? []) as $raw) {
                if (!is_array($raw)) continue;
                $counts['processed']++;
                try {
                    $result = mg_hubspot_import_contact($pdo, $connection, $raw, 'manual');
                    if (isset($counts[$result])) $counts[$result]++; else $counts['updated']++;
                } catch (Throwable $contactError) {
                    $counts['failed']++;
                    mg_security_log('warning', 'merchant.integration.hubspot_contact_failed', 'HubSpot contact import failed.', ['exception_class' => $contactError::class], $merchantUserId);
                }
            }
            $pagination = is_array($page['pagination'] ?? null) ? $page['pagination'] : [];
            $hasNext = (bool)($pagination['has_next_page'] ?? false);
            $cursor = trim((string)($pagination['next_cursor'] ?? '')) ?: null;
            $pdo->prepare("INSERT INTO merchant_integration_sync_state (connection_id,resource_key,cursor_value,last_attempt_at,metadata_json,created_at,updated_at) VALUES (?,'contacts',?,NOW(),?,NOW(),NOW()) ON DUPLICATE KEY UPDATE cursor_value=VALUES(cursor_value),last_attempt_at=NOW(),metadata_json=VALUES(metadata_json),updated_at=NOW()")
                ->execute([$connectionId, $hasNext ? $cursor : null, json_encode(['has_next_page' => $hasNext, 'marketing_consent_mode' => 'unknown', 'addresses_excluded' => true, 'phone_numbers_excluded' => true], JSON_UNESCAPED_SLASHES)]);
            if (!$hasNext || $cursor === null) break;
        }
        $status = $counts['failed'] > 0 ? 'partial' : 'completed';
        $pdo->prepare('UPDATE merchant_integration_sync_runs SET status=?,cursor_value=?,processed_count=?,created_count=?,updated_count=?,skipped_count=?,failed_count=?,finished_at=NOW(),updated_at=NOW() WHERE id=?')
            ->execute([$status, $hasNext ? $cursor : null, $counts['processed'], $counts['created'], $counts['updated'] + $counts['linked'], $counts['skipped'] + $counts['review'], $counts['failed'], $runId]);
        $pdo->prepare("UPDATE merchant_integration_sync_state SET last_success_at=NOW(),last_error_code=NULL,last_error_message=NULL,updated_at=NOW() WHERE connection_id=? AND resource_key='contacts'")
            ->execute([$connectionId]);
        $pdo->prepare('UPDATE merchant_integration_connections SET last_sync_at=NOW(),last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_at=NOW() WHERE id=?')
            ->execute([$connectionId]);
        return ['run_id' => $runPublicId, 'status' => $status, 'counts' => $counts, 'has_more' => $hasNext, 'next_cursor_saved' => $hasNext, 'addresses_imported' => false, 'phone_numbers_imported' => false, 'marketing_consent_imported' => false];
    } catch (Throwable $error) {
        $pdo->prepare("UPDATE merchant_integration_sync_runs SET status='failed',error_code=?,error_message=?,processed_count=?,created_count=?,updated_count=?,skipped_count=?,failed_count=?,finished_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([mb_substr($error::class, 0, 120), mb_substr($error->getMessage(), 0, 1000), $counts['processed'], $counts['created'], $counts['updated'] + $counts['linked'], $counts['skipped'] + $counts['review'], $counts['failed'], $runId]);
        $pdo->prepare("INSERT INTO merchant_integration_sync_state (connection_id,resource_key,cursor_value,last_attempt_at,last_error_code,last_error_message,created_at,updated_at) VALUES (?,'contacts',?,NOW(),?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE last_attempt_at=NOW(),last_error_code=VALUES(last_error_code),last_error_message=VALUES(last_error_message),updated_at=NOW()")
            ->execute([$connectionId, $cursor, mb_substr($error::class, 0, 120), mb_substr($error->getMessage(), 0, 1000)]);
        throw $error;
    }
}

function mg_hubspot_contacts_status(PDO $pdo, int $merchantUserId): array
{
    if (!mg_integration_schema_ready($pdo)) return ['connected' => false, 'counts' => []];
    $connection = mg_integration_connection_row($pdo, $merchantUserId, 'hubspot', false);
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
            'marketing_consent_imported' => false,
            'marketing_consent_inferred' => false,
            'subscription_preferences_imported' => false,
        ],
    ];
}
