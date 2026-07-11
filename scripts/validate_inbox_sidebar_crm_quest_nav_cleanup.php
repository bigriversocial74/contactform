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
        str_contains($inbox, '$agent_tab = \'inbox\';')
        && str_contains($inbox, "require __DIR__ . '/includes/gift-action-center.php';"),
        'Inbox continues to use the shared agent sidebar through the gift action center'
    );

    $expect(
        str_contains($agentSidebar, 'if ($agentSidebarActive === \'inbox\')')
        && str_contains($agentSidebar, "['feed-following', 'merchant_crm', 'ads-manager']")
        && str_contains($agentSidebar, '$appSidebarNav[$inboxHiddenNavKey][\'visible\'] = false'),
        'Inbox continues to hide Following, Merchant CRM, and Campaign Ads'
    );

    $expect(
        str_contains($agentSidebar, '$appSidebarNav[\'training-lab\'] = [\'visible\' => false]')
        && str_contains($appSidebar, '!isset($appSidebarNav[\'training-lab\'])'),
        'Inbox continues to suppress the automatically injected Training Lab item'
    );

    $globallyHiddenKeys = [
        'loyalty_quests',
        'quest_creative',
        'quest_reviews',
        'quest_delivery',
        'quest_analytics',
        'campaign_embed_leads',
        'campaign_embed_analytics',
    ];

    $expect(
        str_contains($merchantWorkspace, '$globallyHiddenMerchantNavKey')
        && str_contains($merchantWorkspace, 'unset($merchantNav[$globallyHiddenMerchantNavKey])')
        && !str_contains($merchantWorkspace, 'if ($merchantView === \'merchant_crm\')'),
        'Quest and embed navigation is removed globally instead of only on Merchant CRM'
    );

    foreach ($globallyHiddenKeys as $key) {
        $expect(
            str_contains($merchantWorkspace, "'{$key}'"),
            'Global hidden navigation list contains ' . $key
        );
    }

    foreach ([
        "'loyalty_quests' => ['Loyalty Quests'",
        "'quest_creative' => ['Quest Creative'",
        "'quest_reviews' => ['Quest Reviews'",
        "'quest_delivery' => ['Quest Delivery'",
        "'quest_analytics' => ['Quest Analytics'",
        "'campaign_embed_leads' => ['Embed Leads'",
        "'campaign_embed_analytics' => ['Embed Analytics'",
    ] as $routeMarker) {
        $expect(
            str_contains($merchantWorkspace, $routeMarker),
            'Direct route remains registered while sidebar link is hidden: ' . $routeMarker
        );
    }
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
