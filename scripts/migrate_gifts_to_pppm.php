<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/db.php';

$pdo = mg_db();
$lockName = 'microgifter_pppm_legacy_gift_migration';
$lock = $pdo->prepare('SELECT GET_LOCK(?, 30)');
$lock->execute([$lockName]);
if ((int) $lock->fetchColumn() !== 1) {
    fwrite(STDERR, "Could not acquire PPPM legacy migration lock.\n");
    exit(1);
}

function pppm_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function map_status(string $status): string
{
    return match ($status) {
        'draft' => 'created',
        'sent' => 'sent',
        'delivered' => 'delivered',
        'claimed' => 'redeemed',
        'expired' => 'expired',
        'cancelled' => 'cancelled',
        default => 'created',
    };
}

try {
    $gifts = $pdo->query(
        'SELECT g.* FROM gifts g
         LEFT JOIN pppm_legacy_gift_map map ON map.gift_id = g.id
         WHERE map.gift_id IS NULL
         ORDER BY g.id ASC'
    )->fetchAll();

    $sourceLookup = $pdo->prepare(
        "SELECT id FROM pppm_sources
         WHERE owner_user_id = ? AND source_type = 'legacy_gift' AND provider = 'microgifter' LIMIT 1"
    );
    $sourceInsert = $pdo->prepare(
        "INSERT INTO pppm_sources
         (public_id, owner_user_id, source_type, provider, name, status, created_at, updated_at)
         VALUES (?, ?, 'legacy_gift', 'microgifter', 'Legacy gift migration', 'active', NOW(), NOW())"
    );
    $requestInsert = $pdo->prepare(
        "INSERT INTO pppm_issuance_requests
         (public_id, source_id, issuer_user_id, merchant_user_id, source_reference, item_type, funding_type,
          quantity, unit_value_cents, currency, recipient_user_id, recipient_name, title, description,
          terms_snapshot_json, metadata_json, status, issued_count, requested_at, completed_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, 'gift', 'other', 1, ?, ?, ?, ?, ?, ?, NULL, ?, 'issued', 1, ?, ?, NOW(), NOW())"
    );
    $itemInsert = $pdo->prepare(
        "INSERT INTO pppm_items
         (public_id, issuance_request_id, source_id, unit_sequence, item_type, funding_type, issuer_user_id,
          merchant_user_id, owner_user_id, recipient_user_id, source_reference, title_snapshot,
          description_snapshot, value_cents_snapshot, currency_snapshot, metadata_snapshot_json,
          status, version_no, issued_at, sent_at, delivered_at, redeemed_at, expires_at, created_at, updated_at)
         VALUES (?, ?, ?, 1, 'gift', 'other', ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, NOW(), NOW())"
    );
    $eventInsert = $pdo->prepare(
        'INSERT INTO pppm_item_events
         (pppm_item_id, actor_user_id, event_type, from_status, to_status, metadata_json, created_at)
         VALUES (?, ?, ?, NULL, ?, ?, ?)'
    );
    $snapshotInsert = $pdo->prepare(
        'INSERT INTO pppm_item_snapshots
         (pppm_item_id, version_no, status_snapshot, owner_user_id_snapshot, recipient_user_id_snapshot,
          merchant_user_id_snapshot, title_snapshot, value_cents_snapshot, currency_snapshot,
          terms_snapshot_json, metadata_snapshot_json, created_at)
         VALUES (?, 1, ?, ?, ?, NULL, ?, ?, ?, NULL, ?, NOW())'
    );
    $mapInsert = $pdo->prepare('INSERT INTO pppm_legacy_gift_map (gift_id, pppm_item_id, mapped_at) VALUES (?, ?, NOW())');

    $count = 0;
    foreach ($gifts as $gift) {
        $pdo->beginTransaction();
        try {
            $senderId = (int) $gift['sender_user_id'];
            $sourceLookup->execute([$senderId]);
            $sourceId = $sourceLookup->fetchColumn();
            if (!$sourceId) {
                $sourceInsert->execute([pppm_uuid(), $senderId]);
                $sourceId = (int) $pdo->lastInsertId();
            }

            $metadata = [];
            if (!empty($gift['metadata_json'])) {
                $decoded = json_decode((string) $gift['metadata_json'], true);
                if (is_array($decoded)) {
                    $metadata = $decoded;
                }
            }
            $metadata['legacy_gift_id'] = (string) $gift['public_id'];
            $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $status = map_status((string) $gift['status']);
            $requestPublicId = pppm_uuid();
            $issuedAt = $gift['created_at'] ?? date('Y-m-d H:i:s');
            $completedAt = $gift['updated_at'] ?? $issuedAt;

            $requestInsert->execute([
                $requestPublicId,
                (int) $sourceId,
                $senderId,
                null,
                (string) $gift['public_id'],
                (int) $gift['value_cents'],
                (string) $gift['currency'],
                $gift['recipient_user_id'] !== null ? (int) $gift['recipient_user_id'] : null,
                (string) $gift['recipient_name'],
                (string) $gift['title'],
                $gift['description'] ?? null,
                $metadataJson,
                $issuedAt,
                $completedAt,
            ]);
            $requestId = (int) $pdo->lastInsertId();

            $itemInsert->execute([
                (string) $gift['public_id'],
                $requestId,
                (int) $sourceId,
                $senderId,
                $senderId,
                $gift['recipient_user_id'] !== null ? (int) $gift['recipient_user_id'] : null,
                (string) $gift['public_id'],
                (string) $gift['title'],
                $gift['description'] ?? null,
                (int) $gift['value_cents'],
                (string) $gift['currency'],
                $metadataJson,
                $status,
                $issuedAt,
                $gift['sent_at'] ?? null,
                $gift['delivered_at'] ?? null,
                $gift['claimed_at'] ?? null,
                $gift['expires_at'] ?? null,
            ]);
            $itemId = (int) $pdo->lastInsertId();

            $eventInsert->execute([
                $itemId,
                $senderId,
                'legacy_gift_imported',
                $status,
                $metadataJson,
                $issuedAt,
            ]);
            $snapshotInsert->execute([
                $itemId,
                $status,
                $senderId,
                $gift['recipient_user_id'] !== null ? (int) $gift['recipient_user_id'] : null,
                (string) $gift['title'],
                (int) $gift['value_cents'],
                (string) $gift['currency'],
                $metadataJson,
            ]);
            $mapInsert->execute([(int) $gift['id'], $itemId]);
            $pdo->commit();
            $count++;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    echo "Mapped {$count} legacy gifts to PPPM.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    try {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    } catch (Throwable) {
    }
}
