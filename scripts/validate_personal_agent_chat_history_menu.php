<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;
$read = static function (string $path) use ($root): string {
    $file = $root . '/' . ltrim($path, '/');
    if (!is_file($file)) throw new RuntimeException('Missing required file: ' . $path);
    $content = file_get_contents($file);
    if (!is_string($content)) throw new RuntimeException('Unable to read required file: ' . $path);
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
    $page = $read('agent.php');
    $workspace = $read('includes/agent-workspace.php');
    $sidebar = $read('includes/personal-agent-sidebar.php');
    $dialogs = $read('includes/personal-agent/workspace-dialogs.php');
    $loader = $read('includes/personal-gifting-agent.php');
    $service = $read('includes/personal-agent/threads.php');
    $marketplaceCards = $read('includes/personal-agent/marketplace-result-cards.php');
    $marketplaceResponse = $read('includes/personal-agent/marketplace-response.php');
    $api = $read('api/user-agent/threads.php');
    $chatApi = $read('api/user-agent/chat.php');
    $js = $read('assets/js/personal-agent-chat-history.js');
    $css = $read('assets/css/personal-agent-chat-history.css');
    $schema = $read('database/20260714_personal_gifting_agent_phase2.sql');

    $plusPos = strpos($workspace, 'data-open-agent-dialog="menu"');
    $textareaPos = strpos($workspace, '<textarea');
    $expect($plusPos !== false && $textareaPos !== false && $plusPos < $textareaPos, 'Plus menu trigger sits left of the composer input');
    $expect(str_contains($dialogs, 'data-personal-agent-dialog="menu"') && str_contains($dialogs, 'mg-personal-agent-menu-grid'), 'Plus trigger opens the Agent menu modal');

    $labels = ['Home','Contacts','Birthdays','Gift Calendar','Draft Plans','Scheduled Gifts','Recurring Programs','Reminders','Group Gifting','Recipient Requests','Gift Bundles','Claim & Redemption','Agent Memory','Settings','Inbox','Sent','Claimed','Loyalty Cards','Training Lab'];
    $menuCoverage = true;
    foreach ($labels as $label) $menuCoverage = $menuCoverage && str_contains($dialogs, "'label'=>'{$label}'");
    $expect($menuCoverage, 'Former sidebar destinations are all available in the menu modal');

    $expect(str_contains($sidebar, 'data-personal-agent-thread-groups') && substr_count($sidebar, 'data-personal-agent-new-chat') >= 2, 'Sidebar is dedicated to chat history and new chat');
    $expect(!str_contains($sidebar, '$appSidebarNav') && !str_contains($sidebar, "'label' => 'Home'"), 'Old menu navigation is removed from the sidebar');
    $expect(str_contains($css, '.mg-personal-chat-row.is-active') && str_contains($css, '.mg-personal-chat-delete'), 'Active and delete chat controls are styled');
    $expect(str_contains($css, 'grid-template-columns:48px minmax(0,1fr) 90px'), 'Composer reserves internal columns for plus, input, and send');

    $expect(str_contains($loader, "require_once __DIR__ . '/personal-agent/threads.php';"), 'Thread service is loaded by the Personal Agent runtime');
    $expect(str_contains($service, 'function mg_personal_agent_threads') && str_contains($service, 'function mg_personal_agent_thread_detail') && str_contains($service, 'function mg_personal_agent_create_thread') && str_contains($service, 'function mg_personal_agent_delete_thread'), 'Thread list, load, create, and delete services exist');
    $expect(str_contains($service, 'WHERE t.owner_user_id=?') && str_contains($service, 'WHERE owner_user_id=? AND public_id=?') && str_contains($service, 'DELETE FROM user_agent_threads WHERE owner_user_id=?'), 'Every thread operation is owner scoped');
    $expect(str_contains($schema, 'CONSTRAINT fk_user_agent_messages_thread FOREIGN KEY (thread_id) REFERENCES user_agent_threads(id) ON DELETE CASCADE'), 'Deleting a chat cascades its messages');
    $expect(str_contains($api, 'mg_require_api_user') && str_contains($api, 'mg_require_csrf_for_write') && str_contains($api, "'delete' => mg_personal_agent_delete_thread"), 'Thread writes require authentication and CSRF');

    $expect(str_contains($service, 'mg_personal_agent_thread_title_from_message') && str_contains($service, "'[private detail]'") && str_contains($service, "'••••'"), 'Automatic chat labels redact common private details');
    $threadTitleChain = str_contains($chatApi, 'mg_personal_agent_chat_with_thread_title')
        || (str_contains($chatApi, 'mg_personal_agent_chat_with_marketplace_cards') && str_contains($marketplaceCards, 'mg_personal_agent_chat_with_thread_title($pdo,$userId,$input)'))
        || (str_contains($chatApi, 'mg_personal_agent_chat_with_marketplace_response')
            && str_contains($marketplaceResponse, 'mg_personal_agent_chat_with_marketplace_cards($pdo,$userId,$input)')
            && str_contains($marketplaceCards, 'mg_personal_agent_chat_with_thread_title($pdo,$userId,$input)'));
    $expect($threadTitleChain, 'First user message assigns the chat title');
    $expect(str_contains($js, "return 'Today'") && str_contains($js, "return 'Yesterday'") && str_contains($js, "return 'Previous 7 days'"), 'Chats are grouped and labeled by date');
    $expect(str_contains($js, "new URLSearchParams(window.location.search).get('thread')") && str_contains($js, 'loadThread(selected.id'), 'Active chat is restored from the URL or latest history');
    $expect(str_contains($js, "Microgifter.post('/api/user-agent/threads.php', { action: 'create' }") && str_contains($js, "action: 'delete'"), 'Client creates and deletes chats through the thread API');
    $expect(str_contains($js, "window.confirm('Delete \"'") && str_contains($js, "'\" and all of its messages?')"), 'Chat deletion requires explicit user confirmation');
    $expect(str_contains($page, '/assets/css/personal-agent-chat-history.css?v=1.0.0') && str_contains($page, '/assets/js/personal-agent-chat-history.js?v=1.0.0'), 'Chat history assets are loaded with cache versions');
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Personal Agent chat history validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}

echo "Personal Agent chat history validation passed: {$passes} checks.\n";
