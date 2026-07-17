<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (string)file_get_contents($root . '/' . $path) : '';

$watch = $read('watch-reward.php');
$listen = $read('listen-reward.php');
$stamp = $read('stamp-card.php');
$css = $read('assets/css/watch-listen-stamp-compact-layout-v1.css');

$sharedCss = '/assets/css/watch-listen-stamp-compact-layout-v1.css?v=1.0.0';

$checks = [
    'Watch loads compact campaign stylesheet and class' => str_contains($watch, $sharedCss) && str_contains($watch, 'mg-rl-watch mg-rl-compact-campaign'),
    'Listen loads compact campaign stylesheet and class' => str_contains($listen, $sharedCss) && str_contains($listen, 'mg-rl-listen mg-rl-compact-campaign'),
    'Stamp loads compact campaign stylesheet and class' => str_contains($stamp, $sharedCss) && str_contains($stamp, 'mg-rl-stamp mg-rl-compact-campaign'),
    'Watch hero text block is removed' => !str_contains($watch, '<header class="mg-rl-hero">') && !str_contains($watch, 'Watch to</span>'),
    'Listen hero text block is removed' => !str_contains($listen, '<header class="mg-rl-hero">') && !str_contains($listen, 'Listen to</span>'),
    'Stamp hero text block is removed' => !str_contains($stamp, '<header class="mg-rl-hero">') && !str_contains($stamp, 'mg-public-campaign-trust-row'),
    'Watch keeps media, three supporting cards, and desktop join column' => str_contains($watch, 'data-watch-video-shell') && str_contains($watch, 'mg_campaign_media_render_cards($cardContext)') && str_contains($watch, 'mg-rl-join-desktop'),
    'Listen keeps media, three supporting cards, and desktop join column' => str_contains($listen, 'data-listen-audio-shell') && str_contains($listen, 'mg_campaign_media_render_cards($cardContext)') && str_contains($listen, 'mg-rl-join-desktop'),
    'Stamp keeps canvas and desktop join column' => str_contains($stamp, 'data-stamp-stage') && str_contains($stamp, 'data-stamp-card-visual') && str_contains($stamp, 'mg-rl-join-desktop'),
    'Stamp uses exactly three summary cards' => substr_count($stamp, '<article class="mg-rl-card mg-stamp-summary-card') === 3 && str_contains($stamp, 'mg-stamp-item-card') && str_contains($stamp, 'mg-stamp-rules-card') && str_contains($stamp, 'mg-stamp-status-card'),
    'Stamp preserves Save Card control inside the compact cards' => str_contains($stamp, 'mg-stamp-card-save-toggle') && str_contains($stamp, 'data-loyalty-save-toggle') && str_contains($stamp, 'data-campaign-id'),
    'Stamp runtime data hooks are preserved' => str_contains($stamp, 'data-stamp-summary-state') && str_contains($stamp, 'data-stamp-card-status') && str_contains($stamp, 'data-campaign-result'),
    'Compact layout top-aligns both columns' => str_contains($css, '.mg-rl-page.mg-rl-compact-campaign .mg-rl-wrap') && str_contains($css, 'align-items: start;') && str_contains($css, '.mg-rl-wrap > .mg-rl-join-desktop') && str_contains($css, 'align-self: start;'),
    'Compact layout keeps three desktop cards' => str_contains($css, 'grid-template-columns: repeat(3, minmax(0, 1fr));'),
    'Shared page stylesheet removes any fallback hero' => str_contains($css, '.mg-rl-page.mg-rl-compact-campaign .mg-rl-hero') && str_contains($css, 'display: none !important;'),
    'Footer uses a blue background' => str_contains($css, '.mg-site-footer.mg-universal-footer') && str_contains($css, 'background: linear-gradient(135deg, #071c33 0%, #0d3459 100%) !important;'),
    'Footer brand, columns, links, and bottom copy are white' => str_contains($css, '.mg-universal-footer .mg-footer-brand-panel p') && str_contains($css, '.mg-universal-footer .mg-footer-column a') && str_contains($css, '.mg-universal-footer .mg-footer-bottom p') && str_contains($css, 'color: #fff !important;'),
    'Footer logo is converted for dark background' => str_contains($css, '.mg-universal-footer .mg-footer-logo img') && str_contains($css, 'filter: brightness(0) invert(1) !important;'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, 'Watch, Listen, and Stamp compact layout validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Watch, Listen, and Stamp compact layout contract: ' . count($checks) . '/' . count($checks) . ".\n";
