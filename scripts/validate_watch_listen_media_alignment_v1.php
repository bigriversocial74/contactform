<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$failures = [];
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    return is_file($full) ? (string)file_get_contents($full) : '';
};
$assert = static function (string $label, bool $passed) use (&$checks, &$failures): void {
    $checks[] = [$label, $passed];
    if (!$passed) $failures[] = $label;
};

$watch = $read('watch-reward.php');
$listen = $read('listen-reward.php');
$helper = $read('includes/campaign-media-landing.php');
$foundation = $read('includes/campaign-landing-foundation.php');
$css = $read('assets/css/campaign-media-alignment-v1.css');
$watchJs = $read('assets/js/public-watch-video-reward.js');
$listenJs = $read('assets/js/public-listen-music-reward.js');
$watchApi = $read('api/public/campaigns/watch-progress-v2.php');
$sharedMediaApi = $read('api/public/campaigns/_media_progress_v2.php');
$listenApi = $read('api/public/campaigns/listen-progress.php');

$assert('Watch page uses shared media landing helper',
    str_contains($watch, "includes/campaign-media-landing.php")
    && str_contains($watch, "mg_campaign_landing_bootstrap('watch_video_reward'")
    && !str_contains($watch, 'function mg_watch_reward_load'));
$assert('Listen page uses shared media landing helper',
    str_contains($listen, "includes/campaign-media-landing.php")
    && str_contains($listen, "mg_campaign_landing_bootstrap('listen_music_reward'")
    && !str_contains($listen, 'function mg_listen_reward_load'));
$assert('Media pages expose dynamic metadata and canonical state',
    str_contains($watch, '$page_meta')
    && str_contains($listen, '$page_meta')
    && str_contains($watch, 'mg_campaign_landing_state')
    && str_contains($listen, 'mg_campaign_landing_state'));
$assert('Media pages support merchant preview and closed-state isolation',
    str_contains($watch, 'data-watch-video-preview')
    && str_contains($listen, 'data-listen-music-preview')
    && str_contains($watch, 'data-campaign-closed')
    && str_contains($listen, 'data-campaign-closed'));
$assert('Media pages use campaign artwork priority',
    str_contains($watch, 'mg_campaign_landing_campaign_image')
    && str_contains($watch, '$posterImage')
    && str_contains($listen, 'mg_campaign_landing_campaign_image')
    && str_contains($listen, '$primaryImage'));
$assert('Media pages use shared merchant profile and View profile action',
    str_contains($helper, 'mg_campaign_landing_render_profile')
    && str_contains($helper, 'mg_campaign_media_render_join'));
$assert('Media cards use standardized campaign labels',
    str_contains($helper, 'Reward Info')
    && str_contains($helper, 'Reward Levels')
    && str_contains($helper, 'Active Status &amp; Updates'));
$assert('Watch page preserves YouTube and uploaded video providers',
    str_contains($watch, 'data-watch-uploaded-player')
    && str_contains($watch, 'youtube.com/embed')
    && str_contains($watch, 'youtube.com/iframe_api'));
$assert('Listen page preserves Spotify and uploaded audio providers',
    str_contains($listen, 'open.spotify.com/embed/track')
    && str_contains($listen, 'data-listen-uploaded-player')
    && str_contains($listen, 'data-listen-spotify-confirm'));
$assert('Watch progress runtime and milestone issuance remain connected',
    str_contains($watchJs, 'watch-progress-v2.php')
    && str_contains($watchJs, 'progress_percent')
    && str_contains($watchApi, "mg_media_reward_progress_v2('watch_video_reward'")
    && str_contains($sharedMediaApi, '\'issued_rewards\'=>$issued'));
$assert('Listen progress runtime and milestone issuance remain connected',
    str_contains($listenJs, 'listen-progress.php')
    && str_contains($listenJs, 'progress_percent')
    && str_contains($listenApi, "require_once __DIR__ . '/_media_progress_v2.php'")
    && str_contains($listenApi, "mg_media_reward_progress_v2('listen_music_reward'")
    && str_contains($sharedMediaApi, 'function mg_media_reward_issue_v2')
    && str_contains($sharedMediaApi, '\'issued_rewards\'=>$issued'));
$assert('Watch and Listen share the same participation, CRM, wallet, and Inbox authority',
    str_contains($sharedMediaApi, 'mg_public_campaign_policy_resolve')
    && str_contains($sharedMediaApi, 'mg_merchant_crm_record_event')
    && str_contains($sharedMediaApi, 'mg_zero_reward_issue_from_wallet')
    && str_contains($sharedMediaApi, "'pppm_destination'=>'inbox'"));
$assert('Pages load one scoped media stylesheet instead of two legacy polish files',
    str_contains($watch, 'campaign-media-alignment-v1.css')
    && str_contains($listen, 'campaign-media-alignment-v1.css')
    && !str_contains($watch, 'listen-wave-reward-polish-v1.css')
    && !str_contains($watch, 'watch-listen-sidebar-rewards-v1.css')
    && !str_contains($listen, 'listen-wave-reward-polish-v1.css')
    && !str_contains($listen, 'watch-listen-sidebar-rewards-v1.css'));
$assert('Media stylesheet is page scoped',
    str_contains($css, '.mg-rl-media .mg-rl-wave')
    && str_contains($css, '.mg-rl-media .mg-rl-reward-item')
    && str_contains($css, '.mg-rl-media .mg-rl-profile-link')
    && !str_contains($css, 'html,body'));
$assert('Campaign foundation primitives remain available',
    str_contains($foundation, 'function mg_campaign_landing_bootstrap')
    && str_contains($foundation, 'function mg_campaign_landing_render_profile'));

foreach ($checks as [$label, $passed]) {
    echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
}
if ($failures) {
    echo PHP_EOL . 'Watch + Listen Media Alignment validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}
echo PHP_EOL . 'Watch + Listen Media Alignment validation passed.' . PHP_EOL;
