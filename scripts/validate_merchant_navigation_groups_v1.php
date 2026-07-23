<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/merchant-navigation.php';

$items = mg_merchant_navigation_items();
$sidebar = mg_merchant_navigation_sidebar('merchant-loyalty-quests');
$appSidebar = (string) file_get_contents($root . '/includes/app-sidebar.php');
$accordionCss = (string) file_get_contents($root . '/assets/css/merchant-sidebar-accordion.css');
$loggedInHeader = (string) file_get_contents($root . '/includes/header-templates/logged-in.php');

$expectedOrder = [
    'overview',
    'products',
    'reward_templates',
    'campaigns',
    'creator_campaigns',
    'claims',
    'locations',
    'loyalty_quests',
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
    'creator_campaigns' => '/merchant-creator-campaigns.php',
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
    'Products & Engagement' => ['products', 'reward_templates', 'campaigns', 'creator_campaigns', 'claims', 'locations', 'loyalty_quests'],
    'Insights & Records' => ['notifications', 'orders', 'stamps', 'reviews', 'pppm', 'merchant_crm'],
    'Storefront & Distribution' => ['storefront', 'merchant_pwa', 'hosted_games', 'distribution', 'developer_api', 'store_canvas', 'world_canvas', 'integrations'],
    'Business Operations' => ['campaign_ads', 'payments', 'media', 'team', 'agent_chat', 'settings'],
];

$merchantPanelStart = strpos($loggedInHeader, 'mg-account-merchant-panel');
$merchantPanelEnd = $merchantPanelStart === false ? false : strpos($loggedInHeader, '<?php else: ?>', $merchantPanelStart);
$merchantPanel = ($merchantPanelStart !== false && $merchantPanelEnd !== false)
    ? substr($loggedInHeader, $merchantPanelStart, $merchantPanelEnd - $merchantPanelStart)
    : '';

$removedDropdownHrefs = [
    '/merchant-products.php',
    '/merchant-campaigns.php',
    '/merchant-claims.php',
    '/merchant-reward-templates.php',
    '/merchant-locations.php',
];
$keptDropdownHrefs = [
    '/merchant.php',
    '/merchant-creator-campaigns.php',
    '/merchant-notifications.php',
    '/merchant-pppm.php',
    '/merchant-stamps.php',
    '/merchant-crm.php',
    '/merchant-quest-reviews.php',
];

$checks = [];
$checks[] = ['name' => 'dashboard first in navigation', 'ok' => array_key_first($items) === 'overview'
    && ($items['overview'][0] ?? '') === 'Dashboard'
    && ($items['overview'][2] ?? '') === '/merchant.php'
    && ($items['overview'][3] ?? null) === ''];
$checks[] = ['name' => 'exact grouped navigation order', 'ok' => array_keys($items) === $expectedOrder];
$checks[] = ['name' => 'all prior merchant links preserved', 'ok' => array_reduce(array_keys($legacyHrefs), static function (bool $ok, string $key) use ($items, $legacyHrefs): bool {
    return $ok && isset($items[$key]) && ($items[$key][2] ?? '') === $legacyHrefs[$key];
}, true)];
$checks[] = ['name' => 'creator campaigns standalone destination', 'ok' => ($items['creator_campaigns'][2] ?? '') === '/merchant-creator-campaigns.php'];
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
$groupedSections = array_values(array_unique(array_filter(array_map(static fn(array $item): string => (string) ($item[3] ?? ''), $items))));
$checks[] = ['name' => 'all navigation entries have destinations', 'ok' => !in_array('', $hrefs, true) && !in_array('#', $hrefs, true)];
$checks[] = ['name' => 'sidebar mirrors item order', 'ok' => array_keys($sidebar) === $expectedOrder];
$checks[] = ['name' => 'loyalty quest active state', 'ok' => !empty($sidebar['loyalty_quests']['active']) && mg_merchant_navigation_active_key('merchant-quest-reviews') === 'loyalty_quests'];
$checks[] = ['name' => 'creator campaign builder active alias', 'ok' => mg_merchant_navigation_active_key('merchant-creator-campaign-builder') === 'creator_campaigns'];
$checks[] = ['name' => 'world canvas active alias', 'ok' => mg_merchant_navigation_active_key('world-canvas') === 'world_canvas'];
$checks[] = ['name' => 'four grouped sections plus primary dashboard', 'ok' => $groupedSections === array_keys($expectedSections)];
$checks[] = ['name' => 'native accessible accordion rendering', 'ok' => str_contains($appSidebar, '<details class="mg-side-nav-accordion"')
    && str_contains($appSidebar, '<summary><strong>')
    && str_contains($appSidebar, 'data-merchant-nav-accordions')
    && str_contains($appSidebar, '$sectionHasActiveItem')
    && str_contains($appSidebar, 'merchant-sidebar-accordion.css?v=1.0.0')];
$checks[] = ['name' => 'accordion visual separation', 'ok' => str_contains($accordionCss, '.mg-side-nav-accordion')
    && str_contains($accordionCss, 'padding:0 0 7px')
    && str_contains($accordionCss, 'border-bottom:1px solid #edf2f7')
    && str_contains($accordionCss, 'prefers-reduced-motion')];
$checks[] = ['name' => 'merchant dropdown panel found', 'ok' => $merchantPanel !== ''];
$checks[] = ['name' => 'merchant dropdown removes requested shortcuts', 'ok' => array_reduce($removedDropdownHrefs, static fn(bool $ok, string $href): bool => $ok && !str_contains($merchantPanel, 'href="' . $href . '"'), true)];
$checks[] = ['name' => 'merchant dropdown keeps operational shortcuts', 'ok' => array_reduce($keptDropdownHrefs, static fn(bool $ok, string $href): bool => $ok && str_contains($merchantPanel, 'href="' . $href . '"'), true)];
$checks[] = ['name' => 'quest reviews is final merchant dropdown item', 'ok' => strrpos($merchantPanel, '/merchant-quest-reviews.php') > strrpos($merchantPanel, '/merchant-crm.php')
    && substr_count($merchantPanel, 'class="mg-account-action"') === count($keptDropdownHrefs)];

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
