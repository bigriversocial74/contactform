<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function mg_pppm_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function mg_pppm_item_id(): string
{
    return 'PPPM-' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
}

function mg_pppm_json(mixed $value): ?string
{
    if ($value === null || $value === [] || $value === '') {
        return null;
    }
    if (!is_array($value)) {
        mg_fail('Expected an object.', 422);
    }
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || strlen($json) > 262144) {
        mg_fail('Payload is too large.', 422);
    }
    return $json;
}

function mg_pppm_text(mixed $value, string $field, int $max, bool $required = true): ?string
{
    $text = trim((string) ($value ?? ''));
    if ($text === '' && !$required) {
        return null;
    }
    if ($text === '' || mb_strlen($text) > $max) {
        mg_fail('Invalid PPPM request.', 422, [$field => "Enter {$field} using {$max} characters or fewer."]);
    }
    return $text;
}

function mg_pppm_source_owned(int $userId, string $publicId): array
{
    $stmt = mg_db()->prepare('SELECT * FROM pppm_sources WHERE public_id = ? AND owner_user_id = ? AND status = ? LIMIT 1');
    $stmt->execute([$publicId, $userId, 'active']);
    $source = $stmt->fetch();
    if (!$source) {
        mg_fail('PPPM source not found.', 404);
    }
    return $source;
}

function mg_pppm_item_accessible(int $userId, string $publicId): array
{
    $stmt = mg_db()->prepare(
        'SELECT * FROM pppm_items
         WHERE public_id = ? AND (issuer_user_id = ? OR merchant_user_id = ? OR owner_user_id = ? OR recipient_user_id = ?)
         LIMIT 1'
    );
    $stmt->execute([$publicId, $userId, $userId, $userId, $userId]);
    $item = $stmt->fetch();
    if (!$item) {
        mg_fail('PPPM item not found.', 404);
    }
    return $item;
}

function mg_pppm_public_item(array $row): array
{
    $terms = !empty($row['terms_snapshot_json']) ? json_decode((string) $row['terms_snapshot_json'], true) : [];
    $metadata = !empty($row['metadata_snapshot_json']) ? json_decode((string) $row['metadata_snapshot_json'], true) : [];
    return [
        'id' => (string) $row['public_id'],
        'item_type' => (string) $row['item_type'],
        'funding_type' => (string) $row['funding_type'],
        'status' => (string) $row['status'],
        'title' => (string) $row['title_snapshot'],
        'description' => (string) ($row['description_snapshot'] ?? ''),
        'value_cents' => (int) $row['value_cents_snapshot'],
        'currency' => (string) $row['currency_snapshot'],
        'source_reference' => $row['source_reference'] ?? null,
        'source_line_reference' => $row['source_line_reference'] ?? null,
        'terms' => is_array($terms) ? $terms : [],
        'metadata' => is_array($metadata) ? $metadata : [],
        'issued_at' => $row['issued_at'] ?? null,
        'expires_at' => $row['expires_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_pppm_record_event(PDO $pdo, array $item, string $eventType, ?string $fromStatus, ?string $toStatus, ?int $actorUserId, ?int $sourceEventId = null, array $metadata = []): void
{
    $pdo->prepare(
        'INSERT INTO pppm_item_events
         (pppm_item_id, actor_user_id, event_type, from_status, to_status, source_event_id, metadata_json, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
    )->execute([
        (int) $item['id'],
        $actorUserId,
        $eventType,
        $fromStatus,
        $toStatus,
        $sourceEventId,
        $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
    ]);

    $pdo->prepare(
        'INSERT INTO pppm_item_snapshots
         (pppm_item_id, version_no, status_snapshot, owner_user_id_snapshot, recipient_user_id_snapshot,
          merchant_user_id_snapshot, title_snapshot, value_cents_snapshot, currency_snapshot,
          terms_snapshot_json, metadata_snapshot_json, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    )->execute([
        (int) $item['id'],
        (int) $item['version_no'],
        (string) $item['status'],
        $item['owner_user_id'] ?? null,
        $item['recipient_user_id'] ?? null,
        $item['merchant_user_id'] ?? null,
        (string) $item['title_snapshot'],
        (int) $item['value_cents_snapshot'],
        (string) $item['currency_snapshot'],
        $item['terms_snapshot_json'] ?? null,
        $item['metadata_snapshot_json'] ?? null,
    ]);
}

function mg_pppm_locked_by_public_id(PDO $pdo, string $publicId): array
{
    $stmt = $pdo->prepare('SELECT * FROM pppm_items WHERE public_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$publicId]);
    $item = $stmt->fetch();
    if (!$item) {
        throw new RuntimeException('PPPM item not found.');
    }
    return $item;
}

function mg_pppm_locked_by_id(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM pppm_items WHERE id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) {
        throw new RuntimeException('PPPM item not found.');
    }
    return $item;
}

function mg_pppm_refresh(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM pppm_items WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) {
        throw new RuntimeException('PPPM item not found.');
    }
    return $item;
}

function mg_pppm_transfer_owner(PDO $pdo, string $pppmPublicId, int $newOwnerUserId, string $sourceType, string $sourceReference, ?int $actorUserId = null, array $metadata = []): array
{
    if ($newOwnerUserId < 1 || trim($sourceType) === '' || trim($sourceReference) === '') {
        throw new InvalidArgumentException('Valid PPPM owner transfer context is required.');
    }

    $item = mg_pppm_locked_by_public_id($pdo, $pppmPublicId);
    $oldOwner = (int)($item['owner_user_id'] ?? 0);
    $key = 'pppm-owner-transfer:' . $pppmPublicId . ':' . $newOwnerUserId . ':' . $sourceType . ':' . $sourceReference;

    $existing = $pdo->prepare('SELECT * FROM entitlement_transfers WHERE idempotency_key=? LIMIT 1');
    $existing->execute(['owner-sync:' . $pppmPublicId . ':' . $newOwnerUserId . ':' . $sourceReference]);
    if ($row = $existing->fetch()) {
        return ['transfer_id' => $row['public_id'], 'old_owner_user_id' => $oldOwner ?: null, 'new_owner_user_id' => $newOwnerUserId, 'duplicate' => true];
    }

    if ($oldOwner !== $newOwnerUserId) {
        $pdo->prepare('UPDATE pppm_items SET owner_user_id=?,recipient_user_id=COALESCE(recipient_user_id,?),version_no=version_no+1,updated_at=NOW() WHERE id=?')
            ->execute([$newOwnerUserId, $newOwnerUserId, (int)$item['id']]);
        $updated = mg_pppm_refresh($pdo, (int)$item['id']);
        mg_pppm_record_event($pdo, $updated, 'owner_transferred', null, (string)$updated['status'], $actorUserId, null, array_merge($metadata, [
            'from_user_id' => $oldOwner ?: null,
            'to_user_id' => $newOwnerUserId,
            'source_type' => $sourceType,
            'source_reference' => $sourceReference,
            'idempotency_key' => $key,
        ]));
        mg_event('pppm.owner_transferred', ['pppm_item_id' => $pppmPublicId, 'from_user_id' => $oldOwner ?: null, 'to_user_id' => $newOwnerUserId, 'source_type' => $sourceType, 'source_reference' => $sourceReference], $actorUserId);
        $item = $updated;
    }

    require_once dirname(__DIR__) . '/entitlements/_lifecycle.php';
    $entitlementTransfer = mg_entitlements_sync_pppm_owner($pdo, $pppmPublicId, $newOwnerUserId, $sourceType, $sourceReference, $actorUserId);

    return [
        'transfer_id' => $entitlementTransfer['transfer_id'] ?? null,
        'old_owner_user_id' => $oldOwner ?: null,
        'new_owner_user_id' => $newOwnerUserId,
        'entitlements' => $entitlementTransfer,
        'duplicate' => (bool)($entitlementTransfer['duplicate'] ?? false),
    ];
}

function mg_pppm_redeem(PDO $pdo, string|int $pppmItem, int $actorUserId, string $sourceType, string $sourceReference, array $metadata = []): array
{
    if (trim($sourceType) === '' || trim($sourceReference) === '') {
        throw new InvalidArgumentException('Valid PPPM redemption context is required.');
    }

    $item = is_int($pppmItem) ? mg_pppm_locked_by_id($pdo, $pppmItem) : mg_pppm_locked_by_public_id($pdo, (string)$pppmItem);
    $from = (string)$item['status'];
    if ($from === 'redeemed') {
        return ['pppm_item_id' => (string)$item['public_id'], 'status' => 'redeemed', 'duplicate' => true];
    }
    if (in_array($from, ['cancelled','revoked','expired','replaced'], true)) {
        throw new RuntimeException('PPPM item cannot be redeemed from its current state.');
    }

    $pdo->prepare("UPDATE pppm_items SET status='redeemed',redeemed_at=COALESCE(redeemed_at,NOW()),version_no=version_no+1,updated_at=NOW() WHERE id=? AND status<>'redeemed'")
        ->execute([(int)$item['id']]);
    $updated = mg_pppm_refresh($pdo, (int)$item['id']);
    mg_pppm_record_event($pdo, $updated, 'redeemed', $from, 'redeemed', $actorUserId, null, array_merge($metadata, [
        'source_type' => $sourceType,
        'source_reference' => $sourceReference,
    ]));
    mg_event('pppm.redeemed', ['pppm_item_id' => (string)$updated['public_id'], 'source_type' => $sourceType, 'source_reference' => $sourceReference], $actorUserId);

    return ['pppm_item_id' => (string)$updated['public_id'], 'status' => 'redeemed', 'duplicate' => false];
}
