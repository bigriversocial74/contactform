<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('GET');
$user = mg_require_permission('social.posts.create');
$userId = (int)$user['id'];
$type = strtolower(trim((string)($_GET['type'] ?? 'product')));
$query = trim((string)($_GET['q'] ?? ''));
$selected = trim((string)($_GET['selected'] ?? ''));
$limit = max(1, min(30, (int)($_GET['limit'] ?? 24)));

if (!in_array($type, ['product','microgift','plan'], true)) {
    mg_fail('Invalid attachment type.', 422);
}
if (mb_strlen($query) > 100) {
    mg_fail('Search is too long.', 422);
}
if ($selected !== '' && (strlen($selected) > 80 || preg_match('/^[A-Za-z0-9-]+$/', $selected) !== 1)) {
    mg_fail('Invalid selected attachment.', 422);
}

mg_rate_limit('social.attachment_options', 'user:' . $userId, 180, 60);
$pdo = mg_db();

function mg_social_picker_asset_url(?string $provider, ?string $storageKey): ?string
{
    $provider = strtolower(trim((string)$provider));
    $storageKey = trim((string)$storageKey);
    if ($storageKey === '' || str_contains($storageKey, '..') || str_contains($storageKey, "\\")) return null;
    if ($provider === 'local') return '/' . ltrim($storageKey, '/');
    if (preg_match('#^https://#i', $storageKey) === 1 && filter_var($storageKey, FILTER_VALIDATE_URL)) return $storageKey;
    return null;
}

function mg_social_picker_description(mixed $value): ?string
{
    $text = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    return $text === '' ? null : mb_substr($text, 0, 220);
}

function mg_social_picker_item(array $row, string $kind): array
{
    $item = [
        'kind' => $kind,
        'id' => (string)$row['public_id'],
        'title' => (string)($row['title'] ?? $row['name'] ?? 'Untitled'),
        'description' => mg_social_picker_description($row['description'] ?? null),
        'status' => (string)($row['status'] ?? 'active'),
        'preview_url' => mg_social_picker_asset_url($row['preview_provider'] ?? null, $row['preview_key'] ?? null),
    ];

    if ($kind === 'product') {
        $item['product_type'] = (string)($row['product_type'] ?? 'gift');
        $item['slug'] = (string)($row['slug'] ?? '');
        $item['amount_cents'] = (int)($row['unit_value_cents'] ?? 0);
        $item['currency'] = (string)($row['currency'] ?? 'USD');
    } elseif ($kind === 'microgift') {
        $item['source_type'] = (string)($row['source_type'] ?? 'microgift');
        $item['recipient_policy'] = (string)($row['recipient_policy'] ?? 'assigned');
        $item['amount_cents'] = (int)($row['face_value_cents'] ?? 0);
        $item['currency'] = (string)($row['currency'] ?? 'USD');
        $item['issued_at'] = $row['issued_at'] ?? null;
        $item['expires_at'] = $row['expires_at'] ?? null;
    } else {
        $item['amount_cents'] = (int)($row['amount_cents'] ?? 0);
        $item['currency'] = (string)($row['currency'] ?? 'USD');
        $item['interval_unit'] = (string)($row['interval_unit'] ?? 'month');
        $item['interval_count'] = max(1, (int)($row['interval_count'] ?? 1));
        $item['trial_days'] = max(0, (int)($row['trial_days'] ?? 0));
    }
    return $item;
}

if ($type === 'product') {
    $where = "p.merchant_user_id=? AND p.status IN ('draft','published')";
    $params = [$userId];
    if ($selected !== '') {
        $where .= ' AND p.public_id=?';
        $params[] = $selected;
    } elseif ($query !== '') {
        $like = '%' . $query . '%';
        $where .= ' AND (v.title LIKE ? OR v.description LIKE ? OR p.slug LIKE ?)';
        array_push($params, $like, $like, $like);
    }
    $stmt = $pdo->prepare(
        "SELECT p.public_id,p.product_type,p.slug,p.status,
                COALESCE(v.title,'Untitled product') title,v.description,v.unit_value_cents,v.currency,
                a.storage_provider preview_provider,a.storage_key preview_key
         FROM catalog_products p
         LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id
         LEFT JOIN catalog_product_version_assets pva ON pva.id=(
             SELECT pva2.id FROM catalog_product_version_assets pva2
             INNER JOIN catalog_assets a2 ON a2.id=pva2.asset_id
             WHERE pva2.product_version_id=p.current_version_id
               AND a2.status='ready' AND a2.asset_type='image'
             ORDER BY FIELD(pva2.role,'cover','thumbnail','gallery','inside_cover','carousel','other'),pva2.sort_order,pva2.id
             LIMIT 1
         )
         LEFT JOIN catalog_assets a ON a.id=pva.asset_id
         WHERE {$where}
         ORDER BY (p.status='published') DESC,p.updated_at DESC,p.id DESC
         LIMIT {$limit}"
    );
    $stmt->execute($params);
    $items = array_map(static fn(array $row): array => mg_social_picker_item($row, 'product'), $stmt->fetchAll(PDO::FETCH_ASSOC));
    mg_ok(['type'=>'product','items'=>$items,'selected'=>$selected ?: null]);
}

if ($type === 'microgift') {
    $where = '(i.owner_user_id=? OR i.issuer_user_id=?)';
    $params = [$userId, $userId];
    if ($selected !== '') {
        $where .= ' AND i.public_id=?';
        $params[] = $selected;
    } elseif ($query !== '') {
        $like = '%' . $query . '%';
        $where .= ' AND (i.title_snapshot LIKE ? OR i.description_snapshot LIKE ? OR i.source_reference LIKE ?)';
        array_push($params, $like, $like, $like);
    }
    $stmt = $pdo->prepare(
        "SELECT i.public_id,i.title_snapshot title,i.description_snapshot description,i.status,
                i.source_type,i.recipient_policy,i.face_value_cents,i.currency,i.issued_at,i.expires_at,
                NULL preview_provider,NULL preview_key
         FROM microgift_instances i
         WHERE {$where}
         ORDER BY i.created_at DESC,i.id DESC
         LIMIT {$limit}"
    );
    $stmt->execute($params);
    $items = array_map(static fn(array $row): array => mg_social_picker_item($row, 'microgift'), $stmt->fetchAll(PDO::FETCH_ASSOC));
    mg_ok(['type'=>'microgift','items'=>$items,'selected'=>$selected ?: null]);
}

$where = "sp.owner_user_id=? AND sp.status='active'";
$params = [$userId];
if ($selected !== '') {
    $where .= ' AND sp.public_id=?';
    $params[] = $selected;
} elseif ($query !== '') {
    $like = '%' . $query . '%';
    $where .= ' AND (sp.name LIKE ? OR sp.description LIKE ? OR sp.target_reference LIKE ?)';
    array_push($params, $like, $like, $like);
}
$stmt = $pdo->prepare(
    "SELECT sp.public_id,sp.name,sp.description,sp.amount_cents,sp.currency,sp.interval_unit,
            sp.interval_count,sp.trial_days,sp.status,NULL preview_provider,NULL preview_key
     FROM subscription_plans sp
     WHERE {$where}
     ORDER BY sp.updated_at DESC,sp.id DESC
     LIMIT {$limit}"
);
$stmt->execute($params);
$items = array_map(static fn(array $row): array => mg_social_picker_item($row, 'plan'), $stmt->fetchAll(PDO::FETCH_ASSOC));
mg_ok(['type'=>'plan','items'=>$items,'selected'=>$selected ?: null]);
