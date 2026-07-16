<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) throw new RuntimeException('Missing required file: ' . $path);
    $content = file_get_contents($full);
    if (!is_string($content)) throw new RuntimeException('Unable to read required file: ' . $path);
    return $content;
};

$expect = static function (bool $condition, string $label) use (&$failures, &$passes): void {
    if ($condition) { $passes++; return; }
    $failures[] = $label;
    echo "FAIL: {$label}\n";
};

try {
    $inbox = $read('inbox.php');
    $personalSidebar = $read('includes/personal-agent-sidebar.php');
    $giftSidebar = $read('includes/gift-center-sidebar.php');
    $agentSidebar = $read('includes/agent-sidebar.php');
    $appSidebar = $read('includes/app-sidebar.php');
    $merchantWorkspace = $read('includes/merchant-workspace.php');
    $merchantNavigation = $read('includes/merchant-navigation.php');
    $merchantRouter = $read('includes/merchant-view.php');

    $expect(
        str_contains($inbox, '$agent_tab = \'inbox\';')
        && str_contains($inbox, "require __DIR__ . '/includes/gift-action-center.php';")
        && str_contains($giftSidebar, "require __DIR__ . '/personal-agent-sidebar.php'"),
        'Inbox, Sent, and Claimed use the shared Personal Agent sidebar'
    );

    foreach (['Inbox', 'My Feed', 'My Loyalty Cards', 'My Lists', 'Design'] as $label) {
        $expect(
            substr_count($personalSidebar, '<strong>' . $label . '</strong>') === 1,
            'Unified customer sidebar contains one ' . $label . ' entry'
        );
    }

    $newChatCount = substr_count($personalSidebar, '<strong>New Chat</strong>');
    $expect(
        $newChatCount >= 1
        && $newChatCount <= 2
        && str_contains($personalSidebar, 'data-personal-agent-new-chat')
        && str_contains($personalSidebar, '$personalAgentHref'),
        'Unified customer sidebar exposes one entitlement-specific New Chat path'
    );

    $expect(
        !str_contains($personalSidebar, 'Training Lab')
        && !str_contains($personalSidebar, '/training-lab.php')
        && !str_contains($giftSidebar, '$myListsItem'),
        'Training Lab and duplicate My Lists injection are removed'
    );

    $footerPosition = strpos($personalSidebar, 'class="mg-personal-chat-sidebar-footer"');
    $modePosition = strpos($personalSidebar, 'class="mg-agent-footer-mode-switch"');
    $expect(
        str_contains($personalSidebar, 'data-personal-agent-thread-groups')
        && str_contains($personalSidebar, 'data-agent-suggestions-open')
        && str_contains($personalSidebar, 'data-agent-tools-tab="suggestions"')
        && str_contains($personalSidebar, 'data-agent-tools-tab="keywords"')
        && $footerPosition !== false
        && $modePosition !== false
        && $footerPosition < $modePosition,
        'Customer sidebar retains Personal Agent history and footer tools'
    );

    $expect(
        str_contains($agentSidebar, "str_starts_with(\$currentSidebarScript, 'merchant-')")
        && str_contains($agentSidebar, "require_once __DIR__ . '/merchant-navigation.php'")
        && str_contains($agentSidebar, 'mg_merchant_navigation_sidebar($agentSidebarActive)'),
        'Custom merchant pages continue using centralized merchant navigation'
    );

    $expect(
        str_contains($merchantWorkspace, "require_once __DIR__ . '/merchant-navigation.php'")
        && str_contains($merchantWorkspace, 'mg_merchant_navigation_sidebar($merchantView)'),
        'Merchant workspace consumes the centralized merchant navigation source'
    );

    $expect(
        str_contains($merchantNavigation, "'loyalty_quests' => ['Loyalty Quests'")
        && str_contains($merchantNavigation, "'/merchant-loyalty-quests.php'")
        && str_contains($merchantNavigation, "'Products & Engagement'"),
        'Loyalty Quests remains available in merchant navigation'
    );

    foreach (['quest_creative','quest_reviews','quest_delivery','quest_analytics','campaign_embed_leads','campaign_embed_analytics'] as $key) {
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
        $expect(str_contains($merchantNavigation, $aliasMarker), 'Hidden route maps to visible merchant navigation group: ' . $aliasMarker);
    }

    foreach ([
        'merchant-loyalty-quests-view.php',
        'merchant-loyalty-quest-creative-view.php',
        'merchant-loyalty-quest-reviews-view.php',
        'merchant-loyalty-quest-delivery-view.php',
        'merchant-loyalty-quest-analytics-view.php',
    ] as $viewMarker) {
        $expect(str_contains($merchantRouter, $viewMarker), 'Quest route remains available outside the sidebar: ' . $viewMarker);
    }

    $expect(
        str_contains($appSidebar, "str_starts_with(\$currentSidebarScript, 'merchant-')")
        && str_contains($appSidebar, 'mg_merchant_navigation_sidebar($appSidebarActive)')
        && str_contains($appSidebar, 'data-merchant-nav-accordions'),
        'Universal app sidebar retains grouped merchant navigation'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Inbox and global merchant sidebar validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}

echo "Inbox and global merchant sidebar validation passed: {$passes} checks.\n";
