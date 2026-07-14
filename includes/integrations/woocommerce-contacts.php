<?php
declare(strict_types=1);

require_once __DIR__ . '/providers/woocommerce.php';

function mg_woocommerce_provider(): MgWooCommerceProvider
{
    static $provider;
    if (!$provider instanceof MgWooCommerceProvider) $provider = new MgWooCommerceProvider();
    return $provider;
}

function mg_woocommerce_provider_catalog(array $catalog): array
{
    $provider = mg_woocommerce_provider();
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
        if ((string)($item['key'] ?? '') !== 'woocommerce') continue;
        $catalog[$index] = $entry;
        $replaced = true;
        break;
    }
    if (!$replaced) $catalog[] = $entry;
    return array_values($catalog);
}

function mg_woocommerce_datetime(mixed $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $timestamp = strtotime($value);
    return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
}

function mg_woocommerce_normalize_contact(array $customer): array
{
    $externalId = trim((string)($customer['id'] ?? ''));
    $email = strtolower(trim((string)($customer['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
    $firstName = trim((string)($customer['first_name'] ?? ''));
    $lastName = trim((string)($customer['last_name'] ?? ''));
    $name = trim($firstName . ' ' . $lastName);
    $normalized = [
        'external_id' => $externalId,
        'email' => $email,
        'display_name' => $name !== '' ? mb_substr($name, 0, 180) : ($email !== '' ? mb_substr($email, 0, 180) : 'WooCommerce customer'),
        'first_name' => mb_substr($firstName, 0, 100),
        'last_name' => mb_substr($lastName, 0, 100),
        'locale' => '',
        'created_on' => mg_woocommerce_datetime($customer['date_created_gmt'] ?? $customer['date_created'] ?? null),
        'updated_on' => mg_woocommerce_datetime($customer['date_modified_gmt'] ?? $customer['date_modified'] ?? null),
        'marketing' => [
            'accepts_marketing' => false,
            'status' => 'unknown',
            'source' => 'woocommerce',
            'inferred' => false,
        ],
        'order_summary' => [
            'orders_count' => max(0, (int)($customer['orders_count'] ?? 0)),
            'total_spent' => trim((string)($customer['total_spent'] ?? '0')),
            'is_paying_customer' => (bool)($customer['is_paying_customer'] ?? false),
        ],
        'role' => mb_substr(trim((string)($customer['role'] ?? 'customer')), 0, 80),
        'username' => mb_substr(trim((string)($customer['username'] ?? '')), 0, 190),
        'addresses_excluded' => true,
    ];
    $normalized['sync_hash'] = hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return $normalized;
}

function mg_woocommerce_connection_credentials(PDO $pdo, int $merchantUserId, bool $forUpdate = false): ?array
{
    $sql = "SELECT c.*,cr.api_key_ciphertext,cr.metadata_json credential_metadata_json
            FROM merchant_integration_connections c
            INNER JOIN merchant_integration_credentials cr ON cr.connection_id=c.id
            WHERE c.merchant_user_id=? AND c.provider_key='woocommerce'
              AND c.status IN ('active','error')
            ORDER BY (c.status='active') DESC,c.id DESC LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_woocommerce_credentials(PDO $pdo, int $merchantUserId): array
{
    $row = mg_woocommerce_connection_credentials($pdo, $merchantUserId, false);
    if (!$row) throw new RuntimeException('An active WooCommerce connection is required.');
    $decoded = json_decode(mg_integration_decrypt_secret($row['api_key_ciphertext'] ?? null), true);
    if (!is_array($decoded)) throw new MgIntegrationCredentialException('Stored WooCommerce credentials are invalid.');
    $siteUrl = trim((string)($decoded['site_url'] ?? ''));
    $consumerKey = trim((string)($decoded['consumer_key'] ?? ''));
    $consumerSecret = trim((string)($decoded['consumer_secret'] ?? ''));
    if ($siteUrl === '' || $consumerKey === '' || $consumerSecret === '') throw new MgIntegrationCredentialException('Stored WooCommerce credentials are incomplete.');
    return ['connection' => $row, 'site_url' => $siteUrl, 'consumer_key' => $consumerKey, 'consumer_secret' => $consumerSecret];
}

function mg_woocommerce_connect(PDO $pdo, int $merchantUserId, string $siteUrl, string $consumerKey, string $consumerSecret): array
{
    if (!mg_integration_schema_ready($pdo)) throw new RuntimeException('Merchant integrations schema is not installed.');
    if (mg_integration_credential_master_key() === null) throw new MgIntegrationCredentialException('Integration credential encryption is not configured.');
    $provider = mg_woocommerce_provider();
    $siteUrl = $provider->normalizeSiteUrl($siteUrl);
    $account = $provider->validateCredentials($siteUrl, $consumerKey, $consumerSecret);
    $credentialCipher = mg_integration_encrypt_secret(json_encode([
        'site_url' => $siteUrl,
        'consumer_key' => trim($consumerKey),
        'consumer_secret' => trim($consumerSecret),
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $settings = [
        'website' => $account,
        'contact_sync' => 'import_only',
        'addresses_imported' => false,
        'phone_from_address_imported' => false,
        'marketing_consent_mode' => 'unknown_unless_separately_verified',
    ];

    $pdo->beginTransaction();
    try {
        $connection = mg_integration_connection_row($pdo, $merchantUserId, 'woocommerce', true);
        if (!$connection) {
            $publicId = mg_integration_uuid();
            $pdo->prepare("INSERT INTO merchant_integration_connections (public_id,merchant_user_id,provider_key,auth_type,status,sync_direction,external_account_id,external_account_name,external_account_url,scopes_json,settings_json,connected_at,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,'api_key','active','import_only',?,?,?,?,?,NOW(),?,?,NOW(),NOW())")
                ->execute([
                    $publicId,
                    $merchantUserId,
                    'woocommerce',
                    (string)$account['id'],
                    (string)$account['title'],
                    (string)$account['url'],
                    json_encode(['customers:read'], JSON_UNESCAPED_SLASHES),
                    json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $merchantUserId,
                    $merchantUserId,
                ]);
            $connectionId = (int)$pdo->lastInsertId();
        } else {
            $connectionId = (int)$connection['id'];
            $pdo->prepare("UPDATE merchant_integration_connections SET auth_type='api_key',status='active',sync_direction='import_only',external_account_id=?,external_account_name=?,external_account_url=?,scopes_json=?,settings_json=?,connected_at=COALESCE(connected_at,NOW()),disconnected_at=NULL,last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_by_user_id=?,updated_at=NOW() WHERE id=?")
                ->execute([
                    (string)$account['id'],
                    (string)$account['title'],
                    (string)$account['url'],
                    json_encode(['customers:read'], JSON_UNESCAPED_SLASHES),
                    json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $merchantUserId,
                    $connectionId,
                ]);
        }
        $pdo->prepare("INSERT INTO merchant_integration_credentials (connection_id,api_key_ciphertext,metadata_json,created_at,updated_at) VALUES (?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE api_key_ciphertext=VALUES(api_key_ciphertext),access_token_ciphertext=NULL,refresh_token_ciphertext=NULL,oauth_state_hash=NULL,oauth_state_expires_at=NULL,metadata_json=VALUES(metadata_json),updated_at=NOW()")
            ->execute([$connectionId, $credentialCipher, json_encode(['credential_type' => 'woocommerce_rest_api', 'permission_expected' => 'read'], JSON_UNESCAPED_SLASHES)]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    $row = mg_integration_connection_row($pdo, $merchantUserId, 'woocommerce', false);
    return $row ? mg_integration_connection_public($row) : [];
}

function mg_woocommerce_contact_match(PDO $pdo, int $merchantUserId, string $email): ?array
{
    if ($email === '') return null;
    $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE merchant_user_id=? AND primary_email=? LIMIT 1');
    $stmt->execute([$merchantUserId, $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return function_exists('mg_crm_identity_resolve_contact') ? mg_crm_identity_resolve_contact($pdo, $merchantUserId, $row, false) : $row;
    if (function_exists('mg_crm_identity_alias_contact')) return mg_crm_identity_alias_contact($pdo, $merchantUserId, null, $email, null, false);
    return null;
}

function mg_woocommerce_contact_link(PDO $pdo, int $connectionId, string $externalId, bool $forUpdate = false): ?array
{
    $sql = "SELECT * FROM merchant_integration_entity_links WHERE connection_id=? AND entity_type='contact' AND external_entity_id=? LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$connectionId, $externalId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_woocommerce_contact_preview(PDO $pdo, int $merchantUserId, ?string $cursor = null, int $pageSize = 50): array
{
    $auth = mg_woocommerce_credentials($pdo, $merchantUserId);
    $pageNumber = max(1, (int)($cursor ?: 1));
    $page = mg_woocommerce_provider()->listCustomers($auth['site_url'], $auth['consumer_key'], $auth['consumer_secret'], $pageNumber, max(1, min(100, $pageSize)));
    $connection = $auth['connection'];
    $items = [];
    foreach ((array)($page['customers'] ?? []) as $raw) {
        if (!is_array($raw)) continue;
        $contact = mg_woocommerce_normalize_contact($raw);
        $link = $contact['external_id'] !== '' ? mg_woocommerce_contact_link($pdo, (int)$connection['id'], $contact['external_id'], false) : null;
        $matched = $contact['email'] !== '' ? mg_woocommerce_contact_match($pdo, $merchantUserId, $contact['email']) : null;
        $action = 'create';
        $reason = 'New CRM contact';
        $localId = null;
        if ($contact['external_id'] === '' || $contact['email'] === '') {
            $action = 'review';
            $reason = 'Missing a valid customer ID or email';
        } elseif ($link) {
            $localId = (int)($link['local_entity_id'] ?? 0) ?: null;
            $action = hash_equals((string)($link['sync_hash'] ?? ''), (string)$contact['sync_hash']) ? 'unchanged' : 'update';
            $reason = $action === 'unchanged' ? 'Already synchronized' : 'Linked WooCommerce customer changed';
        } elseif ($matched) {
            $localId = (int)$matched['id'];
            $action = 'link';
            $reason = 'Exact CRM email match';
        }
        $items[] = [
            'external_id' => $contact['external_id'],
            'email' => $contact['email'],
            'name' => $contact['display_name'],
            'accepts_marketing' => false,
            'marketing_status' => 'unknown',
            'action' => $action,
            'reason' => $reason,
            'local_contact_id' => $localId,
            'orders_count' => $contact['order_summary']['orders_count'],
            'total_spent' => $contact['order_summary']['total_spent'],
            'addresses_excluded' => true,
        ];
    }
    $pagination = is_array($page['pagination'] ?? null) ? $page['pagination'] : [];
    return [
        'provider' => 'woocommerce',
        'connection_id' => (string)$connection['public_id'],
        'items' => $items,
        'page_count' => count($items),
        'pagination' => [
            'has_next_page' => (bool)($pagination['has_next_page'] ?? false),
            'next_cursor' => isset($pagination['next_page']) ? (string)$pagination['next_page'] : null,
            'total' => (int)($pagination['total'] ?? count($items)),
        ],
        'policy' => [
            'addresses_imported' => false,
            'phone_from_address_imported' => false,
            'marketing_consent_preserved' => false,
            'marketing_consent_inferred' => false,
        ],
    ];
}

function mg_woocommerce_contact_metadata(array $existing, array $contact, string $connectionPublicId): array
{
    $metadata = mg_integration_json($existing['metadata_json'] ?? null);
    $integrations = is_array($metadata['integrations'] ?? null) ? $metadata['integrations'] : [];
    $integrations['woocommerce'] = [
        'connection_id' => $connectionPublicId,
        'external_customer_id' => $contact['external_id'],
        'created_on' => $contact['created_on'],
        'updated_on' => $contact['updated_on'],
        'role' => $contact['role'],
        'username' => $contact['username'],
        'order_summary' => $contact['order_summary'],
        'marketing_consent_status' => 'unknown',
        'marketing_consent_inferred' => false,
        'addresses_excluded' => true,
        'verified_at' => gmdate('Y-m-d H:i:s'),
    ];
    $metadata['integrations'] = $integrations;
    return $metadata;
}

function mg_woocommerce_record_contact_event(PDO $pdo, int $merchantUserId, int $contactId, array $contact, string $eventType, array $metadata = []): void
{
    $pdo->prepare('INSERT INTO merchant_crm_contact_events (public_id,merchant_user_id,crm_contact_id,campaign_id,campaign_type,event_type,source_type,source_public_id,user_id,email,phone,name,value_cents,metadata_json,created_at) VALUES (?,?,?,NULL,?,?,?,?,NULL,?,NULL,?,NULL,?,NOW())')
        ->execute([
            mg_merchant_crm_uuid(),
            $merchantUserId,
            $contactId,
            'non_campaign',
            $eventType,
            'woocommerce_customer',
            mb_substr((string)$contact['external_id'], 0, 80),
            $contact['email'] !== '' ? $contact['email'] : null,
            $contact['display_name'],
            json_encode($metadata + ['addresses_excluded' => true, 'marketing' => $contact['marketing'], 'order_summary' => $contact['order_summary']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
}

function mg_woocommerce_upsert_link(PDO $pdo, int $connectionId, array $contact, ?int $localContactId, string $status, array $metadata): void
{
    $pdo->prepare("INSERT INTO merchant_integration_entity_links (public_id,connection_id,entity_type,external_entity_id,local_entity_type,local_entity_id,external_updated_at,last_synced_at,sync_hash,status,metadata_json,created_at,updated_at)
                   VALUES (?,?,'contact',?,'merchant_crm_contact',?,?,NOW(),?,?,?,NOW(),NOW())
                   ON DUPLICATE KEY UPDATE local_entity_id=VALUES(local_entity_id),external_updated_at=VALUES(external_updated_at),last_synced_at=NOW(),sync_hash=VALUES(sync_hash),status=VALUES(status),metadata_json=VALUES(metadata_json),updated_at=NOW()")
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

function mg_woocommerce_import_contact(PDO $pdo, array $connection, array $rawCustomer, string $triggerType = 'manual'): string
{
    $merchantUserId = (int)$connection['merchant_user_id'];
    $connectionId = (int)$connection['id'];
    $contact = mg_woocommerce_normalize_contact($rawCustomer);
    if ($contact['external_id'] === '') return 'failed';

    $pdo->beginTransaction();
    try {
        $link = mg_woocommerce_contact_link($pdo, $connectionId, $contact['external_id'], true);
        if ($link && (string)($link['sync_hash'] ?? '') === (string)$contact['sync_hash'] && (string)($link['status'] ?? '') === 'linked') {
            $pdo->commit();
            return 'skipped';
        }
        if ($contact['email'] === '') {
            mg_woocommerce_upsert_link($pdo, $connectionId, $contact, null, 'pending_review', ['review_reason' => 'missing_valid_email', 'snapshot' => $contact, 'addresses_excluded' => true]);
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
            $emailOwner = mg_woocommerce_contact_match($pdo, $merchantUserId, $contact['email']);
            if ($emailOwner && (int)$emailOwner['id'] !== $localId) {
                mg_woocommerce_upsert_link($pdo, $connectionId, $contact, $localId, 'conflict', ['review_reason' => 'email_owned_by_another_contact', 'local_contact_id' => $localId, 'email_owner_contact_id' => (int)$emailOwner['id'], 'external_email' => $contact['email'], 'snapshot' => $contact, 'addresses_excluded' => true]);
                $pdo->commit();
                return 'review';
            }
            if ($localEmail !== '' && $localEmail !== $contact['email']) {
                mg_woocommerce_upsert_link($pdo, $connectionId, $contact, $localId, 'conflict', ['review_reason' => 'linked_email_changed', 'local_email' => $localEmail, 'external_email' => $contact['email'], 'snapshot' => $contact, 'addresses_excluded' => true]);
                $pdo->commit();
                return 'review';
            }
            $metadata = mg_woocommerce_contact_metadata($local, $contact, (string)$connection['public_id']);
            $updateName = trim((string)($local['display_name'] ?? '')) === '' || (string)($local['last_source_type'] ?? '') === 'woocommerce_customer';
            $pdo->prepare('UPDATE merchant_crm_contacts SET primary_email=COALESCE(primary_email,?),display_name=IF(?, ?, display_name),last_source_type=?,last_seen_at=GREATEST(last_seen_at,COALESCE(?,NOW())),source_summary_json=?,metadata_json=?,updated_at=NOW() WHERE id=?')
                ->execute([
                    $contact['email'],
                    $updateName ? 1 : 0,
                    $contact['display_name'],
                    'woocommerce_customer',
                    $contact['updated_on'] ?? $contact['created_on'],
                    json_encode(['last_event_type' => 'woocommerce_customer_synced', 'provider' => 'woocommerce'], JSON_UNESCAPED_SLASHES),
                    json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $localId,
                ]);
            $action = $link ? 'updated' : 'linked';
        } else {
            $publicId = mg_merchant_crm_uuid();
            $metadata = mg_woocommerce_contact_metadata([], $contact, (string)$connection['public_id']);
            $firstSeen = $contact['created_on'] ?: gmdate('Y-m-d H:i:s');
            $lastSeen = $contact['updated_on'] ?: $firstSeen;
            $pdo->prepare("INSERT INTO merchant_crm_contacts (public_id,merchant_user_id,user_id,primary_email,primary_phone,display_name,lifecycle_stage,crm_status,last_campaign_type,last_source_type,first_seen_at,last_seen_at,last_engaged_at,source_summary_json,tags_json,metadata_json,created_at,updated_at) VALUES (?,?,NULL,?,NULL,?,'lead','active','non_campaign','woocommerce_customer',?,?,NULL,?,'[]',?,NOW(),NOW())")
                ->execute([
                    $publicId,
                    $merchantUserId,
                    $contact['email'],
                    $contact['display_name'],
                    $firstSeen,
                    $lastSeen,
                    json_encode(['last_event_type' => 'woocommerce_customer_imported', 'provider' => 'woocommerce'], JSON_UNESCAPED_SLASHES),
                    json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]);
            $localId = (int)$pdo->lastInsertId();
            $action = 'created';
        }

        mg_woocommerce_upsert_link($pdo, $connectionId, $contact, $localId, 'linked', ['snapshot' => $contact, 'match' => $action, 'trigger' => $triggerType, 'addresses_excluded' => true]);
        mg_woocommerce_record_contact_event($pdo, $merchantUserId, $localId, $contact, 'woocommerce_customer_' . $action, ['trigger' => $triggerType, 'connection_id' => (string)$connection['public_id']]);
        $pdo->commit();
        return $action;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_woocommerce_sync_contacts(PDO $pdo, int $merchantUserId, bool $reset = false, int $pageSize = 100, int $maxPages = 5): array
{
    $auth = mg_woocommerce_credentials($pdo, $merchantUserId);
    $connection = $auth['connection'];
    $connectionId = (int)$connection['id'];
    $pageSize = max(1, min(100, $pageSize));
    $maxPages = max(1, min(10, $maxPages));
    $stateStmt = $pdo->prepare("SELECT * FROM merchant_integration_sync_state WHERE connection_id=? AND resource_key='contacts' LIMIT 1");
    $stateStmt->execute([$connectionId]);
    $state = $stateStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $pageNumber = $reset ? 1 : max(1, (int)($state['cursor_value'] ?? 1));
    $runPublicId = mg_integration_uuid();
    $pdo->prepare("INSERT INTO merchant_integration_sync_runs (public_id,connection_id,resource_key,direction,trigger_type,status,cursor_value,started_at,created_at,updated_at) VALUES (?,?,'contacts','import','manual','running',?,NOW(),NOW(),NOW())")
        ->execute([$runPublicId, $connectionId, (string)$pageNumber]);
    $runId = (int)$pdo->lastInsertId();
    $counts = ['processed' => 0, 'created' => 0, 'updated' => 0, 'linked' => 0, 'review' => 0, 'skipped' => 0, 'failed' => 0];
    $hasNext = false;

    try {
        for ($pageIndex = 0; $pageIndex < $maxPages; $pageIndex++) {
            $page = mg_woocommerce_provider()->listCustomers($auth['site_url'], $auth['consumer_key'], $auth['consumer_secret'], $pageNumber, $pageSize);
            foreach ((array)($page['customers'] ?? []) as $raw) {
                if (!is_array($raw)) continue;
                $counts['processed']++;
                try {
                    $result = mg_woocommerce_import_contact($pdo, $connection, $raw, 'manual');
                    if (isset($counts[$result])) $counts[$result]++; else $counts['updated']++;
                } catch (Throwable $contactError) {
                    $counts['failed']++;
                    mg_security_log('warning', 'merchant.integration.woocommerce_customer_failed', 'WooCommerce customer import failed.', ['exception_class' => $contactError::class], $merchantUserId);
                }
            }
            $pagination = is_array($page['pagination'] ?? null) ? $page['pagination'] : [];
            $hasNext = (bool)($pagination['has_next_page'] ?? false);
            $pageNumber = max(1, (int)($pagination['next_page'] ?? ($pageNumber + 1)));
            $pdo->prepare("INSERT INTO merchant_integration_sync_state (connection_id,resource_key,cursor_value,last_attempt_at,metadata_json,created_at,updated_at) VALUES (?,'contacts',?,NOW(),?,NOW(),NOW()) ON DUPLICATE KEY UPDATE cursor_value=VALUES(cursor_value),last_attempt_at=NOW(),metadata_json=VALUES(metadata_json),updated_at=NOW()")
                ->execute([$connectionId, $hasNext ? (string)$pageNumber : null, json_encode(['has_next_page' => $hasNext, 'addresses_excluded' => true, 'marketing_consent_inferred' => false], JSON_UNESCAPED_SLASHES)]);
            if (!$hasNext) break;
        }
        $status = $counts['failed'] > 0 ? 'partial' : 'completed';
        $pdo->prepare('UPDATE merchant_integration_sync_runs SET status=?,cursor_value=?,processed_count=?,created_count=?,updated_count=?,skipped_count=?,failed_count=?,finished_at=NOW(),updated_at=NOW() WHERE id=?')
            ->execute([$status, $hasNext ? (string)$pageNumber : null, $counts['processed'], $counts['created'], $counts['updated'] + $counts['linked'], $counts['skipped'] + $counts['review'], $counts['failed'], $runId]);
        $pdo->prepare("UPDATE merchant_integration_sync_state SET last_success_at=NOW(),last_error_code=NULL,last_error_message=NULL,updated_at=NOW() WHERE connection_id=? AND resource_key='contacts'")
            ->execute([$connectionId]);
        $pdo->prepare('UPDATE merchant_integration_connections SET last_sync_at=NOW(),last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_at=NOW() WHERE id=?')
            ->execute([$connectionId]);
        return ['run_id' => $runPublicId, 'status' => $status, 'counts' => $counts, 'has_more' => $hasNext, 'next_cursor_saved' => $hasNext, 'addresses_imported' => false, 'marketing_consent_inferred' => false];
    } catch (Throwable $error) {
        $pdo->prepare("UPDATE merchant_integration_sync_runs SET status='failed',error_code=?,error_message=?,processed_count=?,created_count=?,updated_count=?,skipped_count=?,failed_count=?,finished_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([mb_substr($error::class, 0, 120), mb_substr($error->getMessage(), 0, 1000), $counts['processed'], $counts['created'], $counts['updated'] + $counts['linked'], $counts['skipped'] + $counts['review'], $counts['failed'], $runId]);
        $pdo->prepare("INSERT INTO merchant_integration_sync_state (connection_id,resource_key,cursor_value,last_attempt_at,last_error_code,last_error_message,created_at,updated_at) VALUES (?,'contacts',?,NOW(),?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE last_attempt_at=NOW(),last_error_code=VALUES(last_error_code),last_error_message=VALUES(last_error_message),updated_at=NOW()")
            ->execute([$connectionId, (string)$pageNumber, mb_substr($error::class, 0, 120), mb_substr($error->getMessage(), 0, 1000)]);
        throw $error;
    }
}

function mg_woocommerce_contacts_status(PDO $pdo, int $merchantUserId): array
{
    if (!mg_integration_schema_ready($pdo)) return ['connected' => false, 'counts' => []];
    $connection = mg_integration_connection_row($pdo, $merchantUserId, 'woocommerce', false);
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
        'policy' => ['addresses_imported' => false, 'phone_from_address_imported' => false, 'marketing_consent_inferred' => false],
    ];
}
