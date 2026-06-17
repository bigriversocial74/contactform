<?php
/**
 * Outbox helper for async-capable workflows.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/ids.php';

function mg_outbox_enqueue(
    string $eventType,
    array $payload = [],
    ?string $aggregateType = null,
    ?string $aggregateId = null,
    ?int $accountId = null,
    ?int $storeId = null,
    ?int $ownerUserId = null,
    ?DateTimeInterface $availableAt = null
): string {
    $pdo = mg_db();
    $publicId = mg_public_id();
    $stmt = $pdo->prepare(
        'INSERT INTO outbox_events
         (public_id, account_id, store_id, owner_user_id, event_type, aggregate_type, aggregate_id, payload_json, status, available_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $publicId,
        $accountId,
        $storeId,
        $ownerUserId,
        $eventType,
        $aggregateType,
        $aggregateId,
        json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'pending',
        $availableAt ? $availableAt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
    ]);

    return $publicId;
}

function mg_outbox_request_read_model_refresh(string $modelType, string $scopeType, int $scopeId, ?string $sourceType = null, ?string $sourceId = null): void
{
    $pdo = mg_db();
    $stmt = $pdo->prepare(
        'INSERT INTO read_model_refreshes
         (model_type, scope_type, scope_id, source_type, source_id, status, available_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $stmt->execute([$modelType, $scopeType, $scopeId, $sourceType, $sourceId, 'pending']);
}
