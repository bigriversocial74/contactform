<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path)
    ? (string) file_get_contents($root . '/' . $path)
    : '';

$header = $read('includes/header-components/app-header.php');
$giftSidebar = $read('includes/gift-center-sidebar.php');
$personalSidebar = $read('includes/personal-agent-sidebar.php');
$quickCatalog = $read('includes/agent-quick-actions.php');
$agentPage = $read('agent.php');
$merchantPage = $read('merchant-agent-chat.php');
$subscriptionsPage = $read('account-subscriptions.php');
$workspace = $read('includes/agent-workspace.php');
$dashboard = $read('includes/personal-agent/workspace-dashboard.php');
$chatCss = $read('assets/css/personal-agent-chat-canvas.css');
$fullCanvasCss = $read('assets/css/personal-agent-full-canvas.css');
$historyCss = $read('assets/css/personal-agent-chat-history.css');
$chatJs = $read('assets/js/personal-agent-chat-canvas.js');
$toolsJs = $read('assets/js/agent-sidebar-tools.js');
$chatActions = $read('assets/js/personal-gifting-agent-actions.js');
$listPage = $read('lists.php');
$listJs = $read('assets/js/user-lists-create.js');
$listCss = $read('assets/css/user-lists.css');

$footerPosition = strpos($personalSidebar, 'class="mg-personal-chat-sidebar-footer"');
$modePosition = strpos($personalSidebar, 'class="mg-agent-footer-mode-switch"');

$checks = [
    'Agent top tab is present' => str_contains($header, "['agent','Agent','/agent.php'")
        && str_contains($header, 'data-system-tab="agent"'),
    'gift folders use the unified sidebar' => str_contains($giftSidebar, "require __DIR__ . '/personal-agent-sidebar.php'")
        && !str_contains($giftSidebar, '$myListsItem')
        && !str_contains($giftSidebar, 'mg-gift-center-my-lists'),
    'customer navigation has one My Lists entry' => substr_count($personalSidebar, '<strong>My Lists</strong>') === 1
        && str_contains($personalSidebar, 'href="/lists.php"'),
    'customer navigation includes chat history and footer Agent tools' => str_contains($personalSidebar, 'data-personal-agent-thread-groups')
        && str_contains($personalSidebar, 'data-personal-agent-new-chat')
        && $footerPosition !== false
        && $modePosition !== false
        && $footerPosition < $modePosition
        && str_contains($personalSidebar, 'data-agent-footer-mode-switch')
        && str_contains($personalSidebar, 'data-agent-suggestions-open')
        && str_contains($personalSidebar, 'data-agent-tools-tab="suggestions"')
        && str_contains($personalSidebar, 'data-agent-tools-tab="keywords"')
        && !str_contains($personalSidebar, 'class="mg-agent-mode-switch"')
        && !str_contains($personalSidebar, 'mg-agent-mode-options'),
    'quick keyword catalog includes current Personal and Merchant commands' => str_contains($quickCatalog, 'function mg_agent_quick_action_catalog')
        && str_contains($quickCatalog, "'keyword'=>'/snapshot'")
        && str_contains($quickCatalog, "'keyword'=>'memory'")
        && str_contains($quickCatalog, "'keyword'=>'/m'")
        && str_contains($toolsJs, 'data-agent-keyword-prompt')
        && str_contains($historyCss, '.mg-agent-sidebar-tools-modal'),
    'free signed-in users are routed to subscriptions from both Agent destinations' => str_contains($agentPage, "header('Location: /account-subscriptions.php?agent=personal')")
        && str_contains($merchantPage, "header('Location: /account-subscriptions.php?agent=merchant')")
        && str_contains($personalSidebar, '/account-subscriptions.php?agent=personal')
        && str_contains($personalSidebar, '/account-subscriptions.php?agent=merchant'),
    'subscription checkout page uses the universal Inbox sidebar' => str_contains($subscriptionsPage, "\$agent_sidebar_mode='subscriptions'")
        && str_contains($subscriptionsPage, "require __DIR__ . '/includes/personal-agent-sidebar.php'")
        && !str_contains($subscriptionsPage, "require __DIR__ . '/includes/agent-sidebar.php'")
        && str_contains($subscriptionsPage, '/assets/css/personal-agent-chat-history.css?v=1.4.0'),
    'Training Lab is not in the customer sidebar' => !str_contains($personalSidebar, 'Training Lab')
        && !str_contains($personalSidebar, '/training-lab.php'),
    'chat canvas remains full width and owns its section' => str_contains($dashboard, 'data-agent-canvas')
        && str_contains($dashboard, 'mg-personal-agent-chat-view mg-personal-agent-chat-stream')
        && !str_contains($dashboard, '<div class="mg-personal-agent-chat-stream">')
        && str_contains($chatCss, 'position:absolute!important')
        && str_contains($chatCss, 'overflow-y:auto!important')
        && str_contains($fullCanvasCss, '.mg-personal-agent-chat-view')
        && str_contains($fullCanvasCss, 'width:100%!important')
        && str_contains($fullCanvasCss, 'background:transparent!important')
        && str_contains($fullCanvasCss, 'box-shadow:none!important'),
    'composer sends through the Agent API' => str_contains($workspace, 'mg-personal-agent-composer-row')
        && str_contains($chatActions, "Microgifter.post('/api/user-agent/chat.php'")
        && str_contains($chatJs, 'Good afternoon')
        && str_contains($chatJs, 'Good evening'),
    'Agent assets are loaded with current cache versions' => str_contains($agentPage, 'personal-agent-chat-canvas.css')
        && str_contains($agentPage, 'personal-agent-full-canvas.css?v=1.0.0')
        && str_contains($agentPage, 'personal-agent-chat-history.css?v=1.4.0')
        && str_contains($agentPage, 'personal-agent-chat-history.js?v=1.2.0')
        && str_contains($personalSidebar, 'agent-sidebar-tools.js?v=1.0.0'),
    'My Lists creation remains wired' => substr_count($listPage, 'data-user-list-open-create') >= 2
        && str_contains($listJs, '[data-user-list-open-create]')
        && str_contains($listCss, '.mg-user-lists-main'),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}

if ($failed !== []) {
    fwrite(STDERR, "Personal Agent runtime/navigation validation failed: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo "Personal Agent runtime/navigation validation passed.\n";