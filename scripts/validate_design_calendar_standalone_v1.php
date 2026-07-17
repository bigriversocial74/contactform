<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (string) file_get_contents($root . '/' . $path) : '';

$designPage = $read('design-studio.php');
$calendarPage = $read('design-calendar.php');
$standaloneHeader = $read('includes/standalone-creative-header.php');
$standaloneFooter = $read('includes/standalone-creative-footer.php');
$sidebar = $read('includes/personal-agent-sidebar.php');
$designView = $read('includes/personal-agent/workspace-design.php');
$calendarView = $read('includes/personal-agent/workspace-design-calendar.php');
$standaloneCss = $read('assets/css/design-studio-standalone.css');
$runtimeCss = $read('assets/css/design-studio-runtime-fix.css');
$calendarCss = $read('assets/css/design-calendar-standalone.css');
$templateJs = $read('assets/js/design-studio-template-variants.js');
$scheduleContextJs = $read('assets/js/design-studio-schedule-context.js');
$calendarModalJs = $read('assets/js/design-calendar-modal.js');

$checks = [
    'Design Studio is an authenticated independent page' => str_contains($designPage, "mg_require_auth('/signin.php', '/design-studio.php')") && !str_contains($designPage, "header('Location: /agent.php?view=design'") && str_contains($designPage, "require __DIR__ . '/includes/personal-agent-sidebar.php'") && str_contains($designPage, "require __DIR__ . '/includes/personal-agent/workspace-design.php'"),
    'Design Calendar is an authenticated independent page' => str_contains($calendarPage, "mg_require_auth('/signin.php', '/design-calendar.php')") && str_contains($calendarPage, "require __DIR__ . '/includes/personal-agent-sidebar.php'") && str_contains($calendarPage, "require __DIR__ . '/includes/personal-agent/workspace-design-calendar.php'"),
    'Both pages keep the main sidebar' => str_contains($designPage, 'mg-app-shell mg-standalone-creative-shell') && str_contains($calendarPage, 'mg-app-shell mg-standalone-creative-shell') && str_contains($designPage, "require __DIR__ . '/includes/personal-agent-sidebar.php'") && str_contains($calendarPage, "require __DIR__ . '/includes/personal-agent-sidebar.php'"),
    'Main sidebar includes separate Design and Calendar destinations' => str_contains($sidebar, "'design-calendar.php' => 'calendar'") && str_contains($sidebar, 'href="/design-studio.php"') && str_contains($sidebar, 'href="/design-calendar.php"') && str_contains($sidebar, '<strong>Calendar</strong>'),
    'Standalone pages render no shared or local header bar' => str_contains($designPage, "require __DIR__ . '/includes/standalone-creative-header.php'") && str_contains($calendarPage, "require __DIR__ . '/includes/standalone-creative-header.php'") && !str_contains($designPage, "require __DIR__ . '/includes/header.php'") && !str_contains($calendarPage, "require __DIR__ . '/includes/header.php'") && !str_contains($standaloneHeader, 'header-components/app-header.php') && !str_contains($designPage, 'mg-standalone-creative-bar') && !str_contains($calendarPage, 'mg-standalone-creative-bar'),
    'Standalone pages render no site footer' => str_contains($designPage, "require __DIR__ . '/includes/standalone-creative-footer.php'") && str_contains($calendarPage, "require __DIR__ . '/includes/standalone-creative-footer.php'") && !str_contains($standaloneFooter, 'mg-site-footer'),
    'Standalone runtime loads only essential shared scripts plus page scripts' => str_contains($standaloneFooter, '/assets/js/microgifter.js') && str_contains($standaloneFooter, '/assets/js/api-client.js') && str_contains($standaloneFooter, '/assets/js/universal-header.js') && !str_contains($standaloneFooter, 'store-chat-widget.js') && !str_contains($standaloneFooter, 'public-market-ticker.js'),
    'Sidebar footer and sidebar tools are server-side suppressed' => str_contains($designPage, '$suppress_agent_sidebar_footer = true') && str_contains($calendarPage, '$suppress_agent_sidebar_footer = true') && str_contains($designPage, '$suppress_agent_sidebar_tools = true') && str_contains($calendarPage, '$suppress_agent_sidebar_tools = true') && str_contains($sidebar, 'if (!$suppressAgentSidebarFooter)') && str_contains($sidebar, 'if ($user && !$suppressAgentSidebarTools)'),
    'Standalone pages load complete sidebar and runtime CSS' => str_contains($designPage, '/assets/css/personal-agent-chat-history.css?v=1.4.0') && str_contains($calendarPage, '/assets/css/personal-agent-chat-history.css?v=1.4.0') && str_contains($designPage, '/assets/css/design-studio-runtime-fix.css?v=1.0.0') && str_contains($calendarPage, '/assets/css/design-studio-runtime-fix.css?v=1.0.0') && str_contains($standaloneHeader, '/assets/css/app-shell.css') && str_contains($standaloneHeader, '/assets/css/compact-sidebars.css') && str_contains($runtimeCss, '.mg-standalone-sidebar-toggle'),
    'Standalone pages use the remaining full canvas beside the sidebar' => str_contains($standaloneCss, '.mg-standalone-creative-workspace') && str_contains($standaloneCss, 'min-height:100svh') && str_contains($standaloneCss, 'padding:0!important') && str_contains($standaloneCss, '.mg-standalone-creative-canvas'),
    'Print template picker contains four templates' => substr_count($designView, 'data-design-template="') === 4 && str_contains($designView, 'data-design-template="support-local"') && str_contains($designView, 'data-design-template="gift-better"') && str_contains($designView, 'data-design-template="reward-visit"') && str_contains($designView, 'data-design-template="local-favorite"') && str_contains($standaloneCss, 'grid-template-columns:repeat(4,minmax(0,1fr))'),
    'Table tent keeps a bottom green QR banner and larger logo' => str_contains($designView, 'mg-agent-print-qr-band') && str_contains($designView, 'data-design-qr') && str_contains($standaloneCss, '.mg-agent-print-design.is-tent .mg-agent-print-qr-band') && str_contains($standaloneCss, 'background:var(--design-green)') && str_contains($standaloneCss, 'width:124px;height:124px'),
    'Template variants update print copy and visual treatment' => str_contains($templateJs, "'support-local'") && str_contains($templateJs, "'gift-better'") && str_contains($templateJs, "'reward-visit'") && str_contains($templateJs, "'local-favorite'") && str_contains($templateJs, 'headline.innerHTML') && str_contains($templateJs, 'canvas.classList.add'),
    'Calendar is omitted from the Design page when standalone' => str_contains($designView, '$includeDesignCalendar') && str_contains($designPage, '$designStudioIncludeCalendar = false') && str_contains($calendarView, '$isStandaloneCalendar'),
    'Calendar entries have distinct theme styling' => str_contains($calendarCss, '.theme-product_spotlight') && str_contains($calendarCss, '.theme-gift_idea') && str_contains($calendarCss, '.theme-reward_promotion') && str_contains($calendarCss, '.theme-merchant_story') && str_contains($calendarCss, '.theme-customer_review') && str_contains($calendarCss, '.theme-local_support'),
    'Calendar click opens a complete settings modal' => str_contains($calendarModalJs, 'data-calendar-entry-modal') && str_contains($calendarModalJs, 'data-calendar-modal-preview-caption') && str_contains($calendarModalJs, 'name="scheduled_date"') && str_contains($calendarModalJs, 'name="scheduled_time"') && str_contains($calendarModalJs, 'name="campaign_theme"') && str_contains($calendarModalJs, 'name="caption_standard"') && str_contains($calendarModalJs, 'data-modal-platform-copy') && str_contains($calendarModalJs, "action: 'update'"),
    'Calendar card decorator cannot trigger its own mutation loop' => str_contains($calendarModalJs, "openButton.textContent !== 'Preview'") && !str_contains($calendarModalJs, "if (openButton) openButton.textContent = 'Preview'"),
    'Scheduled posts hand off to the standalone social Design Studio' => str_contains($calendarModalJs, "'/design-studio.php?mode=social'") && str_contains($scheduleContextJs, "params.get('mode') !== 'social'") && str_contains($scheduleContextJs, 'data-social-product-select') && str_contains($scheduleContextJs, 'data-social-format') && str_contains($scheduleContextJs, 'data-social-layout'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}
if ($failed !== []) {
    fwrite(STDERR, 'Standalone Design and Calendar validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}
echo 'Standalone Design and Calendar contract: ' . count($checks) . '/' . count($checks) . ".\n";
