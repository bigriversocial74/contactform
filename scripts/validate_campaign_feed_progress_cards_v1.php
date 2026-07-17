<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (string)file_get_contents($root . '/' . $path) : '';

$helper = $read('api/public/_campaign_feed_v1.php');
$helperV2 = $read('api/public/_campaign_feed_v2.php');
$progressV2 = $read('api/public/_campaign_feed_v2_progress.php');
$resilientV2 = $read('api/public/_campaign_feed_v2_resilient.php');
$contractV2 = $read('api/public/_feed_contract_v2.php');
$endpoint = $read('api/public/campaign-feed.php');
$feed = $read('feed.php');
$newfeed = $read('newfeed.php');
$js = $read('assets/js/feed-campaign-progress-cards-v1.js');
$jsV2 = $read('assets/js/feed-campaign-progress-cards-v2.js');
$bridgeV2 = $read('assets/js/feed-contract-v2-bridge.js');
$css = $read('assets/css/feed-campaign-progress-cards-v1.css');

$checks = [
    'Campaign v1 helper and legacy endpoint remain available' => $helper !== '' && $endpoint !== '',
    'Campaign v2 helper uses Watch and Listen campaign types' => str_contains($helperV2, "'watch_video_reward','listen_music_reward'")
        && !str_contains($helperV2, "'stamp_card_reward'"),
    'Campaign query requires active public merchant profiles' => str_contains($helperV2, "c.status='active'")
        && str_contains($helperV2, "pp.visibility IN ('public','unlisted')")
        && str_contains($helperV2, "u.status='active'"),
    'Following mode uses active social follows' => str_contains($helperV2, 'social_follows sf')
        && str_contains($helperV2, "sf.status='active'"),
    'Viewer mutes and blocks are honored' => str_contains($helperV2, 'social_mutes sm')
        && str_contains($helperV2, 'social_blocks sb'),
    'Campaign timing and inventory are honored' => str_contains($helperV2, 'c.starts_at<=NOW()')
        && str_contains($helperV2, 'c.ends_at>=NOW()')
        && str_contains($helperV2, 'c.issued_count<c.quantity_limit'),
    'Viewer playback progress reads campaign contact metadata' => str_contains($progressV2, 'campaign_contacts')
        && str_contains($progressV2, 'max_progress_percent')
        && str_contains($progressV2, 'progress_percent'),
    'Reward shipment is projected through Action Center Contract v2' => str_contains($helperV2, 'mg_ac_wallet_select_sql')
        && str_contains($helperV2, 'mg_ac_wallet_public_item')
        && str_contains($helperV2, 'mg_action_center_contract_item')
        && str_contains($helperV2, 'MG_ACTION_CENTER_CONTRACT_VERSION'),
    'Action Center and progress enrichments can fall back safely' => str_contains($resilientV2, 'mg_campaign_feed_v2_items_with_progress')
        && str_contains($resilientV2, 'mg_campaign_feed_v1_items')
        && str_contains($resilientV2, "campaign.feed_v2_enrichment_failed"),
    'Reward levels include completion and shipped states' => str_contains($helperV2, "'complete'")
        && str_contains($helperV2, "'shipped'")
        && str_contains($helperV2, 'next_level_percent'),
    'Campaign links route to Watch Listen and Action Center' => str_contains($helperV2, "'watch-reward.php'")
        && str_contains($helperV2, "'listen-reward.php'")
        && str_contains($helperV2, "'url' => '/inbox.php'"),
    'Legacy campaign endpoint remains GET-only and rate limited' => str_contains($endpoint, "mg_require_method('GET')")
        && str_contains($endpoint, "mg_rate_limit('campaign.feed.read'"),
    'Primary feed uses one Feed Contract v2 request' => str_contains($feed, 'feed-contract-v2-bridge.js?v=2.0.0')
        && str_contains($feed, 'feed-campaign-progress-cards-v2.js?v=2.0.0')
        && str_contains($contractV2, 'mg_campaign_feed_v2_resilient_items')
        && !str_contains($jsV2, '/api/public/campaign-feed.php'),
    'Primary feed retains campaign CSS and slot' => str_contains($feed, 'feed-campaign-progress-cards-v1.css?v=1.0.0')
        && str_contains($feed, 'data-campaign-feed-list'),
    'Following legacy feed retains v1 campaign runtime' => str_contains($newfeed, 'feed-campaign-progress-cards-v1.css?v=1.0.0')
        && str_contains($newfeed, 'feed-campaign-progress-cards-v1.js?v=1.0.0')
        && str_contains($newfeed, 'data-campaign-feed-list'),
    'Feed Contract bridge publishes the unified response' => str_contains($bridgeV2, 'mg:feed-contract-v2')
        && str_contains($bridgeV2, '/api/public/feed\\.php')
        && str_contains($bridgeV2, 'MicrogifterFeedContractV2Latest'),
    'V2 client renders progress reward levels and Action Center state' => str_contains($jsV2, 'mg-campaign-feed-progress')
        && str_contains($jsV2, 'mg-campaign-feed-levels')
        && str_contains($jsV2, 'progress_percent')
        && str_contains($jsV2, 'In Action Center')
        && str_contains($jsV2, 'Open Inbox'),
    'V2 client supports feed tabs and hides Mine cards' => str_contains($jsV2, "mode === 'mine'")
        && str_contains($jsV2, 'data-feed-tab'),
    'Both clients use safe URL and text rendering' => str_contains($js, 'function safeUrl')
        && str_contains($jsV2, 'function safeUrl')
        && str_contains($jsV2, 'textContent')
        && !str_contains($jsV2, 'innerHTML'),
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
