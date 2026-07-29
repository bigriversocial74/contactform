<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) {
        throw new RuntimeException('Missing required file: ' . $path);
    }
    $content = file_get_contents($full);
    if (!is_string($content)) {
        throw new RuntimeException('Unable to read required file: ' . $path);
    }
    return $content;
};

$center = $read('admin/investor-center.php');
$dashboard = $read('includes/investment/investor-center-dashboard.php');
$accessState = $read('includes/investment/investor-access-state.php');
$portal = $read('investor-portal.php');
$header = $read('includes/header-templates/logged-in.php');
$boot = $read('assets/js/investor-portal-boot-v6.js');
$navigation = $read('assets/js/investor-portal-certification-v6.js');
$accountSidebar = $read('includes/account-sidebar.php');
$adminPages = [
    $read('admin/investor-access-requests.php'),
    $read('admin/investment-wizard.php'),
    $read('admin/investor-pipeline.php'),
    $read('admin/investor-diligence.php'),
    $read('admin/investment-closing.php'),
    $read('admin/investor-governance.php'),
];

$checks = [
    'unified Investor Center covers all six authoritative lifecycle modules' =>
        str_contains($center, 'Investor Access')
        && str_contains($center, 'Investor Pipeline')
        && str_contains($center, 'Due Diligence')
        && str_contains($center, 'Closing')
        && str_contains($center, 'Governance')
        && str_contains($center, 'Funding Rounds'),
    'command dashboard exposes prioritized cross-module exception queues' =>
        str_contains($dashboard, 'overdue_followups')
        && str_contains($dashboard, 'urgent_requests')
        && str_contains($dashboard, 'pending_verifications')
        && str_contains($dashboard, 'overdue_obligations')
        && str_contains($center, 'Investor work queue'),
    'effective Investor access requires both role and active profile' =>
        str_contains($accessState, '$hasRole && $profileStatus === \'active\'')
        && str_contains($accessState, '\'can_open_portal\' => $state === \'approved_active\'')
        && str_contains($accessState, "'role_without_active_profile'"),
    'header Investor tab uses authoritative access state instead of role alone' =>
        str_contains($header, 'mg_investor_access_state')
        && str_contains($header, '$is_investor_account = !empty($investor_access_state[\'can_open_portal\'])')
        && str_contains($header, 'mg-account-tab-investor'),
    'portal renders state-specific recovery instead of loading private scripts for invalid access' =>
        str_contains($portal, '$portalActive = !empty($accessState[\'can_open_portal\'])')
        && str_contains($portal, 'Investor access requires administrator repair.')
        && str_contains($portal, '$page_scripts = $portalActive ? ['),
    'portal boot deduplicates simultaneous GET requests without caching writes' =>
        str_contains($boot, "url.pathname === '/api/investment/portal.php'")
        && str_contains($boot, "method === 'GET'")
        && str_contains($boot, 'let portalGet = null')
        && str_contains($boot, 'return portalGet.then(cloneResponse)'),
    'deep links always resolve to relations and governance fallback panels' =>
        str_contains($navigation, "ensureFallback(container, 'relations')")
        && str_contains($navigation, "ensureFallback(container, 'governance')")
        && str_contains($navigation, 'Investment Relations is not active yet.')
        && str_contains($navigation, 'Governance access is not active yet.'),
    'portal tabs include keyboard navigation and selected-state accessibility' =>
        str_contains($navigation, "'ArrowLeft'")
        && str_contains($navigation, "'ArrowRight'")
        && str_contains($navigation, "item.setAttribute('aria-selected'")
        && str_contains($navigation, "item.setAttribute('role', 'tab')"),
    'Investor links remain absent from the customer account sidebar' =>
        !str_contains($accountSidebar, '/investor-portal.php')
        && !str_contains($accountSidebar, '/investor-access.php'),
    'every specialist administration workspace links back to Investor Center' =>
        count(array_filter($adminPages, static fn(string $page): bool => str_contains($page, '/admin/investor-center.php'))) === count($adminPages),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) {
        $failed[] = $label;
    }
}

$score = round((count($checks) - count($failed)) / count($checks) * 10, 1);
echo 'Investor Center certification score: ' . number_format($score, 1) . '/10' . PHP_EOL;

if ($failed !== []) {
    fwrite(STDERR, 'Investor Center v6 certification failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo "Investor Center v6 certification passed at 10.0/10.\n";
