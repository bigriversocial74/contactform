<?php
declare(strict_types=1);

function lqr_sql_replay_seen(array $config, string $replayKey): bool
{
    try {
        $pdo = lqr_sql_db($config);
        $stmt = $pdo->prepare('SELECT 1 FROM lqr_signed_code_replays WHERE replay_key=? LIMIT 1');
        $stmt->execute([$replayKey]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $ignored) {
        return false;
    }
}

function lqr_sql_mark_replay(array $config, string $replayKey, array $payload = []): void
{
    try {
        $pdo = lqr_sql_db($config);
        $stmt = $pdo->prepare('INSERT IGNORE INTO lqr_signed_code_replays (replay_key,quest_key,code_type,nonce,payload_json,first_seen_at) VALUES (?,?,?,?,?,NOW())');
        $stmt->execute([
            $replayKey,
            (string)($payload['quest_id'] ?? '') ?: null,
            (string)($payload['type'] ?? '') ?: null,
            (string)($payload['nonce'] ?? '') ?: null,
            lqr_sql_json($payload),
        ]);
    } catch (Throwable $ignored) {}
}

function lqr_sql_webhook_delivery_seen(array $config, string $deliveryId): bool
{
    if ($deliveryId === '') return false;
    try {
        $pdo = lqr_sql_db($config);
        $stmt = $pdo->prepare('SELECT 1 FROM lqr_webhook_deliveries WHERE delivery_id=? LIMIT 1');
        $stmt->execute([$deliveryId]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $ignored) {
        return false;
    }
}

function lqr_sql_record_webhook_delivery(array $config, string $deliveryId, string $event, bool $verified, bool $reconciled, array $payload = [], string $rewardId = '', string $itemId = ''): void
{
    if ($deliveryId === '') return;
    try {
        $pdo = lqr_sql_db($config);
        $stmt = $pdo->prepare('INSERT INTO lqr_webhook_deliveries (delivery_id,event_type,verified,reconciled,reward_id,item_id,payload_json,received_at) VALUES (?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE verified=VALUES(verified),reconciled=VALUES(reconciled),reward_id=VALUES(reward_id),item_id=VALUES(item_id),payload_json=VALUES(payload_json)');
        $stmt->execute([
            $deliveryId,
            $event,
            $verified ? 1 : 0,
            $reconciled ? 1 : 0,
            $rewardId !== '' ? $rewardId : null,
            $itemId !== '' ? $itemId : null,
            lqr_sql_json($payload),
        ]);
    } catch (Throwable $ignored) {}
}
