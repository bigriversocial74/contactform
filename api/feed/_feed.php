<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/catalog/_catalog.php';

function mg_feed_uuid(): string
{
    return mg_catalog_uuid();
}

function mg_feed_post_type(string $value): string
{
    $allowed = ['simple','image','audio','video','greeting_card','multimedia_card','collab'];
    if (!in_array($value, $allowed, true)) {
        mg_fail('Invalid feed post type.', 422);
    }
    return $value;
}

function mg_feed_visibility(string $value): string
{
    $allowed = ['private','recipient','unlisted','public'];
    if (!in_array($value, $allowed, true)) {
        mg_fail('Invalid feed visibility.', 422);
    }
    return $value;
}

function mg_feed_json(mixed $value, int $maxBytes = 262144): ?string
{
    if ($value === null || $value === '' || $value === []) {
        return null;
    }
    if (!is_array($value)) {
        mg_fail('Expected an object.', 422);
    }
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || strlen($json) > $maxBytes) {
        mg_fail('Feed payload is too large.', 422);
    }
    return $json;
}

function mg_feed_post_for_update(PDO $pdo, int $userId, string $publicId): array
{
    $stmt = $pdo->prepare('SELECT * FROM feed_posts WHERE public_id = ? AND merchant_user_id = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$publicId, $userId]);
    $post = $stmt->fetch();
    if (!$post) {
        mg_fail('Feed post not found.', 404);
    }
    return $post;
}

function mg_feed_version_locked(array $post, array $version): bool
{
    return in_array((string) $post['status'], ['promoted','retired','archived'], true)
        || (string) $version['version_status'] === 'published'
        || !empty($version['immutable_at']);
}

function mg_feed_asset_url(?string $assetId): ?string
{
    if (!$assetId) {
        return null;
    }
    return '/api/catalog/asset-file.php?id=' . rawurlencode($assetId);
}
