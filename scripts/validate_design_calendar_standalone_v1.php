<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path)
    ? (string) file_get_contents($root . '/' . $path)
    : '';

$designPage = $read('design-studio.php');
$calendarPage = $read('design-calendar.php');
$sidebar = $read('includes/personal-agent-sidebar.php');
$designView = $read('includes/personal-agent/workspace-design.php');
$calendarView = $read('includes/personal-agent/workspace-design-calendar.php');
$standaloneCss = $read('assets/css/design-studio-standalone.css');
$calendarCss = $read('assets/css/design-calendar-standalone.css');
$templateJs = $read('assets/js/design-studio-template-variants.js');
$scheduleContextJs = $read('assets/js/design-studio-schedule-context.js');
$calendarModalJs = $read('assets/js/design-calendar-modal.js');

$checks = [
    'Design Studio is an authenticated independent page' =>
        str_contains($designPage, "mg_require_auth('/signin.php', '/design-studio.php')")
        && !str_contains($designPage, "header('Location: /agent.php?view=design'")
        && str_contains($designPage, "require __DIR__ . '/includes/personal-agent-sidebar.php'")
        && str_contains($designPage, "require __DIR__ . '/includes/personal-agent/workspace-design.php'"),
    'Design Calendar is an authenticated independent page' =>
        str_contains($calendarPage, "mg_require_auth('/signin.php', '/design-calendar.php')")
        && str_contains($calendarPage, "require __DIR__ . '/includes/personal-agent-sidebar.php'")
        && str_contains($calendarPage, "require __DIR__ . '/includes/personal-agent/workspace-design-calendar.php'"),
    'Both pages keep the main sidebar and independent page navigation' =>
        str_contains($designPage, 'mg-app-shell mg-standalone-creative-shell')
        && str_contains($calendarPage, 'mg-app-shell mg-standalone-creative-shell')
        && str_contains($designPage, 'href="/design-calendar.php"')
        && str_contains($calendarPage, 'href="/design-studio.php"'),
    'Main sidebar includes separate Design and Calendar destinations' =>
        str_contains($sidebar, "'design-calendar.php' => 'calendar'")
        && str_contains($sidebar, 'href="/design-studio.php"')
        && str_contains($sidebar, 'href="/design-calendar.php"')
        && str_contains($sidebar, '<strong>Calendar</strong>'),
    'Top app header and sidebar chat footer are removed only on standalone pages' =>
        str_contains($standaloneCss, 'body.mg-design-studio-standalone-page .mg-app-header')
        && str_contains($standaloneCss, 'body.mg-design-calendar-standalone-page .mg-app-header')
        && str_contains($standaloneCss, '.mg-personal-chat-sidebar-footer')
        && str_contains($standaloneCss, 'display:none!important'),
    'Standalone pages use the remaining full canvas beside the sidebar' =>
        str_contains($standaloneCss, '.mg-standalone-creative-workspace')
        && str_contains($standaloneCss, 'min-height:100svh')
        && str_contains($standaloneCss, 'padding:0!important')
        && str_contains($standaloneCss, '.mg-standalone-creative-canvas'),
    'Print template picker contains four templates' =>
        substr_count($designView, 'data-design-template="') === 4
        && str_contains($designView, 'data-design-template="support-local"')
        && str_contains($designView, 'data-design-template="gift-better"')
        && str_contains($designView, 'data-design-template="reward-visit"')
        && str_contains($designView, 'data-design-template="local-favorite"')
        && str_contains($standaloneCss, 'grid-template-columns:repeat(4,minmax(0,1fr))'),
    'Table tent keeps a bottom green QR banner and larger logo' =>
        str_contains($designView, 'mg-agent-print-qr-band')
        && str_contains($designView, 'data-design-qr')
        && str_contains($standaloneCss, '.mg-agent-print-design.is-tent .mg-agent-print-qr-band')
        && str_contains($standaloneCss, 'background:var(--design-green)')
        && str_contains($standaloneCss, 'width:124px;height:124px'),
    'Template variants update print copy and visual treatment' =>
        str_contains($templateJs, "'support-local'")
        && str_contains($templateJs, "'gift-better'")
        && str_contains($templateJs, "'reward-visit'")
        && str_contains($templateJs, "'local-favorite'")
        && str_contains($templateJs, 'headline.innerHTML')
        && str_contains($templateJs, 'canvas.classList.add'),
    'Calendar is omitted from the Design page when standalone' =>
        str_contains($designView, '$includeDesignCalendar')
        && str_contains($designPage, '$designStudioIncludeCalendar = false')
        && str_contains($calendarView, '$isStandaloneCalendar'),
    'Calendar entries have distinct theme styling' =>
        str_contains($calendarCss, '.theme-product_spotlight')
        && str_contains($calendarCss, '.theme-gift_idea')
        && str_contains($calendarCss, '.theme-reward_promotion')
        && str_contains($calendarCss, '.theme-merchant_story')
        && str_contains($calendarCss, '.theme-customer_review')
        && str_contains($calendarCss, '.theme-local_support'),
    'Calendar click opens a post preview and complete settings modal' =>
        str_contains($calendarModalJs, 'data-calendar-entry-modal')
        && str_contains($calendarModalJs, 'data-calendar-modal-preview-caption')
        && str_contains($calendarModalJs, 'name="scheduled_date"')
        && str_contains($calendarModalJs, 'name="scheduled_time"')
        && str_contains($calendarModalJs, 'name="campaign_theme"')
        && str_contains($calendarModalJs, 'name="caption_standard"')
        && str_contains($calendarModalJs, 'data-modal-platform-copy')
        && str_contains($calendarModalJs, "action: 'update'"),
    'Scheduled posts hand off to the standalone social Design Studio' =>
        str_contains($calendarModalJs, "'/design-studio.php?mode=social'")
        && str_contains($scheduleContextJs, "params.get('mode') !== 'social'")
        && str_contains($scheduleContextJs, 'data-social-product-select')
        && str_contains($scheduleContextJs, 'data-social-format')
        && str_contains($scheduleContextJs, 'data-social-layout'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, "Standalone Design and Calendar validation failed: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Standalone Design and Calendar contract: ' . count($checks) . '/' . count($checks) . ".\n";
