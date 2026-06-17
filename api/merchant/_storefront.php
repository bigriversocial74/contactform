<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_storefront_owned(PDO $pdo, int $userId, bool $forUpdate = false): ?array
{
    $sql = 'SELECT * FROM merchant_storefronts WHERE merchant_user_id = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function mg_storefront_revision(PDO $pdo, int $storefrontId, string $kind): ?array
{
    $column = $kind === 'published' ? 'published_revision_id' : 'draft_revision_id';
    $stmt = $pdo->prepare('SELECT r.* FROM merchant_storefront_states s INNER JOIN merchant_storefront_revisions r ON r.id = ' . $column . ' WHERE s.storefront_id = ? LIMIT 1');
    $stmt->execute([$storefrontId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function mg_storefront_asset_public_id(PDO $pdo, mixed $assetId, ?int $ownerUserId = null): ?string
{
    if (!$assetId) return null;
    $sql = "SELECT public_id FROM catalog_assets WHERE id=? AND status='ready'";
    $params = [(int)$assetId];
    if ($ownerUserId !== null) {
        $sql .= ' AND owner_user_id=?';
        $params[] = $ownerUserId;
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value ? (string)$value : null;
}

function mg_storefront_revision_management(PDO $pdo, array $revision, int $ownerUserId): array
{
    $revision['contact'] = $revision['contact_json'] ? (json_decode((string)$revision['contact_json'], true) ?: []) : [];
    $revision['theme'] = $revision['theme_json'] ? (json_decode((string)$revision['theme_json'], true) ?: []) : [];
    $revision['logo_asset_public_id'] = mg_storefront_asset_public_id($pdo, $revision['logo_asset_id'] ?? null, $ownerUserId);
    $revision['cover_asset_public_id'] = mg_storefront_asset_public_id($pdo, $revision['cover_asset_id'] ?? null, $ownerUserId);
    $revision['logo_preview_url'] = $revision['logo_asset_public_id']
        ? '/api/catalog/asset-file.php?id=' . rawurlencode((string)$revision['logo_asset_public_id'])
        : null;
    $revision['cover_preview_url'] = $revision['cover_asset_public_id']
        ? '/api/catalog/asset-file.php?id=' . rawurlencode((string)$revision['cover_asset_public_id'])
        : null;
    unset($revision['contact_json'], $revision['theme_json']);
    return $revision;
}

function mg_storefront_revision_products(PDO $pdo, int $revisionId): array
{
    $stmt = $pdo->prepare("SELECT p.id,p.public_id,p.slug,p.product_type,p.status,v.title,v.description,v.unit_value_cents,v.currency,rp.sort_order,rp.is_featured,rp.visibility,cover.public_id cover_asset_id
        FROM merchant_storefront_revision_products rp
        INNER JOIN catalog_products p ON p.id=rp.catalog_product_id
        INNER JOIN catalog_product_versions v ON v.id=p.current_version_id
        LEFT JOIN catalog_product_version_assets pva ON pva.product_version_id=v.id AND pva.role='cover'
        LEFT JOIN catalog_assets cover ON cover.id=pva.asset_id
        WHERE rp.storefront_revision_id=? ORDER BY rp.is_featured DESC,rp.sort_order ASC,rp.id ASC");
    $stmt->execute([$revisionId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['cover_preview_url'] = $row['cover_asset_id']
            ? '/api/catalog/asset-file.php?id=' . rawurlencode((string)$row['cover_asset_id'])
            : null;
    }
    unset($row);
    return $rows;
}

function mg_storefront_available_products(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT p.id,p.public_id,p.slug,p.product_type,p.status,p.published_at,v.public_id version_id,v.version_number,v.title,v.description,v.unit_value_cents,v.currency,cover.public_id cover_asset_id
        FROM catalog_products p
        INNER JOIN catalog_product_versions v ON v.id=p.current_version_id
        LEFT JOIN catalog_product_version_assets pva ON pva.product_version_id=v.id AND pva.role='cover'
        LEFT JOIN catalog_assets cover ON cover.id=pva.asset_id
        WHERE p.merchant_user_id=? AND p.status='published'
        ORDER BY p.published_at DESC,p.id DESC");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['cover_preview_url'] = $row['cover_asset_id']
            ? '/api/catalog/asset-file.php?id=' . rawurlencode((string)$row['cover_asset_id'])
            : null;
    }
    unset($row);
    return $rows;
}

function mg_storefront_asset_id(PDO $pdo, int $userId, ?string $publicId): ?int
{
    $publicId = trim((string)$publicId);
    if ($publicId === '') return null;
    $stmt = $pdo->prepare("SELECT id FROM catalog_assets WHERE public_id=? AND owner_user_id=? AND status='ready' AND asset_type='image' LIMIT 1");
    $stmt->execute([$publicId,$userId]);
    $id = $stmt->fetchColumn();
    if (!$id) mg_fail('Storefront image asset unavailable.',422);
    return (int)$id;
}

function mg_storefront_json(mixed $value, int $max = 65535): ?string
{
    if ($value === null || $value === '' || $value === []) return null;
    if (!is_array($value)) mg_fail('Expected storefront object.',422);
    $json = json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || strlen($json) > $max) mg_fail('Storefront payload is too large.',422);
    return $json;
}

function mg_storefront_checksum(array $payload): string
{
    return hash('sha256',json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}

function mg_storefront_normalize_products(PDO $pdo, int $userId, mixed $products): array
{
    if (!is_array($products)) return [];
    $available = mg_storefront_available_products($pdo,$userId);
    $byPublic = [];
    foreach ($available as $row) $byPublic[(string)$row['public_id']] = $row;
    $normalized = [];
    $seen = [];
    foreach ($products as $index => $item) {
        if (!is_array($item)) continue;
        $publicId = trim((string)($item['product_id'] ?? ''));
        if ($publicId === '' || !isset($byPublic[$publicId])) mg_fail('Storefront product unavailable.',422);
        if (isset($seen[$publicId])) continue;
        $seen[$publicId] = true;
        $normalized[] = [
            'catalog_product_id'=>(int)$byPublic[$publicId]['id'],
            'public_id'=>$publicId,
            'sort_order'=>max(0,min(100000,(int)($item['sort_order'] ?? $index))),
            'is_featured'=>!empty($item['is_featured']) ? 1 : 0,
            'visibility'=>($item['visibility'] ?? 'visible') === 'hidden' ? 'hidden' : 'visible',
        ];
    }
    usort($normalized, static fn(array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);
    return $normalized;
}

function mg_storefront_readiness(?array $storefront, ?array $revision, array $products): array
{
    $checks = [
        ['key'=>'name','label'=>'Store name','required'=>true,'complete'=>trim((string)($revision['display_name'] ?? $storefront['display_name'] ?? '')) !== ''],
        ['key'=>'slug','label'=>'Public storefront address','required'=>true,'complete'=>trim((string)($storefront['slug'] ?? '')) !== ''],
        ['key'=>'description','label'=>'Headline or description','required'=>true,'complete'=>trim((string)($revision['headline'] ?? '')) !== '' || trim((string)($revision['description'] ?? '')) !== ''],
        ['key'=>'products','label'=>'At least one visible published product','required'=>true,'complete'=>count(array_filter($products,static fn(array $p):bool=>($p['visibility'] ?? 'visible')==='visible'&&($p['status'] ?? 'published')==='published'))>0],
        ['key'=>'logo','label'=>'Store logo','required'=>false,'complete'=>!empty($revision['logo_asset_id']) || !empty($revision['logo_asset_public_id'])],
        ['key'=>'cover','label'=>'Store cover image','required'=>false,'complete'=>!empty($revision['cover_asset_id']) || !empty($revision['cover_asset_public_id'])],
        ['key'=>'contact','label'=>'Public contact method','required'=>false,'complete'=>!empty($revision['contact']['email']) || !empty($revision['contact']['website'])],
    ];
    $complete = count(array_filter($checks,static fn(array $check):bool=>$check['complete']));
    $requiredComplete = count(array_filter($checks,static fn(array $check):bool=>$check['required']&&!$check['complete']))===0;
    return [
        'score'=>(int)round(($complete/max(1,count($checks)))*100),
        'required_complete'=>$requiredComplete,
        'can_publish'=>$requiredComplete && (($storefront['status'] ?? '') !== 'suspended'),
        'checks'=>$checks,
    ];
}
