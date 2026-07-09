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

$assert('Registry includes watch_video_reward', str_contains($registry, "'watch_video_reward'") && str_contains($registry, 'Watch Video Reward'));
$assert('Registry has public route and progress endpoint', str_contains($registry, '/watch-reward.php') && str_contains($registry, '/api/public/campaigns/watch-progress.php'));
$assert('Registry defines YouTube milestone mode', str_contains($registry, 'youtube_watch_milestones') && str_contains($registry, 'video_milestone_reward'));

$assert('Campaign API parses YouTube URL/video ID', str_contains($campaignsApi, 'mg_campaign_youtube_id') && str_contains($campaignsApi, 'watch_video_url'));
$assert('Campaign API stores required percent and milestones', str_contains($campaignsApi, 'watch_video_required_percent') && str_contains($campaignsApi, 'watch_video_milestone_1_percent') && str_contains($campaignsApi, 'milestones'));
$assert('Campaign API blocks active watch campaigns without YouTube ID', str_contains($campaignsApi, 'Active Watch Video Reward campaigns require a valid YouTube URL or video ID'));

$assert('Builder injects Watch Video Reward UI', str_contains($builderJs, 'installWatchVideoFields') && str_contains($builderJs, 'watch_video_reward'));
$assert('Builder supports milestone reward selectors', str_contains($builderJs, 'data-watch-video-reward-template-select') && str_contains($builderJs, 'watch_video_milestone_3_reward_template_id'));
$assert('Builder shows watch milestone rule summary', str_contains($builderJs, 'YouTube watch milestones'));

$assert('Public watch page exists and loads YouTube campaign', str_contains($page, 'data-watch-video-reward') && str_contains($page, 'Watch Video Reward'));
$assert('Public watch page includes YouTube iframe API', str_contains($page, 'youtube.com/iframe_api'));
$assert('Public watch JS posts progress API', str_contains($watchJs, '/api/public/campaigns/watch-progress.php') && str_contains($watchJs, 'YT.Player'));
$assert('Public watch JS tracks percent watched', str_contains($watchJs, 'progress_percent') && str_contains($watchJs, 'getDuration') && str_contains($watchJs, 'getCurrentTime'));

$assert('Progress API records watch events', str_contains($progressApi, 'watch_reward.progress') && str_contains($progressApi, 'watch_reward.started'));
$assert('Progress API issues milestone rewards', str_contains($progressApi, 'mg_watch_reward_issue') && str_contains($progressApi, 'watch_reward.issued'));
$assert('Progress API prevents duplicate milestone gifts', str_contains($progressApi, 'mg_watch_reward_already_issued') && str_contains($progressApi, "$.milestone_percent"));
$assert('Progress API uses wallet source_type watch_video_reward', str_contains($progressApi, "source_type='watch_video_reward'") && str_contains($progressApi, "'watch_video_reward'"));

$assert('SQL migration adds watch_video_reward campaign/source enums', str_contains($sql, "'watch_video_reward'") && str_contains($sql, 'ALTER TABLE campaigns') && str_contains($sql, 'ALTER TABLE wallet_items'));
$assert('Workflow lints watch page/API and runs validator', str_contains($workflow, 'watch-reward.php') && str_contains($workflow, 'api/public/campaigns/watch-progress.php') && str_contains($workflow, 'validate_watch_video_reward_v1_4.php'));

foreach ($checks as [$label, $passed]) echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
if ($failures) {
    echo PHP_EOL . 'Watch Video Reward v1.4 validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Watch Video Reward v1.4 validation passed.' . PHP_EOL;
