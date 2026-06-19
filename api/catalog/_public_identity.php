<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog.php';

function mg_catalog_require_merchant_slug(
    PDO $pdo,
    int $merchantUserId,
    string $slug,
    ?int $excludeProductId = null
): void {
    $sql = 'SELECT id FROM catalog_products WHERE merchant_user_id=? AND slug=?';
    $params = [$merchantUserId, $slug];
    if ($excludeProductId !== null) {
        $sql .= ' AND id<>?';
        $params[] = $excludeProductId;
    }
    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetchColumn()) {
        mg_fail('This product slug is already used in your catalog.', 409);
    }
}

function mg_catalog_require_global_published_slug(
    PDO $pdo,
    string $slug,
    int $excludeProductId
): void {
    $stmt = $pdo->prepare(
        "SELECT public_id FROM catalog_products
         WHERE slug=? AND id<>? AND status='published'
         LIMIT 1"
    );
    $stmt->execute([$slug, $excludeProductId]);
    if ($stmt->fetchColumn()) {
        mg_fail('This public product URL is already in use. Choose a different product slug.', 409);
    }
}

function mg_catalog_resolve_public_product_identity(
    PDO $pdo,
    ?string $publicId,
    ?string $slug
): array {
    $publicId = trim((string)$publicId);
    $slug = trim((string)$slug);

    if ($publicId !== '') {
        $stmt = $pdo->prepare(
            "SELECT id,public_id,slug FROM catalog_products
             WHERE public_id=? AND status='published'
             LIMIT 1"
        );
        $stmt->execute([$publicId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) mg_fail('Product not found.', 404);
        if ($slug !== '' && !hash_equals((string)$product['slug'], $slug)) {
            mg_fail('Product link is invalid.', 404);
        }
        return $product;
    }

    if ($slug === '') mg_fail('Product not found.', 404);
    $stmt = $pdo->prepare(
        "SELECT id,public_id,slug FROM catalog_products
         WHERE slug=? AND status='published'
         ORDER BY id ASC LIMIT 2"
    );
    $stmt->execute([$slug]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($matches) !== 1) {
        mg_fail(count($matches) > 1 ? 'Product link is ambiguous.' : 'Product not found.', count($matches) > 1 ? 409 : 404);
    }
    return $matches[0];
}
