<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (string)file_get_contents($root . '/' . $path) : '';

$helper = $read('api/public/_campaign_feed_v1.php');
$endpoint = $read('api/public/campaign-feed.php');
$feed = $read('feed.php');
$newfeed = $read('newfeed.php');
$js = $read('assets/js/feed-campaign-progress-cards-v1.js');
$css = $read('assets/css/feed-campaign-progress-cards-v1.css');

$checks = [
    'Campaign helper exists' => $helper !== '',
    'Campaign endpoint exists' => $endpoint !== '',
    'Only Watch and Listen campaign types are queried' => str_contains($helper, "'watch_video_reward','listen_music_reward'")
        && !str_contains($helper, "'stamp_card_reward'"),
    'Campaign query requires active public merchant profiles' => str_contains($helper, "c.status='active'")
        && str_contains($helper, "pp.visibility IN ('public','unlisted')")
        && str_contains($helper, "u.status='active'"),
    'Following mode uses active social follows' => str_contains($helper, 'social_follows sf')
        && str_contains($helper, "sf.status='active'"),
    'Viewer mutes and blocks are honored' => str_contains($helper, 'social_mutes sm')
        && str_contains($helper, 'social_blocks sb'),
    'Campaign timing and inventory are honored' => str_contains($helper, 'c.starts_at<=NOW()')
        && str_contains($helper, 'c.ends_at>=NOW()')
        && str_contains($helper, 'c.issued_count<c.quantity_limit'),
    'Viewer progress reads campaign contact metadata' => str_contains($helper, 'campaign_contacts')
        && str_contains($helper, 'max_progress_percent'),
    'Reward shipment reads wallet items and milestone percent' => str_contains($helper, 'wallet_items')
        && str_contains($helper, 'milestone_percent')
        && str_contains($helper, 'reward_shipped_at'),
    'Reward levels include completion and shipped states' => str_contains($helper, "'complete'")
        && str_contains($helper, "'shipped'")
        && str_contains($helper, 'next_level_percent'),
    'Campaign links route to Watch and Listen pages' => str_contains($helper, "'watch-reward.php'")
        && str_contains($helper, "'listen-reward.php'"),
    'Endpoint is GET-only and rate limited' => str_contains($endpoint, "mg_require_method('GET')")
        && str_contains($endpoint, "mg_rate_limit('campaign.feed.read'"),
    'Anonymous Discover can use short public cache' => str_contains($endpoint, "mode === 'discover'")
        && str_contains($endpoint, 'Cache-Control: public, max-age=20'),
    'Primary feed loads campaign assets and slot' => str_contains($feed, 'feed-campaign-progress-cards-v1.css?v=1.0.0')
        && str_contains($feed, 'feed-campaign-progress-cards-v1.js?v=1.0.0')
        && str_contains($feed, 'data-campaign-feed-list'),
    'Following feed loads campaign assets and slot' => str_contains($newfeed, 'feed-campaign-progress-cards-v1.css?v=1.0.0')
        && str_contains($newfeed, 'feed-campaign-progress-cards-v1.js?v=1.0.0')
        && str_contains($newfeed, 'data-campaign-feed-list'),
    'Client renders progress bar and reward levels' => str_contains($js, 'mg-campaign-feed-progress')
        && str_contains($js, 'mg-campaign-feed-levels')
        && str_contains($js, 'progress_percent'),
    'Client renders reward shipped status' => str_contains($js, 'Reward shipped')
        && str_contains($js, 'reward_shipped_count')
        && str_contains($js, 'reward_shipped_at'),
    'Client supports Discover Following and hides Mine cards' => str_contains($js, "['discover', 'following'].includes(currentMode)")
        && str_contains($js, "data-feed-tab"),
    'Client uses safe URL and text rendering' => str_contains($js, 'function safeUrl')
        && str_contains($js, 'textContent')
        && !str_contains($js, 'innerHTML'),
    'Campaign cards remain compact and responsive' => str_contains($css, 'grid-template-columns:92px minmax(0,1fr)')
        && str_contains($css, '.mg-campaign-feed-level')
        && str_contains($css, '@media(max-width:640px)'),
    'Shipment state uses green minimal styling' => str_contains($css, '.mg-campaign-feed-state.is-shipped')
        && str_contains($css, '.mg-campaign-feed-level.is-shipped'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, 'Campaign feed progress card validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Campaign feed progress card contract: ' . count($checks) . '/' . count($checks) . ".\n";
