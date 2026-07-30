<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$failures = [];
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    return is_file($full) ? (string) file_get_contents($full) : '';
};
$assert = static function (string $label, bool $passed) use (&$checks, &$failures): void {
    $checks[] = [$label, $passed];
    if (!$passed) $failures[] = $label;
};

$registry = $read('includes/campaign-types.php');
$campaignsApi = $read('api/merchant/campaigns.php') . "\n" . $read('api/merchant/campaigns-core.php');
$uploadApi = $read('api/merchant/listen-audio-upload.php');
$builderJs = $read('assets/js/stage12-listen-music-reward.js');
$view = $read('includes/merchant-campaigns-view.php');
$campaignPage = $read('merchant-campaigns.php');
$page = $read('listen-reward.php');
$publicJs = $read('assets/js/public-listen-music-reward.js');
$progressApi = $read('api/public/campaigns/listen-progress.php');
$sharedProgressApi = $read('api/public/campaigns/_media_progress_v2.php');
$sql = $read('database/listen_music_reward_v1_5.sql');
$workflow = $read('.github/workflows/stage12-campaigns-validation.yml');

$builderLoadsScript = str_contains($view, 'stage12-listen-music-reward.js') || str_contains($campaignPage, 'stage12-listen-music-reward.js');
$builderHasUploadControl = str_contains($builderJs, 'data-listen-audio-upload-input');
$builderHasMilestoneRewardSelects = str_contains($builderJs, 'listen_milestone_3_reward_template_id')
    || str_contains($builderJs, "listen_milestone_'+n+'_reward_template_id")
    || str_contains($builderJs, 'listen_milestone_') && str_contains($builderJs, '_reward_template_id');

$assert('Registry includes listen_music_reward', str_contains($registry, 'listen_music_reward') && str_contains($registry, 'Listen Music Reward'));
$assert('Registry supports Spotify and uploaded audio', str_contains($registry, 'spotify') && str_contains($registry, 'uploaded_audio_url') && str_contains($registry, 'audio_listen_milestones'));
$assert('Campaign API validates Spotify and audio assets', str_contains($campaignsApi, 'mg_campaign_spotify_track_id') && str_contains($campaignsApi, 'mg_campaign_listen_asset') && str_contains($campaignsApi, 'listen_music_reward'));
$assert('Merchant audio upload API stores persistent audio assets', $uploadApi !== '' && str_contains($uploadApi, 'mg_storage_store_uploaded_file') && str_contains($uploadApi, "'audio'") && str_contains($uploadApi, 'listen_music_reward'));
$assert('Campaign builder loads Listen Music controls', $builderLoadsScript && $builderHasUploadControl && $builderHasMilestoneRewardSelects);
$assert('Public listen page renders Spotify and uploaded audio', str_contains($page, 'data-listen-music-reward') && str_contains($page, 'open.spotify.com/embed/track') && str_contains($page, 'data-listen-uploaded-player'));
$assert('Public listen JS tracks uploaded audio and Spotify confirmation', str_contains($publicJs, 'data-listen-uploaded-player') && str_contains($publicJs, 'data-listen-spotify-confirm') && str_contains($publicJs, 'listen-progress.php'));
$assert('Progress API records and issues music rewards',
    str_contains($progressApi, "require_once __DIR__ . '/_media_progress_v2.php'")
    && str_contains($progressApi, "mg_media_reward_progress_v2('listen_music_reward'")
    && str_contains($sharedProgressApi, 'mg_media_reward_event_v2')
    && str_contains($sharedProgressApi, 'mg_media_reward_issue_v2')
    && str_contains($sharedProgressApi, "'issued_rewards'=>" . '$issued')
);
$assert('Progress API prevents duplicate milestone rewards',
    str_contains($sharedProgressApi, 'mg_media_reward_already_v2')
    && str_contains($sharedProgressApi, 'milestone_percent')
    && str_contains($sharedProgressApi, 'if(mg_media_reward_already_v2')
);
$assert('SQL migration adds listen_music_reward enum values', str_contains($sql, 'listen_music_reward') && str_contains($sql, 'ALTER TABLE campaigns') && str_contains($sql, 'ALTER TABLE campaign_contacts') && str_contains($sql, 'ALTER TABLE wallet_items'));
$assert('Workflow covers Listen Music Reward files', str_contains($workflow, 'listen-reward.php') && str_contains($workflow, 'listen-progress.php') && str_contains($workflow, 'listen-audio-upload.php') && str_contains($workflow, 'validate_listen_music_reward_v1_5.php'));

foreach ($checks as [$label, $passed]) echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
if ($failures) {
    echo PHP_EOL . 'Listen Music Reward v1.5 validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}
echo PHP_EOL . 'Listen Music Reward v1.5 validation passed.' . PHP_EOL;
