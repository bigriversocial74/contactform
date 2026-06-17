<?php
declare(strict_types=1);

require_once __DIR__ . '/_gift.php';
require_once dirname(__DIR__) . '/pppm/_activity.php';

mg_require_method('GET');
$user = mg_require_permission('gift.activity.view');
$box = trim((string) ($_GET['box'] ?? 'inbox'));
$limit = max(1, min(100, (int) ($_GET['limit'] ?? 50)));

$items = mg_pppm_activity_box($box, (int) $user['id'], $limit);
$remaining = max(0, $limit - count($items));

if ($remaining > 0) {
    [$where, $mode] = mg_gift_box_where($box);
    $sql =
        'SELECT g.*, sender.display_name AS sender_name, sender.full_name AS sender_full_name,
                recipient.display_name AS recipient_display_name, recipient.full_name AS recipient_full_name
         FROM gifts g
         INNER JOIN users sender ON sender.id = g.sender_user_id
         LEFT JOIN users recipient ON recipient.id = g.recipient_user_id
         LEFT JOIN pppm_legacy_gift_map legacy_map ON legacy_map.gift_id = g.id
         WHERE legacy_map.gift_id IS NULL AND ' . $where . '
         ORDER BY COALESCE(g.claimed_at, g.delivered_at, g.sent_at, g.updated_at, g.created_at) DESC, g.id DESC
         LIMIT ' . $remaining;

    $stmt = mg_db()->prepare($sql);
    $params = $mode === 'both' ? [(int) $user['id'], (int) $user['id']] : [(int) $user['id']];
    $stmt->execute($params);
    $legacyItems = array_map(
        static function (array $row) use ($user): array {
            $public = mg_gift_row_to_public($row, (int) $user['id']);
            $public['legacy'] = true;
            $public['pppm_id'] = null;
            return $public;
        },
        $stmt->fetchAll()
    );
    $items = array_merge($items, $legacyItems);
}

usort($items, static function (array $a, array $b): int {
    return strcmp((string) ($b['timestamp'] ?? ''), (string) ($a['timestamp'] ?? ''));
});

mg_ok([
    'box' => $box,
    'gifts' => array_slice($items, 0, $limit),
    'count' => min(count($items), $limit),
    'source' => 'pppm_with_legacy_fallback',
]);
