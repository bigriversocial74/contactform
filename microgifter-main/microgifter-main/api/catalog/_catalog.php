<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function mg_catalog_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function mg_catalog_slug(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if ($slug === '' || strlen($slug) > 160) {
        mg_fail('Invalid product slug.', 422);
    }
    return $slug;
}

function mg_catalog_json(mixed $value): ?string
{
    if ($value === null || $value === '' || $value === []) {
        return null;
    }
    if (!is_array($value)) {
        mg_fail('Expected an object.', 422);
    }
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || strlen($json) > 262144) {
        mg_fail('Catalog payload is too large.', 422);
    }
    return $json;
}

function mg_catalog_product_for_update(PDO $pdo, int $userId, string $publicId): array
{
    $stmt = $pdo->prepare('SELECT * FROM catalog_products WHERE public_id = ? AND merchant_user_id = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$publicId, $userId]);
    $product = $stmt->fetch();
    if (!$product) {
        mg_fail('Catalog product not found.', 404);
    }
    return $product;
}

function mg_catalog_version_checksum(array $payload): string
{
    ksort($payload);
    return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}
