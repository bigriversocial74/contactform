<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/profiles.php';
require_once dirname(__DIR__) . '/merchant/_storefront.php';
require_once dirname(__DIR__) . '/tips/_public_availability.php';

mg_require_method('GET');
$user = mg_require_api_user();
$userId = (int)$user['id'];
$pdo = mg_db();
$profile = mg_profile_ensure_for_user($userId);
$profileId = (int)$profile['id'];
$links = mg_profile_links($profileId, false);
$sections = mg_profile_sections($profileId, false);
$readiness = mg_profile_readiness($profile, $links, $sections);

$storefront = mg_storefront_owned($pdo, $userId);
$aggregate = $pdo->prepare(
    "SELECT
      (SELECT COUNT(*) FROM catalog_products WHERE merchant_user_id=?) AS products_total,
      (SELECT COUNT(*) FROM catalog_products WHERE merchant_user_id=? AND status='published') AS products_published,
      (SELECT COUNT(*) FROM feed_posts WHERE merchant_user_id=?) AS posts_total,
      (SELECT COUNT(*) FROM feed_posts WHERE merchant_user_id=? AND status IN ('published','promoted')) AS posts_published,
      (SELECT COUNT(*) FROM subscription_plans WHERE owner_user_id=?) AS plans_total,
      (SELECT COUNT(*) FROM subscription_plans WHERE owner_user_id=? AND status='active') AS plans_active,
      (SELECT COUNT(*) FROM catalog_assets WHERE owner_user_id=? AND asset_type='image' AND status='ready'
         AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.profile_role')) IN ('avatar','cover')) AS profile_media"
);
$aggregate->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId]);
$counts = $aggregate->fetch(PDO::FETCH_ASSOC) ?: [];

$social = $pdo->prepare(
    "SELECT
       (SELECT COUNT(*) FROM social_follows WHERE followed_user_id=? AND status='active') AS followers,
       (SELECT COUNT(DISTINCT subscriber_user_id) FROM subscriptions
        WHERE recipient_user_id=? AND recovery_status='clear'
          AND status IN ('trialing','active','cancel_pending') AND current_period_end>NOW()) AS supporters"
);
$social->execute([$userId, $userId]);
$socialCounts = $social->fetch(PDO::FETCH_ASSOC) ?: ['followers' => 0, 'supporters' => 0];

try {
    $tip = mg_tip_public_profile_capability($pdo, (string)$profile['public_id'], null);
} catch (Throwable) {
    $tip = ['available' => false];
}

mg_ok([
    'profile' => [
        'status' => (string)$profile['status'],
        'visibility' => (string)$profile['visibility'],
        'slug' => (string)$profile['slug'],
        'public_url' => '/profile.php?slug=' . rawurlencode((string)$profile['slug']),
        'preview_url' => '/profile.php?slug=' . rawurlencode((string)$profile['slug']) . '&preview=1',
        'completion_score' => (int)$profile['completion_score'],
        'published_at' => $profile['published_at'] ?? null,
        'updated_at' => $profile['updated_at'] ?? null,
        'readiness' => $readiness,
    ],
    'storefront' => $storefront ? [
        'exists' => true,
        'status' => (string)$storefront['status'],
        'slug' => (string)$storefront['slug'],
        'display_name' => (string)$storefront['display_name'],
        'public_url' => '/store.php?s=' . rawurlencode((string)$storefront['slug']),
        'manage_url' => '/merchant-storefront.php',
    ] : [
        'exists' => false,
        'status' => 'not_created',
        'manage_url' => '/merchant-storefront.php',
    ],
    'products' => [
        'total' => (int)($counts['products_total'] ?? 0),
        'published' => (int)($counts['products_published'] ?? 0),
        'manage_url' => '/merchant-products.php',
    ],
    'posts' => [
        'total' => (int)($counts['posts_total'] ?? 0),
        'published' => (int)($counts['posts_published'] ?? 0),
        'public_url' => '/profile.php?slug=' . rawurlencode((string)$profile['slug']) . '#mg-profile-posts-title',
    ],
    'subscriptions' => [
        'plans_total' => (int)($counts['plans_total'] ?? 0),
        'plans_active' => (int)($counts['plans_active'] ?? 0),
        'supporters' => (int)($socialCounts['supporters'] ?? 0),
        'manage_url' => '/account-subscriptions.php',
    ],
    'tip' => [
        'available' => !empty($tip['available']),
        'manage_url' => '/wallet.php',
    ],
    'audience' => [
        'followers' => (int)($socialCounts['followers'] ?? 0),
        'supporters' => (int)($socialCounts['supporters'] ?? 0),
    ],
    'media' => [
        'ready_assets' => (int)($counts['profile_media'] ?? 0),
    ],
], 'Profile editor summary.');
