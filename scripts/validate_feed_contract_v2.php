<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $value = @file_get_contents($root . '/' . $path);
    return is_string($value) ? $value : '';
};

$files = [
    'page' => $read('feed.php'),
    'endpoint' => $read('api/public/feed.php'),
    'contract' => $read('api/public/_feed_contract_v2.php'),
    'posts' => $read('api/public/_feed_resilient_v1.php'),
    'campaigns' => $read('api/public/_campaign_feed_v2.php'),
    'progress' => $read('api/public/_campaign_feed_v2_progress.php'),
    'fallback' => $read('api/public/_campaign_feed_v2_resilient.php'),
    'action_center' => $read('api/account/_action_center_contract.php'),
    'wallet' => $read('api/account/_action_center_wallet.php'),
    'bridge' => $read('assets/js/feed-contract-v2-bridge.js'),
    'campaign_client' => $read('assets/js/feed-campaign-progress-cards-v2.js'),
    'css' => $read('assets/css/feed-contract-v2.css'),
];

$checks = [
    'Feed page declares and cache-busts Contract v2 assets' =>
        str_contains($files['page'], 'data-feed-contract-version="2"')
        && str_contains($files['page'], 'feed-contract-v2-bridge.js?v=2.0.0')
        && str_contains($files['page'], 'social-feed.js?v=2.0.0')
        && str_contains($files['page'], 'feed-campaign-progress-cards-v2.js?v=2.0.0')
        && !str_contains($files['page'], 'feed-campaign-progress-cards-v1.js?v=1.0.0'),
    'Feed page includes a source warning region' =>
        str_contains($files['page'], 'data-feed-source-warning')
        && str_contains($files['page'], 'feed-contract-v2.css?v=2.0.0'),
    'Public endpoint serves a backward-compatible Feed Contract v2 payload' =>
        str_contains($files['endpoint'], 'mg_public_feed_contract_v2')
        && str_contains($files['endpoint'], "'contract_version'")
        && str_contains($files['endpoint'], "'feed' => \$feed")
        && str_contains($files['endpoint'], "'campaigns' => \$campaigns")
        && str_contains($files['endpoint'], "'sources' => \$sources")
        && str_contains($files['endpoint'], "'warnings'"),
    'Post and campaign sources are isolated independently' =>
        substr_count($files['contract'], 'try {') >= 2
        && str_contains($files['contract'], 'feed.contract_v2_posts_failed')
        && str_contains($files['contract'], 'feed.contract_v2_campaigns_failed')
        && str_contains($files['contract'], "'posts_unavailable'")
        && str_contains($files['contract'], "'campaigns_unavailable'"),
    'Regular post projection remains per-item resilient' =>
        str_contains($files['posts'], 'catch (Throwable $error)')
        && str_contains($files['posts'], 'social.feed_post_skipped')
        && str_contains($files['posts'], "'skipped_items'"),
    'Campaign reward state uses Action Center wallet and presentation helpers' =>
        str_contains($files['campaigns'], 'mg_ac_wallet_select_sql')
        && str_contains($files['campaigns'], 'mg_ac_wallet_public_item')
        && str_contains($files['campaigns'], 'mg_action_center_contract_business_names')
        && str_contains($files['campaigns'], 'mg_action_center_contract_item')
        && str_contains($files['action_center'], "'contract_version' => MG_ACTION_CENTER_CONTRACT_VERSION")
        && str_contains($files['wallet'], 'mg_ac_wallet_select_sql'),
    'Campaign response exposes canonical Action Center state without raw metadata' =>
        str_contains($files['campaigns'], "'action_center' => [")
        && str_contains($files['campaigns'], "'contract_version'")
        && str_contains($files['campaigns'], "'action_item_id'")
        && str_contains($files['campaigns'], "'url' => \$actionItemId !== '' ? '/inbox.php' : null")
        && !str_contains($files['campaigns'], "'metadata_json' =>"),
    'Playback progress remains campaign-contact backed' =>
        str_contains($files['progress'], 'campaign_contacts')
        && str_contains($files['progress'], 'max_progress_percent')
        && str_contains($files['progress'], 'progress_percent')
        && str_contains($files['progress'], 'max($contactProgress, $rewardProgress)'),
    'Optional enrichments degrade to base campaign cards' =>
        str_contains($files['progress'], 'campaign.feed_progress_unavailable')
        && str_contains($files['fallback'], 'mg_campaign_feed_v2_items_with_progress')
        && str_contains($files['fallback'], 'mg_campaign_feed_v1_items')
        && str_contains($files['fallback'], 'campaign.feed_v2_enrichment_failed'),
    'Primary feed uses one public feed read instead of a second campaign request' =>
        str_contains($files['bridge'], '/api/public/feed\\.php')
        && str_contains($files['bridge'], 'mg:feed-contract-v2')
        && str_contains($files['bridge'], 'MicrogifterFeedContractV2Latest')
        && !str_contains($files['campaign_client'], '/api/public/campaign-feed.php')
        && !str_contains($files['campaign_client'], 'fetch('),
    'Campaign client renders canonical progress and Action Center navigation' =>
        str_contains($files['campaign_client'], 'progress_percent')
        && str_contains($files['campaign_client'], 'reward_shipped_count')
        && str_contains($files['campaign_client'], 'In Action Center')
        && str_contains($files['campaign_client'], 'Open Inbox'),
    'Campaign client keeps My Posts free of campaign cards' =>
        str_contains($files['campaign_client'], "mode === 'mine'")
        && str_contains($files['campaign_client'], 'render([])'),
    'Client rendering is text-safe and URL-safe' =>
        str_contains($files['campaign_client'], 'function safeUrl')
        && str_contains($files['campaign_client'], 'textContent')
        && !str_contains($files['campaign_client'], 'innerHTML'),
    'Partial source failures have visible nonfatal styling' =>
        str_contains($files['campaign_client'], 'data-feed-source-warning')
        && str_contains($files['css'], '.mg-feed-source-warning')
        && str_contains($files['css'], '.mg-campaign-feed-loading'),
    'No new SQL or configuration behavior is introduced' =>
        !str_contains($files['contract'], 'CREATE TABLE')
        && !str_contains($files['campaigns'], 'ALTER TABLE')
        && !str_contains($files['endpoint'], 'config.php'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, 'Feed Contract v2 validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Feed Contract v2: ' . count($checks) . '/' . count($checks) . ".\n";
