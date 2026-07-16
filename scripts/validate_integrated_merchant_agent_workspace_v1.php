<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'personal_page' => $root . '/agent.php',
    'personal_workspace' => $root . '/includes/agent-workspace.php',
    'personal_dashboard' => $root . '/includes/personal-agent/workspace-dashboard.php',
    'shared_sidebar' => $root . '/includes/personal-agent-sidebar.php',
    'merchant_page' => $root . '/merchant-agent-chat.php',
    'merchant_view' => $root . '/includes/merchant-agent-chat-view.php',
    'merchant_api' => $root . '/api/ai/merchant-agent-chat.php',
    'snapshot_service' => $root . '/includes/ai/merchant-agent-snapshot.php',
    'personal_handoff' => $root . '/assets/js/agent-merchant-handoff.js',
    'merchant_receiver' => $root . '/assets/js/merchant-agent-handoff-receiver.js',
    'merchant_history' => $root . '/assets/js/merchant-agent-sidebar-history.js',
    'merchant_drawer' => $root . '/assets/js/merchant-agent-chat-mobile.js',
    'merchant_chat' => $root . '/assets/js/merchant-agent-chat.js',
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

$checks = [
    'Personal Agent loads full-canvas, compact-sidebar, compatibility, and merchant handoff assets' =>
        str_contains($content['personal_page'], '/assets/css/personal-agent-full-canvas.css?v=1.0.0')
        && str_contains($content['personal_page'], '/assets/css/personal-agent-chat-history.css?v=1.3.0')
        && str_contains($content['personal_page'], '/assets/css/agent-mode-switch.css?v=1.0.0')
        && str_contains($content['personal_page'], '/assets/js/agent-merchant-handoff.js?v=1.0.0'),
    'Personal chat section owns the canvas without a nested chat-stream container' =>
        str_contains($content['personal_dashboard'], 'mg-personal-agent-chat-view mg-personal-agent-chat-stream')
        && !str_contains($content['personal_dashboard'], '<div class="mg-personal-agent-chat-stream">')
        && str_contains($content['personal_canvas_css'], '.mg-personal-agent-chat-view')
        && str_contains($content['personal_canvas_css'], 'width:100%!important')
        && str_contains($content['personal_canvas_css'], 'background:transparent!important'),
    'large Agent mode cards are removed and replaced by one compact switch row' =>
        !str_contains($content['shared_sidebar'], 'class="mg-agent-mode-switch"')
        && !str_contains($content['shared_sidebar'], 'mg-agent-mode-options')
        && str_contains($content['shared_sidebar'], 'mg-agent-sidebar-switch')
        && str_contains($content['shared_sidebar'], 'data-agent-mode-link="personal"')
        && str_contains($content['shared_sidebar'], 'data-agent-mode-link="merchant"')
        && str_contains($content['history_css'], '.mg-agent-sidebar-switch'),
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
    'Merchant Agent page gates access with merchant entitlements and AI permissions' =>
        str_contains($content['merchant_page'], "mg_has_permission('merchant.ai.plan')")
        && str_contains($content['merchant_page'], "mg_has_permission('merchant.ai.review')")
        && str_contains($content['merchant_page'], "mg_workspace_role_allows_permission(\$mg_package_context, 'merchant.ai.plan')")
        && str_contains($content['merchant_page'], "mg_workspace_role_allows_permission(\$mg_package_context, 'merchant.ai.review')")
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
    'Merchant Agent loads cache-busted snapshot assets' =>
        str_contains($content['merchant_page'], '/assets/css/merchant-agent-snapshot.css?v=1.0.0')
        && str_contains($content['merchant_page'], '/assets/js/merchant-agent-chat.js?v=2.3.0')
        && str_contains($content['merchant_page'], '/assets/css/personal-agent-chat-history.css?v=1.3.0'),
    'merchant requests remain protected by the existing permission and CSRF boundary' =>
        str_contains($content['merchant_api'], 'mg_require_csrf_for_write($input)')
        && str_contains($content['merchant_api'], 'mg_merchant_require_permission($permission)')
        && str_contains($content['merchant_api'], 'mg_merchant_ensure_workspace($pdo, $user)'),
    'Personal and Merchant Agent data boundaries remain visibly separate' =>
        str_contains($content['merchant_view'], 'Business data only')
        && str_contains($content['merchant_view'], 'Approval-first actions')
        && str_contains($content['shared_sidebar'], 'Scoped to your merchant workspace')
        && str_contains($content['shared_sidebar'], 'Private to your account'),
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
