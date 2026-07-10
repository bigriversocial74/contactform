<?php
declare(strict_types=1);

function lqr_webhook_delivery_exists(PDO $pdo, string $deliveryId): bool
{
    if ($deliveryId === '') return false;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM lqr_webhook_deliveries WHERE delivery_id=?');
    $stmt->execute([$deliveryId]);
    return (int)$stmt->fetchColumn() > 0;
}

function lqr_webhook_store_delivery(PDO $pdo, array $entry): void
{
    $payload = json_encode($entry['payload'] ?? null, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($payload)) $payload = 'null';
    $stmt = $pdo->prepare(
        "INSERT INTO lqr_webhook_deliveries
            (delivery_id,event_type,verified,reconciled,reward_id,item_id,payload_json,received_at)
         VALUES (?,?,?,?,?,?,?,NOW())
         ON DUPLICATE KEY UPDATE
            event_type=VALUES(event_type),verified=GREATEST(verified,VALUES(verified)),
            reconciled=GREATEST(reconciled,VALUES(reconciled)),reward_id=COALESCE(VALUES(reward_id),reward_id),
            item_id=COALESCE(VALUES(item_id),item_id),payload_json=VALUES(payload_json)"
    );
    $stmt->execute([
        (string)$entry['delivery_id'],
        (string)($entry['event_type'] ?? 'webhook.unknown'),
        !empty($entry['verified']) ? 1 : 0,
        !empty($entry['reconciled']) ? 1 : 0,
        trim((string)($entry['reward_id'] ?? '')) ?: null,
        trim((string)($entry['item_id'] ?? '')) ?: null,
        $payload,
    ]);
}

function lqr_webhook_recent_deliveries(PDO $pdo, int $limit = 10): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->query(
        'SELECT delivery_id,event_type,verified,reconciled,reward_id,item_id,payload_json,received_at
           FROM lqr_webhook_deliveries ORDER BY received_at DESC,id DESC LIMIT ' . $limit
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) {
        $decoded = json_decode((string)($row['payload_json'] ?? ''), true);
        $row['payload'] = is_array($decoded) ? $decoded : [];
        unset($row['payload_json']);
    }
    unset($row);
    return $rows;
}

function lqr_webhook_delivery_count(PDO $pdo, bool $verifiedOnly = false): int
{
    $sql = 'SELECT COUNT(*) FROM lqr_webhook_deliveries';
    if ($verifiedOnly) $sql .= ' WHERE verified=1';
    return (int)$pdo->query($sql)->fetchColumn();
}
