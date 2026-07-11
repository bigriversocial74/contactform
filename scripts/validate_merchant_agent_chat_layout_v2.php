<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$page = file_get_contents($root . '/merchant-agent-chat.php') ?: '';
$view = file_get_contents($root . '/includes/merchant-agent-chat-view.php') ?: '';
$css = file_get_contents($root . '/assets/css/merchant-agent-chat-layout-v2.css') ?: '';
$js = file_get_contents($root . '/assets/js/merchant-agent-chat-mobile.js') ?: '';
$chatJs = file_get_contents($root . '/assets/js/merchant-agent-chat.js') ?: '';

$checks = [
    'page loads canonical layout last' => str_contains($page, "merchant-agent-chat-layout-v2.css?v=2.1.0"),
    'legacy competing layout files removed from page' => !str_contains($page, 'merchant-agent-chat-mobile.css')
        && !str_contains($page, 'merchant-agent-chat-cleanup.css')
        && !str_contains($page, 'merchant-agent-chat-flat-layout.css')
        && !str_contains($page, 'merchant-agent-chat-desktop.css')
        && !str_contains($page, 'merchant-agent-chat-mobile-offset.css'),
    'creative presets module is removed from page assets' => !str_contains($page, 'merchant-agent-creative-presets.css')
        && !str_contains($page, 'merchant-agent-creative-presets.js')
        && !str_contains($page, 'data-agent-creative-presets'),
    'page has a dedicated body scope' => str_contains($page, "\$page_body_class = 'mg-merchant-agent-chat-page'")
        && str_contains($page, 'mg-agent-chat-canvas-layout-v2-3'),
    'page uses canonical app shell class' => str_contains($page, 'mg-agent-chat-layout-v2')
        && !str_contains($page, 'mg-agent-chat-app-no-nav'),
    'normal application sidebar remains present' => str_contains($page, 'includes/agent-sidebar.php')
        && !str_contains($css, '.mg-app-sidebar{display:none'),
    'dedicated controls trigger exists' => str_contains($view, 'data-agent-chat-drawer-open')
        && str_contains($view, 'aria-controls="agent-chat-drawer"')
        && str_contains($view, 'mg-agent-chat-mobile-controls'),
    'drawer has explicit heading and close control' => str_contains($view, '<strong>Agent controls</strong>')
        && str_contains($view, 'data-agent-chat-drawer-close'),
    'mobile header offset is applied exactly once' => str_contains($page, 'html body.mg-app-page.mg-merchant-agent-chat-page .mg-main')
        && str_contains($page, 'padding-top:0!important')
        && str_contains($page, 'html body.mg-app-page.mg-merchant-agent-chat-page .mg-app-shell.mg-agent-chat-layout-v2')
        && str_contains($page, 'padding-top:var(--mg-mobile-topbar,72px)!important')
        && str_contains($page, '--mg-mobile-shell-offset:var(--mg-mobile-topbar,72px)')
        && !str_contains($page, '--mg-app-header:58px'),
    'mobile document is locked to the viewport' => str_contains($page, 'height:100dvh!important')
        && str_contains($page, 'min-height:100dvh!important')
        && str_contains($page, 'body.mg-merchant-agent-chat-page')
        && str_contains($page, 'overflow:hidden!important'),
    'merchant workspace subtracts only the real topbar' => str_contains($page, 'height:calc(100dvh - var(--mg-mobile-topbar,72px))!important')
        && str_contains($page, 'padding:0 8px max(8px,env(safe-area-inset-bottom))!important'),
    'conversation status and composer use explicit three-row contract' => str_contains($page, 'grid-template-rows:minmax(0,1fr) auto auto!important')
        && str_contains($page, 'grid-row:1!important')
        && str_contains($page, 'grid-row:2!important')
        && str_contains($page, 'grid-row:3!important'),
    'composer is a separated pinned footer row' => str_contains($page, 'position:sticky!important')
        && str_contains($page, 'bottom:0!important')
        && str_contains($page, 'align-self:end!important')
        && str_contains($page, 'z-index:80!important')
        && str_contains($page, 'box-shadow:0 -14px 34px'),
    'conversation is the only vertical scroll owner' => str_contains($page, '.mg-agent-chat-layout-v2 .mg-agent-chat-feed')
        && str_contains($page, 'overflow-y:auto!important')
        && str_contains($page, '-webkit-overflow-scrolling:touch!important')
        && str_contains($page, '.mg-agent-chat-layout-v2 .mg-agent-chat-page')
        && str_contains($page, 'overflow:hidden!important'),
    'empty status row does not create footer spacing' => str_contains($page, '.mg-agent-chat-main-stack>.mg-form-status:empty')
        && str_contains($page, 'display:none!important'),
    'main canvas suggestions are restored' => str_contains($chatJs, 'function promptButtons()')
        && str_contains($chatJs, 'data-agent-chat-prompts')
        && str_contains($page, '.mg-agent-chat-layout-v2 .mg-agent-chat-prompts')
        && str_contains($page, 'display:grid!important')
        && str_contains($page, 'grid-template-columns:repeat(2,minmax(0,1fr))!important'),
    'mobile suggestions collapse to one column' => str_contains($page, '@media(max-width:640px)')
        && str_contains($page, 'grid-template-columns:minmax(0,1fr)!important'),
    'mobile composer remains one row' => str_contains($css, 'grid-template-columns:40px minmax(0,1fr) 40px 40px!important')
        && str_contains($css, 'grid-column:auto!important')
        && str_contains($css, 'grid-row:auto!important'),
    'agent controls drawer respects app header' => str_contains($css, 'top:var(--mg-app-header)!important')
        && str_contains($css, 'transform:translateX(105%)!important')
        && str_contains($css, '.mg-agent-chat-page.is-drawer-open .mg-agent-chat-right'),
    'global hamburger is not intercepted' => !str_contains($js, 'data-app-sidebar-toggle')
        && !str_contains($js, 'data-mobile-menu-toggle')
        && !str_contains($js, 'isMenuTrigger'),
    'dedicated drawer supports compact viewports' => str_contains($js, "matchMedia('(max-width: 980px)')")
        && str_contains($js, 'openButton.addEventListener')
        && str_contains($js, "event.key === 'Escape'"),
    'chat feature hooks remain intact' => str_contains($view, 'data-agent-chat-feed')
        && str_contains($view, 'data-agent-chat-form')
        && str_contains($view, 'data-agent-chat-voice')
        && str_contains($view, 'data-agent-chat-send')
        && str_contains($view, 'data-agent-chat-scope'),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) {
        $failed[] = $name;
    }
}

$score = round((count($checks) - count($failed)) / count($checks) * 10, 1);
echo 'Merchant agent chat layout score: ' . number_format($score, 1) . '/10' . PHP_EOL;

if ($failed !== []) {
    fwrite(STDERR, 'Merchant agent chat layout validation failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "Merchant agent chat layout v2.3 contract passed at 10.0/10.\n";
