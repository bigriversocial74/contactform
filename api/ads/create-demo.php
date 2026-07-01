<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_ads.php';

mg_require_method('POST');
$user = mg_require_api_user();
$pdo = mg_db();
$input = mg_input();
mg_require_csrf_for_write($input);
mg_ads_require_admin_user($user);

try {
    if (function_exists('mg_rate_limit')) {
        mg_rate_limit('ads.create_demo', 'user:' . (int)$user['id'], 12, 60);
    }

    mg_ads_require_schema($pdo);
    mg_ads_seed_placements($pdo);

    $adminUserId = (int)($user['id'] ?? 0);
    $demoMerchantId = max(1, (int)($input['merchant_id'] ?? $adminUserId));
    $startsAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $endsAt = (new DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s');

    $pdo->prepare("UPDATE ad_campaigns SET status='archived', updated_at=NOW() WHERE merchant_id=? AND title LIKE 'Demo: Advertise on Microgifter%'")->execute([$demoMerchantId]);

    $demoAds = [
        [
            'title' => 'Demo: Advertise on Microgifter Feed',
            'headline' => 'Advertise on Microgifter',
            'description' => 'Promote local rewards, gift offers, campaign drops, and sponsored placements where customers can claim, save, and redeem.',
            'objective' => 'local_awareness',
            'cta_label' => 'View Ad Options',
            'destination_url' => '/merchant-ad-manager.php',
            'sponsored_label' => 'Sponsored',
            'placements' => ['feed_sponsored_card'],
            'claim_cap' => 250,
        ],
        [
            'title' => 'Demo: Advertise on Microgifter Sidebar',
            'headline' => 'Feature Your Local Campaign',
            'description' => 'Use sidebar placement to keep your sponsored reward visible beside the customer feed.',
            'objective' => 'claim_growth',
            'cta_label' => 'Boost Campaign',
            'destination_url' => '/merchant-ad-manager.php',
            'sponsored_label' => 'Featured',
            'placements' => ['sidebar_sponsored_card'],
            'claim_cap' => 100,
        ],
        [
            'title' => 'Demo: Advertise on Microgifter World Canvas',
            'headline' => 'Sponsor a World Canvas Pin',
            'description' => 'Show a sponsored local opportunity on the map and connect regional attention to measurable claims and redemptions.',
            'objective' => 'local_drop',
            'cta_label' => 'Sponsor Local Drop',
            'destination_url' => '/merchant-ad-manager.php',
            'sponsored_label' => 'Promoted Merchant',
            'placements' => ['world_canvas_sponsored_pin'],
            'claim_cap' => 300,
        ],
        [
            'title' => 'Demo: Advertise on Microgifter Target Zones',
            'headline' => 'Launch a Sponsored Target Zone',
            'description' => 'Turn a neighborhood, event area, or local business district into a measurable sponsored reward zone.',
            'objective' => 'target_zone_activation',
            'cta_label' => 'Create Target Zone Ad',
            'destination_url' => '/merchant-ad-manager.php',
            'sponsored_label' => 'Sponsored Local Drop',
            'placements' => ['target_zone_sponsored_drop'],
            'claim_cap' => 500,
        ],
    ];

    $created = [];
    foreach ($demoAds as $index => $demo) {
        $payload = $demo + [
            'budget_type' => 'claim_cap',
            'budget_amount' => 0,
            'redemption_cap' => null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'image_url' => '',
            'targeting' => [
                'demo' => true,
                'product' => 'Microgifter Campaign Ads Manager',
                'phase' => 'Phase 1',
                'ad_product' => 'Feed, Sidebar, World Canvas, and Target Zone sponsored placements',
            ],
        ];
        $campaign = mg_ads_upsert_campaign($pdo, $demoMerchantId, $payload, null);
        $publicId = (string)($campaign['public_id'] ?? '');
        if ($publicId !== '') {
            $approved = mg_ads_review_campaign($pdo, $adminUserId, $publicId, 'approve', 'Auto-approved admin demo ad for Campaign Ads Manager Phase 1 testing.');
            $created[] = $approved;
        }
    }

    mg_ok([
        'schema_ready' => true,
        'created_count' => count($created),
        'campaigns' => $created,
        'message' => 'Demo advertising-on-Microgifter ads created and approved.',
    ], 'Demo ads created.');
} catch (Throwable $error) {
    mg_security_log('error', 'ads.create_demo_failed', 'Campaign Ads Manager demo seed failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], (int)($user['id'] ?? 0));
    mg_fail($error->getMessage(), 422);
}
