<?php
declare(strict_types=1);

require_once __DIR__ . '/_media_progress_v2.php';

mg_media_reward_progress_v2('listen_music_reward', [
    'label' => 'listen',
    'noun' => 'listened gift',
    'default_provider' => 'spotify',
    'providers' => ['spotify', 'uploaded'],
    'provider_field' => 'audio_provider',
    'input_provider' => 'audio_provider',
    'match' => [
        'spotify_track_id' => 'spotify_track_id',
        'uploaded_asset_id' => 'uploaded_asset_id',
    ],
    'context_inputs' => [
        'audio_provider' => 'audio_provider',
        'spotify_track_id' => 'spotify_track_id',
        'uploaded_asset_id' => 'uploaded_asset_id',
        'uploaded_audio_url' => 'uploaded_audio_url',
        'duration_seconds' => 'duration_seconds',
        'current_time_seconds' => 'current_time_seconds',
    ],
]);
