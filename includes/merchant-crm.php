<?php
declare(strict_types=1);

function mg_merchant_crm_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_merchant_crm_token(mixed $value, string $fallback = 'unknown'): string
{
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9_:-]+/', '_', $value) ?: '';
    $value = trim($value, '_:-');
    return $value !== '' ? substr($value, 0, 80) : $fallback;
}

function mg_merchant_crm_email(mixed $email): ?string
{
    $email = strtolower(trim((string) $email));
    if ($email === '') return null;
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? substr($email, 0, 255) : null;
}

function mg_merchant_crm_text(mixed $value, int $max): ?string
{
    $value = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
    return $value !== '' ? substr($value, 0, $max) : null;
}

function mg_merchant_crm_stage(string $eventType, string $sourceType, string $campaignType): string
{
    if (str_contains($eventType, 'purchase') || $sourceType === 'purchase') return 'customer';
    if (str_contains($eventType, 'redeem') || str_contains($eventType, 'claim')) return 'redeemer';
    if ($sourceType === 'profile_follow' || $campaignType === 'profile_follow') return 'follower';
    if (str_contains($eventType, 'support')) return 'supporter';
    if (str_contains($eventType, 'reward')) return 'prospect';
    return 'lead';
}

function mg_merchant_crm_contact(PDO $pdo, int $merchantId, ?int $userId, ?string $email): ?array
{
    if ($userId) {
        $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE merchant_user_id=? AND user_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$merchantId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
    }
    if ($email) {
        $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE merchant_user_id=? AND primary_email=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$merchantId, $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
    }
    return null;
}

function mg_merchant_crm_record_event(PDO $pdo, array $input): array
{
    $merchantId = (int) ($input['merchant_user_id'] ?? 0);
    if ($merchantId < 1) return ['schema_ready' => false, 'skipped' => true];

    $campaignType = mg_merchant_crm_token($input['campaign_type'] ?? null);
    $sourceType = mg_merchant_crm_token($input['source_type'] ?? $campaignType, $campaignType);
    $eventType = mg_merchant_crm_token($input['event_type'] ?? 'crm_entry', 'crm_entry');
    $campaignId = isset($input['campaign_id']) && (int) $input['campaign_id'] > 0 ? (int) $input['campaign_id'] : null;
    $userId = isset($input['user_id']) && (int) $input['user_id'] > 0 ? (int) $input['user_id'] : null;
    $email = mg_merchant_crm_email($input['email'] ?? null);
    $phone = mg_merchant_crm_text($input['phone'] ?? null, 80);
    $name = mg_merchant_crm_text($input['name'] ?? null, 180);
    $sourcePublicId = mg_merchant_crm_text($input['source_public_id'] ?? null, 80);
    $valueCents = isset($input['value_cents']) ? max(0, (int) $input['value_cents']) : null;
    $metadata = is_array($input['metadata'] ?? null) ? $input['metadata'] : [];
    $metadata['campaign_type'] = $campaignType;
    $metadata['source_type'] = $sourceType;
    $metadata['event_type'] = $eventType;
    $stage = mg_merchant_crm_stage($eventType, $sourceType, $campaignType);

    try {
        $contact = mg_merchant_crm_contact($pdo, $merchantId, $userId, $email);
        if ($contact) {
            $contactId = (int) $contact['id'];
            $contactPublicId = (string) $contact['public_id'];
            $pdo->prepare('UPDATE merchant_crm_contacts SET user_id=COALESCE(user_id,?), primary_email=COALESCE(primary_email,?), primary_phone=COALESCE(?,primary_phone), display_name=COALESCE(?,display_name), lifecycle_stage=?, last_campaign_type=?, last_source_type=?, last_seen_at=NOW(), last_engaged_at=NOW(), source_summary_json=?, metadata_json=?, updated_at=NOW() WHERE id=?')
                ->execute([$userId, $email, $phone, $name, $stage, $campaignType, $sourceType, json_encode(['last_event_type'=>$eventType], JSON_UNESCAPED_SLASHES), json_encode($metadata, JSON_UNESCAPED_SLASHES), $contactId]);
        } else {
            $contactPublicId = mg_merchant_crm_uuid();
            $pdo->prepare('INSERT INTO merchant_crm_contacts (public_id,merchant_user_id,user_id,primary_email,primary_phone,display_name,lifecycle_stage,crm_status,last_campaign_type,last_source_type,first_seen_at,last_seen_at,last_engaged_at,source_summary_json,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW(),?,?,NOW(),NOW())')
                ->execute([$contactPublicId, $merchantId, $userId, $email, $phone, $name, $stage, 'active', $campaignType, $sourceType, json_encode(['last_event_type'=>$eventType], JSON_UNESCAPED_SLASHES), json_encode($metadata, JSON_UNESCAPED_SLASHES)]);
            $contactId = (int) $pdo->lastInsertId();
        }

        $eventPublicId = mg_merchant_crm_uuid();
        $pdo->prepare('INSERT INTO merchant_crm_contact_events (public_id,merchant_user_id,crm_contact_id,campaign_id,campaign_type,event_type,source_type,source_public_id,user_id,email,phone,name,value_cents,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())')
            ->execute([$eventPublicId, $merchantId, $contactId, $campaignId, $campaignType, $eventType, $sourceType, $sourcePublicId, $userId, $email, $phone, $name, $valueCents, json_encode($metadata, JSON_UNESCAPED_SLASHES)]);

        if ($campaignId) {
            $pdo->prepare('INSERT INTO merchant_crm_contact_campaigns (public_id,merchant_user_id,crm_contact_id,campaign_id,campaign_type,first_event_at,last_event_at,event_count,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW(),1,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE last_event_at=NOW(),event_count=event_count+1,metadata_json=VALUES(metadata_json),updated_at=NOW()')
                ->execute([mg_merchant_crm_uuid(), $merchantId, $contactId, $campaignId, $campaignType, json_encode(['last_event_type'=>$eventType,'source_type'=>$sourceType], JSON_UNESCAPED_SLASHES)]);
        }

        return ['schema_ready'=>true,'contact_id'=>$contactPublicId,'event_id'=>$eventPublicId,'campaign_type'=>$campaignType,'source_type'=>$sourceType];
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant_crm.record_event_failed', 'Merchant CRM event could not be recorded.', ['exception_class'=>$error::class, 'message'=>$error->getMessage()], $merchantId);
        return ['schema_ready'=>false,'skipped'=>true,'campaign_type'=>$campaignType,'source_type'=>$sourceType];
    }
}
