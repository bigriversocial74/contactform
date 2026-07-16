<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$personalSidebarPath = $root . '/includes/personal-agent-sidebar.php';
$giftSidebarPath = $root . '/includes/gift-center-sidebar.php';
$agentSidebarPath = $root . '/includes/agent-sidebar.php';

foreach ([$personalSidebarPath, $giftSidebarPath, $agentSidebarPath] as $requiredPath) {
    if (!is_file($requiredPath)) {
        fwrite(STDERR, "Missing required file: {$requiredPath}\n");
        exit(1);
    }
}

$sidebar = (string) file_get_contents($personalSidebarPath);
$giftSidebar = (string) file_get_contents($giftSidebarPath);
$agentSidebar = (string) file_get_contents($agentSidebarPath);

$labels = ['Inbox', 'My Feed', 'My Loyalty Cards', 'My Lists', 'New Chat', 'Design'];
$checks = [];
foreach ($labels as $label) {
    $checks[$label . ' is present once'] = substr_count($sidebar, '<strong>' . $label . '</strong>') === 1;
}

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

$checks['training lab is absent'] = !str_contains($sidebar, 'Training Lab') && !str_contains($sidebar, '/training-lab.php');
$checks['chat history is shared'] = str_contains($sidebar, 'data-personal-agent-thread-groups')
    && str_contains($sidebar, 'Private to your account');
$checks['gift folders use the unified sidebar directly'] = str_contains($giftSidebar, "require __DIR__ . '/personal-agent-sidebar.php'")
    && !str_contains($giftSidebar, '$myListsItem')
    && !str_contains($giftSidebar, 'mg-gift-center-my-lists');
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
