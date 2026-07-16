<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'personal_page' => $root . '/agent.php',
    'personal_workspace' => $root . '/includes/agent-workspace.php',
    'shared_sidebar' => $root . '/includes/personal-agent-sidebar.php',
    'merchant_page' => $root . '/merchant-agent-chat.php',
    'merchant_view' => $root . '/includes/merchant-agent-chat-view.php',
    'merchant_api' => $root . '/api/ai/merchant-agent-chat.php',
    'personal_handoff' => $root . '/assets/js/agent-merchant-handoff.js',
    'merchant_receiver' => $root . '/assets/js/merchant-agent-handoff-receiver.js',
    'merchant_history' => $root . '/assets/js/merchant-agent-sidebar-history.js',
    'merchant_drawer' => $root . '/assets/js/merchant-agent-chat-mobile.js',
    'mode_css' => $root . '/assets/css/agent-mode-switch.css',
    'workspace_css' => $root . '/assets/css/merchant-agent-integrated-workspace.css',
    'compat_css' => $root . '/assets/css/merchant-agent-integrated-compat.css',
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
    'Personal Agent loads the mode switch and slash merchant router' =>
        str_contains($content['personal_page'], '/assets/css/agent-mode-switch.css?v=1.0.0')
        && str_contains($content['personal_page'], '/assets/js/agent-merchant-handoff.js?v=1.0.0'),
    'Personal Agent exposes merchant access and explains the slash command' =>
        str_contains($content['personal_workspace'], 'data-merchant-agent-access=')
        && str_contains($content['personal_workspace'], 'type /m followed by a merchant request'),
    'slash merchant parsing is scoped only to the Personal Agent composer' =>
        str_contains($content['personal_handoff'], 'data-personal-gifting-agent')
        && str_contains($content['personal_handoff'], 'data-personal-agent-composer')
        && str_contains($content['personal_handoff'], '/(?:m|merchant)')
        && !str_contains($content['personal_handoff'], 'data-merchant-agent-chat'),
    'Merchant Agent receives transferred prompts without implementing slash commands' =>
        str_contains($content['merchant_receiver'], 'data-merchant-agent-chat')
        && str_contains($content['merchant_receiver'], "source !== 'personal-agent'")
        && str_contains($content['merchant_receiver'], 'form.requestSubmit()')
        && !str_contains($content['merchant_receiver'], 'merchantCommand')
        && !str_contains($content['merchant_receiver'], '/(?:m|merchant)'),
    'shared sidebar supports Personal and Merchant Agent modes' =>
        str_contains($content['shared_sidebar'], 'data-agent-sidebar-mode=')
        && str_contains($content['shared_sidebar'], 'data-merchant-agent-thread-groups')
        && str_contains($content['shared_sidebar'], 'data-merchant-agent-new-chat')
        && str_contains($content['shared_sidebar'], "if (!\$isMerchantAgentMode): ?><kbd>/m</kbd>")
        && str_contains($content['shared_sidebar'], '/merchant-agent-chat.php'),
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
        && str_contains($content['merchant_view'], 'mg-merchant-agent-intro')
        && str_contains($content['merchant_view'], 'mg-merchant-agent-composer')
        && str_contains($content['workspace_css'], 'height:calc(100svh - var(--mg-app-header))')
        && str_contains($content['workspace_css'], 'position:absolute!important')
        && str_contains($content['workspace_css'], 'bottom:16px!important'),
    'Merchant Agent controls are an integrated drawer instead of a permanent third column' =>
        str_contains($content['merchant_view'], 'data-agent-chat-drawer-open')
        && str_contains($content['merchant_view'], 'data-agent-chat-drawer')
        && str_contains($content['merchant_drawer'], 'var shouldOpen = !!isOpen')
        && str_contains($content['workspace_css'], 'transform:translateX(105%)')
        && str_contains($content['workspace_css'], '.is-drawer-open .mg-agent-chat-right'),
    'Merchant chat threads populate the shared sidebar' =>
        str_contains($content['merchant_history'], 'data-merchant-agent-thread-groups')
        && str_contains($content['merchant_history'], 'data-agent-thread-select')
        && str_contains($content['merchant_history'], 'data-merchant-agent-open-thread')
        && str_contains($content['merchant_history'], 'data-merchant-agent-new-chat'),
    'legacy Merchant Agent layout rules are neutralized after the integrated stylesheet' =>
        str_contains($content['merchant_page'], '/assets/css/merchant-agent-integrated-workspace.css?v=1.0.0')
        && str_contains($content['merchant_page'], '/assets/css/merchant-agent-integrated-compat.css?v=1.0.0')
        && str_contains($content['compat_css'], '.mg-agent-chat-prompts button:before')
        && str_contains($content['compat_css'], '.mg-agent-chat-right'),
    'merchant requests remain protected by the existing API permission boundary' =>
        str_contains($content['merchant_api'], "mg_merchant_require_permission('merchant.ai.review')")
        && str_contains($content['merchant_api'], "mg_merchant_require_permission(\$action === 'send_message' ? 'merchant.ai.plan' : 'merchant.ai.review')")
        && str_contains($content['merchant_api'], 'mg_require_csrf_for_write($input)'),
    'Personal and Merchant Agent data boundaries remain visibly separate' =>
        str_contains($content['merchant_view'], 'Business data only')
        && str_contains($content['merchant_view'], 'Approval-first actions')
        && str_contains($content['shared_sidebar'], 'Scoped to your merchant workspace')
        && str_contains($content['shared_sidebar'], 'Private to your account'),
    'shared mode switch receives dedicated responsive styling' =>
        str_contains($content['mode_css'], '.mg-agent-mode-switch')
        && str_contains($content['mode_css'], '.mg-agent-mode-option.is-active')
        && str_contains($content['mode_css'], '@media(max-width:980px)'),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}

if ($failed !== []) {
    fwrite(STDERR, "\nIntegrated Merchant Agent workspace validation failed: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

$total = count($checks);
echo "\nIntegrated Merchant Agent workspace contract: {$total}/{$total}.\n";
