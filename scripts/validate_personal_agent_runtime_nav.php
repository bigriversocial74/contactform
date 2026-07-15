<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $file = $root . '/' . $path;
    return is_file($file) ? (string) file_get_contents($file) : '';
};

$identityPaths = [
    'includes/personal-agent/data.php',
    'includes/personal-agent/context.php',
    'includes/personal-agent/workflows-core.php',
    'includes/personal-agent/workflows-data-plans.php',
    'includes/personal-agent/workflows-data-groups.php',
    'includes/personal-agent/workflows-data-bundles.php',
    'includes/user-contact-lists.php',
    'includes/user-contact-search.php',
];

$identitySource = '';
foreach ($identityPaths as $path) {
    $identitySource .= $read($path);
}

$header = $read('includes/header-components/app-header.php');
$giftCenter = $read('includes/gift-action-center.php') . $read('includes/gift-center-sidebar.php');
$agentSidebar = $read('includes/personal-agent-sidebar.php');
$agentDashboard = $read('includes/personal-agent/workspace-dashboard.php');
$listCss = $read('assets/css/user-lists.css');

$checks = [
    'users table public_id assumption removed' => !str_contains($identitySource, 'u.public_id')
        && !preg_match('/FROM\s+users\s+WHERE\s+public_id/i', $identitySource),
    'public profile identifiers used' => substr_count($identitySource, 'pp.public_id') >= 8
        && str_contains($identitySource, 'public_profiles'),
    'Agent top tab restored' => str_contains($header, "['agent','Agent','/agent.php'")
        && str_contains($header, 'data-system-tab="agent"')
        && str_contains($header, 'display:inline-flex!important'),
    'My Lists added to gift center sidebar' => str_contains($giftCenter, 'gift-center-sidebar.php')
        && str_contains($giftCenter, 'My Lists')
        && str_contains($giftCenter, '/lists.php'),
    'list management removed from Agent chat navigation' => !str_contains($agentSidebar, "'lists' =>")
        && !str_contains($agentDashboard, 'Manage lists')
        && !str_contains($agentDashboard, 'href="/lists.php"'),
    'My Lists uses full app content width' => str_contains($listCss, '.mg-user-lists-main')
        && str_contains($listCss, 'max-width:1600px')
        && !str_contains($listCss, 'grid-template-columns:var(--mg-app-sidebar')
        && str_contains($listCss, '.mg-user-lists-state .mg-btn'),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) {
        $failed[] = $name;
    }
}

if ($failed !== []) {
    fwrite(STDERR, "\nPersonal Agent runtime/navigation validation failed: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo "\nPersonal Agent runtime/navigation repair: 10/10.\n";
