<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (string) file_get_contents($root . '/' . $path) : '';

$page = $read('design-calendar.php');
$workspace = $read('includes/personal-agent/workspace-design-calendar.php');
$calendarRuntime = $read('assets/js/personal-agent-design-studio-calendar.js');
$rowRuntime = $read('assets/js/design-calendar-row-layout-v1.js');
$rowCss = $read('assets/css/design-calendar-row-layout-v1.css');

$checks = [
    'Calendar loads the row layout stylesheet and runtime' => str_contains($page, '/assets/css/design-calendar-row-layout-v1.css?v=1.0.0') && str_contains($page, '/assets/js/design-calendar-row-layout-v1.js?v=1.0.0'),
    'Per-entry selector is removed from rendered calendar cards' => str_contains($rowRuntime, "card.querySelector('.mg-calendar-select-item')") && str_contains($rowRuntime, 'selector.remove()') && str_contains($rowCss, '.mg-calendar-select-item') && str_contains($rowCss, 'display:none!important'),
    'Bulk select visible and bulk tools remain available' => str_contains($workspace, 'data-calendar-select-visible') && str_contains($workspace, 'data-calendar-bulk-apply') && str_contains($workspace, 'data-calendar-bulk-remove') && str_contains($calendarRuntime, 'selectVisible?.addEventListener'),
    'Grid cards hide product titles and use a two-column badge header' => str_contains($rowCss, '.mg-design-calendar-grid-view .mg-calendar-event-title') && str_contains($rowCss, 'grid-template-columns:minmax(0,1fr) auto!important'),
    'Stacked rows restore a semantic title from the card label' => str_contains($rowRuntime, 'titleFromCard') && str_contains($rowRuntime, 'mg-calendar-event-title') && str_contains($rowRuntime, 'Edit scheduled ad for'),
    'Stacked rows use a wide flexible content column' => str_contains($rowCss, 'grid-template-columns:clamp(170px,20vw,230px) minmax(280px,1fr) 86px!important') && str_contains($rowCss, '"image title actions"'),
    'Stacked title is full width and readable' => str_contains($rowCss, '.mg-calendar-stack-day .mg-calendar-event-title') && str_contains($rowCss, 'max-width:none') && str_contains($rowCss, 'font-size:18px') && str_contains($rowCss, 'overflow-wrap:anywhere'),
    'Stacked row aligns image, metadata, and Edit action independently' => str_contains($rowCss, 'grid-area:image!important') && str_contains($rowCss, 'grid-area:meta!important') && str_contains($rowCss, 'grid-area:actions!important'),
    'Stacked rows include tablet and mobile reflow' => str_contains($rowCss, '@media (max-width:760px)') && str_contains($rowCss, '@media (max-width:520px)') && str_contains($rowCss, 'grid-template-columns:1fr!important'),
    'Decorator handles dynamic calendar rerenders without replacing the main runtime' => str_contains($rowRuntime, 'MutationObserver') && str_contains($rowRuntime, 'requestAnimationFrame') && str_contains($rowRuntime, "root.querySelectorAll('.mg-design-calendar-event')"),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, 'Design Calendar row layout validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Design Calendar row layout contract: ' . count($checks) . '/' . count($checks) . ".\n";
