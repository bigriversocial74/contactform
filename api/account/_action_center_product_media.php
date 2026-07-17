<?php
declare(strict_types=1);

function mg_action_center_product_media_metadata(array $item): array
{
    foreach (['_metadata', 'metadata_json', 'instance_metadata_json', 'metadata'] as $key) {
        $raw = $item[$key] ?? null;
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || trim($raw) === '') continue;
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) return $decoded;
        } catch (Throwable) {
        }
    }
    return [];
}

function mg_action_center_product_media_public_id(array $item): string
{
    $metadata = mg_action_center_product_media_metadata($item);
    $futureDemand = is_array($metadata['future_demand_metadata'] ?? null) ? $metadata['future_demand_metadata'] : [];
    $reward = is_array($metadata['reward_template_metadata'] ?? null) ? $metadata['reward_template_metadata'] : [];

    foreach ([
        $item['product_id'] ?? '',
        $item['catalog_product_id'] ?? '',
        $metadata['catalog_product_id'] ?? '',
        $metadata['product_public_id'] ?? '',
        $futureDemand['catalog_product_id'] ?? '',
        $reward['catalog_product_id'] ?? '',
        $reward['product_public_id'] ?? '',
    ] as $candidate) {
        $candidate = strtolower(trim((string) $candidate));
        if ($candidate !== '' && preg_match('/^[a-f0-9-]{36}$/', $candidate) === 1) return $candidate;
    }

    return '';
}

function mg_action_center_product_media_row(array $row, string $versionBasis): array
{
    $productId = trim((string) ($row['product_public_id'] ?? ''));
    $versionId = trim((string) ($row['product_version_public_id'] ?? ''));
    $slug = trim((string) ($row['product_slug'] ?? ''));
    $assetId = trim((string) ($row['product_cover_asset_public_id'] ?? ''));
    $status = strtolower(trim((string) ($row['product_status'] ?? ''));
    $isPublic = $status === 'published';

    return [
        'product_id' => $productId,
        'product_version_id' => $versionId,
        'product_slug' => $slug,
        'catalog_product_type' => trim((string) ($row['catalog_product_type'] ?? '')),
        'product_title' => trim((string) ($row['product_title'] ?? '')),
        'product_status' => $status,
        'product_is_public' => $isPublic,
        'product_version_basis' => $versionBasis,
        'product_image_url' => $assetId !== '' ? '/api/public/media.php?asset=' . rawurlencode($assetId) : '',
        'product_url' => $isPublic && $productId !== '' && $slug !== ''
            ? '/product.php?id=' . rawurlencode($productId) . '&p=' . rawurlencode($slug)
            : ($isPublic && $slug !== '' ? '/product.php?p=' . rawurlencode($slug) : ''),
        'image_source' => $assetId !== ''
            ? ($versionBasis === 'exact_instance_version' ? 'catalog_product_version_cover' : 'catalog_product_current_cover')
            : '',
    ];
}

function mg_action_center_attach_product_media(PDO $pdo, int $userId, array $items): array
{
    if ($items === []) return [];

    $actionIds = [];
    $metadataProductIds = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $actionId = trim((string) ($item['action_item_id'] ?? ''));
        if ($actionId !== '' && !str_starts_with($actionId, 'wallet-')) $actionIds[$actionId] = true;
        $productId = mg_action_center_product_media_public_id($item);
        if ($productId !== '') $metadataProductIds[$productId] = true;
    }

    $byActionId = [];
    if ($actionIds !== []) {
        try {
            $ids = array_keys($actionIds);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT ac.public_id action_item_id,
                           cp.public_id product_public_id,cp.slug product_slug,cp.product_type catalog_product_type,cp.status product_status,
                           cpv.public_id product_version_public_id,cpv.title product_title,cpv.version_status product_version_status,
                           cover.public_id product_cover_asset_public_id
                    FROM microgift_inbox_items ac
                    INNER JOIN microgift_instances i ON i.id=ac.instance_id
                    LEFT JOIN commerce_order_items coi ON coi.id=i.commerce_order_item_id
                    LEFT JOIN catalog_product_versions cpv ON cpv.id=COALESCE(coi.product_version_id,i.product_version_id)
                    LEFT JOIN catalog_products cp ON cp.id=COALESCE(coi.product_id,cpv.product_id,i.product_id)
                    LEFT JOIN catalog_assets cover ON cover.id=(
                        SELECT pva.asset_id
                        FROM catalog_product_version_assets pva
                        WHERE pva.product_version_id=cpv.id AND pva.role='cover'
                        ORDER BY pva.sort_order ASC,pva.id ASC
                        LIMIT 1
                    ) AND cover.status='ready'
                    WHERE ac.user_id=? AND ac.public_id IN ({$placeholders})";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([$userId], $ids));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $actionId = trim((string) ($row['action_item_id'] ?? ''));
                if ($actionId !== '') $byActionId[$actionId] = mg_action_center_product_media_row($row, 'exact_instance_version');
            }
        } catch (Throwable $error) {
            if (function_exists('mg_security_log')) {
                mg_security_log('warning', 'account.gift_product_media_partial', 'Linked product data was partially unavailable.', ['exception_type' => $error::class], $userId);
            }
        }
    }

    $byProductId = [];
    if ($metadataProductIds !== []) {
        try {
            $ids = array_keys($metadataProductIds);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT cp.public_id product_public_id,cp.slug product_slug,cp.product_type catalog_product_type,cp.status product_status,
                           cpv.public_id product_version_public_id,cpv.title product_title,cpv.version_status product_version_status,
                           cover.public_id product_cover_asset_public_id
                    FROM catalog_products cp
                    LEFT JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id AND cpv.product_id=cp.id
                    LEFT JOIN catalog_assets cover ON cover.id=(
                        SELECT pva.asset_id
                        FROM catalog_product_version_assets pva
                        WHERE pva.product_version_id=cp.current_version_id AND pva.role='cover'
                        ORDER BY pva.sort_order ASC,pva.id ASC
                        LIMIT 1
                    ) AND cover.status='ready'
                    WHERE cp.public_id IN ({$placeholders}) AND cp.status='published'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $productId = trim((string) ($row['product_public_id'] ?? ''));
                if ($productId !== '') $byProductId[$productId] = mg_action_center_product_media_row($row, 'current_catalog_fallback');
            }
        } catch (Throwable $error) {
            if (function_exists('mg_security_log')) {
                mg_security_log('warning', 'account.gift_product_metadata_media_partial', 'Catalog fallback data was partially unavailable.', ['exception_type' => $error::class], $userId);
            }
        }
    }

    foreach ($items as &$item) {
        if (!is_array($item)) continue;
        $actionId = trim((string) ($item['action_item_id'] ?? ''));
        $productId = mg_action_center_product_media_public_id($item);
        $media = $byActionId[$actionId] ?? ($productId !== '' ? ($byProductId[$productId] ?? null) : null);
        if (!is_array($media)) continue;
        foreach ($media as $key => $value) {
            if ($value !== '' || !array_key_exists($key, $item)) $item[$key] = $value;
        }
    }
    unset($item);

    return $items;
}
