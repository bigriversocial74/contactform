<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path)
    ? (string) file_get_contents($root . '/' . $path)
    : '';

$page = $read('agent.php');
$workspace = $read('includes/agent-workspace.php');
$sidebar = $read('includes/personal-agent-sidebar.php');
$giftSidebar = $read('includes/gift-center-sidebar.php');
$dialogs = $read('includes/personal-agent/workspace-dialogs.php');
$service = $read('includes/personal-agent/threads.php');
$api = $read('api/user-agent/threads.php');
$js = $read('assets/js/personal-agent-chat-history.js');
$css = $read('assets/css/personal-agent-chat-history.css');
$schema = $read('database/20260714_personal_gifting_agent_phase2.sql');

$checks = [
    'composer menu trigger remains left of input' => strpos($workspace, 'data-open-agent-dialog="menu"') !== false
        && strpos($workspace, 'data-open-agent-dialog="menu"') < strpos($workspace, '<textarea'),
    'Agent menu dialog remains available' => str_contains($dialogs, 'data-personal-agent-dialog="menu"')
        && str_contains($dialogs, 'mg-personal-agent-menu-grid'),
    'unified sidebar contains approved navigation once' => substr_count($sidebar, '<strong>Inbox</strong>') === 1
        && substr_count($sidebar, '<strong>My Feed</strong>') === 1
        && substr_count($sidebar, '<strong>My Loyalty Cards</strong>') === 1
        && substr_count($sidebar, '<strong>My Lists</strong>') === 1
        && substr_count($sidebar, '<strong>New Chat</strong>') === 1
        && substr_count($sidebar, '<strong>Design</strong>') === 1,
    'gift folders consume the unified sidebar' => str_contains($giftSidebar, "require __DIR__ . '/personal-agent-sidebar.php'"),
    'chat groups and actions remain available' => str_contains($sidebar, 'data-personal-agent-thread-groups')
        && str_contains($sidebar, 'data-personal-agent-new-chat')
        && str_contains($sidebar, 'href="/design-studio.php"')
        && str_contains($sidebar, 'mg-personal-chat-divider'),
    'Training Lab is removed from the customer sidebar' => !str_contains($sidebar, 'Training Lab')
        && !str_contains($sidebar, '/training-lab.php'),
    'flat sidebar styling remains' => str_contains($css, '.mg-personal-chat-action{')
        && str_contains($css, 'border:0;border-radius:0;background:transparent')
        && str_contains($css, '.mg-personal-chat-row.is-active{border:0;background:transparent;box-shadow:none}')
        && str_contains($css, '.mg-personal-chat-delete'),
    'thread services remain available' => str_contains($service, 'function mg_personal_agent_threads')
        && str_contains($service, 'function mg_personal_agent_thread_detail')
        && str_contains($service, 'function mg_personal_agent_create_thread')
        && str_contains($service, 'function mg_personal_agent_delete_thread'),
    'thread API retains authenticated writes' => str_contains($api, 'mg_require_api_user')
        && str_contains($api, 'mg_require_csrf_for_write'),
    'message rows cascade with threads' => str_contains($schema, 'ON DELETE CASCADE'),
    'chat history groups by date' => str_contains($js, "return 'Today'")
        && str_contains($js, "return 'Yesterday'")
        && str_contains($js, "return 'Previous 7 days'"),
    'shared sidebar can create and open threads' => str_contains($js, "Microgifter.post('/api/user-agent/threads.php', { action: 'create' }")
        && str_contains($js, "window.location.href = '/agent.php?thread='")
        && str_contains($js, "action: 'delete'"),
    'current cache versions are loaded' => str_contains($page, '/assets/css/personal-agent-chat-history.css?v=1.2.0')
        && str_contains($page, '/assets/js/personal-agent-chat-history.js?v=1.1.0'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, 'Personal Agent chat history validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo "Personal Agent chat history validation passed.\n";
