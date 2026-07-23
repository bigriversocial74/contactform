<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path)
    ? (file_get_contents($root . '/' . $path) ?: '')
    : '';

$sidebar = $read('includes/personal-agent-sidebar.php');
$css = $read('assets/css/agent-sidebar-footer-sticky-v1.css');
$appShell = $read('assets/css/app-shell.css');
$inbox = $read('includes/gift-action-center.php');
$agent = $read('includes/agent-workspace.php');

$checks = [
    'shared sidebar loads sticky footer stylesheet' => str_contains($sidebar, '/assets/css/agent-sidebar-footer-sticky-v1.css?v=1.0.0'),
    'sidebar keeps the shared footer controls' => str_contains($sidebar, 'class="mg-personal-chat-sidebar-footer"')
        && str_contains($sidebar, 'data-agent-sidebar-footer-tools')
        && str_contains($sidebar, 'data-agent-footer-mode-switch'),
    'sidebar remains a viewport flex column' => str_contains($css, '.mg-app-page .mg-personal-chat-sidebar')
        && str_contains($css, 'flex-direction: column !important')
        && str_contains($css, 'overflow: hidden !important'),
    'desktop sidebar uses dynamic viewport height' => str_contains($css, '@media (min-width: 981px)')
        && str_contains($css, 'height: 100dvh !important')
        && str_contains($css, 'min-height: 100dvh !important')
        && str_contains($css, 'max-height: 100dvh !important')
        && str_contains($css, 'inset: 0 auto 0 0 !important'),
    'chat list owns flexible scroll space' => str_contains($css, '> .mg-unified-agent-list')
        && str_contains($css, '> .mg-personal-chat-history')
        && str_contains($css, 'flex: 1 1 auto !important')
        && str_contains($css, 'min-height: 0 !important')
        && str_contains($css, 'overflow-y: auto !important'),
    'footer is pinned to the bottom dock' => str_contains($css, '> .mg-personal-chat-sidebar-footer')
        && str_contains($css, 'position: sticky')
        && str_contains($css, 'bottom: 0')
        && str_contains($css, 'margin-top: auto !important')
        && str_contains($css, 'flex: 0 0 auto !important'),
    'footer remains visually separated and safe-area aware' => str_contains($css, 'background: #fff')
        && str_contains($css, 'box-shadow: 0 -14px 24px')
        && str_contains($css, 'env(safe-area-inset-bottom)'),
    'mobile drawer keeps bottom anchoring without forced full height' => str_contains($css, '@media (max-width: 980px)')
        && str_contains($css, 'top: var(--mg-app-header, 58px) !important')
        && str_contains($css, 'bottom: 0 !important')
        && str_contains($css, 'height: auto !important'),
    'base app shell still provides fixed sidebar authority' => str_contains($appShell, '.mg-app-sidebar{position:fixed')
        && str_contains($appShell, 'height:100svh'),
    'shared sidebar is used by inbox and agent workspaces' => str_contains($inbox, "require __DIR__ . '/gift-center-sidebar.php'")
        && str_contains($agent, "require __DIR__ . '/personal-agent-sidebar.php'"),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}

$score = round((count($checks) - count($failed)) / count($checks) * 100);
echo 'Agent sidebar footer score: ' . $score . '/100' . PHP_EOL;

if ($failed !== []) {
    fwrite(STDERR, 'Agent sidebar footer validation failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "Agent sidebar footer sticky contract passed at 100/100.\n";
