<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/market/merchant-market-alerts.php';

mg_require_method('GET');

$user = mg_require_api_user();
$pdo = mg_db();
$userId = (int)($user['id'] ?? 0);

if ($userId < 1) {
    mg_fail('Authentication required.', 401);
}

try {
    $stmt = $pdo->prepare("SELECT id,user_id,slug,display_name,status,visibility FROM public_profiles WHERE user_id=? AND status='active' AND visibility IN ('public','unlisted') ORDER BY updated_at DESC,id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!$profile) {
        mg_ok([
            'alerts' => [[
                'key' => 'market_profile_missing',
                'level' => 'info',
                'title' => 'Market profile missing',
                'body' => 'Create or publish a merchant profile to activate market alerts.',
                'why' => 'Market alerts need an active public or unlisted profile so Microgifter can calculate ticker, score, funnel, and risk movement.',
                'action' => 'Open profile settings and publish your merchant profile.',
                'href' => '/account.php',
            ]],
            'count' => 1,
            'profile' => null,
        ], 'Market alerts loaded.');
    }

    $payload = mg_merchant_market_build($pdo, (string)$profile['slug'], ['viewer_id' => $userId]);
    $snapshots = mg_market_snapshot_load($pdo, $userId, 90);
    $movement = mg_market_explain_movement($payload, $snapshots);
    $alerts = mg_market_alerts_build($payload, $movement, $snapshots);

    mg_ok([
        'alerts' => $alerts,
        'count' => count($alerts),
        'profile' => [
            'slug' => (string)$profile['slug'],
            'display_name' => (string)($profile['display_name'] ?? ''),
        ],
        'latest_snapshot_date' => $snapshots ? (string)($snapshots[count($snapshots) - 1]['snapshot_date'] ?? '') : null,
    ], 'Market alerts loaded.');
} catch (Throwable $e) {
    mg_ok([
        'alerts' => [[
            'key' => 'market_alerts_unavailable',
            'level' => 'info',
            'title' => 'Market alerts unavailable',
            'body' => 'Market alerts could not be calculated right now.',
            'why' => 'The market engine needs profile, campaign, snapshot, and funnel data to calculate alerts.',
            'action' => 'Open the Market Dashboard and refresh after checking the profile setup.',
            'href' => '/account-market.php',
        ]],
        'count' => 1,
        'profile' => null,
    ], 'Market alerts unavailable.');
}
