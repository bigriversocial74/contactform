<?php
declare(strict_types=1);

require_once __DIR__ . '/_pppm.php';

mg_require_method('GET');
$user = mg_require_permission('pppm.items.view');
$id = trim((string) ($_GET['id'] ?? ''));

if ($id !== '') {
    $item = mg_pppm_item_accessible((int) $user['id'], $id);
    $eventsStmt = mg_db()->prepare(
        'SELECT event_type, from_status, to_status, metadata_json, created_at
         FROM pppm_item_events WHERE pppm_item_id = ? ORDER BY created_at ASC, id ASC'
    );
    $eventsStmt->execute([(int) $item['id']]);
    $events = array_map(static function (array $row): array {
        $metadata = !empty($row['metadata_json']) ? json_decode((string) $row['metadata_json'], true) : [];
        return [
            'event_type' => (string) $row['event_type'],
            'from_status' => $row['from_status'] ?? null,
            'to_status' => $row['to_status'] ?? null,
            'metadata' => is_array($metadata) ? $metadata : [],
            'created_at' => $row['created_at'] ?? null,
        ];
    }, $eventsStmt->fetchAll());
    $public = mg_pppm_public_item($item);
    $public['events'] = $events;
    mg_ok(['item' => $public]);
}

$status = trim((string) ($_GET['status'] ?? ''));
$params = [(int) $user['id'], (int) $user['id'], (int) $user['id'], (int) $user['id']];
$where = '(issuer_user_id = ? OR merchant_user_id = ? OR owner_user_id = ? OR recipient_user_id = ?)';
if ($status !== '') {
    $where .= ' AND status = ?';
    $params[] = $status;
}
$limit = max(1, min(100, (int) ($_GET['limit'] ?? 50)));
$stmt = mg_db()->prepare(
    'SELECT * FROM pppm_items WHERE ' . $where . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $limit
);
$stmt->execute($params);
$items = array_map('mg_pppm_public_item', $stmt->fetchAll());
mg_ok(['items' => $items, 'count' => count($items)]);
