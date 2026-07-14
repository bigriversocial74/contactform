<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) {
        throw new RuntimeException('Missing required file: ' . $path);
    }
    $content = file_get_contents($full);
    if (!is_string($content)) {
        throw new RuntimeException('Unable to read required file: ' . $path);
    }
    return $content;
};

$expect = static function (bool $condition, string $label) use (&$failures, &$passes): void {
    if ($condition) {
        $passes++;
        echo "PASS: {$label}\n";
        return;
    }
    $failures[] = $label;
    echo "FAIL: {$label}\n";
};

try {
    $inbox = $read('inbox.php');
    $agentSidebar = $read('includes/agent-sidebar.php');
    $accountSidebar = $read('includes/account-sidebar.php');
    $appSidebar = $read('includes/app-sidebar.php');
    $merchantWorkspace = $read('includes/merchant-workspace.php');
    $merchantNavigation = $read('includes/merchant-navigation.php');
    $merchantRouter = $read('includes/merchant-view.php');

    $expect(
        str_contains($inbox, '$agent_tab = \'inbox\';')
        && str_contains($inbox, "require __DIR__ . '/includes/gift-action-center.php';"),
        'Inbox continues to use the shared agent sidebar through the gift action center'
    );

    $expectedReducedPages = [
        'inbox',
        'loyalty-cards',
        'my-quests',
        'loyalty_quests',
        'subscriptions',
        'notifications',
        'profile',
        'feed-discover',
        'feed-following',
        'feed-mine',
        'world-canvas',
    ];

    $expect(
        str_contains($agentSidebar, '$reducedInboxSidebarPages = [')
        && str_contains($agentSidebar, 'if (!$isMerchantAdminSidebar && ($useInboxSidebar || in_array($agentSidebarActive, $reducedInboxSidebarPages, true)))')
        && str_contains($agentSidebar, "['feed-following', 'merchant_crm', 'ads-manager']")
        && str_contains($agentSidebar, '$appSidebarNav[$inboxHiddenNavKey][\'visible\'] = false'),
        'Reduced customer sidebar filter supports explicit page opt-in without overriding merchant admin pages'
    );

    foreach ($expectedReducedPages as $pageKey) {
        $expect(
            str_contains($agentSidebar, "'{$pageKey}'"),
            'Reduced customer sidebar page list contains ' . $pageKey
        );
    }

    $expect(
        str_contains($agentSidebar, '$appSidebarNav[\'training-lab\'] = [\'visible\' => false]')
        && str_contains($appSidebar, '!isset($appSidebarNav[\'training-lab\'])'),
        'Reduced customer sidebar pages suppress the automatically injected Training Lab item'
    );

    $expect(
        str_contains($agentSidebar, "'my-quests' => [")
        && str_contains($agentSidebar, "'label' => 'My Quests'")
        && str_contains($agentSidebar, "'href' => '/my-quests.php'")
        && str_contains($agentSidebar, "\$agentSidebarActive === 'loyalty_quests'")
        && str_contains($accountSidebar, "'my-quests' => ['Gifts', 'My Quests'"),
        'My Quests is available from both current and compatibility customer navigation'
    );

    $customerInboxPages = [
        'my-quests.php' => '$use_inbox_sidebar = true;',
        'account-subscriptions.php' => '$use_inbox_sidebar = true;',
        'notifications.php' => '$use_inbox_sidebar = true;',
        'feed.php' => '$use_inbox_sidebar = true;',
        'account.php' => '$use_inbox_sidebar = basename(',
    ];

    foreach ($customerInboxPages as $path => $marker) {
        $page = $read($path);
        $expect(
            str_contains($page, $marker)
            && str_contains($page, "require __DIR__ . '/includes/agent-sidebar.php';"),
            $path . ' mounts the shared inbox sidebar contract'
        );
    }

    $expect(
        str_contains($agentSidebar, '$inboxSidebarScripts = [')
        && str_contains($agentSidebar, "'my-quests.php'")
        && str_contains($agentSidebar, "'account-subscriptions.php'")
        && str_contains($agentSidebar, "'notifications.php'")
        && str_contains($agentSidebar, "'feed.php'")
        && str_contains($agentSidebar, "'account.php'"),
        'Customer page filenames have a centralized inbox-sidebar fallback'
    );

    $expect(
        str_contains($agentSidebar, "str_starts_with(\$agentSidebarActive, 'feed-')"),
        'All feed views keep My Feed active when Following is hidden by the inbox sidebar'
    );

    $reducedPages = [
        'loyalty-cards.php' => "\$agent_tab = 'loyalty-cards';",
        'world-canvas.php' => "\$agent_tab = 'world-canvas';",
    ];

    foreach ($reducedPages as $path => $activeMarker) {
        $page = $read($path);
        $expect(
            str_contains($page, $activeMarker)
            && str_contains($page, "require __DIR__ . '/includes/agent-sidebar.php';"),
            $path . ' mounts the shared reduced customer sidebar with the expected active key'
        );
    }

    $merchantAdminPages = [
        'merchant-canvas.php' => "\$agent_tab = 'store-canvas';",
        'merchant-agent-chat.php' => "\$agent_tab = 'agent_chat';",
    ];

    foreach ($merchantAdminPages as $path => $activeMarker) {
        $page = $read($path);
        $expect(
            str_contains($page, $activeMarker)
            && str_contains($page, "require __DIR__ . '/includes/agent-sidebar.php';"),
            $path . ' mounts the shared merchant admin sidebar bridge'
        );
    }

    $expect(
        str_contains($agentSidebar, "str_starts_with(\$currentSidebarScript, 'merchant-')")
        && str_contains($agentSidebar, "require_once __DIR__ . '/merchant-navigation.php'")
        && str_contains($agentSidebar, 'mg_merchant_navigation_sidebar($agentSidebarActive)'),
        'Custom merchant pages use the same centralized merchant navigation'
    );

    $globallyHiddenKeys = [
        'quest_creative',
        'quest_reviews',
        'quest_delivery',
        'quest_analytics',
        'campaign_embed_leads',
        'campaign_embed_analytics',
    ];

    $expect(
        str_contains($merchantWorkspace, "require_once __DIR__ . '/merchant-navigation.php'")
        && str_contains($merchantWorkspace, 'mg_merchant_navigation_sidebar($merchantView)'),
        'Merchant workspace consumes the centralized merchant navigation source'
    );

    $expect(
        str_contains($merchantNavigation, "'loyalty_quests' => ['Loyalty Quests'")
        && str_contains($merchantNavigation, "'/merchant-loyalty-quests.php'")
        && str_contains($merchantNavigation, "'Products & Engagement'"),
        'Loyalty Quests is a visible standalone merchant navigation destination'
    );

    foreach ($globallyHiddenKeys as $key) {
        $expect(
            !preg_match("/'" . preg_quote($key, '/') . "'\\s*=>\\s*\\[/", $merchantNavigation),
            'Hidden quest/embed route is absent from visible merchant navigation: ' . $key
        );
    }

    foreach ([
        "'loyalty_quests' => 'loyalty_quests'",
        "'quest_creative' => 'loyalty_quests'",
        "'quest_reviews' => 'loyalty_quests'",
        "'quest_delivery' => 'loyalty_quests'",
        "'quest_analytics' => 'loyalty_quests'",
        "'campaign_embed_leads' => 'campaigns'",
        "'campaign_embed_analytics' => 'campaigns'",
    ] as $aliasMarker) {
        $expect(
            str_contains($merchantNavigation, $aliasMarker),
            'Hidden route maps to its visible merchant navigation group: ' . $aliasMarker
        );
    }

    foreach ([
        'merchant-loyalty-quests-view.php',
        'merchant-loyalty-quest-creative-view.php',
        'merchant-quest-reviews-view.php',
        'merchant-loyalty-quest-delivery-view.php',
        'merchant-loyalty-quest-analytics-view.php',
    ] as $viewMarker) {
        $expect(
            str_contains($merchantRouter, $viewMarker),
            'Quest route remains available outside the sidebar: ' . $viewMarker
        );
    }

    foreach ([
        'merchant-campaign-embed-leads.php',
        'merchant-campaign-embed-analytics.php',
    ] as $embedPagePath) {
        $embedPage = $read($embedPagePath);
        $expect(
            str_contains($embedPage, "require __DIR__ . '/includes/app-sidebar.php';")
            && str_contains($embedPage, "\$appSidebarVariant = 'merchant'"),
            'Standalone embed route is normalized by the universal merchant sidebar: ' . $embedPagePath
        );
    }

    $expect(
        str_contains($appSidebar, "str_starts_with(\$currentSidebarScript, 'merchant-')")
        && str_contains($appSidebar, 'mg_merchant_navigation_sidebar($appSidebarActive)')
        && str_contains($appSidebar, 'data-merchant-nav-accordions'),
        'Universal app sidebar replaces standalone merchant menu arrays with the shared grouped menu'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Inbox and global merchant sidebar validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "Inbox and global merchant sidebar validation passed: {$passes} checks.\n";
