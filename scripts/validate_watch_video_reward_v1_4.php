<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$failures = [];
$read = static function (string $path) use ($root): string { $full = $root . '/' . ltrim($path, '/'); return is_file($full) ? (string)file_get_contents($full) : ''; };
$assert = static function (string $label, bool $passed) use (&$checks, &$failures): void { $checks[] = [$label, $passed]; if (!$passed) $failures[] = $label; };

$registry = $read('includes/campaign-types.php');
$campaignsApi = $read('api/merchant/campaigns.php');
$uploadApi = $read('api/merchant/watch-video-upload.php');
$builderJs = $read('assets/js/stage12-campaigns.js');
$page = $read('watch-reward.php');
$watchJs = $read('assets/js/public-watch-video-reward.js');
$progressApi = $read('api/public/campaigns/watch-progress.php');
$sql = $read('database/watch_video_reward_v1_4.sql');
$workflow = $read('.github/workflows/stage12-campaigns-validation.yml');

$assert('Watch Video Reward registry exists', str_contains($registry, 'watch_video_reward') && str_contains($registry, 'Watch Video Reward'));
$assert('Registry supports YouTube and uploaded providers', str_contains($registry, 'youtube') && str_contains($registry, 'uploaded') && str_contains($registry, 'uploaded_asset_id'));
$assert('Campaign API saves provider and milestone rules', str_contains($campaignsApi, 'mg_campaign_watch_provider') && str_contains($campaignsApi, 'uploaded_asset_id') && str_contains($campaignsApi, 'watch_video_milestone_'));
$assert('Merchant upload API stores persistent video assets', $uploadApi !== '' && str_contains($uploadApi, 'mg_storage_store_uploaded_file') && str_contains($uploadApi, "'watch_video_reward'") && str_contains($uploadApi, 'catalog_assets'));
$assert('Builder includes upload controls and milestone fields', str_contains($builderJs, 'installWatchVideoFields') && str_contains($builderJs, 'data-watch-video-upload-input') && str_contains($builderJs, 'watch_video_milestone_3_reward_template_id'));
$assert('Public watch page renders uploaded or YouTube video', str_contains($page, 'data-watch-video-reward') && str_contains($page, 'data-watch-uploaded-player') && str_contains($page, 'youtube.com/iframe_api'));
$assert('Public watch JavaScript tracks both providers', str_contains($watchJs, 'YT.Player') && str_contains($watchJs, 'data-watch-uploaded-player') && str_contains($watchJs, 'progress_percent'));
$assert('Progress API validates uploaded provider and issues rewards', str_contains($progressApi, 'uploaded_asset_id') && str_contains($progressApi, 'Video source does not match this campaign') && str_contains($progressApi, 'mg_watch_reward_issue'));
$assert('Progress API prevents duplicate milestone gifts', str_contains($progressApi, 'mg_watch_reward_already_issued') && str_contains($progressApi, 'milestone_percent'));
$assert('SQL migration adds Watch Video Reward enum values', str_contains($sql, 'watch_video_reward') && str_contains($sql, 'ALTER TABLE campaigns') && str_contains($sql, 'ALTER TABLE campaign_contacts') && str_contains($sql, 'ALTER TABLE wallet_items'));
$assert('Workflow covers Watch Video Reward files', str_contains($workflow, 'watch-reward.php') && str_contains($workflow, 'watch-progress.php') && str_contains($workflow, 'watch-video-upload.php') && str_contains($workflow, 'validate_watch_video_reward_v1_4.php'));

foreach ($checks as [$label, $passed]) echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
if ($failures) { echo PHP_EOL . 'Watch Video Reward v1.4 validation failed:' . PHP_EOL; foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL; exit(1); }
echo PHP_EOL . 'Watch Video Reward v1.4 validation passed.' . PHP_EOL;
