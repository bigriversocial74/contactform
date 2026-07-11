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
    $appSidebar = $read('includes/app-sidebar.php');
    $merchantWorkspace = $read('includes/merchant-workspace.php');

    $expect(
        str_contains($inbox, "$agent_tab = 'inbox';")
        && str_contains($inbox, "require __DIR__ . '/includes/gift-action-center.php';"),
        'Inbox continues to use the shared agent sidebar through the gift action center'
    );

    $expect(
        str_contains($agentSidebar, "if ($agentSidebarActive === 'inbox')")
        && str_contains($agentSidebar, "['feed-following', 'merchant_crm', 'ads-manager']")
        && str_contains($agentSidebar, "$appSidebarNav[$inboxHiddenNavKey]['visible'] = false"),
        'Inbox hides Following, Merchant CRM, and Campaign Ads from the visible sidebar'
    );

    $expect(
        str_contains($agentSidebar, "$appSidebarNav['training-lab'] = ['visible' => false]")
        && str_contains($appSidebar, "!isset($appSidebarNav['training-lab'])"),
        'Inbox suppresses the automatically injected Training Lab navigation item'
    );

    foreach (['Following', 'Merchant CRM', 'Campaign Ads'] as $label) {
        $expect(
            str_contains($agentSidebar, "'label' => '" . $label . "'"),
            $label . ' remains available for non-inbox agent pages'
        );
    }

    $expect(
        str_contains($merchantWorkspace, "if ($merchantView === 'merchant_crm')")
        && str_contains($merchantWorkspace, "['loyalty_quests', 'quest_creative', 'quest_reviews', 'quest_delivery', 'quest_analytics']")
        && str_contains($merchantWorkspace, 'unset($merchantNav[$questNavKey])'),
        'Merchant CRM removes every quest navigation key before rendering the sidebar'
    );

    foreach ([
        "'loyalty_quests' => ['Loyalty Quests'",
        "'quest_creative' => ['Quest Creative'",
        "'quest_reviews' => ['Quest Reviews'",
        "'quest_delivery' => ['Quest Delivery'",
        "'quest_analytics' => ['Quest Analytics'",
    ] as $routeMarker) {
        $expect(
            str_contains($merchantWorkspace, $routeMarker),
            'Quest route remains registered outside Merchant CRM: ' . $routeMarker
        );
    }
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Inbox sidebar / Merchant CRM quest navigation validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "Inbox sidebar / Merchant CRM quest navigation validation passed: {$passes} checks.\n";
