<?php
declare(strict_types=1);

function mg_public_campaign_outbound_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_public_campaign_queue_outbound(PDO $pdo, array $campaign, array $contact, string $messageType, array $context = []): array
{
    $merchantId = (int) ($campaign['merchant_user_id'] ?? 0);
    $campaignId = (int) ($campaign['id'] ?? 0);
    $contactId = (int) ($contact['id'] ?? 0);
    if ($merchantId < 1 || $campaignId < 1 || $contactId < 1) return ['queued' => false, 'reason' => 'missing_context'];
    $eventId = mg_public_campaign_outbound_uuid();
    $payload = $context + [
        'message_type' => $messageType,
        'campaign_type' => (string) ($campaign['campaign_type'] ?? 'unknown'),
        'campaign_public_id' => (string) ($campaign['public_id'] ?? ''),
        'contact_public_id' => (string) ($contact['public_id'] ?? ''),
        'email' => (string) ($contact['email'] ?? ''),
        'outbound_email_pending' => true,
    ];
    $stmt = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $stmt->execute([$eventId, $merchantId, $campaignId, null, $contactId, 'outbound_email.queued', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    return ['queued' => true, 'event_id' => $eventId, 'message_type' => $messageType];
}
