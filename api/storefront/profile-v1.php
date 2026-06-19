<?php
declare(strict_types=1);

function mg_storefront_public_asset_url(mixed $url): ?string
{
    $value = trim((string)$url);
    if ($value === '') return null;
    $query = parse_url($value, PHP_URL_QUERY);
    if (!is_string($query)) return $value;
    parse_str($query, $params);
    $asset = trim((string)($params['asset'] ?? ''));
    if ($asset === '' || !ctype_digit($asset)) return $value;

    $stmt = mg_db()->prepare("SELECT public_id FROM catalog_assets WHERE id=? AND status='ready' LIMIT 1");
    $stmt->execute([(int)$asset]);
    $publicId = $stmt->fetchColumn();
    return $publicId ? '/api/public/media.php?asset=' . rawurlencode((string)$publicId) : null;
}

function mg_ok(array $data = [], string $message = 'OK', int $status = 200): never
{
    if (isset($data['storefront']) && is_array($data['storefront'])) {
        foreach (['logo_url','cover_url'] as $field) {
            $data['storefront'][$field] = mg_storefront_public_asset_url($data['storefront'][$field] ?? null);
        }
    }

    if (isset($data['products']) && is_array($data['products'])) {
        foreach ($data['products'] as &$product) {
            if (!is_array($product)) continue;
            $publicId = trim((string)($product['public_id'] ?? ''));
            $slug = trim((string)($product['slug'] ?? ''));
            $product['product_url'] = $publicId !== '' && $slug !== ''
                ? '/product.php?id=' . rawurlencode($publicId) . '&p=' . rawurlencode($slug)
                : null;
            $product['cover_url'] = mg_storefront_public_asset_url($product['cover_url'] ?? null);
            unset($product['cover_asset_id']);
        }
        unset($product);
    }

    mg_json(['ok'=>true,'message'=>$message,'data'=>$data],$status);
}

require __DIR__ . '/_profile_legacy.php';
