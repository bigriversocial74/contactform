<?php
declare(strict_types=1);

/**
 * Canonical Social Design Center v2 registry and review/render helpers.
 *
 * This module intentionally contains no persistence. It composes existing
 * merchant/product/review data and is safe to reuse from pages, APIs and tests.
 */

function mg_social_design_strlen(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function mg_social_design_substr(string $value, int $start, ?int $length = null): string
{
    if (function_exists('mb_substr')) {
        return $length === null ? mb_substr($value, $start) : mb_substr($value, $start, $length);
    }
    return $length === null ? substr($value, $start) : substr($value, $start, $length);
}

function mg_social_design_upper(string $value): string
{
    return function_exists('mb_strtoupper') ? mb_strtoupper($value) : strtoupper($value);
}

function mg_social_design_registry(): array
{
    $formats = [
        'square' => [
            'id' => 'square',
            'label' => 'Post',
            'ratio_label' => '1:1',
            'width' => 1080,
            'height' => 1080,
            'aspect_ratio' => '1 / 1',
            'preview_width' => 432,
            'safe_zone' => 72,
            'type_scale' => 1.0,
            'spacing_scale' => 1.0,
        ],
        'portrait' => [
            'id' => 'portrait',
            'label' => 'Portrait',
            'ratio_label' => '4:5',
            'width' => 1080,
            'height' => 1350,
            'aspect_ratio' => '4 / 5',
            'preview_width' => 432,
            'safe_zone' => 80,
            'type_scale' => 1.04,
            'spacing_scale' => 1.08,
        ],
        'story' => [
            'id' => 'story',
            'label' => 'Reel / Story',
            'ratio_label' => '9:16',
            'width' => 1080,
            'height' => 1920,
            'aspect_ratio' => '9 / 16',
            'preview_width' => 432,
            'safe_zone' => 120,
            'type_scale' => 1.12,
            'spacing_scale' => 1.18,
        ],
    ];

    $variants = [
        'spotlight' => [
            'id' => 'spotlight',
            'label' => 'Spotlight',
            'description' => 'Product-led image with a focused offer panel.',
            'image_position' => 'hero',
            'content_alignment' => 'left',
        ],
        'split' => [
            'id' => 'split',
            'label' => 'Split Feature',
            'description' => 'Balanced product image and information panel.',
            'image_position' => 'split',
            'content_alignment' => 'left',
        ],
        'bold' => [
            'id' => 'bold',
            'label' => 'Bold Offer',
            'description' => 'Large offer typography with a supporting product image.',
            'image_position' => 'supporting',
            'content_alignment' => 'center',
        ],
    ];

    $allFormats = array_keys($formats);
    $allVariants = array_keys($variants);

    $templates = [
        'hero-offer' => [
            'id' => 'hero-offer',
            'label' => 'Hero Offer',
            'description' => 'High-impact product image and direct call to action.',
            'preview' => ['theme' => 'sunrise', 'eyebrow' => 'Featured locally'],
            'supported_formats' => $allFormats,
            'supported_variants' => $allVariants,
            'supports_review' => false,
            'blocks' => ['merchant', 'product_image', 'title', 'description', 'price', 'cta'],
        ],
        'minimal-card' => [
            'id' => 'minimal-card',
            'label' => 'Minimal Card',
            'description' => 'Clean spacing and quiet typography for any merchant.',
            'preview' => ['theme' => 'paper', 'eyebrow' => 'Simple and clear'],
            'supported_formats' => $allFormats,
            'supported_variants' => $allVariants,
            'supports_review' => false,
            'blocks' => ['merchant', 'product_image', 'title', 'description', 'price', 'cta'],
        ],
        'review-highlight' => [
            'id' => 'review-highlight',
            'label' => 'Review Highlight',
            'description' => 'Uses a real approved review when one is available.',
            'preview' => ['theme' => 'coral', 'eyebrow' => 'Customer voice'],
            'supported_formats' => $allFormats,
            'supported_variants' => $allVariants,
            'supports_review' => true,
            'blocks' => ['merchant', 'product_image', 'title', 'price', 'review_or_fallback', 'cta'],
        ],
        'premium-dark' => [
            'id' => 'premium-dark',
            'label' => 'Premium Dark',
            'description' => 'A polished dark presentation for premium offers.',
            'preview' => ['theme' => 'midnight', 'eyebrow' => 'Premium presentation'],
            'supported_formats' => $allFormats,
            'supported_variants' => $allVariants,
            'supports_review' => false,
            'blocks' => ['merchant', 'product_image', 'title', 'description', 'price', 'cta'],
        ],
        'giftable-offer' => [
            'id' => 'giftable-offer',
            'label' => 'Giftable Offer',
            'description' => 'Gifting-forward composition with ribbon-inspired details.',
            'preview' => ['theme' => 'berry', 'eyebrow' => 'Made for gifting'],
            'supported_formats' => $allFormats,
            'supported_variants' => $allVariants,
            'supports_review' => false,
            'blocks' => ['merchant', 'product_image', 'title', 'description', 'price', 'cta'],
        ],
        'bold-price-poster' => [
            'id' => 'bold-price-poster',
            'label' => 'Bold Price Poster',
            'description' => 'Makes the real product price the visual anchor.',
            'preview' => ['theme' => 'electric', 'eyebrow' => 'Price-led offer'],
            'supported_formats' => $allFormats,
            'supported_variants' => $allVariants,
            'supports_review' => false,
            'blocks' => ['merchant', 'product_image', 'title', 'price', 'cta'],
        ],
        'editorial-split' => [
            'id' => 'editorial-split',
            'label' => 'Editorial Split',
            'description' => 'Magazine-inspired hierarchy for product storytelling.',
            'preview' => ['theme' => 'editorial', 'eyebrow' => 'Local edition'],
            'supported_formats' => $allFormats,
            'supported_variants' => $allVariants,
            'supports_review' => false,
            'blocks' => ['merchant', 'product_image', 'title', 'description', 'price', 'cta'],
        ],
        'full-photo-cta' => [
            'id' => 'full-photo-cta',
            'label' => 'Full Photo CTA',
            'description' => 'Immersive image treatment with a compact action panel.',
            'preview' => ['theme' => 'photo', 'eyebrow' => 'See it. Gift it.'],
            'supported_formats' => $allFormats,
            'supported_variants' => $allVariants,
            'supports_review' => false,
            'blocks' => ['merchant', 'product_image', 'title', 'price', 'cta'],
        ],
        'clean-coupon' => [
            'id' => 'clean-coupon',
            'label' => 'Clean Coupon',
            'description' => 'Coupon-inspired framing without invented promotion data.',
            'preview' => ['theme' => 'coupon', 'eyebrow' => 'Local offer'],
            'supported_formats' => $allFormats,
            'supported_variants' => $allVariants,
            'supports_review' => false,
            'blocks' => ['merchant', 'product_image', 'title', 'description', 'price', 'cta'],
        ],
        'social-proof-promo' => [
            'id' => 'social-proof-promo',
            'label' => 'Social Proof Promo',
            'description' => 'Pairs the product with a real public review when available.',
            'preview' => ['theme' => 'community', 'eyebrow' => 'Shared locally'],
            'supported_formats' => $allFormats,
            'supported_variants' => $allVariants,
            'supports_review' => true,
            'blocks' => ['merchant', 'product_image', 'title', 'price', 'review_or_fallback', 'cta'],
        ],
    ];

    return [
        'version' => 2,
        'templates' => $templates,
        'formats' => $formats,
        'variants' => $variants,
        'fallbacks' => [
            'review' => 'Support local. Gift better.',
            'description' => 'Discover this local product, service, or experience on Microgifter.',
            'cta' => 'Shop this product',
        ],
    ];
}

function mg_social_design_review_text(array $row): string
{
    foreach (['body', 'review_body', 'quote', 'text'] as $key) {
        $value = trim((string)($row[$key] ?? ''));
        if ($value !== '') return preg_replace('/\s+/u', ' ', strip_tags($value)) ?: '';
    }
    return '';
}

function mg_social_design_review_name(array $row): string
{
    $name = trim((string)($row['reviewer'] ?? $row['reviewer_name'] ?? $row['author'] ?? ''));
    $name = preg_replace('/\s+/u', ' ', strip_tags($name)) ?: '';
    if ($name === '') return 'Verified customer';

    $parts = preg_split('/\s+/u', $name) ?: [];
    $first = trim((string)($parts[0] ?? ''));
    if ($first === '') return 'Verified customer';
    if (count($parts) === 1) return mg_social_design_substr($first, 0, 40);

    $last = trim((string)end($parts));
    $initial = $last !== '' ? mg_social_design_upper(mg_social_design_substr($last, 0, 1)) . '.' : '';
    return trim(mg_social_design_substr($first, 0, 40) . ' ' . $initial);
}

function mg_social_design_normalize_review(array $row, string $source): ?array
{
    $status = strtolower(trim((string)($row['status'] ?? 'published')));
    if ($status !== 'published') return null;
    if (!empty($row['deleted_at']) || !empty($row['is_deleted'])) return null;

    $quote = mg_social_design_review_text($row);
    if ($quote === '') return null;
    if (mg_social_design_strlen($quote) > 180) $quote = rtrim(mg_social_design_substr($quote, 0, 177)) . '…';

    $rating = (int)($row['rating'] ?? 0);
    $rating = $rating >= 1 && $rating <= 5 ? $rating : null;

    return [
        'id' => (string)($row['public_id'] ?? $row['id'] ?? ''),
        'source' => $source,
        'quote' => $quote,
        'reviewer' => mg_social_design_review_name($row),
        'rating' => $rating,
        'featured' => !empty($row['featured_on_profile']) || !empty($row['featured']),
        'submitted_at' => (string)($row['submitted_at'] ?? $row['created_at'] ?? ''),
    ];
}

function mg_social_design_review_score(array $review): int
{
    $score = !empty($review['featured']) ? 1000 : 0;
    $score += ((int)($review['rating'] ?? 0)) * 100;
    $timestamp = strtotime((string)($review['submitted_at'] ?? ''));
    if ($timestamp !== false) $score += (int)min(99, max(0, floor($timestamp / 31557600) - 40));
    return $score;
}

function mg_social_design_best_review(array $rows, string $source): ?array
{
    $eligible = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $review = mg_social_design_normalize_review($row, $source);
        if ($review !== null) $eligible[] = $review;
    }
    usort($eligible, static fn(array $a, array $b): int => mg_social_design_review_score($b) <=> mg_social_design_review_score($a));
    return $eligible[0] ?? null;
}

function mg_social_design_select_review(array $productReviews, array $merchantReviews): ?array
{
    return mg_social_design_best_review($productReviews, 'product')
        ?? mg_social_design_best_review($merchantReviews, 'merchant');
}

function mg_social_design_table_exists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[a-z0-9_]+$/i', $table) !== 1) return false;
    static $cache = [];
    $key = spl_object_id($pdo) . ':' . $table;
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute([$table]);
        return $cache[$key] = (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return $cache[$key] = false;
    }
}

function mg_social_design_column_exists(PDO $pdo, string $table, string $column): bool
{
    if (preg_match('/^[a-z0-9_]+$/i', $table . $column) !== 1) return false;
    static $cache = [];
    $key = spl_object_id($pdo) . ':' . $table . ':' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $stmt->execute([$table, $column]);
        return $cache[$key] = (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return $cache[$key] = false;
    }
}

function mg_social_design_review_rows(PDO $pdo, string $sql, array $params): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}

function mg_social_design_product_review_rows(
    PDO $pdo,
    int $merchantId,
    int $productInternalId,
    string $productPublicId
): array {
    $base = "SELECT r.public_id,r.reviewer_name,r.rating,r.review_body,r.status,r.featured_on_profile,r.submitted_at
             FROM customer_reviews r";
    $tail = " AND r.status='published' AND TRIM(r.review_body)<>'' ORDER BY r.featured_on_profile DESC,r.rating DESC,r.submitted_at DESC,r.id DESC LIMIT 25";

    $directColumns = [
        'catalog_product_id' => $productInternalId,
        'product_id' => $productInternalId,
        'catalog_product_public_id' => $productPublicId,
        'product_public_id' => $productPublicId,
    ];
    foreach ($directColumns as $column => $value) {
        if (!mg_social_design_column_exists($pdo, 'customer_reviews', $column)) continue;
        return mg_social_design_review_rows(
            $pdo,
            $base . " WHERE r.merchant_user_id=? AND r.`{$column}`=?" . $tail,
            [$merchantId, $value]
        );
    }

    if (!mg_social_design_table_exists($pdo, 'campaigns')) return [];
    $campaignColumns = [
        'catalog_product_id' => $productInternalId,
        'product_id' => $productInternalId,
        'catalog_product_public_id' => $productPublicId,
        'product_public_id' => $productPublicId,
    ];
    foreach ($campaignColumns as $column => $value) {
        if (!mg_social_design_column_exists($pdo, 'campaigns', $column)) continue;
        return mg_social_design_review_rows(
            $pdo,
            $base . " INNER JOIN campaigns c ON c.id=r.campaign_id
                     WHERE r.merchant_user_id=? AND c.merchant_user_id=? AND c.`{$column}`=?" . $tail,
            [$merchantId, $merchantId, $value]
        );
    }

    return [];
}

function mg_social_design_resolve_review(
    PDO $pdo,
    int $merchantId,
    int $productInternalId,
    string $productPublicId
): ?array {
    if ($merchantId < 1 || !mg_social_design_table_exists($pdo, 'customer_reviews')) return null;

    $productRows = mg_social_design_product_review_rows(
        $pdo,
        $merchantId,
        $productInternalId,
        $productPublicId
    );
    $merchantRows = mg_social_design_review_rows(
        $pdo,
        "SELECT public_id,reviewer_name,rating,review_body,status,featured_on_profile,submitted_at
         FROM customer_reviews
         WHERE merchant_user_id=? AND status='published' AND TRIM(review_body)<>''
         ORDER BY featured_on_profile DESC,rating DESC,submitted_at DESC,id DESC
         LIMIT 25",
        [$merchantId]
    );

    return mg_social_design_select_review($productRows, $merchantRows);
}

function mg_social_design_render_payload(
    array $product,
    array $merchant,
    string $templateId,
    string $formatId,
    string $variantId,
    ?array $review = null
): array {
    $registry = mg_social_design_registry();
    if (!isset($registry['templates'][$templateId])) throw new InvalidArgumentException('Unknown social template.');
    if (!isset($registry['formats'][$formatId])) throw new InvalidArgumentException('Unknown social format.');
    if (!isset($registry['variants'][$variantId])) throw new InvalidArgumentException('Unknown social layout variant.');

    $template = $registry['templates'][$templateId];
    if (!in_array($formatId, $template['supported_formats'], true)) {
        throw new InvalidArgumentException('Template does not support the selected format.');
    }
    if (!in_array($variantId, $template['supported_variants'], true)) {
        throw new InvalidArgumentException('Template does not support the selected layout variant.');
    }

    $title = trim((string)($product['title'] ?? $product['slug'] ?? ''));
    $description = trim(strip_tags((string)($product['description'] ?? '')));
    $description = preg_replace('/\s+/u', ' ', $description) ?: '';
    $cta = trim((string)($product['cta'] ?? $product['metadata']['cta'] ?? $registry['fallbacks']['cta']));
    $eligibleReview = !empty($template['supports_review']) && is_array($review)
        ? mg_social_design_normalize_review($review + ['status' => 'published'], (string)($review['source'] ?? 'merchant'))
        : null;

    return [
        'registry_version' => $registry['version'],
        'composition' => [
            'template' => $templateId,
            'format' => $formatId,
            'variant' => $variantId,
        ],
        'output' => [
            'width' => (int)$registry['formats'][$formatId]['width'],
            'height' => (int)$registry['formats'][$formatId]['height'],
        ],
        'merchant' => [
            'name' => trim((string)($merchant['name'] ?? $merchant['display_name'] ?? 'Your Business')),
            'avatar_url' => trim((string)($merchant['avatar_url'] ?? '')),
        ],
        'product' => [
            'id' => (string)($product['public_id'] ?? ''),
            'title' => $title !== '' ? $title : 'Untitled product',
            'description' => $description !== '' ? $description : $registry['fallbacks']['description'],
            'price' => $product['price'] ?? $product['unit_value_cents'] ?? null,
            'currency' => (string)($product['currency'] ?? 'USD'),
            'image_url' => trim((string)($product['image_url'] ?? '')),
            'cta' => $cta !== '' ? $cta : $registry['fallbacks']['cta'],
        ],
        'review' => $eligibleReview,
        'review_fallback' => !empty($template['supports_review']) && $eligibleReview === null
            ? $registry['fallbacks']['review']
            : null,
    ];
}
