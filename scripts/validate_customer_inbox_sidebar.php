<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$sidebarPath = $root . '/includes/agent-sidebar.php';
if (!is_file($sidebarPath)) {
    fwrite(STDERR, "Missing required file: {$sidebarPath}\n");
    exit(1);
}

$sidebar = (string) file_get_contents($sidebarPath);

$expected = [
    "'inbox' => [" => "'label' => 'Inbox'",
    "'my-feed' => [" => "'label' => 'My Feed'",
    "'loyalty-cards' => [" => "'label' => 'My Loyalty Cards'",
    "'lists' => [" => "'label' => 'My Lists'",
    "'design-studio' => [" => "'label' => 'Design Studio'",
    "'new-chat' => [" => "'label' => 'New Chat'",
];

$checks = [];
foreach ($expected as $key => $label) {
    $checks[$label . ' is present'] = str_contains($sidebar, $key) && str_contains($sidebar, $label);
}

$checks['links use the requested destinations'] =
    str_contains($sidebar, "'href' => '/inbox.php'")
    && str_contains($sidebar, "'href' => '/feed.php'")
    && str_contains($sidebar, "'href' => '/loyalty-cards.php'")
    && str_contains($sidebar, "'href' => '/lists.php'")
    && str_contains($sidebar, "'href' => '/design-studio.php'")
    && str_contains($sidebar, "'href' => '/agent.php'");

$checks['removed customer links are absent from the visible nav array'] =
    !str_contains($sidebar, "'my-quests' => [")
    && !str_contains($sidebar, "'agent_chat' => [")
    && !str_contains($sidebar, "'messages' => [")
    && !str_contains($sidebar, "'store-canvas' => [")
    && !str_contains($sidebar, "'world-canvas' => [")
    && !str_contains($sidebar, "'build' => [")
    && !str_contains($sidebar, "'feed-following' => [")
    && !str_contains($sidebar, "'merchant_crm' => [")
    && !str_contains($sidebar, "'ads-manager' => [");

$checks['merchant admin navigation remains isolated'] =
    str_contains($sidebar, "str_starts_with(\$currentSidebarScript, 'merchant-')")
    && str_contains($sidebar, "require_once __DIR__ . '/merchant-navigation.php'")
    && str_contains($sidebar, 'mg_merchant_navigation_sidebar($agentSidebarActive)');

$positions = [];
foreach (array_keys($expected) as $key) {
    $positions[] = strpos($sidebar, $key);
}
$checks['customer links appear in the requested order'] =
    !in_array(false, $positions, true)
    && $positions === array_values(array_unique($positions))
    && $positions === (function (array $values): array { $sorted = $values; sort($sorted); return $sorted; })($positions);

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}

if ($failed !== []) {
    fwrite(STDERR, "\nCustomer inbox sidebar validation failed: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo "\nCustomer inbox sidebar contract passed.\n";
