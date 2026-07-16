<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$personalSidebarPath = $root . '/includes/personal-agent-sidebar.php';
$giftSidebarPath = $root . '/includes/gift-center-sidebar.php';
$agentSidebarPath = $root . '/includes/agent-sidebar.php';
$subscriptionsPath = $root . '/account-subscriptions.php';
$quickCatalogPath = $root . '/includes/agent-quick-actions.php';
$toolsJsPath = $root . '/assets/js/agent-sidebar-tools.js';

foreach ([$personalSidebarPath, $giftSidebarPath, $agentSidebarPath, $subscriptionsPath, $quickCatalogPath, $toolsJsPath] as $requiredPath) {
    if (!is_file($requiredPath)) {
        fwrite(STDERR, "Missing required file: {$requiredPath}\n");
        exit(1);
    }
}

$sidebar = (string) file_get_contents($personalSidebarPath);
$giftSidebar = (string) file_get_contents($giftSidebarPath);
$agentSidebar = (string) file_get_contents($agentSidebarPath);
$subscriptions = (string) file_get_contents($subscriptionsPath);
$quickCatalog = (string) file_get_contents($quickCatalogPath);
$toolsJs = (string) file_get_contents($toolsJsPath);

$labels = ['Inbox', 'My Feed', 'My Loyalty Cards', 'My Lists', 'New Chat', 'Design'];
$checks = [];
foreach (['Inbox', 'My Feed', 'My Loyalty Cards', 'My Lists', 'Design'] as $label) {
    $checks[$label . ' is present once'] = substr_count($sidebar, '<strong>' . $label . '</strong>') === 1;
}
$newChatCount = substr_count($sidebar, '<strong>New Chat</strong>');
$checks['New Chat has one mutually exclusive rendered path'] = $newChatCount >= 1 && $newChatCount <= 2
    && str_contains($sidebar, 'data-personal-agent-new-chat')
    && str_contains($sidebar, '$personalAgentHref');

$checks['links use the requested destinations'] =
    str_contains($sidebar, 'href="/inbox.php"')
    && str_contains($sidebar, 'href="/feed.php"')
    && str_contains($sidebar, 'href="/loyalty-cards.php"')
    && str_contains($sidebar, 'href="/lists.php"')
    && str_contains($sidebar, 'data-personal-agent-new-chat')
    && str_contains($sidebar, 'href="/design-studio.php"');

$checks['requested links appear in the shared order'] = (function () use ($sidebar, $labels): bool {
    $positions = [];
    foreach ($labels as $label) {
        $positions[] = strpos($sidebar, '<strong>' . $label . '</strong>');
    }
    if (in_array(false, $positions, true)) return false;
    $sorted = $positions;
    sort($sorted);
    return $positions === $sorted;
})();

$footerPosition = strpos($sidebar, 'class="mg-personal-chat-sidebar-footer"');
$modePosition = strpos($sidebar, 'class="mg-agent-footer-mode-switch"');
$checks['training lab is absent'] = !str_contains($sidebar, 'Training Lab') && !str_contains($sidebar, '/training-lab.php');
$checks['chat history and Agent tools are shared'] = str_contains($sidebar, 'data-personal-agent-thread-groups')
    && str_contains($sidebar, 'data-agent-suggestions-open')
    && str_contains($sidebar, 'data-agent-tools-tab="suggestions"')
    && str_contains($sidebar, 'data-agent-tools-tab="keywords"')
    && $footerPosition !== false
    && $modePosition !== false
    && $footerPosition < $modePosition;
$checks['footer Agent buttons enforce package destinations'] = str_contains($sidebar, '$hasPersonalAgentAccess')
    && str_contains($sidebar, '$hasMerchantAgentAccess')
    && str_contains($sidebar, '/account-subscriptions.php?agent=personal')
    && str_contains($sidebar, '/account-subscriptions.php?agent=merchant');
$checks['quick actions are centralized and executable'] = str_contains($quickCatalog, 'function mg_agent_quick_action_catalog')
    && str_contains($quickCatalog, "'keyword'=>'/snapshot'")
    && str_contains($quickCatalog, "'keyword'=>'memory'")
    && str_contains($toolsJs, 'form.requestSubmit()')
    && str_contains($toolsJs, 'data-agent-tools-entitled');
$checks['subscriptions use the universal Inbox sidebar'] = str_contains($subscriptions, "\$agent_sidebar_mode='subscriptions'")
    && str_contains($subscriptions, "require __DIR__ . '/includes/personal-agent-sidebar.php'")
    && !str_contains($subscriptions, "require __DIR__ . '/includes/agent-sidebar.php'")
    && str_contains($subscriptions, '/assets/css/personal-agent-chat-history.css?v=1.4.0');
$checks['gift folders use the unified sidebar directly'] = str_contains($giftSidebar, "require __DIR__ . '/personal-agent-sidebar.php'")
    && !str_contains($giftSidebar, '$myListsItem')
    && !str_contains($giftSidebar, 'mg-gift-center-my-lists');
$checks['feed loyalty cards and lists route through the unified sidebar'] =
    str_contains($agentSidebar, "'feed.php'")
    && str_contains($agentSidebar, "'loyalty-cards.php'")
    && str_contains($agentSidebar, "'lists.php'")
    && str_contains($agentSidebar, "require __DIR__ . '/personal-agent-sidebar.php'")
    && str_contains($agentSidebar, '/assets/css/personal-agent-chat-history.css?v=1.2.0')
    && str_contains($agentSidebar, '/assets/js/personal-agent-chat-history.js?v=1.1.0');
$checks['public feed fallback remains available'] = str_contains($agentSidebar, '$user !== null')
    && str_contains($agentSidebar, '$useUnifiedCustomerSidebar');
$checks['merchant admin navigation remains isolated'] =
    str_contains($agentSidebar, "str_starts_with(\$currentSidebarScript, 'merchant-')")
    && str_contains($agentSidebar, "require_once __DIR__ . '/merchant-navigation.php'")
    && str_contains($agentSidebar, 'mg_merchant_navigation_sidebar($agentSidebarActive)');

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}

if ($failed !== []) {
    fwrite(STDERR, "\nUnified customer sidebar validation failed: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo "\nUnified customer sidebar contract passed.\n";
