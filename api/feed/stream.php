<?php
declare(strict_types=1);

require_once __DIR__ . '/_feed.php';

mg_require_method('GET');
$user = mg_require_permission('gift.activity.view');
$limit = max(1, min(12, (int) ($_GET['limit'] ?? 6)));
$cursor = max(0, (int) ($_GET['cursor'] ?? 0));
$startId = trim((string) ($_GET['item'] ?? ''));

$params = [(int) $user['id'], (int) $user['id'], (int) $user['id'], (int) $user['id']];
$whereCursor = '';
if ($cursor > 0) {
    $whereCursor = ' AND p.id < ?';
    $params[] = $cursor;
} elseif ($startId !== '') {
    $whereCursor = ' AND p.id <= COALESCE((SELECT id FROM pppm_items WHERE public_id = ? LIMIT 1), p.id)';
    $params[] = $startId;
}

$sql =
    'SELECT p.id AS item_db_id, p.public_id AS pppm_id, p.status AS item_status,
            p.title_snapshot, p.description_snapshot, p.value_cents_snapshot, p.currency_snapshot,
            p.issuer_user_id, p.owner_user_id, p.recipient_user_id, p.recipient_external_id,
            p.sent_at, p.delivered_at, p.viewed_at, p.redeemed_at, p.created_at,
            fp.public_id AS post_id, fp.post_type, fp.visibility, fp.status AS post_status,
            fpv.id AS post_version_db_id, fpv.public_id AS post_version_id, fpv.headline,
            fpv.caption, fpv.cta_label, fpv.cta_url, fpv.offer_snapshot_json,
            fpv.presentation_json, cp.public_id AS product_id, cp.slug AS product_slug,
            ms.slug AS storefront_slug, ms.display_name AS merchant_name,
            issuer.display_name AS issuer_name, issuer.full_name AS issuer_full_name
     FROM pppm_items p
     LEFT JOIN pppm_feed_bindings pfb ON pfb.pppm_item_id = p.id
     LEFT JOIN feed_post_versions fpv ON fpv.id = pfb.feed_post_version_id
     LEFT JOIN feed_posts fp ON fp.id = fpv.feed_post_id
     LEFT JOIN catalog_products cp ON cp.id = fp.catalog_product_id
     LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id = fp.merchant_user_id AND ms.status = \'published\'
     LEFT JOIN users issuer ON issuer.id = p.issuer_user_id
     WHERE (p.recipient_user_id = ? OR p.owner_user_id = ? OR p.issuer_user_id = ? OR p.merchant_user_id = ?)' .
     $whereCursor .
    ' ORDER BY p.id DESC LIMIT ' . ($limit + 1);

$stmt = mg_db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$hasMore = count($rows) > $limit;
if ($hasMore) array_pop($rows);

$elementStmt = mg_db()->prepare(
    'SELECT fpe.public_id, fpe.element_type, fpe.sort_order, fpe.content_json,
            ca.public_id AS asset_id, ca.asset_type, ca.mime_type
     FROM feed_post_elements fpe
     LEFT JOIN catalog_assets ca ON ca.id = fpe.asset_id
     WHERE fpe.feed_post_version_id = ?
     ORDER BY fpe.sort_order ASC, fpe.id ASC'
);

$items = [];
foreach ($rows as $row) {
    $elements = [];
    if (!empty($row['post_version_db_id'])) {
        $elementStmt->execute([(int) $row['post_version_db_id']]);
        foreach ($elementStmt->fetchAll() as $element) {
            $content = $element['content_json'] ? json_decode((string) $element['content_json'], true) : null;
            $elements[] = [
                'id' => $element['public_id'],
                'type' => $element['element_type'],
                'sort_order' => (int) $element['sort_order'],
                'asset_id' => $element['asset_id'],
                'asset_type' => $element['asset_type'],
                'mime_type' => $element['mime_type'],
                'media_url' => $element['asset_id'] ? '/api/feed/media.php?asset=' . rawurlencode((string) $element['asset_id']) . '&item=' . rawurlencode((string) $row['pppm_id']) : null,
                'content' => is_array($content) ? $content : [],
            ];
        }
    }

    $postType = $row['post_type'] ?: 'simple';
    $merchantName = trim((string) ($row['merchant_name'] ?: $row['issuer_name'] ?: $row['issuer_full_name'] ?: 'Microgifter merchant'));
    $items[] = [
        'pppm_id' => $row['pppm_id'],
        'status' => $row['item_status'],
        'title' => $row['title_snapshot'],
        'description' => $row['description_snapshot'],
        'value_cents' => (int) $row['value_cents_snapshot'],
        'currency' => $row['currency_snapshot'],
        'merchant_name' => $merchantName,
        'storefront_url' => $row['storefront_slug'] ? '/store.php?s=' . rawurlencode((string) $row['storefront_slug']) : null,
        'product_url' => $row['product_slug'] ? '/product.php?p=' . rawurlencode((string) $row['product_slug']) : null,
        'post_id' => $row['post_id'],
        'post_version_id' => $row['post_version_id'],
        'post_type' => $postType,
        'headline' => $row['headline'] ?: $row['title_snapshot'],
        'caption' => $row['caption'] ?: $row['description_snapshot'],
        'cta_label' => $row['cta_label'],
        'cta_url' => $row['cta_url'],
        'offer' => $row['offer_snapshot_json'] ? (json_decode((string) $row['offer_snapshot_json'], true) ?: []) : [],
        'presentation' => $row['presentation_json'] ? (json_decode((string) $row['presentation_json'], true) ?: []) : [],
        'elements' => $elements,
        'sheet' => [
            'pppm_id' => $row['pppm_id'],
            'sent_from' => $merchantName,
            'recipient' => $row['recipient_external_id'] ?: ((int) $row['recipient_user_id'] === (int) $user['id'] ? 'You' : 'Recipient'),
            'timestamp' => $row['delivered_at'] ?: $row['sent_at'] ?: $row['created_at'],
            'gift_type' => ucfirst(str_replace('_', ' ', $postType)),
            'value' => (($row['currency_snapshot'] === 'USD') ? '$' : $row['currency_snapshot'] . ' ') . number_format(((int) $row['value_cents_snapshot']) / 100, 2),
            'claim_status' => $row['item_status'],
        ],
    ];
}

$nextCursor = $hasMore && $rows ? (int) end($rows)['item_db_id'] : null;
mg_ok(['items' => $items, 'next_cursor' => $nextCursor, 'has_more' => $hasMore]);
