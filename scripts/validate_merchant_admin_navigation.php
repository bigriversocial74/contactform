<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$navigationPath = $root . '/includes/merchant-navigation.php';
$workspacePath = $root . '/includes/merchant-workspace.php';
$agentSidebarPath = $root . '/includes/agent-sidebar.php';
$appSidebarPath = $root . '/includes/app-sidebar.php';

$navigation = is_file($navigationPath) ? (string) file_get_contents($navigationPath) : '';
$workspace = is_file($workspacePath) ? (string) file_get_contents($workspacePath) : '';
$agentSidebar = is_file($agentSidebarPath) ? (string) file_get_contents($agentSidebarPath) : '';
$appSidebar = is_file($appSidebarPath) ? (string) file_get_contents($appSidebarPath) : '';

$requiredFragments = [
    "'overview' => ['Overview', 'Workspace health', '/merchant.php', 'Dashboard']",
    "'notifications' => ['Notifications', 'Tips, voucher messages, alerts', '/merchant-notifications.php', 'Dashboard']",
    "'reward_templates' => ['Rewards', 'Wallet-ready offers', '/merchant-reward-templates.php', 'Commerce']",
    "'pppm' => ['Microgift Totals', 'Items and lifecycle', '/merchant-pppm.php', 'Commerce']",
    "'merchant_crm' => ['Merchant CRM', 'Customers and campaign history', '/merchant-crm.php', 'Customers & Campaigns']",
    "'campaign_ads' => ['Campaign Ads', 'Boost campaigns and local drops', '/merchant-ad-manager.php', 'Customers & Campaigns']",
    "'storefront' => ['Storefront', 'Public merchant page', '/merchant-storefront.php', 'Store Presence']",
    "'store_canvas' => ['Store Canvas', 'Live avatars and customer activity', '/merchant-canvas.php', 'Store Presence']",
    "'payments' => ['Payments', 'Checkout and reconciliation', '/merchant-payments.php', 'Finance']",
    "'locations' => ['Locations', 'Stores and claim scope', '/merchant-locations.php', 'Business Settings']",
];

$removedFragments = [
    "'onboarding' => [",
    "'campaign_stamps' => [",
    "'intelligence' => [",
    "['Reward Templates'",
    "['PPPM Items'",
];

$routeAliases = [
    "'onboarding' => 'overview'",
    "'intelligence' => 'overview'",
    "'campaign_stamps' => 'stamps'",
    "'loyalty_quests' => 'campaigns'",
    "'quest_creative' => 'campaigns'",
    "'quest_reviews' => 'campaigns'",
    "'quest_delivery' => 'campaigns'",
    "'quest_analytics' => 'campaigns'",
    "'campaign_embed_leads' => 'campaigns'",
    "'campaign_embed_analytics' => 'campaigns'",
];

$standardMerchantPages = [
    'merchant.php',
    'merchant-notifications.php',
    'merchant-products.php',
    'merchant-product.php',
    'merchant-reward-templates.php',
    'merchant-campaigns.php',
    'merchant-crm.php',
    'merchant-stamps.php',
    'merchant-storefront.php',
    'merchant-storefront-preview.php',
    'merchant-pwa.php',
    'merchant-orders.php',
    'merchant-pppm.php',
    'merchant-pppm-item.php',
    'merchant-distribution.php',
    'merchant-distribution-program.php',
    'merchant-developer-api.php',
    'merchant-claims.php',
    'merchant-claim.php',
    'merchant-wallet-redemptions.php',
    'merchant-media.php',
    'merchant-locations.php',
    'merchant-team.php',
    'merchant-payments.php',
    'merchant-settings.php',
    'merchant-onboarding.php',
    'merchant-intelligence.php',
    'merchant-campaign-stamps.php',
    'merchant-loyalty-quests.php',
    'merchant-loyalty-quest-creative.php',
    'merchant-quest-reviews.php',
    'merchant-loyalty-quest-delivery.php',
    'merchant-loyalty-quest-analytics.php',
];

$requiredChecks = [];
foreach ($requiredFragments as $fragment) {
    $requiredChecks[$fragment] = str_contains($navigation, $fragment);
}

$removedChecks = [];
foreach ($removedFragments as $fragment) {
    $removedChecks[$fragment] = !str_contains($navigation, $fragment);
}

$aliasChecks = [];
foreach ($routeAliases as $fragment) {
    $aliasChecks[$fragment] = str_contains($navigation, $fragment);
}

$sectionOrder = ['Dashboard', 'Commerce', 'Customers & Campaigns', 'Store Presence', 'Finance', 'Business Settings'];
$sectionPositions = [];
$lastPosition = -1;
$sectionsOrdered = true;
foreach ($sectionOrder as $section) {
    $position = strpos($navigation, "'" . $section . "'");
    $sectionPositions[$section] = $position;
    if ($position === false || $position <= $lastPosition) {
        $sectionsOrdered = false;
    } else {
        $lastPosition = $position;
    }
}

$standardPageChecks = [];
foreach ($standardMerchantPages as $page) {
    $path = $root . '/' . $page;
    $source = is_file($path) ? (string) file_get_contents($path) : '';
    $standardPageChecks[$page] = [
        'exists' => is_file($path),
        'uses_shared_menu' => str_contains($source, 'includes/merchant-workspace.php'),
    ];
}

$allStandardPagesUseSharedMenu = true;
foreach ($standardPageChecks as $check) {
    $allStandardPagesUseSharedMenu = $allStandardPagesUseSharedMenu && $check['exists'] && $check['uses_shared_menu'];
}

$merchantSidebarPages = [];
foreach (glob($root . '/merchant*.php') ?: [] as $path) {
    $source = (string) file_get_contents($path);
    $page = basename($path);
    $usesWorkspace = str_contains($source, 'includes/merchant-workspace.php');
    $usesAgentSidebar = str_contains($source, 'includes/agent-sidebar.php');
    $usesAppSidebar = str_contains($source, 'includes/app-sidebar.php');
    if (!$usesWorkspace && !$usesAgentSidebar && !$usesAppSidebar) {
        continue;
    }

    $merchantSidebarPages[$page] = [
        'workspace' => $usesWorkspace,
        'agent_sidebar' => $usesAgentSidebar,
        'app_sidebar' => $usesAppSidebar,
        'covered_by_shared_navigation' => $usesWorkspace || $usesAgentSidebar || $usesAppSidebar,
    ];
}

$allMerchantSidebarPagesCovered = $merchantSidebarPages !== [];
foreach ($merchantSidebarPages as $check) {
    $allMerchantSidebarPagesCovered = $allMerchantSidebarPagesCovered && $check['covered_by_shared_navigation'];
}

$workspaceUsesCentralNavigation = str_contains($workspace, "require_once __DIR__ . '/merchant-navigation.php'")
    && str_contains($workspace, 'mg_merchant_navigation_sidebar($merchantView)');
$agentSidebarUsesCentralNavigation = str_contains($agentSidebar, "require_once __DIR__ . '/merchant-navigation.php'")
    && str_contains($agentSidebar, 'mg_merchant_navigation_sidebar($agentSidebarActive)')
    && str_contains($agentSidebar, "str_starts_with(\$currentSidebarScript, 'merchant-')");
$appSidebarEnforcesCentralNavigation = str_contains($appSidebar, "str_starts_with(\$currentSidebarScript, 'merchant-')")
    && str_contains($appSidebar, "require_once __DIR__ . '/merchant-navigation.php'")
    && str_contains($appSidebar, "\$appSidebarVariant = 'merchant'")
    && str_contains($appSidebar, 'mg_merchant_navigation_sidebar($appSidebarActive)');

$ok = $navigation !== ''
    && $workspace !== ''
    && $agentSidebar !== ''
    && $appSidebar !== ''
    && !in_array(false, $requiredChecks, true)
    && !in_array(false, $removedChecks, true)
    && !in_array(false, $aliasChecks, true)
    && $sectionsOrdered
    && $workspaceUsesCentralNavigation
    && $agentSidebarUsesCentralNavigation
    && $appSidebarEnforcesCentralNavigation
    && $allStandardPagesUseSharedMenu
    && $allMerchantSidebarPagesCovered;

echo json_encode([
    'ok' => $ok,
    'required_labels_and_groups' => $requiredChecks,
    'removed_items_and_old_labels' => $removedChecks,
    'route_aliases' => $aliasChecks,
    'section_order' => $sectionOrder,
    'section_positions' => $sectionPositions,
    'sections_ordered' => $sectionsOrdered,
    'workspace_uses_central_navigation' => $workspaceUsesCentralNavigation,
    'agent_sidebar_uses_central_navigation' => $agentSidebarUsesCentralNavigation,
    'app_sidebar_enforces_central_navigation' => $appSidebarEnforcesCentralNavigation,
    'standard_merchant_pages' => $standardPageChecks,
    'all_standard_pages_use_shared_menu' => $allStandardPagesUseSharedMenu,
    'merchant_sidebar_pages' => $merchantSidebarPages,
    'all_merchant_sidebar_pages_covered' => $allMerchantSidebarPagesCovered,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($ok ? 0 : 1);
