<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/catalog/_catalog.php';

final class MgPublicProductException extends RuntimeException
{
    public function __construct(string $message, int $status = 404)
    {
        parent::__construct($message, $status);
    }

    public function status(): int
    {
        $status = (int) $this->getCode();
        return in_array($status, [400, 404, 409, 410, 422], true) ? $status : 500;
    }
}

function mg_public_product_safe_url(mixed $value): ?string
{
    $url = trim((string) $value);
    if ($url === '' || strlen($url) > 800 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
        return null;
    }
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
        $parts = parse_url($url);
        return $parts !== false && !isset($parts['scheme'], $parts['host'], $parts['user'], $parts['pass']) ? $url : null;
    }
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
        return null;
    }
    if (!in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
        return null;
    }
    return isset($parts['user']) || isset($parts['pass']) ? null : $url;
}

function mg_public_product_absolute_url(string $path): string
{
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path) === 1) return $path;
    if (!str_starts_with($path, '/')) $path = '/' . ltrim($path, '/');

    $https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'microgifter.com'));
    if (!is_string($host) || $host === '') $host = 'microgifter.com';
    return $scheme . '://' . $host . $path;
}

function mg_public_product_decode_json(mixed $value): array
{
    if (!is_string($value) || trim($value) === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_public_product_resolve_identity(PDO $pdo, mixed $publicId, mixed $slug): array
{
    $publicId = trim((string) $publicId);
    $slug = trim((string) $slug);

    $byId = static function (string $id) use ($pdo): array {
        $stmt = $pdo->prepare(
            "SELECT id, public_id, slug FROM catalog_products
             WHERE public_id=? AND status='published' LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new MgPublicProductException('This product is unavailable.', 404);
        }
        return $row;
    };

    if ($publicId !== '') {
        $identity = $byId($publicId);
        if ($slug !== '' && !hash_equals((string) $identity['slug'], $slug)) {
            throw new MgPublicProductException('This product link is invalid.', 404);
        }
        return $identity;
    }

    if ($slug === '') {
        throw new MgPublicProductException('Product not found.', 404);
    }
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $slug)) {
        return $byId($slug);
    }

    $stmt = $pdo->prepare(
        "SELECT id, public_id, slug FROM catalog_products
         WHERE slug=? AND status='published' ORDER BY id ASC LIMIT 2"
    );
    $stmt->execute([$slug]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($matches) !== 1) {
        throw new MgPublicProductException(
            count($matches) > 1 ? 'This product link is ambiguous.' : 'This product is unavailable.',
            count($matches) > 1 ? 409 : 404
        );
    }
    return $matches[0];
}

function mg_public_product_load(PDO $pdo, mixed $publicId, mixed $slug): array
{
    $identity = mg_public_product_resolve_identity($pdo, $publicId, $slug);

    $stmt = $pdo->prepare(
        "SELECT cp.id AS product_db_id, cp.public_id, cp.slug, cp.product_type, cp.published_at,
                cp.merchant_user_id, cpv.id AS version_db_id, cpv.public_id AS version_id,
                cpv.title, cpv.description, cpv.unit_value_cents, cpv.currency,
                cpv.expiration_policy_json, cpv.terms_json, cpv.fulfillment_json, cpv.metadata_json
         FROM catalog_products cp
         INNER JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id
         WHERE cp.public_id=? AND cp.status='published' AND cpv.version_status='published'
         LIMIT 1"
    );
    $stmt->execute([(string) $identity['public_id']]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        throw new MgPublicProductException('This product is unavailable.', 404);
    }

    $merchantStmt = $pdo->prepare(
        "SELECT u.display_name AS user_display_name, u.full_name,
                (SELECT mw.display_name FROM merchant_workspaces mw WHERE mw.merchant_user_id=u.id ORDER BY mw.id ASC LIMIT 1) AS workspace_name,
                pp.slug AS profile_slug, pp.display_name AS profile_name, pp.headline AS profile_headline,
                pp.avatar_url AS profile_avatar_url, pp.status AS profile_status, pp.visibility AS profile_visibility,
                ms.slug AS storefront_slug, ms.display_name AS storefront_name, ms.status AS storefront_status
         FROM users u
         LEFT JOIN public_profiles pp ON pp.user_id=u.id
         LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=u.id
         WHERE u.id=? LIMIT 1"
    );
    $merchantStmt->execute([(int) $product['merchant_user_id']]);
    $merchantRow = $merchantStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $feedStmt = $pdo->prepare(
        "SELECT fp.public_id AS post_id, fp.post_type, fp.visibility,
                fpv.public_id AS post_version_id, fpv.headline, fpv.caption, fpv.cta_label,
                fpv.cta_url, fpv.offer_snapshot_json, fpv.presentation_json
         FROM feed_posts fp
         INNER JOIN feed_post_versions fpv ON fpv.id=fp.current_version_id
         WHERE fp.catalog_product_id=? AND fp.status IN ('published','promoted')
           AND fp.visibility IN ('public','unlisted') AND fpv.version_status='published'
         ORDER BY fp.promoted_at DESC, fp.updated_at DESC LIMIT 1"
    );
    $feedStmt->execute([(int) $product['product_db_id']]);
    $feed = $feedStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $assetsStmt = $pdo->prepare(
        "SELECT cpva.role, cpva.sort_order, ca.public_id AS asset_id, ca.asset_type, ca.mime_type
         FROM catalog_product_version_assets cpva
         INNER JOIN catalog_assets ca ON ca.id=cpva.asset_id AND ca.status='ready'
         WHERE cpva.product_version_id=? ORDER BY cpva.sort_order ASC, cpva.id ASC"
    );
    $assetsStmt->execute([(int) $product['version_db_id']]);
    $assets = array_map(static function (array $asset): array {
        $asset['url'] = '/api/public/media.php?asset=' . rawurlencode((string) $asset['asset_id']);
        $asset['sort_order'] = (int) ($asset['sort_order'] ?? 0);
        return $asset;
    }, $assetsStmt->fetchAll(PDO::FETCH_ASSOC));

    $locationsStmt = $pdo->prepare(
        "SELECT ml.public_id AS id, ml.name, ml.address_line1, ml.city, ml.region,
                ml.postal_code, ml.country_code, cpvl.is_primary
         FROM catalog_product_version_locations cpvl
         INNER JOIN merchant_locations ml ON ml.id=cpvl.merchant_location_id AND ml.status='active'
         WHERE cpvl.product_version_id=? AND cpvl.availability_status='available'
         ORDER BY cpvl.is_primary DESC, ml.name ASC, ml.id ASC"
    );
    $locationsStmt->execute([(int) $product['version_db_id']]);
    $locations = array_map(static function (array $location): array {
        $location['is_primary'] = !empty($location['is_primary']);
        return $location;
    }, $locationsStmt->fetchAll(PDO::FETCH_ASSOC));

    $elements = [];
    if (!empty($feed['post_version_id'])) {
        $elementsStmt = $pdo->prepare(
            "SELECT fpe.public_id, fpe.element_type, fpe.sort_order, fpe.content_json,
                    ca.public_id AS asset_id, ca.asset_type, ca.mime_type
             FROM feed_post_elements fpe
             LEFT JOIN catalog_assets ca ON ca.id=fpe.asset_id
             WHERE fpe.feed_post_version_id=(SELECT id FROM feed_post_versions WHERE public_id=? LIMIT 1)
             ORDER BY fpe.sort_order ASC, fpe.id ASC"
        );
        $elementsStmt->execute([(string) $feed['post_version_id']]);
        foreach ($elementsStmt->fetchAll(PDO::FETCH_ASSOC) as $element) {
            $element['content'] = mg_public_product_decode_json($element['content_json'] ?? null);
            $element['url'] = !empty($element['asset_id'])
                ? '/api/public/media.php?asset=' . rawurlencode((string) $element['asset_id'])
                : null;
            $element['sort_order'] = (int) ($element['sort_order'] ?? 0);
            unset($element['content_json']);
            $elements[] = $element;
        }
    }

    $product['expiration_policy'] = mg_public_product_decode_json($product['expiration_policy_json'] ?? null);
    $product['terms'] = mg_public_product_decode_json($product['terms_json'] ?? null);
    $product['fulfillment'] = mg_public_product_decode_json($product['fulfillment_json'] ?? null);
    $product['metadata'] = mg_public_product_decode_json($product['metadata_json'] ?? null);
    $product['offer'] = mg_public_product_decode_json($feed['offer_snapshot_json'] ?? null);
    $product['presentation'] = mg_public_product_decode_json($feed['presentation_json'] ?? null);
    unset(
        $product['expiration_policy_json'],
        $product['terms_json'],
        $product['fulfillment_json'],
        $product['metadata_json'],
        $product['product_db_id'],
        $product['version_db_id']
    );

    foreach (['post_id', 'post_type', 'visibility', 'post_version_id', 'headline', 'caption', 'cta_label', 'cta_url'] as $field) {
        $product[$field] = $feed[$field] ?? null;
    }

    $builderType = (string) ($product['fulfillment']['builder_type'] ?? $product['presentation']['builder_type'] ?? 'simple_product');
    if (!in_array($builderType, ['simple_product', 'greeting_card', 'multimedia_greeting_card', 'simple_collab'], true)) {
        $builderType = 'simple_product';
    }

    $mediaByRole = [];
    foreach ($assets as $asset) {
        $role = trim((string) ($asset['role'] ?? ''));
        if ($role !== '' && !isset($mediaByRole[$role])) {
            $mediaByRole[$role] = $asset;
        }
    }

    $merchantName = trim((string) ($merchantRow['workspace_name'] ?? ''));
    if ($merchantName === '') $merchantName = trim((string) ($merchantRow['profile_name'] ?? ''));
    if ($merchantName === '') $merchantName = trim((string) ($merchantRow['storefront_name'] ?? ''));
    if ($merchantName === '') $merchantName = trim((string) ($merchantRow['user_display_name'] ?? ''));
    if ($merchantName === '') $merchantName = trim((string) ($merchantRow['full_name'] ?? ''));
    if ($merchantName === '') $merchantName = 'Local merchant';

    $profilePublic = (string) ($merchantRow['profile_status'] ?? '') === 'active'
        && in_array((string) ($merchantRow['profile_visibility'] ?? ''), ['public', 'unlisted'], true);
    $profileSlug = trim((string) ($merchantRow['profile_slug'] ?? ''));
    $storefrontSlug = trim((string) ($merchantRow['storefront_slug'] ?? ''));

    $product['builder_type'] = $builderType;
    $product['media_by_role'] = $mediaByRole;
    $product['public_url'] = mg_catalog_public_product_url((string) $product['public_id'], (string) $product['slug']);
    $product['storefront_url'] = $storefrontSlug !== '' && (string) ($merchantRow['storefront_status'] ?? '') === 'published'
        ? '/store.php?s=' . rawurlencode($storefrontSlug)
        : null;
    $product['merchant_name'] = $merchantName;
    $product['merchant'] = [
        'name' => $merchantName,
        'headline' => trim((string) ($merchantRow['profile_headline'] ?? '')),
        'avatar_url' => mg_public_product_safe_url($merchantRow['profile_avatar_url'] ?? null),
        'profile_url' => $profilePublic && $profileSlug !== ''
            ? '/profile.php?slug=' . rawurlencode($profileSlug)
            : null,
        'storefront_url' => $product['storefront_url'],
    ];
    $product['locations'] = $locations;
    $product['availability'] = [
        'status' => $locations !== [] ? 'available' : 'online',
        'location_count' => count($locations),
    ];
    $product['assets'] = $assets;
    $product['elements'] = $elements;
    $product['is_purchasable'] = true;

    return $product;
}

function mg_public_product_asset(array $product, string $role): ?array
{
    $asset = $product['media_by_role'][$role] ?? null;
    return is_array($asset) && !empty($asset['url']) ? $asset : null;
}

function mg_public_product_text(array $value, array $keys): string
{
    foreach ($keys as $key) {
        $candidate = trim((string) ($value[$key] ?? ''));
        if ($candidate !== '') return $candidate;
    }
    return '';
}
