<?php
declare(strict_types=1);

function mg_mailchimp_contact_match(PDO $pdo, int $merchantUserId, string $email): ?array
{
    if ($email === '') return null;
    $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE merchant_user_id=? AND primary_email=? LIMIT 1');
    $stmt->execute([$merchantUserId, $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return function_exists('mg_crm_identity_resolve_contact') ? mg_crm_identity_resolve_contact($pdo, $merchantUserId, $row, false) : $row;
    return function_exists('mg_crm_identity_alias_contact') ? mg_crm_identity_alias_contact($pdo, $merchantUserId, null, $email, null, false) : null;
}

function mg_mailchimp_contact_link(PDO $pdo, int $connectionId, string $externalId, string $audienceId, bool $forUpdate = false): ?array
{
    $compoundId = $audienceId . ':' . $externalId;
    $sql = "SELECT * FROM merchant_integration_entity_links WHERE connection_id=? AND entity_type='contact' AND external_entity_id=? LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$connectionId, $compoundId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function mg_mailchimp_contact_preview(PDO $pdo, int $merchantUserId, ?string $cursor = null, int $pageSize = 50): array
{
    $auth = mg_mailchimp_credentials($pdo, $merchantUserId);
    $audience = mg_mailchimp_selected_audience($auth);
    $offset = max(0, (int)($cursor ?? 0));
    try {
        $page = mg_mailchimp_provider()->listMembers($auth['api_endpoint'], $auth['token'], $audience['id'], $offset, max(1, min(1000, $pageSize)));
    } catch (Throwable $error) {
        mg_mailchimp_mark_reauthorization($pdo, (int)$auth['connection']['id'], $error);
        throw $error;
    }
    $items = [];
    foreach ((array)($page['members'] ?? []) as $raw) {
        if (!is_array($raw)) continue;
        $contact = mg_mailchimp_normalize_member($raw, $audience['id']);
        $link = $contact['external_id'] !== '' ? mg_mailchimp_contact_link($pdo, (int)$auth['connection']['id'], $contact['external_id'], $audience['id']) : null;
        $matched = $contact['email'] !== '' ? mg_mailchimp_contact_match($pdo, $merchantUserId, $contact['email']) : null;
        $action = 'create'; $reason = 'New CRM contact';
        if ($contact['external_id'] === '' || $contact['email'] === '') { $action = 'review'; $reason = 'Missing a valid member ID or email'; }
        elseif ($link) { $action = hash_equals((string)($link['sync_hash'] ?? ''), (string)$contact['sync_hash']) ? 'unchanged' : 'update'; $reason = $action === 'unchanged' ? 'Already synchronized' : 'Linked Mailchimp member changed'; }
        elseif ($matched) { $action = 'link'; $reason = 'Exact CRM email match'; }
        $items[] = [
            'external_id' => $contact['external_id'], 'email' => $contact['email'], 'name' => $contact['display_name'],
            'accepts_marketing' => $contact['marketing']['accepts_marketing'], 'marketing_status' => $contact['marketing']['status'],
            'action' => $action, 'reason' => $reason, 'addresses_excluded' => true, 'phone_numbers_excluded' => true,
        ];
    }
    $total = max(0, (int)($page['total_items'] ?? count($items)));
    $nextOffset = $offset + count($items);
    return [
        'provider' => 'mailchimp', 'connection_id' => (string)$auth['connection']['public_id'], 'audience' => $audience,
        'items' => $items, 'page_count' => count($items),
        'pagination' => ['has_next_page' => $nextOffset < $total, 'next_cursor' => $nextOffset < $total ? (string)$nextOffset : null, 'total_items' => $total],
        'policy' => ['addresses_imported' => false, 'phone_numbers_imported' => false, 'marketing_status_preserved' => true, 'marketing_consent_inferred' => false],
    ];
}

function mg_mailchimp_contact_metadata(array $existing, array $contact, string $connectionPublicId, string $audienceName): array
{
    $metadata = mg_integration_json($existing['metadata_json'] ?? null);
    $integrations = is_array($metadata['integrations'] ?? null) ? $metadata['integrations'] : [];
    $integrations['mailchimp'] = [
        'connection_id' => $connectionPublicId, 'audience_id' => $contact['audience_id'], 'audience_name' => $audienceName,
        'external_member_id' => $contact['external_id'], 'unique_email_id' => $contact['unique_email_id'], 'web_id' => $contact['web_id'],
        'email_type' => $contact['email_type'], 'vip' => $contact['vip'], 'tags' => $contact['tags'],
        'created_on' => $contact['created_on'], 'updated_on' => $contact['updated_on'], 'marketing' => $contact['marketing'],
        'addresses_excluded' => true, 'phone_numbers_excluded' => true, 'verified_at' => gmdate('Y-m-d H:i:s'),
    ];
    $metadata['integrations'] = $integrations;
    return $metadata;
}

function mg_mailchimp_upsert_link(PDO $pdo, int $connectionId, array $contact, ?int $localContactId, string $status, array $metadata): void
{
    $compoundId = $contact['audience_id'] . ':' . $contact['external_id'];
    $pdo->prepare("INSERT INTO merchant_integration_entity_links (public_id,connection_id,entity_type,external_entity_id,local_entity_type,local_entity_id,external_updated_at,last_synced_at,sync_hash,status,metadata_json,created_at,updated_at) VALUES (?,?,'contact',?,'merchant_crm_contact',?,?,NOW(),?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE local_entity_id=VALUES(local_entity_id),external_updated_at=VALUES(external_updated_at),last_synced_at=NOW(),sync_hash=VALUES(sync_hash),status=VALUES(status),metadata_json=VALUES(metadata_json),updated_at=NOW()")
        ->execute([mg_integration_uuid(), $connectionId, $compoundId, $localContactId, $contact['updated_on'] ?? $contact['created_on'], $contact['sync_hash'], $status, json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
}

function mg_mailchimp_record_contact_event(PDO $pdo, int $merchantUserId, int $contactId, array $contact, string $eventType, array $metadata = []): void
{
    $pdo->prepare('INSERT INTO merchant_crm_contact_events (public_id,merchant_user_id,crm_contact_id,campaign_id,campaign_type,event_type,source_type,source_public_id,user_id,email,phone,name,value_cents,metadata_json,created_at) VALUES (?,?,?,NULL,?,?,?,?,NULL,?,NULL,?,NULL,?,NOW())')
        ->execute([mg_merchant_crm_uuid(), $merchantUserId, $contactId, 'non_campaign', $eventType, 'mailchimp_member', mb_substr($contact['audience_id'] . ':' . $contact['external_id'], 0, 80), $contact['email'] ?: null, $contact['display_name'], json_encode($metadata + ['marketing' => $contact['marketing']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
}

function mg_mailchimp_import_contact(PDO $pdo, array $connection, array $rawMember, string $audienceId, string $audienceName, string $triggerType = 'manual'): string
{
    $merchantUserId = (int)$connection['merchant_user_id'];
    $connectionId = (int)$connection['id'];
    $contact = mg_mailchimp_normalize_member($rawMember, $audienceId);
    if ($contact['external_id'] === '') return 'failed';
    $pdo->beginTransaction();
    try {
        $link = mg_mailchimp_contact_link($pdo, $connectionId, $contact['external_id'], $audienceId, true);
        if ($link && (string)($link['sync_hash'] ?? '') === (string)$contact['sync_hash'] && (string)($link['status'] ?? '') === 'linked') { $pdo->commit(); return 'skipped'; }
        if ($contact['email'] === '') { mg_mailchimp_upsert_link($pdo, $connectionId, $contact, null, 'pending_review', ['review_reason' => 'missing_valid_email', 'snapshot' => $contact]); $pdo->commit(); return 'review'; }
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
            $emailOwner = mg_mailchimp_contact_match($pdo, $merchantUserId, $contact['email']);
            if ($emailOwner && (int)$emailOwner['id'] !== $localId) { mg_mailchimp_upsert_link($pdo, $connectionId, $contact, $localId, 'conflict', ['review_reason' => 'email_owned_by_another_contact', 'snapshot' => $contact]); $pdo->commit(); return 'review'; }
            if ($localEmail !== '' && $localEmail !== $contact['email']) { mg_mailchimp_upsert_link($pdo, $connectionId, $contact, $localId, 'conflict', ['review_reason' => 'linked_email_changed', 'local_email' => $localEmail, 'external_email' => $contact['email'], 'snapshot' => $contact]); $pdo->commit(); return 'review'; }
            $metadata = mg_mailchimp_contact_metadata($local, $contact, (string)$connection['public_id'], $audienceName);
            $updateName = trim((string)($local['display_name'] ?? '')) === '' || (string)($local['last_source_type'] ?? '') === 'mailchimp_member';
            $pdo->prepare('UPDATE merchant_crm_contacts SET primary_email=COALESCE(primary_email,?),display_name=IF(?, ?, display_name),last_source_type=?,last_seen_at=GREATEST(last_seen_at,COALESCE(?,NOW())),source_summary_json=?,metadata_json=?,updated_at=NOW() WHERE id=?')
                ->execute([$contact['email'], $updateName ? 1 : 0, $contact['display_name'], 'mailchimp_member', $contact['updated_on'] ?? $contact['created_on'], json_encode(['last_event_type' => 'mailchimp_member_synced', 'provider' => 'mailchimp', 'marketing_status' => $contact['marketing']['status']], JSON_UNESCAPED_SLASHES), json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $localId]);
            $action = $link ? 'updated' : 'linked';
        } else {
            $metadata = mg_mailchimp_contact_metadata([], $contact, (string)$connection['public_id'], $audienceName);
            $firstSeen = $contact['created_on'] ?: gmdate('Y-m-d H:i:s'); $lastSeen = $contact['updated_on'] ?: $firstSeen;
            $pdo->prepare("INSERT INTO merchant_crm_contacts (public_id,merchant_user_id,user_id,primary_email,primary_phone,display_name,lifecycle_stage,crm_status,last_campaign_type,last_source_type,first_seen_at,last_seen_at,last_engaged_at,source_summary_json,tags_json,metadata_json,created_at,updated_at) VALUES (?,?,NULL,?,NULL,?,'lead','active','non_campaign','mailchimp_member',?,?,NULL,?,?,?,NOW(),NOW())")
                ->execute([mg_merchant_crm_uuid(), $merchantUserId, $contact['email'], $contact['display_name'], $firstSeen, $lastSeen, json_encode(['last_event_type' => 'mailchimp_member_imported', 'provider' => 'mailchimp', 'marketing_status' => $contact['marketing']['status']], JSON_UNESCAPED_SLASHES), json_encode($contact['tags'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
            $localId = (int)$pdo->lastInsertId(); $action = 'created';
        }
        mg_mailchimp_upsert_link($pdo, $connectionId, $contact, $localId, 'linked', ['snapshot' => $contact, 'match' => $action, 'trigger' => $triggerType]);
        mg_mailchimp_record_contact_event($pdo, $merchantUserId, $localId, $contact, 'mailchimp_member_' . $action, ['trigger' => $triggerType, 'audience_id' => $audienceId, 'audience_name' => $audienceName]);
        $pdo->commit();
        return $action;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
