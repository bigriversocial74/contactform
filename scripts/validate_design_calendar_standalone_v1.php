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
$calendarJs = $read('assets/js/personal-agent-design-studio-calendar.js');
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
    'Calendar removes the main hero and keeps grid and stack views' => !str_contains($calendarView, 'mg-design-calendar-hero') && str_contains($calendarView, 'data-calendar-view="grid"') && str_contains($calendarView, 'data-calendar-view="stack"') && str_contains($calendarView, 'data-calendar-grid') && str_contains($calendarView, 'data-calendar-stack'),
    'Calendar setup controls live in an Edit Calendar modal' => str_contains($calendarView, 'data-calendar-plan-modal') && str_contains($calendarView, 'data-calendar-plan-open') && str_contains($calendarView, 'data-calendar-generator') && str_contains($calendarJs, 'openPlanModal') && str_contains($calendarJs, "root.classList.toggle('has-generated-calendar'") && str_contains($calendarCss, '.mg-calendar-plan-modal'),
    'Generated calendar entries omit visible product titles and use one Edit action' => str_contains($calendarJs, 'mg-calendar-theme-badge') && str_contains($calendarJs, 'mg-calendar-status-badge') && str_contains($calendarJs, 'data-calendar-open>Edit') && str_contains($calendarJs, 'aria-label="Edit scheduled ad for') && !str_contains($calendarJs, '<strong>${escapeHtml(title') && !str_contains($calendarJs, 'data-calendar-duplicate'),
    'Calendar entries have distinct theme styling' => str_contains($calendarCss, '.theme-product_spotlight') && str_contains($calendarCss, '.theme-gift_idea') && str_contains($calendarCss, '.theme-reward_promotion') && str_contains($calendarCss, '.theme-merchant_story') && str_contains($calendarCss, '.theme-customer_review') && str_contains($calendarCss, '.theme-local_support'),
    'Per-ad modal renders the actual selected social ad template' => str_contains($calendarPage, '/assets/css/personal-agent-design-studio-social.css?v=1.0.0') && str_contains($calendarModalJs, 'data-calendar-modal-ad-template') && str_contains($calendarModalJs, 'mg-agent-social-canvas') && str_contains($calendarModalJs, "adTemplate.classList.add('is-'") && str_contains($calendarModalJs, "adTemplate.classList.add('layout-'") && str_contains($calendarModalJs, 'data-calendar-ad-image') && str_contains($calendarModalJs, 'data-calendar-ad-title') && str_contains($calendarModalJs, 'data-calendar-ad-cta'),
    'Calendar modal edits, duplicates, removes, and saves scheduled ads directly' => str_contains($calendarModalJs, 'name="scheduled_date"') && str_contains($calendarModalJs, 'name="scheduled_time"') && str_contains($calendarModalJs, 'name="campaign_theme"') && str_contains($calendarModalJs, 'name="caption_standard"') && str_contains($calendarModalJs, 'data-modal-platform-copy') && str_contains($calendarModalJs, "action: 'update'") && str_contains($calendarModalJs, "action: 'duplicate'") && str_contains($calendarModalJs, "action: 'delete'"),
    'Calendar card decorator cannot trigger its own mutation loop' => str_contains($calendarModalJs, "openButton.textContent !== 'Edit'") && !str_contains($calendarModalJs, "if (openButton) openButton.textContent = 'Edit'"),
    'Scheduled ads hand off to the standalone social Design Studio' => str_contains($calendarModalJs, "'/design-studio.php?mode=social'") && str_contains($scheduleContextJs, "params.get('mode') !== 'social'") && str_contains($scheduleContextJs, 'data-social-product-select') && str_contains($scheduleContextJs, 'data-social-format') && str_contains($scheduleContextJs, 'data-social-layout'),
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
