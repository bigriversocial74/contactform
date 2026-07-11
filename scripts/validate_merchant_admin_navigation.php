<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$navigationPath = $root . '/includes/merchant-navigation.php';
$workspacePath = $root . '/includes/merchant-workspace.php';
$agentSidebarPath = $root . '/includes/agent-sidebar.php';

$navigation = is_file($navigationPath) ? (string) file_get_contents($navigationPath) : '';
$workspace = is_file($workspacePath) ? (string) file_get_contents($workspacePath) : '';
$agentSidebar = is_file($agentSidebarPath) ? (string) file_get_contents($agentSidebarPath) : '';

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
    "'onboarding' =>",
    "'campaign_stamps' =>",
    "'intelligence' =>",
    "['Reward Templates'",
    "['PPPM Items'",
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
];

$customMerchantPages = [
    'merchant-agent-chat.php',
    'merchant-ad-manager.php',
    'merchant-ad-performance.php',
    'merchant-canvas.php',
];

$requiredChecks = [];
foreach ($requiredFragments as $fragment) {
    $requiredChecks[$fragment] = str_contains($navigation, $fragment);
}

$removedChecks = [];
foreach ($removedFragments as $fragment) {
    $removedChecks[$fragment] = !str_contains($navigation, $fragment);
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

$customPageChecks = [];
foreach ($customMerchantPages as $page) {
    $path = $root . '/' . $page;
    $source = is_file($path) ? (string) file_get_contents($path) : '';
    $customPageChecks[$page] = [
        'exists' => is_file($path),
        'uses_shared_menu_bridge' => str_contains($source, 'includes/agent-sidebar.php'),
    ];
}

$allStandardPagesUseSharedMenu = true;
foreach ($standardPageChecks as $check) {
    $allStandardPagesUseSharedMenu = $allStandardPagesUseSharedMenu && $check['exists'] && $check['uses_shared_menu'];
}

$allCustomPagesUseSharedMenuBridge = true;
foreach ($customPageChecks as $check) {
    $allCustomPagesUseSharedMenuBridge = $allCustomPagesUseSharedMenuBridge && $check['exists'] && $check['uses_shared_menu_bridge'];
}

$workspaceUsesCentralNavigation = str_contains($workspace, "require_once __DIR__ . '/merchant-navigation.php'")
    && str_contains($workspace, 'mg_merchant_navigation_sidebar($merchantView)');
$agentSidebarUsesCentralNavigation = str_contains($agentSidebar, "require_once __DIR__ . '/merchant-navigation.php'")
    && str_contains($agentSidebar, 'mg_merchant_navigation_sidebar($agentSidebarActive)')
    && str_contains($agentSidebar, "str_starts_with(\$currentSidebarScript, 'merchant-')");

$ok = $navigation !== ''
    && $workspace !== ''
    && $agentSidebar !== ''
    && !in_array(false, $requiredChecks, true)
    && !in_array(false, $removedChecks, true)
    && $sectionsOrdered
    && $workspaceUsesCentralNavigation
    && $agentSidebarUsesCentralNavigation
    && $allStandardPagesUseSharedMenu
    && $allCustomPagesUseSharedMenuBridge;

echo json_encode([
    'ok' => $ok,
    'required_labels_and_groups' => $requiredChecks,
    'removed_items_and_old_labels' => $removedChecks,
    'section_order' => $sectionOrder,
    'section_positions' => $sectionPositions,
    'sections_ordered' => $sectionsOrdered,
    'workspace_uses_central_navigation' => $workspaceUsesCentralNavigation,
    'agent_sidebar_uses_central_navigation' => $agentSidebarUsesCentralNavigation,
    'standard_merchant_pages' => $standardPageChecks,
    'custom_merchant_pages' => $customPageChecks,
    'all_standard_pages_use_shared_menu' => $allStandardPagesUseSharedMenu,
    'all_custom_pages_use_shared_menu_bridge' => $allCustomPagesUseSharedMenuBridge,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($ok ? 0 : 1);
