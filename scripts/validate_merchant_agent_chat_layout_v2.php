<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$page = file_get_contents($root . '/merchant-agent-chat.php') ?: '';
$view = file_get_contents($root . '/includes/merchant-agent-chat-view.php') ?: '';
$css = file_get_contents($root . '/assets/css/merchant-agent-integrated-workspace.css') ?: '';
$compatCss = file_get_contents($root . '/assets/css/merchant-agent-integrated-compat.css') ?: '';
$js = file_get_contents($root . '/assets/js/merchant-agent-chat-mobile.js') ?: '';
$chatJs = file_get_contents($root . '/assets/js/merchant-agent-chat.js') ?: '';
$sidebar = file_get_contents($root . '/includes/personal-agent-sidebar.php') ?: '';

$checks = [
    'page loads the integrated Agent layout assets' =>
        str_contains($page, 'merchant-agent-integrated-workspace.css?v=1.0.0')
        && str_contains($page, 'merchant-agent-integrated-compat.css?v=1.0.0')
        && !str_contains($page, 'merchant-agent-chat-layout-v2.css'),
    'legacy competing layout files remain removed from page' =>
        !str_contains($page, 'merchant-agent-chat-mobile.css')
        && !str_contains($page, 'merchant-agent-chat-cleanup.css')
        && !str_contains($page, 'merchant-agent-chat-flat-layout.css')
        && !str_contains($page, 'merchant-agent-chat-desktop.css')
        && !str_contains($page, 'merchant-agent-chat-mobile-offset.css'),
    'creative presets module remains removed from page assets' =>
        !str_contains($page, 'merchant-agent-creative-presets.css')
        && !str_contains($page, 'merchant-agent-creative-presets.js')
        && !str_contains($page, 'data-agent-creative-presets'),
    'page uses the main Agent header and dedicated integrated body scope' =>
        str_contains($page, "\$page_body_class = 'mg-integrated-merchant-agent-page'")
        && str_contains($page, "\$page_section = 'agent'")
        && str_contains($page, "\$header_mode = 'agent'")
        && str_contains($page, "\$agent_tab = 'agent'"),
    'page uses the shared Personal Agent app shell geometry' =>
        str_contains($page, 'mg-agent-app mg-personal-agent-app mg-merchant-agent-integrated-app')
        && !str_contains($page, 'mg-agent-chat-layout-v2')
        && !str_contains($page, 'mg-agent-chat-app-no-nav'),
    'main Inbox sidebar replaces the separate merchant navigation sidebar' =>
        str_contains($page, 'includes/personal-agent-sidebar.php')
        && !str_contains($page, 'includes/agent-sidebar.php')
        && str_contains($sidebar, 'data-agent-sidebar-mode=')
        && str_contains($sidebar, 'data-merchant-agent-thread-groups'),
    'dedicated controls trigger exists in the main chat introduction' =>
        str_contains($view, 'data-agent-chat-drawer-open')
        && str_contains($view, 'aria-controls="agent-chat-drawer"')
        && str_contains($view, 'mg-merchant-agent-controls-button'),
    'drawer has an explicit heading and close control' =>
        str_contains($view, '<strong>Merchant Agent controls</strong>')
        && str_contains($view, 'data-agent-chat-drawer-close'),
    'workspace is locked to the Agent viewport without duplicate offsets' =>
        str_contains($css, 'height:calc(100svh - var(--mg-app-header))!important')
        && str_contains($css, 'overflow:hidden!important')
        && str_contains($css, 'height:calc(100dvh - var(--mg-mobile-topbar,72px))!important')
        && !str_contains($page, '--mg-app-header:58px'),
    'conversation canvas owns vertical scrolling' =>
        str_contains($css, '.mg-merchant-agent-main{position:absolute!important')
        && str_contains($css, 'overflow-y:auto!important')
        && str_contains($css, 'overscroll-behavior:contain')
        && str_contains($css, 'scrollbar-gutter:stable'),
    'composer floats above the canvas like the Personal Agent composer' =>
        str_contains($css, '.mg-merchant-agent-composer{position:absolute!important')
        && str_contains($css, 'bottom:16px!important')
        && str_contains($css, 'z-index:120!important')
        && str_contains($css, 'box-shadow:0 20px 55px'),
    'status remains separate and empty status creates no forced row' =>
        str_contains($view, 'mg-agent-chat-status-wrap')
        && str_contains($view, 'data-agent-chat-status')
        && str_contains($css, '.mg-form-status[data-agent-chat-status]:not(:empty)'),
    'main canvas suggestions remain available' =>
        str_contains($chatJs, 'function promptButtons()')
        && str_contains($chatJs, 'data-agent-chat-prompts')
        && str_contains($css, '.mg-agent-chat-prompts')
        && str_contains($css, 'flex-wrap:wrap!important'),
    'legacy suggestion pseudo-elements are neutralized' =>
        str_contains($compatCss, '.mg-agent-chat-prompts button:before')
        && str_contains($compatCss, 'display:none!important')
        && str_contains($compatCss, 'content:none!important'),
    'mobile composer remains a single compact row' =>
        str_contains($css, 'grid-template-columns:42px minmax(0,1fr) 46px 46px')
        && str_contains($css, '@media(max-width:480px)')
        && str_contains($css, 'grid-template-columns:40px minmax(0,1fr) 42px'),
    'Agent controls drawer works on desktop and mobile' =>
        str_contains($css, '.mg-agent-chat-right{position:fixed')
        && str_contains($css, 'transform:translateX(105%)')
        && str_contains($css, '.is-drawer-open .mg-agent-chat-right')
        && str_contains($js, 'var shouldOpen = !!isOpen'),
    'global hamburger remains untouched' =>
        !str_contains($js, 'data-app-sidebar-toggle')
        && !str_contains($js, 'data-mobile-menu-toggle')
        && !str_contains($js, 'isMenuTrigger'),
    'dedicated drawer supports keyboard and click controls' =>
        str_contains($js, 'openButton.addEventListener')
        && str_contains($js, "event.key === 'Escape'")
        && str_contains($js, 'data-agent-chat-drawer-close'),
    'chat feature hooks remain intact' =>
        str_contains($view, 'data-agent-chat-feed')
        && str_contains($view, 'data-agent-chat-form')
        && str_contains($view, 'data-agent-chat-voice')
        && str_contains($view, 'data-agent-chat-send')
        && str_contains($view, 'data-agent-chat-scope'),
    'permission-gated runtime mounting remains explicit' =>
        str_contains($page, "mg_has_permission('merchant.ai.plan')")
        && str_contains($page, "mg_has_permission('merchant.ai.review')")
        && str_contains($page, "\$merchantAgentAllowed ? ' data-merchant-agent-chat' : ''"),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}

$score = round((count($checks) - count($failed)) / count($checks) * 10, 1);
echo 'Merchant agent chat layout score: ' . number_format($score, 1) . '/10' . PHP_EOL;

if ($failed !== []) {
    fwrite(STDERR, 'Merchant agent chat layout validation failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "Merchant agent integrated chat layout contract passed at 10.0/10.\n";
