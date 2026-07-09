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
    if (!$passed) {
        $failures[] = $label;
    }
};

$registry = $read('includes/campaign-types.php');
$campaignsApi = $read('api/merchant/campaigns.php');
$uploadApi = $read('api/merchant/listen-audio-upload.php');
$builderJs = $read('assets/js/stage12-listen-music-reward.js');
$view = $read('includes/merchant-campaigns-view.php');
$campaignPage = $read('merchant-campaigns.php');
$page = $read('listen-reward.php');
$publicJs = $read('assets/js/public-listen-music-reward.js');
$progressApi = $read('api/public/campaigns/listen-progress.php');
$sql = $read('database/listen_music_reward_v1_5.sql');
$workflow = $read('.github/workflows/stage12-campaigns-validation.yml');

$builderLoadsScript = str_contains($view, 'stage12-listen-music-reward.js')
    || str_contains($campaignPage, 'stage12-listen-music-reward.js');
$builderHasUploadControl = str_contains($builderJs, 'data-listen-audio-upload-input');
$builderUsesDynamicRewardGates = str_contains($campaignsApi, 'listen_reward_gates_json')
    && str_contains($campaignsApi, 'mg_campaign_listen_milestones')
    && !str_contains($builderJs, 'Fallback milestone fields');

$assert('Registry includes listen_music_reward', str_contains($registry, 'listen_music_reward') && str_contains($registry, 'Listen Music Reward'));
$assert('Registry supports Spotify and uploaded audio', str_contains($registry, 'spotify') && str_contains($registry, 'uploaded_audio_url') && str_contains($registry, 'audio_listen_milestones'));
$assert('Campaign API validates Spotify and audio assets', str_contains($campaignsApi, 'mg_campaign_spotify_track_id') && str_contains($campaignsApi, 'mg_campaign_listen_asset') && str_contains($campaignsApi, 'listen_music_reward'));
$assert('Merchant audio upload API stores persistent audio assets', $uploadApi !== '' && str_contains($uploadApi, 'mg_storage_store_uploaded_file') && str_contains($uploadApi, "'audio'") && str_contains($uploadApi, 'listen_music_reward'));
$assert('Campaign builder loads Listen Music controls', $builderLoadsScript && $builderHasUploadControl && $builderUsesDynamicRewardGates);
$assert('Public listen page renders Spotify and uploaded audio', str_contains($page, 'data-listen-music-reward') && str_contains($page, 'open.spotify.com/embed/track') && str_contains($page, 'data-listen-uploaded-player'));
$assert('Public listen JS tracks uploaded audio and Spotify confirmation', str_contains($publicJs, 'data-listen-uploaded-player') && str_contains($publicJs, 'data-listen-spotify-confirm') && str_contains($publicJs, 'listen-progress.php'));
$assert('Progress API records and issues music rewards', str_contains($progressApi, 'listen_reward.progress') && str_contains($progressApi, 'listen_reward.issued') && str_contains($progressApi, 'mg_listen_reward_issue'));
$assert('Progress API prevents duplicate milestone rewards', str_contains($progressApi, 'mg_listen_reward_already_issued') && str_contains($progressApi, 'milestone_percent'));
$assert('SQL migration adds listen_music_reward enum values', str_contains($sql, 'listen_music_reward') && str_contains($sql, 'ALTER TABLE campaigns') && str_contains($sql, 'ALTER TABLE campaign_contacts') && str_contains($sql, 'ALTER TABLE wallet_items'));
$assert('Workflow covers Listen Music Reward files', str_contains($workflow, 'listen-reward.php') && str_contains($workflow, 'listen-progress.php') && str_contains($workflow, 'listen-audio-upload.php') && str_contains($workflow, 'validate_listen_music_reward_v1_5.php'));

foreach ($checks as [$label, $passed]) {
    echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
}

if ($failures) {
    echo PHP_EOL . 'Listen Music Reward v1.5 validation failed:' . PHP_EOL;
    foreach ($failures as $failure) {
        echo ' - ' . $failure . PHP_EOL;
    }
    exit(1);
}

echo PHP_EOL . 'Listen Music Reward v1.5 validation passed.' . PHP_EOL;
