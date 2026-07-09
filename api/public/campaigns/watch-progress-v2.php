<?php
declare(strict_types=1);
require_once __DIR__ . '/_media_progress_v2.php';
mg_media_reward_progress_v2('watch_video_reward', [
    'label' => 'watch',
    'noun' => 'watched gift',
    'default_provider' => 'youtube',
    'providers' => ['youtube','uploaded'],
    'provider_field' => 'video_provider',
    'input_provider' => 'video_provider',
    'match' => ['youtube_video_id' => 'video_id', 'uploaded_asset_id' => 'uploaded_asset_id'],
    'context_inputs' => [
        'video_provider' => 'video_provider',
        'video_id' => 'video_id',
        'uploaded_asset_id' => 'uploaded_asset_id',
        'uploaded_video_url' => 'uploaded_video_url',
        'duration_seconds' => 'duration_seconds',
        'current_time_seconds' => 'current_time_seconds',
    ],
]);
