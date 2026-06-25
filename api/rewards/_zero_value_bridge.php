<?php
declare(strict_types=1);

function mg_zero_reward_bridge_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_zero_reward_bridge_table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function mg_zero_reward_issue_from_wallet(PDO $pdo, array $input): array
{
    $walletPublicId = (string)($input['wallet_item_public_id'] ?? $input['source_reference'] ?? mg_zero_reward_bridge_uuid());
    $recipientUserId = (int)($input['recipient_user_id'] ?? 0);
    $merchantUserId = (int)($input['merchant_user_id'] ?? 0);
    $title = trim((string)($input['title'] ?? 'Microgifter reward'));
    $sourceType = trim((string)($input['source_type'] ?? 'wallet_reward'));
    $sourceReference = trim((string)($input['source_reference'] ?? $walletPublicId));
    $expiresAt = $input['expires_at'] ?? null;
    $metadata = [
        'wallet_item_public_id' => $walletPublicId,
        'wallet_item_db_id' => $input['wallet_item_db_id'] ?? null,
        'campaign_public_id' => $input['campaign_public_id'] ?? null,
        'reward_template_public_id' => $input['reward_template_public_id'] ?? null,
        'source_type' => $sourceType,
        'source_reference' => $sourceReference,
        'terms' => $input['terms'] ?? [],
    ];

    if ($recipientUserId < 1) {
        return [
            'schema_ready' => true,
            'pending_account_link' => true,
            'wallet_item_id' => $walletPublicId,
            'source_type' => $sourceType,
            'source_reference' => $sourceReference,
        ];
    }

    try {
        if (!mg_zero_reward_bridge_table_exists($pdo, 'gifts')) {
            return [
                'schema_ready' => false,
                'pending_account_link' => false,
                'wallet_item_id' => $walletPublicId,
                'recipient_user_id' => $recipientUserId,
                'source_type' => $sourceType,
            ];
        }

        $publicId = mg_zero_reward_bridge_uuid();
        $stmt = $pdo->prepare('INSERT INTO gifts (public_id, sender_user_id, recipient_user_id, title, description, status, source_type, source_reference, metadata_json, expires_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([
            $publicId,
            $merchantUserId > 0 ? $merchantUserId : null,
            $recipientUserId,
            $title !== '' ? $title : 'Microgifter reward',
            $input['description'] ?? null,
            'sent',
            $sourceType,
            $sourceReference,
            json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $expiresAt ?: null,
        ]);

        return [
            'schema_ready' => true,
            'pending_account_link' => false,
            'gift_id' => $publicId,
            'wallet_item_id' => $walletPublicId,
            'recipient_user_id' => $recipientUserId,
            'source_type' => $sourceType,
            'source_reference' => $sourceReference,
        ];
    } catch (Throwable $error) {
        mg_security_log('warning', 'zero_reward_bridge.issue_failed', 'Unable to project wallet reward into gifts.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantUserId ?: null);
        return [
            'schema_ready' => false,
            'pending_account_link' => false,
            'wallet_item_id' => $walletPublicId,
            'recipient_user_id' => $recipientUserId,
            'source_type' => $sourceType,
            'source_reference' => $sourceReference,
            'error' => 'bridge_failed',
        ];
    }
}
