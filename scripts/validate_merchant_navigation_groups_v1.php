<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/merchant-navigation.php';

$items = mg_merchant_navigation_items();
$sidebar = mg_merchant_navigation_sidebar('merchant-loyalty-quests');

$expectedOrder = [
    'products',
    'reward_templates',
    'campaigns',
    'claims',
    'locations',
    'loyalty_quests',
    'overview',
    'notifications',
    'orders',
    'stamps',
    'reviews',
    'pppm',
    'merchant_crm',
    'storefront',
    'merchant_pwa',
    'hosted_games',
    'distribution',
    'developer_api',
    'store_canvas',
    'world_canvas',
    'integrations',
    'campaign_ads',
    'payments',
    'media',
    'team',
    'agent_chat',
    'settings',
];

$legacyHrefs = [
    'overview' => '/merchant.php',
    'notifications' => '/merchant-notifications.php',
    'products' => '/merchant-products.php',
    'reward_templates' => '/merchant-reward-templates.php',
    'orders' => '/merchant-orders.php',
    'claims' => '/merchant-claims.php',
    'pppm' => '/merchant-pppm.php',
    'merchant_crm' => '/merchant-crm.php',
    'reviews' => '/merchant-reviews.php',
    'campaigns' => '/merchant-campaigns.php',
    'campaign_ads' => '/merchant-ad-manager.php',
    'distribution' => '/merchant-distribution.php',
    'hosted_games' => '/merchant-games.php',
    'agent_chat' => '/merchant-agent-chat.php',
    'storefront' => '/merchant-storefront.php',
    'merchant_pwa' => '/merchant-pwa.php',
    'store_canvas' => '/merchant-canvas.php',
    'media' => '/merchant-media.php',
    'payments' => '/merchant-payments.php',
    'stamps' => '/merchant-stamps.php',
    'locations' => '/merchant-locations.php',
    'team' => '/merchant-team.php',
    'integrations' => '/merchant-integrations.php',
    'developer_api' => '/merchant-distribution.php?developer_api=1',
    'settings' => '/merchant-settings.php',
];

$expectedSections = [
    'Products & Engagement' => ['products', 'reward_templates', 'campaigns', 'claims', 'locations', 'loyalty_quests'],
    'Insights & Records' => ['overview', 'notifications', 'orders', 'stamps', 'reviews', 'pppm', 'merchant_crm'],
    'Storefront & Distribution' => ['storefront', 'merchant_pwa', 'hosted_games', 'distribution', 'developer_api', 'store_canvas', 'world_canvas', 'integrations'],
    'Business Operations' => ['campaign_ads', 'payments', 'media', 'team', 'agent_chat', 'settings'],
];

$checks = [];
$checks[] = ['name' => 'exact grouped navigation order', 'ok' => array_keys($items) === $expectedOrder];
$checks[] = ['name' => 'all prior merchant links preserved', 'ok' => array_reduce(array_keys($legacyHrefs), static function (bool $ok, string $key) use ($items, $legacyHrefs): bool {
    return $ok && isset($items[$key]) && ($items[$key][2] ?? '') === $legacyHrefs[$key];
}, true)];
$checks[] = ['name' => 'loyalty quests standalone destination', 'ok' => ($items['loyalty_quests'][2] ?? '') === '/merchant-loyalty-quests.php'];
$checks[] = ['name' => 'world canvas standalone destination', 'ok' => ($items['world_canvas'][2] ?? '') === '/world-canvas.php'];
$checks[] = ['name' => 'requested merchant labels', 'ok' => ($items['orders'][0] ?? '') === 'My Orders'
    && ($items['stamps'][0] ?? '') === 'My Stamps / Ledger'
    && ($items['reviews'][0] ?? '') === 'My Customer Reviews'
    && ($items['merchant_pwa'][0] ?? '') === 'Branded Apps'
    && ($items['campaign_ads'][0] ?? '') === 'Advertising'];

foreach ($expectedSections as $section => $keys) {
    $actual = [];
    foreach ($items as $key => $item) {
        if (($item[3] ?? '') === $section) {
            $actual[] = $key;
        }
    }
    $checks[] = ['name' => 'section:' . $section, 'ok' => $actual === $keys];
}

$hrefs = array_map(static fn(array $item): string => (string) ($item[2] ?? ''), $items);
$checks[] = ['name' => 'all navigation entries have destinations', 'ok' => !in_array('', $hrefs, true) && !in_array('#', $hrefs, true)];
$checks[] = ['name' => 'sidebar mirrors item order', 'ok' => array_keys($sidebar) === $expectedOrder];
$checks[] = ['name' => 'loyalty quest active state', 'ok' => !empty($sidebar['loyalty_quests']['active']) && mg_merchant_navigation_active_key('merchant-quest-reviews') === 'loyalty_quests'];
$checks[] = ['name' => 'world canvas active alias', 'ok' => mg_merchant_navigation_active_key('world-canvas') === 'world_canvas'];
$checks[] = ['name' => 'four section model only', 'ok' => array_values(array_unique(array_map(static fn(array $item): string => (string) ($item[3] ?? ''), $items))) === array_keys($expectedSections)];

$failed = array_values(array_filter($checks, static fn(array $check): bool => !$check['ok']));
$score = max(0, 10 - count($failed) * 0.5);

echo json_encode([
    'ok' => $failed === [],
    'score' => number_format($score, 1) . '/10',
    'sections' => $expectedSections,
    'checks' => $checks,
    'failed' => $failed,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($failed === [] ? 0 : 1);
