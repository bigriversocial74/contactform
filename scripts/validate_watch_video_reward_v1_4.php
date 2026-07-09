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

$registry = $read('includes/campaign-types.php');
$campaignsApi = $read('api/merchant/campaigns.php');
$builderJs = $read('assets/js/stage12-campaigns.js');
$page = $read('watch-reward.php');
$watchJs = $read('assets/js/public-watch-video-reward.js');
$progressApi = $read('api/public/campaigns/watch-progress.php');
$sql = $read('database/watch_video_reward_v1_4.sql');
$workflow = $read('.github/workflows/stage12-campaigns-validation.yml');

$assert('Watch Video Reward registry exists', str_contains($registry, 'watch_video_reward') && str_contains($registry, 'Watch Video Reward'));
$assert('Watch Video Reward is public and YouTube-first', str_contains($registry, '/watch-reward.php') && str_contains($registry, 'youtube_watch_milestones'));
$assert('Campaign API saves YouTube milestone rules', str_contains($campaignsApi, 'mg_campaign_youtube_id') && str_contains($campaignsApi, 'watch_video_required_percent') && str_contains($campaignsApi, 'watch_video_milestone_'));
$assert('Campaign builder includes YouTube milestone fields', str_contains($builderJs, 'installWatchVideoFields') && str_contains($builderJs, 'watch_video_milestone_3_reward_template_id'));
$assert('Public watch page exists', str_contains($page, 'data-watch-video-reward') && str_contains($page, 'youtube.com/iframe_api'));
$assert('Public watch JavaScript tracks YouTube progress', str_contains($watchJs, 'YT.Player') && str_contains($watchJs, 'progress_percent') && str_contains($watchJs, '/api/public/campaigns/watch-progress.php'));
$assert('Progress API records and issues milestone rewards', str_contains($progressApi, 'watch_reward.progress') && str_contains($progressApi, 'watch_reward.issued') && str_contains($progressApi, 'mg_watch_reward_issue'));
$assert('Progress API prevents duplicate milestone gifts', str_contains($progressApi, 'mg_watch_reward_already_issued') && str_contains($progressApi, 'milestone_percent'));
$assert('Progress API uses Watch Video Reward source', str_contains($progressApi, 'watch_video_reward'));
$assert('SQL migration adds Watch Video Reward enum values', str_contains($sql, 'watch_video_reward') && str_contains($sql, 'ALTER TABLE campaigns') && str_contains($sql, 'ALTER TABLE campaign_contacts') && str_contains($sql, 'ALTER TABLE wallet_items'));
$assert('Workflow covers Watch Video Reward files', str_contains($workflow, 'watch-reward.php') && str_contains($workflow, 'watch-progress.php') && str_contains($workflow, 'validate_watch_video_reward_v1_4.php'));

foreach ($checks as [$label, $passed]) echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
if ($failures) {
    echo PHP_EOL . 'Watch Video Reward v1.4 validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Watch Video Reward v1.4 validation passed.' . PHP_EOL;
