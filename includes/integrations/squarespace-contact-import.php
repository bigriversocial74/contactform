<?php
declare(strict_types=1);

function mg_squarespace_contact_match(PDO $pdo, int $merchantUserId, string $email): ?array
{
    if ($email === '') return null;
    $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE merchant_user_id=? AND primary_email=? LIMIT 1');
    $stmt->execute([$merchantUserId, $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return function_exists('mg_crm_identity_resolve_contact') ? mg_crm_identity_resolve_contact($pdo, $merchantUserId, $row, false) : $row;
    if (function_exists('mg_crm_identity_alias_contact')) return mg_crm_identity_alias_contact($pdo, $merchantUserId, null, $email, null, false);
    return null;
}

function mg_squarespace_contact_link(PDO $pdo, int $connectionId, string $externalId, bool $forUpdate = false): ?array
{
    $sql = "SELECT * FROM merchant_integration_entity_links WHERE connection_id=? AND entity_type='contact' AND external_entity_id=? LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$connectionId, $externalId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_squarespace_contact_preview(PDO $pdo, int $merchantUserId, ?string $cursor = null, int $pageSize = 50): array
{
    if (!mg_integration_schema_ready($pdo)) throw new RuntimeException('Merchant integrations schema is not installed.');
    $auth = mg_squarespace_access_token($pdo, $merchantUserId);
    $connection = $auth['connection'];
    $provider = mg_integration_provider('squarespace');
    if (!$provider instanceof MgSquarespaceProvider) throw new RuntimeException('Squarespace provider is unavailable.');
    $page = $provider->listContacts($auth['token'], $cursor, max(1, min(100, $pageSize)));
    $items = [];
    foreach ((array)($page['contacts'] ?? []) as $raw) {
        if (!is_array($raw)) continue;
        $contact = mg_squarespace_normalize_contact($raw);
        $link = $contact['external_id'] !== '' ? mg_squarespace_contact_link($pdo, (int)$connection['id'], $contact['external_id'], false) : null;
        $matched = $contact['email'] !== '' ? mg_squarespace_contact_match($pdo, $merchantUserId, $contact['email']) : null;
        $action = 'create';
        $reason = 'New CRM contact';
        $localId = null;
        if ($contact['external_id'] === '' || $contact['email'] === '') {
            $action = 'review';
            $reason = 'Missing a valid external ID or email';
        } elseif ($link) {
            $localId = (int)($link['local_entity_id'] ?? 0) ?: null;
            $action = hash_equals((string)($link['sync_hash'] ?? ''), (string)$contact['sync_hash']) ? 'unchanged' : 'update';
            $reason = $action === 'unchanged' ? 'Already synchronized' : 'Linked Squarespace contact changed';
        } elseif ($matched) {
            $localId = (int)$matched['id'];
            $action = 'link';
            $reason = 'Exact CRM email match';
        }
        $items[] = [
            'external_id' => $contact['external_id'],
            'email' => $contact['email'],
            'name' => $contact['display_name'],
            'locale' => $contact['locale'],
            'accepts_marketing' => $contact['marketing']['accepts_marketing'],
            'marketing_joined_on' => $contact['marketing']['joined_on'],
            'marketing_left_on' => $contact['marketing']['left_on'],
            'action' => $action,
            'reason' => $reason,
            'local_contact_id' => $localId,
            'addresses_excluded' => true,
        ];
    }
    $pagination = is_array($page['pagination'] ?? null) ? $page['pagination'] : [];
    return [
        'provider' => 'squarespace',
        'connection_id' => (string)$connection['public_id'],
        'items' => $items,
        'page_count' => count($items),
        'pagination' => [
            'has_next_page' => (bool)($pagination['hasNextPage'] ?? false),
            'next_cursor' => trim((string)($pagination['nextPageCursor'] ?? '')) ?: null,
        ],
        'policy' => ['addresses_imported' => false, 'phone_from_address_imported' => false, 'marketing_consent_preserved' => true],
    ];
}

function mg_squarespace_contact_metadata(array $existing, array $contact, string $connectionPublicId): array
{
    $metadata = mg_integration_json($existing['metadata_json'] ?? null);
    $integrations = is_array($metadata['integrations'] ?? null) ? $metadata['integrations'] : [];
    $integrations['squarespace'] = [
        'connection_id' => $connectionPublicId,
        'external_contact_id' => $contact['external_id'],
        'locale' => $contact['locale'],
        'created_on' => $contact['created_on'],
        'updated_on' => $contact['updated_on'],
        'accepts_marketing' => $contact['marketing']['accepts_marketing'],
        'marketing_joined_on' => $contact['marketing']['joined_on'],
        'marketing_left_on' => $contact['marketing']['left_on'],
        'consent_source' => 'squarespace',
        'consent_verified_at' => gmdate('Y-m-d H:i:s'),
        'addresses_excluded' => true,
    ];
    $metadata['integrations'] = $integrations;
    return $metadata;
}

function mg_squarespace_record_contact_event(PDO $pdo, int $merchantUserId, int $contactId, array $contact, string $eventType, array $metadata = []): void
{
    $pdo->prepare('INSERT INTO merchant_crm_contact_events (public_id,merchant_user_id,crm_contact_id,campaign_id,campaign_type,event_type,source_type,source_public_id,user_id,email,phone,name,value_cents,metadata_json,created_at) VALUES (?,?,?,NULL,?,?,?,?,NULL,?,NULL,?,NULL,?,NOW())')
        ->execute([
            mg_merchant_crm_uuid(),
            $merchantUserId,
            $contactId,
            'non_campaign',
            $eventType,
            'squarespace_contact',
            mb_substr((string)$contact['external_id'], 0, 80),
            $contact['email'] !== '' ? $contact['email'] : null,
            $contact['display_name'],
            json_encode($metadata + ['addresses_excluded' => true, 'marketing' => $contact['marketing']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
}

function mg_squarespace_upsert_link(PDO $pdo, int $connectionId, array $contact, ?int $localContactId, string $status, array $metadata): void
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

function mg_squarespace_import_contact(PDO $pdo, array $connection, array $rawContact, string $triggerType = 'manual'): string
{
    $merchantUserId = (int)$connection['merchant_user_id'];
    $connectionId = (int)$connection['id'];
    $contact = mg_squarespace_normalize_contact($rawContact);
    if ($contact['external_id'] === '') return 'failed';

    $pdo->beginTransaction();
    try {
        $link = mg_squarespace_contact_link($pdo, $connectionId, $contact['external_id'], true);
        if ($link && (string)($link['sync_hash'] ?? '') === (string)$contact['sync_hash'] && (string)($link['status'] ?? '') === 'linked') {
            $pdo->commit();
            return 'skipped';
        }
        if ($contact['email'] === '') {
            mg_squarespace_upsert_link($pdo, $connectionId, $contact, null, 'pending_review', [
                'review_reason' => 'missing_valid_email',
                'snapshot' => $contact,
                'addresses_excluded' => true,
            ]);
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
            $emailOwner = mg_squarespace_contact_match($pdo, $merchantUserId, $contact['email']);
            if ($emailOwner && (int)$emailOwner['id'] !== $localId) {
                mg_squarespace_upsert_link($pdo, $connectionId, $contact, $localId, 'conflict', [
                    'review_reason' => 'email_owned_by_another_contact',
                    'local_contact_id' => $localId,
                    'email_owner_contact_id' => (int)$emailOwner['id'],
                    'external_email' => $contact['email'],
                    'snapshot' => $contact,
                    'addresses_excluded' => true,
                ]);
                $pdo->commit();
                return 'review';
            }
            if ($localEmail !== '' && $localEmail !== $contact['email']) {
                mg_squarespace_upsert_link($pdo, $connectionId, $contact, $localId, 'conflict', [
                    'review_reason' => 'linked_email_changed',
                    'local_email' => $localEmail,
                    'external_email' => $contact['email'],
                    'snapshot' => $contact,
                    'addresses_excluded' => true,
                ]);
                $pdo->commit();
                return 'review';
            }
            $metadata = mg_squarespace_contact_metadata($local, $contact, (string)$connection['public_id']);
            $updateName = trim((string)($local['display_name'] ?? '')) === '' || (string)($local['last_source_type'] ?? '') === 'squarespace_contact';
            $pdo->prepare('UPDATE merchant_crm_contacts SET primary_email=COALESCE(primary_email,?),display_name=IF(?, ?, display_name),last_source_type=?,last_seen_at=GREATEST(last_seen_at,COALESCE(?,NOW())),source_summary_json=?,metadata_json=?,updated_at=NOW() WHERE id=?')
                ->execute([
                    $contact['email'],
                    $updateName ? 1 : 0,
                    $contact['display_name'],
                    'squarespace_contact',
                    $contact['updated_on'] ?? $contact['created_on'],
                    json_encode(['last_event_type' => 'squarespace_contact_synced', 'provider' => 'squarespace'], JSON_UNESCAPED_SLASHES),
                    json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $localId,
                ]);
            $action = $link ? 'updated' : 'linked';
        } else {
            $publicId = mg_merchant_crm_uuid();
            $metadata = mg_squarespace_contact_metadata([], $contact, (string)$connection['public_id']);
            $firstSeen = $contact['created_on'] ?: gmdate('Y-m-d H:i:s');
            $lastSeen = $contact['updated_on'] ?: $firstSeen;
            $pdo->prepare("INSERT INTO merchant_crm_contacts (public_id,merchant_user_id,user_id,primary_email,primary_phone,display_name,lifecycle_stage,crm_status,last_campaign_type,last_source_type,first_seen_at,last_seen_at,last_engaged_at,source_summary_json,tags_json,metadata_json,created_at,updated_at) VALUES (?,?,NULL,?,NULL,?,'lead','active','non_campaign','squarespace_contact',?,?,NULL,?,'[]',?,NOW(),NOW())")
                ->execute([
                    $publicId,
                    $merchantUserId,
                    $contact['email'],
                    $contact['display_name'],
                    $firstSeen,
                    $lastSeen,
                    json_encode(['last_event_type' => 'squarespace_contact_imported', 'provider' => 'squarespace'], JSON_UNESCAPED_SLASHES),
                    json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]);
            $localId = (int)$pdo->lastInsertId();
            $action = 'created';
        }

        mg_squarespace_upsert_link($pdo, $connectionId, $contact, $localId, 'linked', [
            'snapshot' => $contact,
            'match' => $action,
            'trigger' => $triggerType,
            'addresses_excluded' => true,
        ]);
        mg_squarespace_record_contact_event($pdo, $merchantUserId, $localId, $contact, 'squarespace_contact_' . $action, [
            'trigger' => $triggerType,
            'connection_id' => (string)$connection['public_id'],
        ]);
        $pdo->commit();
        return $action;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
