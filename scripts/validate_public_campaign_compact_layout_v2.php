<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (string)file_get_contents($root . '/' . $path) : '';

$cssPath = '/assets/css/public-campaign-compact-layout-v2.css?v=1.0.0';
$css = $read('assets/css/public-campaign-compact-layout-v2.css');
$foundation = $read('includes/campaign-landing-foundation.php');
$sharedRenderer = $read('includes/public-campaign-page.php');

$specialized = [
    'instant-win.php' => ['mg-rl-instant', 'data-instant-win-experience', '/api/public/campaigns/instant-win.php'],
    'check-in-reward.php' => ['mg-rl-specialized-checkin', 'data-check-in-reward-form', '/api/public/campaigns/check-in.php'],
    'survey-feedback.php' => ['mg-rl-specialized-survey', 'entry_feedback', '/api/public/campaigns/survey-feedback.php'],
    'rsvp-event.php' => ['mg-rl-specialized-rsvp', 'data-rsvp-event-form', '/api/public/campaigns/rsvp-event.php'],
];

$generic = [
    'contest.php',
    'qr-reward.php',
    'agent-offer.php',
    'birthday-vip.php',
    'referral-reward.php',
    'newsletter-signup.php',
];

$checks = [];
foreach ($specialized as $path => [$pageClass, $runtimeHook, $endpoint]) {
    $source = $read($path);
    $checks[$path . ' loads compact stylesheet'] = str_contains($source, $cssPath);
    $checks[$path . ' opts into compact layout'] = str_contains($source, 'mg-rl-compact-campaign');
    $checks[$path . ' preserves campaign runtime'] = str_contains($source, $runtimeHook) && str_contains($source, $endpoint);
    $checks[$path . ' preserves canvas and right join column'] = str_contains($source, 'mg-rl-player') && str_contains($source, 'mg-rl-join-desktop') && str_contains($source, $pageClass);
    $checks[$path . ' preserves three-card foundation call'] = str_contains($source, 'mg_campaign_landing_render_bottom_cards([');
}

foreach ($generic as $path) {
    $source = $read($path);
    $checks[$path . ' loads compact stylesheet'] = str_contains($source, $cssPath);
    $checks[$path . ' keeps shared campaign renderer'] = str_contains($source, "require __DIR__ . '/includes/public-campaign-page.php'");
}

$checks['Shared renderer keeps simple campaign class'] = str_contains($sharedRenderer, 'mg-rl-simple-campaign');
$checks['Shared renderer keeps reward canvas and right join column'] = str_contains($sharedRenderer, 'mg-rl-simple-reward-canvas') && str_contains($sharedRenderer, 'mg-rl-join-desktop');
$checks['Shared renderer keeps foundation card call'] = str_contains($sharedRenderer, 'mg_campaign_landing_render_bottom_cards([');
$checks['Foundation renderer provides three cards'] = substr_count($foundation, '<article class="mg-rl-card">') >= 3;
$checks['Compact CSS covers dedicated and shared renderers'] = str_contains($css, ':is(.mg-rl-compact-campaign, .mg-rl-simple-campaign)');
$checks['Compact CSS hides oversized hero'] = str_contains($css, '.mg-rl-hero') && str_contains($css, 'display: none !important;');
$checks['Compact CSS orders canvas before preview and cards'] = str_contains($css, 'order: 1;') && str_contains($css, 'order: 2;') && str_contains($css, 'order: 4;');
$checks['Compact CSS top-aligns both desktop columns'] = str_contains($css, 'align-items: start;') && str_contains($css, 'align-self: start;');
$checks['Compact CSS preserves three desktop cards'] = str_contains($css, 'grid-template-columns: repeat(3, minmax(0, 1fr));');
$checks['Compact CSS provides responsive two and one column cards'] = str_contains($css, 'repeat(2, minmax(0, 1fr))') && str_contains($css, 'grid-template-columns: 1fr;');
$checks['Footer uses blue background'] = str_contains($css, 'background: linear-gradient(135deg, #071c33 0%, #0d3459 100%) !important;');
$checks['Footer copy and links are white'] = str_contains($css, '.mg-footer-brand-panel p') && str_contains($css, '.mg-footer-column a') && str_contains($css, '.mg-footer-bottom p') && str_contains($css, 'color: #fff !important;');
$checks['Footer logo is adjusted for blue background'] = str_contains($css, 'filter: brightness(0) invert(1) !important;');

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, 'Public Campaign Compact Layout v2 validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Public Campaign Compact Layout v2 contract: ' . count($checks) . '/' . count($checks) . ".\n";
