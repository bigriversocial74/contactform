<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'agent' => $root . '/agent.php',
    'workspace' => $root . '/includes/personal-agent/multi-agent-workspace.php',
    'css' => $root . '/assets/css/specialty-agent-composer-parity-v1.css',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label} file: {$path}\n");
        exit(1);
    }
}

$agent = (string) file_get_contents($files['agent']);
$workspace = (string) file_get_contents($files['workspace']);
$css = (string) file_get_contents($files['css']);

$checks = [
    'stylesheet loads after task-agent styles' => str_contains($agent, "task-agent-monitoring-v1.css?v=1.0.0','/assets/css/personal-agent-stats-single-row-v1.css?v=1.0.0','/assets/css/specialty-agent-composer-parity-v1.css?v=1.0.0"),
    'specialty body scope remains selected-agent only' => str_contains($agent, "mg-specialized-agent-selected"),
    'shared runtime form receives specialty class' => str_contains($workspace, 'mg-agent-runtime-composer mg-specialty-agent-composer'),
    'composer textarea is one row' => str_contains($workspace, 'name="message" rows="1"'),
    'textarea has an accessible label' => str_contains($workspace, 'aria-label="Message this specialty agent"'),
    'send button remains submit behavior' => str_contains($workspace, 'type="submit" aria-label="Send message"'),
    'send control uses compact arrow treatment' => str_contains($workspace, '<span aria-hidden="true">↑</span>'),
    'status remains live and connected to runtime' => str_contains($workspace, 'data-agent-runtime-status aria-live="polite"'),
    'composer stays at the bottom' => str_contains($css, 'position:sticky!important') && str_contains($css, 'bottom:0!important'),
    'composer uses rounded container' => str_contains($css, 'border-radius:20px!important'),
    'desktop composer is compact' => str_contains($css, 'min-height:48px!important') && str_contains($css, 'grid-template-columns:minmax(0,1fr) 48px!important'),
    'textarea uses rounded agent parity styling' => str_contains($css, 'border-radius:24px!important'),
    'send button is circular' => str_contains($css, 'border-radius:999px!important') && str_contains($css, 'width:48px!important'),
    'empty runtime status consumes no height' => str_contains($css, '[data-agent-runtime-status]:empty') && str_contains($css, 'display:none!important'),
    'mobile composer remains compact' => str_contains($css, 'grid-template-columns:minmax(0,1fr) 44px!important') && str_contains($css, 'min-height:44px!important'),
    'mobile safe area is respected' => str_contains($css, 'env(safe-area-inset-bottom)'),
    'reduced motion is respected' => str_contains($css, '@media(prefers-reduced-motion:reduce)'),
    'rules are limited to specialty agent pages' => substr_count($css, 'mg-specialized-agent-selected') >= 10,
];

$failed = [];
foreach ($checks as $name => $passed) {
    printf("[%s] %s\n", $passed ? 'PASS' : 'FAIL', $name);
    if (!$passed) $failed[] = $name;
}

$score = (int) round((count($checks) - count($failed)) / count($checks) * 100);
printf("Specialty Agent composer parity score: %d/100\n", $score);

if ($failed) {
    fwrite(STDERR, "Failed checks: " . implode(', ', $failed) . "\n");
    exit(1);
}

exit(0);
