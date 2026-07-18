<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'personal_page' => $root . '/agent.php',
    'subscriptions_page' => $root . '/account-subscriptions.php',
    'personal_workspace' => $root . '/includes/agent-workspace.php',
    'personal_dashboard' => $root . '/includes/personal-agent/workspace-dashboard.php',
    'shared_sidebar' => $root . '/includes/personal-agent-sidebar.php',
    'quick_catalog' => $root . '/includes/agent-quick-actions.php',
    'merchant_page' => $root . '/merchant-agent-chat.php',
    'merchant_view' => $root . '/includes/merchant-agent-chat-view.php',
    'merchant_api' => $root . '/api/ai/merchant-agent-chat.php',
    'snapshot_service' => $root . '/includes/ai/merchant-agent-snapshot.php',
    'personal_handoff' => $root . '/assets/js/agent-merchant-handoff.js',
    'merchant_receiver' => $root . '/assets/js/merchant-agent-handoff-receiver.js',
    'merchant_history' => $root . '/assets/js/merchant-agent-sidebar-history.js',
    'merchant_drawer' => $root . '/assets/js/merchant-agent-chat-mobile.js',
    'merchant_chat' => $root . '/assets/js/merchant-agent-chat.js',
    'sidebar_tools_js' => $root . '/assets/js/agent-sidebar-tools.js',
    'history_css' => $root . '/assets/css/personal-agent-chat-history.css',
    'personal_canvas_css' => $root . '/assets/css/personal-agent-full-canvas.css',
    'workspace_css' => $root . '/assets/css/merchant-agent-integrated-workspace.css',
    'compat_css' => $root . '/assets/css/merchant-agent-integrated-compat.css',
    'snapshot_css' => $root . '/assets/css/merchant-agent-snapshot.css',
];

$content = [];
foreach ($paths as $key => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing required file: {$path}\n");
        exit(1);
    }
    $content[$key] = (string) file_get_contents($path);
}

$footerPosition = strpos($content['shared_sidebar'], 'class="mg-personal-chat-sidebar-footer"');
$modeSwitchPosition = strpos($content['shared_sidebar'], 'class="mg-agent-footer-mode-switch"');
$merchantRedirectPosition = strpos($content['merchant_page'], "header('Location: /account-subscriptions.php?agent=merchant')");
$merchantHeaderPosition = strpos($content['merchant_page'], "require __DIR__ . '/includes/header.php'");

$checks = [
    'Personal Agent loads full-canvas, footer tools, compatibility, and merchant handoff assets' =>
        str_contains($content['personal_page'], '/assets/css/personal-agent-full-canvas.css?v=1.0.0')
        && str_contains($content['personal_page'], '/assets/css/personal-agent-chat-history.css?v=1.4.0')
        && str_contains($content['personal_page'], '/assets/css/agent-mode-switch.css?v=1.0.0')
        && str_contains($content['personal_page'], '/assets/js/agent-merchant-handoff.js?v=1.0.0')
        && str_contains($content['shared_sidebar'], '/assets/js/agent-sidebar-tools.js?v=1.0.0'),
    'Personal chat section owns the canvas without a nested chat-stream container' =>
        str_contains($content['personal_dashboard'], 'mg-personal-agent-chat-view mg-personal-agent-chat-stream')
        && !str_contains($content['personal_dashboard'], '<div class="mg-personal-agent-chat-stream">')
        && str_contains($content['personal_canvas_css'], '.mg-personal-agent-chat-view')
        && str_contains($content['personal_canvas_css'], 'width:100%!important')
        && str_contains($content['personal_canvas_css'], 'background:transparent!important'),
    'Agent toggle is removed from the top and rendered inside the sidebar footer' =>
        !str_contains($content['shared_sidebar'], 'class="mg-agent-mode-switch"')
        && !str_contains($content['shared_sidebar'], 'mg-agent-mode-options')
        && $footerPosition !== false
        && $modeSwitchPosition !== false
        && $footerPosition < $modeSwitchPosition
        && str_contains($content['shared_sidebar'], 'data-agent-footer-mode-switch')
        && str_contains($content['shared_sidebar'], 'data-agent-mode-link="personal"')
        && str_contains($content['shared_sidebar'], 'data-agent-mode-link="merchant"')
        && str_contains($content['history_css'], '.mg-agent-footer-mode-switch'),
    'sidebar footer replaces informational copy with Suggestions and two tabbed sections' =>
        !str_contains($content['shared_sidebar'], 'Scoped to your merchant workspace')
        && !str_contains($content['shared_sidebar'], 'Merchant requests use permission-checked business data and approval-first actions.')
        && str_contains($content['shared_sidebar'], 'data-agent-suggestions-open')
        && str_contains($content['shared_sidebar'], 'data-agent-tools-tab="suggestions"')
        && str_contains($content['shared_sidebar'], 'data-agent-tools-tab="keywords"')
        && str_contains($content['shared_sidebar'], 'data-agent-suggestion-prompt')
        && str_contains($content['shared_sidebar'], 'data-agent-keyword-prompt'),
    'expandable quick-action catalog contains current Personal and Merchant commands' =>
        str_contains($content['quick_catalog'], 'function mg_agent_quick_action_catalog')
        && str_contains($content['quick_catalog'], "'keyword'=>'/snapshot'")
        && str_contains($content['quick_catalog'], "'keyword'=>'memory'")
        && str_contains($content['quick_catalog'], "'keyword'=>'contact count'")
        && str_contains($content['quick_catalog'], "'keyword'=>'saved opportunities'")
        && str_contains($content['quick_catalog'], "'keyword'=>'/m'")
        && str_contains($content['quick_catalog'], "'keyword'=>'review queue'"),
    'suggestions run in one click while keywords remain editable before sending' =>
        str_contains($content['sidebar_tools_js'], "event.target.closest('[data-agent-suggestion-prompt]')")
        && str_contains($content['sidebar_tools_js'], "event.target.closest('[data-agent-keyword-prompt]')")
        && str_contains($content['sidebar_tools_js'], 'suggestion.getAttribute')
        && str_contains($content['sidebar_tools_js'], "true,\n        true")
        && str_contains($content['sidebar_tools_js'], "false,\n        true")
        && str_contains($content['sidebar_tools_js'], 'form.requestSubmit()')
        && str_contains($content['sidebar_tools_js'], 'sessionStorage.setItem'),
    'free accounts can open Personal Agent systematic flows while Merchant Agent remains entitlement-gated' =>
        !str_contains($content['personal_page'], "header('Location: /account-subscriptions.php?agent=personal')")
        && str_contains($content['personal_page'], "\$header_mode = 'agent'")
        && $merchantRedirectPosition !== false
        && $merchantHeaderPosition !== false
        && $merchantRedirectPosition < $merchantHeaderPosition
        && str_contains($content['shared_sidebar'], '/account-subscriptions.php?agent=personal')
        && str_contains($content['shared_sidebar'], '/account-subscriptions.php?agent=merchant')
        && str_contains($content['sidebar_tools_js'], 'data-agent-tools-entitled')
        && str_contains($content['sidebar_tools_js'], 'subscriptionsUrl'),
    'subscription page uses the universal Inbox and Agent sidebar' =>
        str_contains($content['subscriptions_page'], "\$agent_sidebar_mode='subscriptions'")
        && str_contains($content['subscriptions_page'], "require __DIR__ . '/includes/personal-agent-sidebar.php'")
        && !str_contains($content['subscriptions_page'], "require __DIR__ . '/includes/agent-sidebar.php'")
        && str_contains($content['subscriptions_page'], '/assets/css/personal-agent-chat-history.css?v=1.4.0')
        && str_contains($content['subscriptions_page'], '/assets/js/personal-agent-chat-history.js?v=1.1.0'),
    'Personal Agent exposes merchant access for slash handoff' =>
        str_contains($content['personal_workspace'], 'data-merchant-agent-access=')
        && str_contains($content['personal_workspace'], 'type /m followed by a merchant request'),
    'slash merchant parsing is scoped only to the Personal Agent composer' =>
        str_contains($content['personal_handoff'], 'data-personal-gifting-agent')
        && str_contains($content['personal_handoff'], 'data-personal-agent-composer')
        && str_contains($content['personal_handoff'], '/(?:m|merchant)')
        && !str_contains($content['personal_handoff'], 'data-merchant-agent-chat'),
    'merchant handoff prompt uses short-lived session state instead of URL data' =>
        str_contains($content['personal_handoff'], 'sessionStorage.setItem')
        && str_contains($content['merchant_receiver'], 'sessionStorage.getItem')
        && str_contains($content['merchant_receiver'], 'sessionStorage.removeItem')
        && !str_contains($content['personal_handoff'], "searchParams.set('prompt'")
        && !str_contains($content['merchant_receiver'], "params.get('prompt')"),
    'Merchant Agent receives transferred prompts without implementing slash commands' =>
        str_contains($content['merchant_receiver'], 'data-merchant-agent-chat')
        && str_contains($content['merchant_receiver'], "source !== 'personal-agent'")
        && str_contains($content['merchant_receiver'], 'form.requestSubmit()')
        && !str_contains($content['merchant_receiver'], 'merchantCommand')
        && !str_contains($content['merchant_receiver'], '/(?:m|merchant)'),
    'Merchant Agent uses the same Agent header and Inbox sidebar shell' =>
        str_contains($content['merchant_page'], "\$page_section = 'agent'")
        && str_contains($content['merchant_page'], "\$header_mode = 'agent'")
        && str_contains($content['merchant_page'], "\$agent_tab = 'agent'")
        && str_contains($content['merchant_page'], "require __DIR__ . '/includes/personal-agent-sidebar.php'")
        && !str_contains($content['merchant_page'], "require __DIR__ . '/includes/agent-sidebar.php'"),
    'Merchant Agent page gates access with merchant entitlements and direct owner scope while AI status remains separate' =>
        $merchantRedirectPosition !== false
        && str_contains($content['merchant_page'], 'mg_merchant_agent_owner_context')
        && str_contains($content['merchant_page'], "\$merchantAgentAllowed = \$hasMerchantAccess && \$isMerchantOwner")
        && !str_contains($content['merchant_page'], '$hasMerchantPlanPermission')
        && !str_contains($content['merchant_page'], '$hasMerchantReviewPermission')
        && str_contains($content['merchant_page'], 'data-merchant-agent-access='),
    'Merchant Agent matches the Personal Agent chat-first canvas' =>
        str_contains($content['merchant_view'], 'mg-merchant-agent-main')
        && str_contains($content['merchant_view'], 'mg-merchant-agent-chat-stream')
        && str_contains($content['merchant_view'], 'mg-merchant-agent-composer')
        && str_contains($content['workspace_css'], 'height:calc(100svh - var(--mg-app-header))')
        && str_contains($content['workspace_css'], 'bottom:16px!important'),
    'Merchant Agent controls remain an integrated drawer' =>
        str_contains($content['merchant_view'], 'data-agent-chat-drawer-open')
        && str_contains($content['merchant_view'], 'data-agent-chat-drawer')
        && str_contains($content['merchant_drawer'], 'var shouldOpen = !!isOpen')
        && str_contains($content['workspace_css'], 'transform:translateX(105%)'),
    'Merchant chat threads populate the shared sidebar' =>
        str_contains($content['merchant_history'], 'data-merchant-agent-thread-groups')
        && str_contains($content['merchant_history'], 'data-agent-thread-select')
        && str_contains($content['merchant_history'], 'data-merchant-agent-open-thread')
        && str_contains($content['merchant_history'], 'data-merchant-agent-new-chat'),
    'snapshot keyword routes to a database-only service before external AI' =>
        str_contains($content['merchant_api'], "require_once dirname(__DIR__, 2) . '/includes/ai/merchant-agent-snapshot.php'")
        && str_contains($content['merchant_api'], "\$action === 'snapshot'")
        && str_contains($content['merchant_api'], 'mg_merchant_snapshot_is_keyword')
        && str_contains($content['merchant_api'], 'mg_merchant_snapshot_chat_response')
        && strpos($content['merchant_api'], 'mg_merchant_snapshot_chat_response') < strpos($content['merchant_api'], 'mg_ai_chat_send_with_memory'),
    'snapshot service aggregates merchant data without external AI or customer details' =>
        str_contains($content['snapshot_service'], 'pppm_issuance_requests')
        && str_contains($content['snapshot_service'], 'social_follows')
        && str_contains($content['snapshot_service'], 'feed_post_comments')
        && str_contains($content['snapshot_service'], 'campaign_contacts')
        && str_contains($content['snapshot_service'], 'merchant_crm_contacts')
        && str_contains($content['snapshot_service'], "'external_ai_called' => false")
        && str_contains($content['snapshot_service'], "'customer_details_included' => false")
        && str_contains($content['snapshot_service'], "'model' => 'database-snapshot-v1'"),
    'snapshot runtime shows a visible query animation and database-only result status' =>
        str_contains($content['merchant_chat'], 'function isSnapshotKeyword')
        && str_contains($content['merchant_chat'], "action: snapshotRequest ? 'snapshot' : 'send_message'")
        && str_contains($content['merchant_chat'], 'mg-agent-snapshot-thinking')
        && str_contains($content['merchant_chat'], 'Promise.all([request, delay(650)])')
        && str_contains($content['snapshot_css'], '@keyframes mgMerchantSnapshotPulse'),
    'Merchant Agent loads current contact-aware chat, snapshot, and footer assets' =>
        str_contains($content['merchant_page'], '/assets/css/merchant-agent-snapshot.css?v=1.0.0')
        && str_contains($content['merchant_page'], '/assets/js/merchant-agent-chat.js?v=2.4.0')
        && str_contains($content['merchant_page'], '/assets/js/merchant-agent-contact-action-center.js?v=1.0.0')
        && str_contains($content['merchant_page'], '/assets/css/personal-agent-chat-history.css?v=1.4.0'),
    'merchant requests remain protected by owner, data-permission, CSRF, and workspace boundaries' =>
        str_contains($content['merchant_api'], 'mg_require_csrf_for_write($input)')
        && str_contains($content['merchant_api'], 'mg_merchant_agent_require_owner_access($pdo)')
        && str_contains($content['merchant_api'], 'mg_merchant_agent_require_owner_permission($user,')
        && str_contains($content['merchant_api'], 'mg_merchant_ensure_workspace($pdo, $user)'),
    'Personal and Merchant Agent data boundaries remain visibly separate' =>
        str_contains($content['merchant_view'], 'Business data only')
        && str_contains($content['merchant_view'], 'Approval-first actions')
        && str_contains($content['quick_catalog'], "'title' => 'Personal Agent'")
        && str_contains($content['quick_catalog'], "'title' => 'Merchant Agent'"),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}

if ($failed !== []) {
    fwrite(STDERR, "\nIntegrated Agent workspace validation failed: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

$total = count($checks);
echo "\nIntegrated Agent workspace contract: {$total}/{$total}.\n";
