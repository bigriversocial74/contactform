<?php
declare(strict_types=1);

require_once __DIR__ . '/_gift.php';
require_once dirname(__DIR__) . '/pppm/_activity.php';

mg_require_method('GET');
$user = mg_require_permission('gift.activity.view');
$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '' || strlen($id) > 32 || !preg_match('/^(GFT|PPPM)-[A-Z0-9-]+$/', $id)) {
    mg_fail('Invalid item identifier.', 422);
}

$pppm = mg_pppm_activity_find((int) $user['id'], $id);
if ($pppm) {
    $eventsStmt = mg_db()->prepare(
        'SELECT event_type, from_status, to_status, metadata_json, created_at
         FROM pppm_item_events
         WHERE pppm_item_id = ?
         ORDER BY created_at ASC, id ASC'
    );
    $eventsStmt->execute([(int) $pppm['id']]);
    $events = array_map(static function (array $event): array {
        $metadata = [];
        if (!empty($event['metadata_json'])) {
            $decoded = json_decode((string) $event['metadata_json'], true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }
        return [
            'type' => (string) $event['event_type'],
            'from_status' => $event['from_status'] ?? null,
            'to_status' => $event['to_status'] ?? null,
            'metadata' => $metadata,
            'created_at' => $event['created_at'] ?? null,
        ];
    }, $eventsStmt->fetchAll());

    $threadStmt = mg_db()->prepare(
        'SELECT mt.public_id
         FROM message_threads mt
         INNER JOIN message_thread_participants mtp ON mtp.thread_id = mt.id
         LEFT JOIN pppm_legacy_gift_map legacy_map ON legacy_map.pppm_item_id = ?
         WHERE mtp.user_id = ?
           AND (mt.pppm_item_id = ? OR (legacy_map.gift_id IS NOT NULL AND mt.gift_id = legacy_map.gift_id))
         ORDER BY mt.updated_at DESC LIMIT 1'
    );
    $threadStmt->execute([(int) $pppm['id'], (int) $user['id'], (int) $pppm['id']]);
    $threadId = $threadStmt->fetchColumn();

    $public = mg_pppm_activity_public($pppm, (int) $user['id']);
    $public['events'] = $events;
    $public['thread_id'] = is_string($threadId) ? $threadId : null;
    $public['legacy'] = false;
    mg_ok(['gift' => $public, 'source' => 'pppm']);
}

$gift = mg_gift_require_accessible((int) $user['id'], $id);
$eventsStmt = mg_db()->prepare(
    'SELECT event_type, metadata_json, created_at
     FROM gift_events
     WHERE gift_id = ?
     ORDER BY created_at ASC, id ASC'
);
$eventsStmt->execute([(int) $gift['id']]);
$events = array_map(static function (array $event): array {
    $metadata = [];
    if (!empty($event['metadata_json'])) {
        $decoded = json_decode((string) $event['metadata_json'], true);
        if (is_array($decoded)) {
            $metadata = $decoded;
        }
    }
    return [
        'type' => (string) $event['event_type'],
        'metadata' => $metadata,
        'created_at' => $event['created_at'] ?? null,
    ];
}, $eventsStmt->fetchAll());

$threadStmt = mg_db()->prepare(
    'SELECT mt.public_id
     FROM message_threads mt
     INNER JOIN message_thread_participants mtp ON mtp.thread_id = mt.id
     WHERE mt.gift_id = ? AND mtp.user_id = ?
     ORDER BY mt.updated_at DESC LIMIT 1'
);
$threadStmt->execute([(int) $gift['id'], (int) $user['id']]);
$threadId = $threadStmt->fetchColumn();

$publicGift = mg_gift_row_to_public($gift, (int) $user['id']);
$publicGift['events'] = $events;
$publicGift['thread_id'] = is_string($threadId) ? $threadId : null;
$publicGift['legacy'] = true;
$publicGift['pppm_id'] = null;

mg_ok(['gift' => $publicGift, 'source' => 'legacy']);
