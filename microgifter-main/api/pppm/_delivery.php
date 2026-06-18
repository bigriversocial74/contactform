<?php
declare(strict_types=1);

require_once __DIR__ . '/_pppm.php';

function mg_pppm_delivery_item_for_update(PDO $pdo, int $userId, string $publicId): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM pppm_items
         WHERE public_id = ?
           AND (issuer_user_id = ? OR owner_user_id = ? OR recipient_user_id = ?)
         LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([$publicId, $userId, $userId, $userId]);
    $item = $stmt->fetch();
    if (!$item) {
        mg_fail('PPPM item not found.', 404);
    }
    return $item;
}

function mg_pppm_delivery_channel(mixed $value): string
{
    $channel = trim((string) $value);
    $allowed = ['email','sms','link','push','api','manual','other'];
    if (!in_array($channel, $allowed, true)) {
        mg_fail('Invalid delivery channel.', 422);
    }
    return $channel;
}

function mg_pppm_delivery_destination(mixed $value): ?string
{
    $destination = trim((string) $value);
    if ($destination === '') {
        return null;
    }
    if (mb_strlen($destination) > 255) {
        mg_fail('Invalid delivery destination.', 422);
    }
    return $destination;
}

function mg_pppm_delivery_set_status(PDO $pdo, array $item, string $status, ?int $actorUserId, string $eventType, array $metadata = []): array
{
    $from = (string) $item['status'];
    $columns = [
        'assigned' => 'assigned_at',
        'scheduled' => null,
        'sent' => 'sent_at',
        'delivered' => 'delivered_at',
        'viewed' => 'viewed_at',
    ];
    if (!array_key_exists($status, $columns)) {
        mg_fail('Invalid delivery status.', 422);
    }

    $sql = 'UPDATE pppm_items SET status = ?, version_no = version_no + 1, updated_at = NOW()';
    if ($columns[$status] !== null) {
        $sql .= ', ' . $columns[$status] . ' = NOW()';
    }
    $sql .= ' WHERE id = ?';
    $pdo->prepare($sql)->execute([$status, (int) $item['id']]);

    $stmt = $pdo->prepare('SELECT * FROM pppm_items WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $item['id']]);
    $updated = $stmt->fetch();
    $eventActor = $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null;
    mg_pppm_record_event($pdo, $updated, $eventType, $from, $status, $eventActor, null, $metadata);
    return $updated;
}
