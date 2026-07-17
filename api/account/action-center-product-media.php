<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center_contract.php';

function mg_action_center_media_ids(string $raw): array
{
    $ids = [];
    foreach (explode(',', $raw) as $id) {
        $id = trim($id);
        if ($id !== '' && mb_strlen($id) <= 190) $ids[$id] = $id;
        if (count($ids) >= 80) break;
    }
    return array_values($ids);
}

function mg_action_center_media_kind(string $url, string $fallback = 'download'): string
{
    $path = (string) (parse_url($url, PHP_URL_PATH) ?: $url);
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return match ($extension) {
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg' => 'image',
        'mp3', 'wav', 'm4a', 'aac', 'ogg' => 'audio',
        'mp4', 'mov', 'webm', 'm4v' => 'video',
        'pdf', 'zip' => 'download',
        default => $fallback,
    };
}

function mg_action_center_media_asset(array $asset): array
{
    $url = mg_action_center_contract_safe_url($asset['url'] ?? '');
    $mime = mg_action_center_contract_text($asset['mime_type'] ?? '', 120);
    $kind = strtolower(mg_action_center_contract_text($asset['asset_type'] ?? '', 40));
    if ($kind === '') {
        if (str_starts_with($mime, 'image/')) $kind = 'image';
        elseif (str_starts_with($mime, 'audio/')) $kind = 'audio';
        elseif (str_starts_with($mime, 'video/')) $kind = 'video';
        else $kind = mg_action_center_media_kind($url, 'download');
    }

    return [
        'role' => mg_action_center_contract_text($asset['role'] ?? ($kind === 'image' ? 'gallery' : $kind), 40) ?: 'other',
        'asset_type' => $kind,
        'mime_type' => $mime,
        'title' => mg_action_center_contract_text($asset['title'] ?? '', 240) ?: ucfirst($kind),
        'url' => $url,
        'source' => mg_action_center_contract_text($asset['source'] ?? 'gift', 80) ?: 'gift',
        'sort_order' => max(0, (int) ($asset['sort_order'] ?? 0)),
    ];
}

function mg_action_center_media_dedupe(array $assets): array
{
    $deduped = [];
    foreach ($assets as $asset) {
        if (!is_array($asset)) continue;
        $normalized = mg_action_center_media_asset($asset);
        if ($normalized['url'] === '') continue;
        $key = strtolower($normalized['url']);
        if (!isset($deduped[$key])) $deduped[$key] = $normalized;
    }
    return array_values($deduped);
}

function mg_action_center_media_primary_kind(array $assets): string
{
    foreach (['image', 'video', 'audio', 'download'] as $kind) {
        foreach ($assets as $asset) {
            if ((string) ($asset['asset_type'] ?? '') === $kind) return $kind;
        }
    }
    return $assets === [] ? 'none' : 'media';
}

function mg_action_center_media_load_raw(PDO $pdo, array $ids, int $userId, string $email): array
{
    $canonicalIds = [];
    $walletIds = [];
    foreach ($ids as $id) {
        $walletId = mg_ac_wallet_action_id($id);
        if ($walletId !== null) $walletIds[$walletId] = $id;
        else $canonicalIds[$id] = true;
    }

    $items = [];
    if ($canonicalIds !== []) {
        $values = array_keys($canonicalIds);
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $sql = mg_action_center_select_sql() . " WHERE ac.user_id=? AND ac.public_id IN ({$placeholders}) AND ac.archived_at IS NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$userId], $values));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $item = mg_action_center_public_item($row);
            $items[(string) ($item['action_item_id'] ?? '')] = $item;
        }
    }

    if ($walletIds !== []) {
        $values = array_keys($walletIds);
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $identityParams = [$userId];
        $identity = mg_ac_wallet_identity_where($email, $identityParams);
        $sql = mg_ac_wallet_select_sql() . " WHERE wi.public_id IN ({$placeholders}) AND wi.status<>'cancelled' AND {$identity}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($values, $identityParams));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $item = mg_ac_wallet_public_item($row);
            $items[(string) ($item['action_item_id'] ?? '')] = $item;
        }
    }

    $ordered = [];
    foreach ($ids as $id) {
        if (isset($items[$id])) $ordered[] = $items[$id];
    }
    return $ordered;
}

function mg_action_center_media_catalog_assets(PDO $pdo, array $contracts): array
{
    $versionIds = [];
    foreach ($contracts as $contract) {
        $linked = is_array($contract['linked_resource'] ?? null) ? $contract['linked_resource'] : [];
        $versionId = trim((string) ($linked['version_id'] ?? ''));
        if ($versionId !== '') $versionIds[$versionId] = true;
    }
    if ($versionIds === []) return [];

    $ids = array_keys($versionIds);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT cpv.public_id version_public_id,pva.role,pva.sort_order,
                   ca.public_id asset_id,ca.asset_type,ca.mime_type,ca.original_filename
            FROM catalog_product_versions cpv
            INNER JOIN catalog_product_version_assets pva ON pva.product_version_id=cpv.id
            INNER JOIN catalog_assets ca ON ca.id=pva.asset_id AND ca.status='ready'
            WHERE cpv.public_id IN ({$placeholders})
            ORDER BY cpv.public_id,FIELD(pva.role,'cover','thumbnail','inside_cover','gallery','carousel','audio','download','back','other'),pva.sort_order,pva.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);

    $byVersion = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $versionId = (string) ($row['version_public_id'] ?? '');
        if ($versionId === '') continue;
        $byVersion[$versionId][] = [
            'role' => (string) ($row['role'] ?? 'other'),
            'asset_type' => (string) ($row['asset_type'] ?? 'other'),
            'mime_type' => (string) ($row['mime_type'] ?? ''),
            'title' => (string) ($row['original_filename'] ?? ucfirst((string) ($row['role'] ?? 'media'))),
            'url' => '/api/public/media.php?asset=' . rawurlencode((string) ($row['asset_id'] ?? '')),
            'source' => 'catalog_product_version',
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ];
    }
    return $byVersion;
}

mg_require_method('GET');
$user = mg_require_api_user();
$ids = mg_action_center_media_ids((string) ($_GET['ids'] ?? $_GET['id'] ?? ''));
if ($ids === []) mg_ok(['contract_version' => MG_ACTION_CENTER_CONTRACT_VERSION, 'items' => []]);

$pdo = mg_db();
$rawItems = mg_action_center_media_load_raw($pdo, $ids, (int) $user['id'], mg_ac_wallet_user_email($user));
$contracts = mg_action_center_contract_items($pdo, (int) $user['id'], $rawItems);
$catalogAssets = mg_action_center_media_catalog_assets($pdo, $contracts);

$items = [];
foreach ($contracts as $contract) {
    $actionItemId = (string) ($contract['action_item_id'] ?? '');
    if ($actionItemId === '') continue;
    $linked = is_array($contract['linked_resource'] ?? null) ? $contract['linked_resource'] : [];
    $versionId = (string) ($linked['version_id'] ?? '');
    $presentation = is_array($contract['presentation'] ?? null) ? $contract['presentation'] : [];
    $media = is_array($contract['media'] ?? null) ? $contract['media'] : [];

    $assets = $versionId !== '' ? ($catalogAssets[$versionId] ?? []) : [];
    foreach ((array) ($media['posts'] ?? []) as $post) {
        if (!is_array($post)) continue;
        $url = mg_action_center_contract_safe_url($post['url'] ?? '');
        if ($url === '') continue;
        $type = strtolower((string) ($post['media_type'] ?? $post['type'] ?? ''));
        if (!in_array($type, ['image', 'audio', 'video', 'download'], true)) $type = mg_action_center_media_kind($url, 'download');
        $assets[] = [
            'role' => (string) ($post['type'] ?? ($type === 'image' ? 'gallery' : $type)),
            'asset_type' => $type,
            'mime_type' => '',
            'title' => (string) ($post['title'] ?? ucfirst($type)),
            'url' => $url,
            'source' => 'gift_content',
            'sort_order' => count($assets),
        ];
    }

    $imageUrl = mg_action_center_contract_safe_url($presentation['image_url'] ?? '');
    if ($imageUrl !== '') {
        array_unshift($assets, [
            'role' => 'cover',
            'asset_type' => 'image',
            'mime_type' => '',
            'title' => 'Gift cover',
            'url' => $imageUrl,
            'source' => (string) ($presentation['image_source'] ?? 'gift_presentation'),
            'sort_order' => 0,
        ]);
    }

    $assets = mg_action_center_media_dedupe($assets);
    $items[$actionItemId] = [
        'contract_version' => MG_ACTION_CENTER_CONTRACT_VERSION,
        'action_item_id' => $actionItemId,
        'gift' => $contract['gift'],
        'presentation' => $contract['presentation'],
        'linked_resource' => $contract['linked_resource'],
        'media' => [
            'assets' => $assets,
            'count' => count($assets),
            'primary_kind' => mg_action_center_media_primary_kind($assets),
        ],
    ];
}

mg_ok([
    'contract_version' => MG_ACTION_CENTER_CONTRACT_VERSION,
    'items' => $items,
]);
