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
$agentPage = $read('agent.php');
$agentWorkspace = $read('includes/agent-workspace.php');
$agentSidebar = $read('includes/personal-agent-sidebar.php');
$agentDashboard = $read('includes/personal-agent/workspace-dashboard.php');
$chatCss = $read('assets/css/personal-agent-chat-canvas.css');
$inlineCss = $read('assets/css/personal-agent-inline-intro.css');
$chatJs = $read('assets/js/personal-agent-chat-canvas.js');
$chatActionsJs = $read('assets/js/personal-gifting-agent-actions.js');
$chatApi = $read('api/user-agent/chat.php');
$knowledge = $read('includes/personal-agent/knowledge.php');
$listPage = $read('lists.php');
$listCreateJs = $read('assets/js/user-lists-create.js');
$createListExtension = $read('assets/js/create-list-extension.js');
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
        && str_contains($giftCenter, 'mg-gift-center-my-lists')
        && str_contains($giftCenter, '/lists.php')
        && str_contains($giftCenter, '$use_inbox_sidebar = true'),
    'list management removed from Agent chat navigation' => !str_contains($agentSidebar, "'lists' =>")
        && !str_contains($agentDashboard, 'Manage lists')
        && !str_contains($agentDashboard, 'href="/lists.php"'),
    'detached full width chat canvas' => str_contains($agentDashboard, 'data-agent-canvas')
        && !str_contains($agentDashboard, 'mg-personal-agent-hero')
        && !str_contains($agentDashboard, 'mg-personal-agent-context')
        && str_contains($chatCss, 'position:absolute!important')
        && str_contains($chatCss, 'overflow-y:auto!important')
        && str_contains($chatCss, 'padding:24px 28px 142px!important'),
    'intro greeting carries user stats' => str_contains($agentDashboard, 'Good morning,')
        && str_contains($agentDashboard, 'data-personal-agent-summary')
        && str_contains($agentDashboard, 'mg-personal-agent-message is-assistant is-intro')
        && str_contains($chatJs, 'Good afternoon')
        && str_contains($chatJs, 'Good evening'),
    'intro response is inline transparent and full width' => str_contains($agentPage, 'personal-agent-inline-intro.css')
        && str_contains($inlineCss, '.mg-personal-agent-message.is-intro')
        && str_contains($inlineCss, 'width:100%!important')
        && str_contains($inlineCss, 'background:transparent!important')
        && str_contains($inlineCss, 'border:0!important')
        && str_contains($inlineCss, 'box-shadow:none!important'),
    'composer sends directly and receives an assistant response' => !str_contains($agentDashboard, 'Start a new conversation')
        && !str_contains($agentDashboard, 'data-personal-agent-new-thread')
        && !str_contains($chatJs, 'New personal gifting conversation started.')
        && str_contains($chatActionsJs, "Microgifter.post('/api/user-agent/chat.php'")
        && str_contains($chatActionsJs, 'appendMessage(data.assistant_message)')
        && (str_contains($chatApi, 'mg_personal_agent_chat_v2')
            || str_contains($chatApi, 'mg_personal_agent_chat_with_thread_title')
            || str_contains($chatApi, 'mg_personal_agent_chat_with_marketplace_cards')
            || str_contains($chatApi, 'mg_personal_agent_chat_with_marketplace_response'))
        && str_contains($knowledge, "'assistant_message' => \$assistant"),
    'composer is detached with wide input and compact send' => str_contains($agentWorkspace, 'mg-personal-agent-composer-row')
        && str_contains($chatCss, 'grid-template-columns:minmax(0,1fr) 90px')
        && str_contains($chatCss, 'width:90px!important')
        && str_contains($chatCss, 'bottom:16px!important'),
    'chat canvas assets loaded' => str_contains($agentPage, 'personal-agent-chat-canvas.css')
        && str_contains($agentPage, 'personal-agent-chat-canvas.js')
        && str_contains($agentPage, 'personal-agent-inline-intro.css'),
    'My Lists create buttons are wired' => substr_count($listPage, 'data-user-list-open-create') >= 2
        && str_contains($listPage, 'user-lists-create.js')
        && str_contains($listCreateJs, '[data-user-list-open-create]')
        && str_contains($listCreateJs, '[data-create-center-view]')
        && str_contains($listCreateJs, 'mg-create-menu-open')
        && str_contains($createListExtension, '/api/user-lists/create.php'),
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
