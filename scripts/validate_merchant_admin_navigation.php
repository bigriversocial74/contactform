<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$navPath = $root . '/includes/merchant-workspace.php';
$nav = is_file($navPath) ? (string) file_get_contents($navPath) : '';

$requiredFragments = [
    "'overview' => ['Overview','Workspace health','/merchant.php','Dashboard']",
    "'notifications' => ['Notifications','Tips, voucher messages, alerts','/merchant-notifications.php','Dashboard']",
    "'reward_templates' => ['Rewards','Wallet-ready offers','/merchant-reward-templates.php','Commerce']",
    "'pppm' => ['Microgift Totals','Items and lifecycle','/merchant-pppm.php','Commerce']",
    "'merchant_crm' => ['Merchant CRM','Customers and campaign history','/merchant-crm.php','Customers & Campaigns']",
    "'storefront' => ['Storefront','Public merchant page','/merchant-storefront.php','Store Presence']",
    "'payments' => ['Payments','Checkout and reconciliation','/merchant-payments.php','Finance']",
    "'locations' => ['Locations','Stores and claim scope','/merchant-locations.php','Business Settings']",
];

$removedFragments = [
    "'onboarding' =>",
    "'campaign_stamps' =>",
    "'intelligence' =>",
    "['Reward Templates'",
    "['PPPM Items'",
];

$requiredPages = [
    'merchant.php',
    'merchant-notifications.php',
    'merchant-products.php',
    'merchant-product.php',
    'merchant-reward-templates.php',
    'merchant-campaigns.php',
    'merchant-crm.php',
    'merchant-agent-chat.php',
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

$requiredChecks = [];
foreach ($requiredFragments as $fragment) {
    $requiredChecks[$fragment] = str_contains($nav, $fragment);
}

$removedChecks = [];
foreach ($removedFragments as $fragment) {
    $removedChecks[$fragment] = !str_contains($nav, $fragment);
}

$sectionOrder = ['Dashboard', 'Commerce', 'Customers & Campaigns', 'Store Presence', 'Finance', 'Business Settings'];
$sectionPositions = [];
$lastPosition = -1;
$sectionsOrdered = true;
foreach ($sectionOrder as $section) {
    $position = strpos($nav, "'" . $section . "'");
    $sectionPositions[$section] = $position;
    if ($position === false || $position <= $lastPosition) {
        $sectionsOrdered = false;
    } else {
        $lastPosition = $position;
    }
}

$pageChecks = [];
foreach ($requiredPages as $page) {
    $path = $root . '/' . $page;
    if (!is_file($path)) {
        $pageChecks[$page] = ['exists' => false, 'uses_shared_menu' => false];
        continue;
    }
    $source = (string) file_get_contents($path);
    $pageChecks[$page] = [
        'exists' => true,
        'uses_shared_menu' => str_contains($source, "includes/merchant-workspace.php"),
    ];
}

$allPagesUseSharedMenu = true;
foreach ($pageChecks as $check) {
    $allPagesUseSharedMenu = $allPagesUseSharedMenu && $check['exists'] && $check['uses_shared_menu'];
}

$ok = $nav !== ''
    && !in_array(false, $requiredChecks, true)
    && !in_array(false, $removedChecks, true)
    && $sectionsOrdered
    && $allPagesUseSharedMenu;

echo json_encode([
    'ok' => $ok,
    'required_labels_and_groups' => $requiredChecks,
    'removed_items_and_old_labels' => $removedChecks,
    'section_order' => $sectionOrder,
    'section_positions' => $sectionPositions,
    'sections_ordered' => $sectionsOrdered,
    'merchant_pages' => $pageChecks,
    'all_pages_use_shared_menu' => $allPagesUseSharedMenu,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($ok ? 0 : 1);
