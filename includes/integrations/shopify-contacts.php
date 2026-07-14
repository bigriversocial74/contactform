<?php
declare(strict_types=1);

require_once __DIR__ . '/providers/shopify.php';

function mg_shopify_provider(): MgShopifyProvider
{
    static $provider;
    if (!$provider instanceof MgShopifyProvider) $provider = new MgShopifyProvider();
    return $provider;
}

function mg_shopify_provider_catalog(array $catalog): array
{
    $provider = mg_shopify_provider();
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
        if ((string)($item['key'] ?? '') !== 'shopify') continue;
        $catalog[$index] = $entry;
        $replaced = true;
        break;
    }
    if (!$replaced) $catalog[] = $entry;
    return array_values($catalog);
}

function mg_shopify_datetime(mixed $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $timestamp = strtotime($value);
    return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
}

function mg_shopify_normalize_contact(array $customer): array
{
    $emailRecord = is_array($customer['defaultEmailAddress'] ?? null) ? $customer['defaultEmailAddress'] : [];
    $email = strtolower(trim((string)($emailRecord['emailAddress'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
    $firstName = trim((string)($customer['firstName'] ?? ''));
    $lastName = trim((string)($customer['lastName'] ?? ''));
    $name = trim($firstName . ' ' . $lastName);
    $marketingState = strtoupper(trim((string)($emailRecord['marketingState'] ?? 'UNKNOWN')));
    $amountSpent = is_array($customer['amountSpent'] ?? null) ? $customer['amountSpent'] : [];
    $normalized = [
        'external_id' => trim((string)($customer['id'] ?? '')),
        'email' => $email,
        'display_name' => $name !== '' ? mb_substr($name, 0, 180) : ($email !== '' ? mb_substr($email, 0, 180) : 'Shopify customer'),
        'first_name' => mb_substr($firstName, 0, 100),
        'last_name' => mb_substr($lastName, 0, 100),
        'created_on' => mg_shopify_datetime($customer['createdAt'] ?? null),
        'updated_on' => mg_shopify_datetime($customer['updatedAt'] ?? null),
        'customer_state' => mb_substr(strtoupper(trim((string)($customer['state'] ?? ''))), 0, 40),
        'verified_email' => (bool)($customer['verifiedEmail'] ?? false),
        'marketing' => [
            'accepts_marketing' => $marketingState === 'SUBSCRIBED',
            'status' => $marketingState,
            'source' => 'shopify',
            'inferred' => false,
        ],
        'order_summary' => [
            'orders_count' => max(0, (int)($customer['numberOfOrders'] ?? 0)),
            'amount_spent' => trim((string)($amountSpent['amount'] ?? '0')),
            'currency' => mb_substr(trim((string)($amountSpent['currencyCode'] ?? '')), 0, 8),
        ],
        'tags' => array_values(array_slice(array_filter(array_map('strval', (array)($customer['tags'] ?? []))), 0, 100)),
        'addresses_excluded' => true,
        'phone_numbers_excluded' => true,
    ];
    $normalized['sync_hash'] = hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return $normalized;
}

function mg_shopify_begin_oauth(PDO $pdo, int $merchantUserId, string $shop): array
{
    if (!mg_integration_schema_ready($pdo)) throw new RuntimeException('Merchant integrations schema is not installed.');
    if (mg_integration_credential_master_key() === null) throw new MgIntegrationCredentialException('Integration credential encryption is not configured.');
    $provider = mg_shopify_provider();
    if (!$provider->isConfigured()) throw new RuntimeException('Shopify OAuth is not configured.');
    $shop = $provider->normalizeShopDomain($shop);
    $state = bin2hex(random_bytes(32));
    $stateHash = hash('sha256', $state);

    $pdo->beginTransaction();
    try {
        $connection = mg_integration_connection_row($pdo, $merchantUserId, 'shopify', true);
        if (!$connection || (string)($connection['status'] ?? '') === 'disconnected') {
            $publicId = mg_integration_uuid();
            $pdo->prepare("INSERT INTO merchant_integration_connections (public_id,merchant_user_id,provider_key,auth_type,status,sync_direction,external_account_url,scopes_json,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,'oauth2','pending','import_only',?,?,?, ?,NOW(),NOW())")
                ->execute([$publicId, $merchantUserId, 'shopify', 'https://' . $shop, json_encode($provider->scopes(), JSON_UNESCAPED_SLASHES), $merchantUserId, $merchantUserId]);
            $connectionId = (int)$pdo->lastInsertId();
        } else {
            $connectionId = (int)$connection['id'];
            $pdo->prepare("UPDATE merchant_integration_connections SET status='pending',external_account_url=?,scopes_json=?,last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_by_user_id=?,updated_at=NOW() WHERE id=?")
                ->execute(['https://' . $shop, json_encode($provider->scopes(), JSON_UNESCAPED_SLASHES), $merchantUserId, $connectionId]);
        }
        $metadata = ['shop_domain' => $shop, 'oauth_started_at' => gmdate('c')];
        $pdo->prepare("INSERT INTO merchant_integration_credentials (connection_id,oauth_state_hash,oauth_state_expires_at,metadata_json,created_at,updated_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE),?,NOW(),NOW()) ON DUPLICATE KEY UPDATE oauth_state_hash=VALUES(oauth_state_hash),oauth_state_expires_at=VALUES(oauth_state_expires_at),metadata_json=VALUES(metadata_json),updated_at=NOW()")
            ->execute([$connectionId, $stateHash, json_encode($metadata, JSON_UNESCAPED_SLASHES)]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    return [
        'provider' => 'shopify',
        'shop_domain' => $shop,
        'authorization_url' => $provider->buildAuthorizationUrl($shop, $state),
        'state_expires_in' => 600,
    ];
}

function mg_shopify_pending_oauth(PDO $pdo, int $merchantUserId, string $stateHash, bool $forUpdate = false): ?array
{
    $sql = "SELECT c.*,cr.oauth_state_hash,cr.oauth_state_expires_at,cr.metadata_json credential_metadata_json
            FROM merchant_integration_connections c
            INNER JOIN merchant_integration_credentials cr ON cr.connection_id=c.id
            WHERE c.merchant_user_id=? AND c.provider_key='shopify' AND cr.oauth_state_hash=? AND cr.oauth_state_expires_at>=NOW()
            ORDER BY c.id DESC LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$merchantUserId, $stateHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_shopify_complete_oauth(PDO $pdo, int $merchantUserId, array $query): array
{
    $provider = mg_shopify_provider();
    $state = trim((string)($query['state'] ?? ''));
    $code = trim((string)($query['code'] ?? ''));
    $shop = $provider->normalizeShopDomain((string)($query['shop'] ?? ''));
    $timestamp = (int)($query['timestamp'] ?? 0);
    if ($state === '' || $code === '') throw new RuntimeException('Shopify did not return a valid authorization response.');
    if ($timestamp <= 0 || abs(time() - $timestamp) > 600) throw new RuntimeException('Shopify authorization response expired.');
    if (!$provider->verifyCallbackHmac($query)) throw new RuntimeException('Shopify authorization signature is invalid.');
    $stateHash = hash('sha256', $state);
    $pending = mg_shopify_pending_oauth($pdo, $merchantUserId, $stateHash, false);
    if (!$pending) throw new RuntimeException('The Shopify authorization request expired or does not match this account.');
    $metadata = mg_integration_json($pending['credential_metadata_json'] ?? null);
    if (!hash_equals((string)($metadata['shop_domain'] ?? ''), $shop)) throw new RuntimeException('Shopify returned a different store than the one requested.');

    $tokenResponse = $provider->exchangeAuthorizationCode($shop, $code);
    $accessToken = trim((string)($tokenResponse['access_token'] ?? ''));
    if ($accessToken === '') throw new RuntimeException('Shopify did not return an access token.');
    $grantedScopes = array_values(array_filter(array_map('trim', explode(',', (string)($tokenResponse['scope'] ?? '')))));
    if (!in_array('read_customers', $grantedScopes, true)) throw new RuntimeException('Shopify did not grant read_customers access.');
    $shopRecord = $provider->fetchShop($shop, $accessToken);
    $settings = [
        'shop' => $shopRecord,
        'contact_sync' => 'import_only',
        'addresses_imported' => false,
        'phone_numbers_imported' => false,
        'marketing_consent_mode' => 'preserve_shopify_state',
        'protected_customer_data_required' => true,
    ];

    $pdo->beginTransaction();
    try {
        $locked = mg_shopify_pending_oauth($pdo, $merchantUserId, $stateHash, true);
        if (!$locked) throw new RuntimeException('The Shopify authorization request was already used or expired.');
        $connectionId = (int)$locked['id'];
        $pdo->prepare("UPDATE merchant_integration_connections SET status='active',external_account_id=?,external_account_name=?,external_account_url=?,scopes_json=?,settings_json=?,connected_at=COALESCE(connected_at,NOW()),disconnected_at=NULL,last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_by_user_id=?,updated_at=NOW() WHERE id=?")
            ->execute([
                (string)$shopRecord['id'],
                trim((string)($shopRecord['name'] ?? $shop)),
                trim((string)($shopRecord['primaryDomain']['url'] ?? ('https://' . $shop))),
                json_encode($grantedScopes, JSON_UNESCAPED_SLASHES),
                json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $merchantUserId,
                $connectionId,
            ]);
        $pdo->prepare("UPDATE merchant_integration_credentials SET access_token_ciphertext=?,refresh_token_ciphertext=NULL,access_expires_at=NULL,refresh_expires_at=NULL,oauth_state_hash=NULL,oauth_state_expires_at=NULL,metadata_json=?,updated_at=NOW() WHERE connection_id=?")
            ->execute([
                mg_integration_encrypt_secret($accessToken),
                json_encode(['shop_domain' => $shop, 'api_version' => $provider->apiVersion(), 'token_type' => 'offline_non_expiring'], JSON_UNESCAPED_SLASHES),
                $connectionId,
            ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    $row = mg_integration_connection_row($pdo, $merchantUserId, 'shopify', false);
    return $row ? mg_integration_connection_public($row) : [];
}

function mg_shopify_connection_credentials(PDO $pdo, int $merchantUserId): array
{
    $stmt = $pdo->prepare("SELECT c.*,cr.access_token_ciphertext,cr.metadata_json credential_metadata_json FROM merchant_integration_connections c INNER JOIN merchant_integration_credentials cr ON cr.connection_id=c.id WHERE c.merchant_user_id=? AND c.provider_key='shopify' AND c.status IN ('active','error','reauthorization_required') ORDER BY (c.status='active') DESC,c.id DESC LIMIT 1");
    $stmt->execute([$merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('An active Shopify connection is required.');
    $metadata = mg_integration_json($row['credential_metadata_json'] ?? null);
    $shop = mg_shopify_provider()->normalizeShopDomain((string)($metadata['shop_domain'] ?? ''));
    $token = mg_integration_decrypt_secret($row['access_token_ciphertext'] ?? null);
    if ($token === '') throw new MgIntegrationCredentialException('Stored Shopify access credentials are unavailable.');
    return ['connection' => $row, 'shop_domain' => $shop, 'token' => $token];
}

function mg_shopify_contact_match(PDO $pdo, int $merchantUserId, string $email): ?array
{
    if ($email === '') return null;
    $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE merchant_user_id=? AND primary_email=? LIMIT 1');
    $stmt->execute([$merchantUserId, $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return function_exists('mg_crm_identity_resolve_contact') ? mg_crm_identity_resolve_contact($pdo, $merchantUserId, $row, false) : $row;
    return function_exists('mg_crm_identity_alias_contact') ? mg_crm_identity_alias_contact($pdo, $merchantUserId, null, $email, null, false) : null;
}

function mg_shopify_contact_link(PDO $pdo, int $connectionId, string $externalId, bool $forUpdate = false): ?array
{
    $sql = "SELECT * FROM merchant_integration_entity_links WHERE connection_id=? AND entity_type='contact' AND external_entity_id=? LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$connectionId, $externalId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_shopify_contact_preview(PDO $pdo, int $merchantUserId, ?string $cursor = null, int $pageSize = 50): array
{
    $auth = mg_shopify_connection_credentials($pdo, $merchantUserId);
    $page = mg_shopify_provider()->listCustomers($auth['shop_domain'], $auth['token'], $cursor, max(1, min(250, $pageSize)));
    $connection = $auth['connection'];
    $items = [];
    foreach ((array)($page['customers'] ?? []) as $raw) {
        if (!is_array($raw)) continue;
        $contact = mg_shopify_normalize_contact($raw);
        $link = $contact['external_id'] !== '' ? mg_shopify_contact_link($pdo, (int)$connection['id'], $contact['external_id']) : null;
        $matched = $contact['email'] !== '' ? mg_shopify_contact_match($pdo, $merchantUserId, $contact['email']) : null;
        $action = 'create';
        $reason = 'New CRM contact';
        if ($contact['external_id'] === '' || $contact['email'] === '') {
            $action = 'review';
            $reason = 'Missing a valid customer ID or email';
        } elseif ($link) {
            $action = hash_equals((string)($link['sync_hash'] ?? ''), (string)$contact['sync_hash']) ? 'unchanged' : 'update';
            $reason = $action === 'unchanged' ? 'Already synchronized' : 'Linked Shopify customer changed';
        } elseif ($matched) {
            $action = 'link';
            $reason = 'Exact CRM email match';
        }
        $items[] = [
            'external_id' => $contact['external_id'],
            'email' => $contact['email'],
            'name' => $contact['display_name'],
            'accepts_marketing' => $contact['marketing']['accepts_marketing'],
            'marketing_status' => $contact['marketing']['status'],
            'action' => $action,
            'reason' => $reason,
            'orders_count' => $contact['order_summary']['orders_count'],
            'amount_spent' => $contact['order_summary']['amount_spent'],
            'addresses_excluded' => true,
        ];
    }
    $pagination = is_array($page['pagination'] ?? null) ? $page['pagination'] : [];
    return [
        'provider' => 'shopify',
        'connection_id' => (string)$connection['public_id'],
        'items' => $items,
        'page_count' => count($items),
        'pagination' => $pagination,
        'policy' => [
            'addresses_imported' => false,
            'phone_numbers_imported' => false,
            'marketing_consent_preserved' => true,
            'marketing_consent_inferred' => false,
        ],
    ];
}

function mg_shopify_contact_metadata(array $existing, array $contact, string $connectionPublicId): array
{
    $metadata = mg_integration_json($existing['metadata_json'] ?? null);
    $integrations = is_array($metadata['integrations'] ?? null) ? $metadata['integrations'] : [];
    $integrations['shopify'] = [
        'connection_id' => $connectionPublicId,
        'external_customer_id' => $contact['external_id'],
        'created_on' => $contact['created_on'],
        'updated_on' => $contact['updated_on'],
        'customer_state' => $contact['customer_state'],
        'verified_email' => $contact['verified_email'],
        'marketing' => $contact['marketing'],
        'order_summary' => $contact['order_summary'],
        'tags' => $contact['tags'],
        'addresses_excluded' => true,
        'phone_numbers_excluded' => true,
        'verified_at' => gmdate('Y-m-d H:i:s'),
    ];
    $metadata['integrations'] = $integrations;
    return $metadata;
}

function mg_shopify_upsert_link(PDO $pdo, int $connectionId, array $contact, ?int $localContactId, string $status, array $metadata): void
{
    $pdo->prepare("INSERT INTO merchant_integration_entity_links (public_id,connection_id,entity_type,external_entity_id,local_entity_type,local_entity_id,external_updated_at,last_synced_at,sync_hash,status,metadata_json,created_at,updated_at) VALUES (?,?,'contact',?,'merchant_crm_contact',?,?,NOW(),?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE local_entity_id=VALUES(local_entity_id),external_updated_at=VALUES(external_updated_at),last_synced_at=NOW(),sync_hash=VALUES(sync_hash),status=VALUES(status),metadata_json=VALUES(metadata_json),updated_at=NOW()")
        ->execute([
            mg_integration_uuid(), $connectionId, $contact['external_id'], $localContactId,
            $contact['updated_on'] ?? $contact['created_on'], $contact['sync_hash'], $status,
            json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
}

function mg_shopify_import_contact(PDO $pdo, array $connection, array $rawCustomer, string $triggerType = 'manual'): string
{
    $merchantUserId = (int)$connection['merchant_user_id'];
    $connectionId = (int)$connection['id'];
    $contact = mg_shopify_normalize_contact($rawCustomer);
    if ($contact['external_id'] === '') return 'failed';

    $pdo->beginTransaction();
    try {
        $link = mg_shopify_contact_link($pdo, $connectionId, $contact['external_id'], true);
        if ($link && (string)($link['sync_hash'] ?? '') === (string)$contact['sync_hash'] && (string)($link['status'] ?? '') === 'linked') {
            $pdo->commit();
            return 'skipped';
        }
        if ($contact['email'] === '') {
            mg_shopify_upsert_link($pdo, $connectionId, $contact, null, 'pending_review', ['review_reason' => 'missing_valid_email', 'snapshot' => $contact]);
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

        $action = 'updated';
        if ($local) {
            $localId = (int)$local['id'];
            $localEmail = strtolower(trim((string)($local['primary_email'] ?? '')));
            $emailOwner = mg_shopify_contact_match($pdo, $merchantUserId, $contact['email']);
            if ($emailOwner && (int)$emailOwner['id'] !== $localId) {
                mg_shopify_upsert_link($pdo, $connectionId, $contact, $localId, 'conflict', ['review_reason' => 'email_owned_by_another_contact', 'email_owner_contact_id' => (int)$emailOwner['id'], 'snapshot' => $contact]);
                $pdo->commit();
                return 'review';
            }
            if ($localEmail !== '' && $localEmail !== $contact['email']) {
                mg_shopify_upsert_link($pdo, $connectionId, $contact, $localId, 'conflict', ['review_reason' => 'linked_email_changed', 'local_email' => $localEmail, 'external_email' => $contact['email'], 'snapshot' => $contact]);
                $pdo->commit();
                return 'review';
            }
            $metadata = mg_shopify_contact_metadata($local, $contact, (string)$connection['public_id']);
            $updateName = trim((string)($local['display_name'] ?? '')) === '' || (string)($local['last_source_type'] ?? '') === 'shopify_customer';
            $pdo->prepare('UPDATE merchant_crm_contacts SET primary_email=COALESCE(primary_email,?),display_name=IF(?, ?, display_name),last_source_type=?,last_seen_at=GREATEST(last_seen_at,COALESCE(?,NOW())),source_summary_json=?,metadata_json=?,updated_at=NOW() WHERE id=?')
                ->execute([
                    $contact['email'], $updateName ? 1 : 0, $contact['display_name'], 'shopify_customer',
                    $contact['updated_on'] ?? $contact['created_on'],
                    json_encode(['last_event_type' => 'shopify_customer_synced', 'provider' => 'shopify'], JSON_UNESCAPED_SLASHES),
                    json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $localId,
                ]);
            $action = $link ? 'updated' : 'linked';
        } else {
            $metadata = mg_shopify_contact_metadata([], $contact, (string)$connection['public_id']);
            $firstSeen = $contact['created_on'] ?: gmdate('Y-m-d H:i:s');
            $lastSeen = $contact['updated_on'] ?: $firstSeen;
            $pdo->prepare("INSERT INTO merchant_crm_contacts (public_id,merchant_user_id,user_id,primary_email,primary_phone,display_name,lifecycle_stage,crm_status,last_campaign_type,last_source_type,first_seen_at,last_seen_at,last_engaged_at,source_summary_json,tags_json,metadata_json,created_at,updated_at) VALUES (?,?,NULL,?,NULL,?,'lead','active','non_campaign','shopify_customer',?,?,NULL,?,'[]',?,NOW(),NOW())")
                ->execute([
                    mg_merchant_crm_uuid(), $merchantUserId, $contact['email'], $contact['display_name'], $firstSeen, $lastSeen,
                    json_encode(['last_event_type' => 'shopify_customer_imported', 'provider' => 'shopify'], JSON_UNESCAPED_SLASHES),
                    json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]);
            $localId = (int)$pdo->lastInsertId();
            $action = 'created';
        }

        mg_shopify_upsert_link($pdo, $connectionId, $contact, $localId, 'linked', ['snapshot' => $contact, 'match' => $action, 'trigger' => $triggerType]);
        $pdo->prepare('INSERT INTO merchant_crm_contact_events (public_id,merchant_user_id,crm_contact_id,campaign_id,campaign_type,event_type,source_type,source_public_id,user_id,email,phone,name,value_cents,metadata_json,created_at) VALUES (?,?,?,NULL,?,?,?,?,NULL,?,NULL,?,NULL,?,NOW())')
            ->execute([
                mg_merchant_crm_uuid(), $merchantUserId, $localId, 'non_campaign', 'shopify_customer_' . $action,
                'shopify_customer', mb_substr($contact['external_id'], 0, 80), $contact['email'], $contact['display_name'],
                json_encode(['trigger' => $triggerType, 'connection_id' => (string)$connection['public_id'], 'marketing' => $contact['marketing'], 'order_summary' => $contact['order_summary'], 'addresses_excluded' => true], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        $pdo->commit();
        return $action;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_shopify_sync_contacts(PDO $pdo, int $merchantUserId, bool $reset = false, int $pageSize = 100, int $maxPages = 5): array
{
    $auth = mg_shopify_connection_credentials($pdo, $merchantUserId);
    $connection = $auth['connection'];
    $connectionId = (int)$connection['id'];
    $pageSize = max(1, min(250, $pageSize));
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
            $page = mg_shopify_provider()->listCustomers($auth['shop_domain'], $auth['token'], $cursor, $pageSize);
            foreach ((array)($page['customers'] ?? []) as $raw) {
                if (!is_array($raw)) continue;
                $counts['processed']++;
                try {
                    $result = mg_shopify_import_contact($pdo, $connection, $raw, 'manual');
                    if (isset($counts[$result])) $counts[$result]++; else $counts['updated']++;
                } catch (Throwable $contactError) {
                    $counts['failed']++;
                    mg_security_log('warning', 'merchant.integration.shopify_customer_failed', 'Shopify customer import failed.', ['exception_class' => $contactError::class], $merchantUserId);
                }
            }
            $pagination = is_array($page['pagination'] ?? null) ? $page['pagination'] : [];
            $hasNext = (bool)($pagination['has_next_page'] ?? false);
            $cursor = trim((string)($pagination['next_cursor'] ?? '')) ?: null;
            $pdo->prepare("INSERT INTO merchant_integration_sync_state (connection_id,resource_key,cursor_value,last_attempt_at,metadata_json,created_at,updated_at) VALUES (?,'contacts',?,NOW(),?,NOW(),NOW()) ON DUPLICATE KEY UPDATE cursor_value=VALUES(cursor_value),last_attempt_at=NOW(),metadata_json=VALUES(metadata_json),updated_at=NOW()")
                ->execute([$connectionId, $hasNext ? $cursor : null, json_encode(['has_next_page' => $hasNext, 'addresses_excluded' => true, 'marketing_consent_preserved' => true], JSON_UNESCAPED_SLASHES)]);
            if (!$hasNext || $cursor === null) break;
        }
        $status = $counts['failed'] > 0 ? 'partial' : 'completed';
        $pdo->prepare('UPDATE merchant_integration_sync_runs SET status=?,cursor_value=?,processed_count=?,created_count=?,updated_count=?,skipped_count=?,failed_count=?,finished_at=NOW(),updated_at=NOW() WHERE id=?')
            ->execute([$status, $hasNext ? $cursor : null, $counts['processed'], $counts['created'], $counts['updated'] + $counts['linked'], $counts['skipped'] + $counts['review'], $counts['failed'], $runId]);
        $pdo->prepare("UPDATE merchant_integration_sync_state SET last_success_at=NOW(),last_error_code=NULL,last_error_message=NULL,updated_at=NOW() WHERE connection_id=? AND resource_key='contacts'")->execute([$connectionId]);
        $pdo->prepare('UPDATE merchant_integration_connections SET last_sync_at=NOW(),last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_at=NOW() WHERE id=?')->execute([$connectionId]);
        return ['run_id' => $runPublicId, 'status' => $status, 'counts' => $counts, 'has_more' => $hasNext, 'next_cursor_saved' => $hasNext, 'addresses_imported' => false, 'marketing_consent_preserved' => true];
    } catch (Throwable $error) {
        $pdo->prepare("UPDATE merchant_integration_sync_runs SET status='failed',error_code=?,error_message=?,processed_count=?,created_count=?,updated_count=?,skipped_count=?,failed_count=?,finished_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([mb_substr($error::class, 0, 120), mb_substr($error->getMessage(), 0, 1000), $counts['processed'], $counts['created'], $counts['updated'] + $counts['linked'], $counts['skipped'] + $counts['review'], $counts['failed'], $runId]);
        throw $error;
    }
}

function mg_shopify_contacts_status(PDO $pdo, int $merchantUserId): array
{
    if (!mg_integration_schema_ready($pdo)) return ['connected' => false, 'counts' => []];
    $connection = mg_integration_connection_row($pdo, $merchantUserId, 'shopify', false);
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
        'policy' => ['addresses_imported' => false, 'phone_numbers_imported' => false, 'marketing_consent_preserved' => true, 'marketing_consent_inferred' => false],
    ];
}
