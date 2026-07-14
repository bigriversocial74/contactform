<?php
declare(strict_types=1);

function mg_klaviyo_profile_match(PDO $pdo, int $merchantUserId, string $email): ?array
{
    if ($email === '') return null;
    $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE merchant_user_id=? AND primary_email=? LIMIT 1');
    $stmt->execute([$merchantUserId, $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return function_exists('mg_crm_identity_resolve_contact') ? mg_crm_identity_resolve_contact($pdo, $merchantUserId, $row, false) : $row;
    return function_exists('mg_crm_identity_alias_contact') ? mg_crm_identity_alias_contact($pdo, $merchantUserId, null, $email, null, false) : null;
}

function mg_klaviyo_profile_link(PDO $pdo, int $connectionId, string $externalId, bool $forUpdate = false): ?array
{
    $sql = "SELECT * FROM merchant_integration_entity_links WHERE connection_id=? AND entity_type='contact' AND external_entity_id=? LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$connectionId, $externalId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function mg_klaviyo_profile_preview(PDO $pdo, int $merchantUserId, ?string $cursor = null, int $pageSize = 50): array
{
    $auth = mg_klaviyo_access_credentials($pdo, $merchantUserId);
    $page = mg_klaviyo_provider()->listProfiles($auth['token'], $cursor, max(1, min(100, $pageSize)));
    $connection = $auth['connection'];
    $items = [];
    foreach ((array)($page['profiles'] ?? []) as $raw) {
        if (!is_array($raw)) continue;
        $profile = mg_klaviyo_normalize_profile($raw);
        $link = $profile['external_id'] !== '' ? mg_klaviyo_profile_link($pdo, (int)$connection['id'], $profile['external_id']) : null;
        $matched = $profile['email'] !== '' ? mg_klaviyo_profile_match($pdo, $merchantUserId, $profile['email']) : null;
        $action = 'create';
        $reason = 'New CRM contact';
        if ($profile['external_id'] === '' || $profile['email'] === '') {
            $action = 'review';
            $reason = 'Missing a valid Klaviyo profile ID or email';
        } elseif ($link) {
            $action = hash_equals((string)($link['sync_hash'] ?? ''), (string)$profile['sync_hash']) ? 'unchanged' : 'update';
            $reason = $action === 'unchanged' ? 'Already synchronized' : 'Linked Klaviyo profile changed';
        } elseif ($matched) {
            $action = 'link';
            $reason = 'Exact CRM email match';
        }
        $items[] = [
            'external_id' => $profile['external_id'],
            'email' => $profile['email'],
            'name' => $profile['display_name'],
            'accepts_marketing' => $profile['marketing']['accepts_marketing'],
            'marketing_status' => $profile['marketing']['status'],
            'can_receive_email_marketing' => $profile['marketing']['can_receive_email_marketing'],
            'consent_timestamp' => $profile['marketing']['consent_timestamp'],
            'action' => $action,
            'reason' => $reason,
            'addresses_excluded' => true,
            'phone_numbers_excluded' => true,
            'custom_properties_excluded' => true,
        ];
    }
    return [
        'provider' => 'klaviyo',
        'connection_id' => (string)$connection['public_id'],
        'items' => $items,
        'page_count' => count($items),
        'pagination' => is_array($page['pagination'] ?? null) ? $page['pagination'] : [],
        'policy' => [
            'addresses_imported' => false,
            'phone_numbers_imported' => false,
            'custom_properties_imported' => false,
            'marketing_status_preserved' => true,
            'marketing_consent_inferred' => false,
        ],
    ];
}

function mg_klaviyo_profile_metadata(array $existing, array $profile, string $connectionPublicId): array
{
    $metadata = mg_integration_json($existing['metadata_json'] ?? null);
    $integrations = is_array($metadata['integrations'] ?? null) ? $metadata['integrations'] : [];
    $integrations['klaviyo'] = [
        'connection_id' => $connectionPublicId,
        'external_profile_id' => $profile['external_id'],
        'external_reference' => $profile['external_reference'],
        'created_on' => $profile['created_on'],
        'updated_on' => $profile['updated_on'],
        'marketing' => $profile['marketing'],
        'addresses_excluded' => true,
        'phone_numbers_excluded' => true,
        'location_excluded' => true,
        'custom_properties_excluded' => true,
        'verified_at' => gmdate('Y-m-d H:i:s'),
    ];
    $metadata['integrations'] = $integrations;
    return $metadata;
}

function mg_klaviyo_upsert_link(PDO $pdo, int $connectionId, array $profile, ?int $localContactId, string $status, array $metadata): void
{
    $pdo->prepare("INSERT INTO merchant_integration_entity_links (public_id,connection_id,entity_type,external_entity_id,local_entity_type,local_entity_id,external_updated_at,last_synced_at,sync_hash,status,metadata_json,created_at,updated_at) VALUES (?,?,'contact',?,'merchant_crm_contact',?,?,NOW(),?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE local_entity_id=VALUES(local_entity_id),external_updated_at=VALUES(external_updated_at),last_synced_at=NOW(),sync_hash=VALUES(sync_hash),status=VALUES(status),metadata_json=VALUES(metadata_json),updated_at=NOW()")
        ->execute([
            mg_integration_uuid(),
            $connectionId,
            $profile['external_id'],
            $localContactId,
            $profile['updated_on'] ?? $profile['created_on'],
            $profile['sync_hash'],
            $status,
            json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
}

function mg_klaviyo_record_profile_event(PDO $pdo, int $merchantUserId, int $contactId, array $profile, string $eventType, array $metadata = []): void
{
    $pdo->prepare('INSERT INTO merchant_crm_contact_events (public_id,merchant_user_id,crm_contact_id,campaign_id,campaign_type,event_type,source_type,source_public_id,user_id,email,phone,name,value_cents,metadata_json,created_at) VALUES (?,?,?,NULL,?,?,?,?,NULL,?,NULL,?,NULL,?,NOW())')
        ->execute([
            mg_merchant_crm_uuid(),
            $merchantUserId,
            $contactId,
            'non_campaign',
            $eventType,
            'klaviyo_profile',
            mb_substr((string)$profile['external_id'], 0, 80),
            $profile['email'] !== '' ? $profile['email'] : null,
            $profile['display_name'],
            json_encode($metadata + ['marketing' => $profile['marketing']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
}

function mg_klaviyo_import_profile(PDO $pdo, array $connection, array $rawProfile, string $triggerType = 'manual'): string
{
    $merchantUserId = (int)$connection['merchant_user_id'];
    $connectionId = (int)$connection['id'];
    $profile = mg_klaviyo_normalize_profile($rawProfile);
    if ($profile['external_id'] === '') return 'failed';

    $pdo->beginTransaction();
    try {
        $link = mg_klaviyo_profile_link($pdo, $connectionId, $profile['external_id'], true);
        if ($link && (string)($link['sync_hash'] ?? '') === (string)$profile['sync_hash'] && (string)($link['status'] ?? '') === 'linked') {
            $pdo->commit();
            return 'skipped';
        }
        if ($profile['email'] === '') {
            mg_klaviyo_upsert_link($pdo, $connectionId, $profile, null, 'pending_review', ['review_reason' => 'missing_valid_email', 'snapshot' => $profile]);
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
        if (!$local) $local = mg_merchant_crm_contact($pdo, $merchantUserId, null, $profile['email'], null);

        if ($local) {
            $localId = (int)$local['id'];
            $localEmail = strtolower(trim((string)($local['primary_email'] ?? '')));
            $emailOwner = mg_klaviyo_profile_match($pdo, $merchantUserId, $profile['email']);
            if ($emailOwner && (int)$emailOwner['id'] !== $localId) {
                mg_klaviyo_upsert_link($pdo, $connectionId, $profile, $localId, 'conflict', ['review_reason' => 'email_owned_by_another_contact', 'email_owner_contact_id' => (int)$emailOwner['id'], 'snapshot' => $profile]);
                $pdo->commit();
                return 'review';
            }
            if ($localEmail !== '' && $localEmail !== $profile['email']) {
                mg_klaviyo_upsert_link($pdo, $connectionId, $profile, $localId, 'conflict', ['review_reason' => 'linked_email_changed', 'local_email' => $localEmail, 'external_email' => $profile['email'], 'snapshot' => $profile]);
                $pdo->commit();
                return 'review';
            }
            $metadata = mg_klaviyo_profile_metadata($local, $profile, (string)$connection['public_id']);
            $updateName = trim((string)($local['display_name'] ?? '')) === '' || (string)($local['last_source_type'] ?? '') === 'klaviyo_profile';
            $pdo->prepare('UPDATE merchant_crm_contacts SET primary_email=COALESCE(primary_email,?),display_name=IF(?, ?, display_name),last_source_type=?,last_seen_at=GREATEST(last_seen_at,COALESCE(?,NOW())),source_summary_json=?,metadata_json=?,updated_at=NOW() WHERE id=?')
                ->execute([
                    $profile['email'],
                    $updateName ? 1 : 0,
                    $profile['display_name'],
                    'klaviyo_profile',
                    $profile['updated_on'] ?? $profile['created_on'],
                    json_encode(['last_event_type' => 'klaviyo_profile_synced', 'provider' => 'klaviyo', 'marketing_status' => $profile['marketing']['status']], JSON_UNESCAPED_SLASHES),
                    json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $localId,
                ]);
            $action = $link ? 'updated' : 'linked';
        } else {
            $metadata = mg_klaviyo_profile_metadata([], $profile, (string)$connection['public_id']);
            $firstSeen = $profile['created_on'] ?: gmdate('Y-m-d H:i:s');
            $lastSeen = $profile['updated_on'] ?: $firstSeen;
            $pdo->prepare("INSERT INTO merchant_crm_contacts (public_id,merchant_user_id,user_id,primary_email,primary_phone,display_name,lifecycle_stage,crm_status,last_campaign_type,last_source_type,first_seen_at,last_seen_at,last_engaged_at,source_summary_json,tags_json,metadata_json,created_at,updated_at) VALUES (?,?,NULL,?,NULL,?,'lead','active','non_campaign','klaviyo_profile',?,?,NULL,?,'[]',?,NOW(),NOW())")
                ->execute([
                    mg_merchant_crm_uuid(),
                    $merchantUserId,
                    $profile['email'],
                    $profile['display_name'],
                    $firstSeen,
                    $lastSeen,
                    json_encode(['last_event_type' => 'klaviyo_profile_imported', 'provider' => 'klaviyo', 'marketing_status' => $profile['marketing']['status']], JSON_UNESCAPED_SLASHES),
                    json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]);
            $localId = (int)$pdo->lastInsertId();
            $action = 'created';
        }

        mg_klaviyo_upsert_link($pdo, $connectionId, $profile, $localId, 'linked', ['snapshot' => $profile, 'match' => $action, 'trigger' => $triggerType]);
        mg_klaviyo_record_profile_event($pdo, $merchantUserId, $localId, $profile, 'klaviyo_profile_' . $action, ['trigger' => $triggerType, 'connection_id' => (string)$connection['public_id']]);
        $pdo->commit();
        return $action;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
