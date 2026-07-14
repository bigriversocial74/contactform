<?php
declare(strict_types=1);

/**
 * Documentation-only configuration reference.
 *
 * Reward Drop reads production values from environment variables. Do not rename
 * this file to config.php and do not commit real credentials.
 */
return [
    'MG_REWARD_DROP_API_BASE_URL' => 'https://microgifter.com',
    'MG_REWARD_DROP_PUBLIC_URL' => 'https://microgifter.com/games/reward-drop',
    'MG_REWARD_DROP_API_KEY' => 'set_in_server_environment_only',
    'MG_REWARD_DROP_PROGRAM_ID' => 'distribution_program_public_id',
    'MG_REWARD_DROP_TEMPLATE_ID' => 'pppm_template_public_id',
    'MG_REWARD_DROP_WEBHOOK_SECRET' => 'set_in_server_environment_only',
    'MG_REWARD_DROP_STATE_KEY' => 'independent_random_server_secret',
    'MG_REWARD_DROP_TARGET_SCORE' => '12',
    'MG_REWARD_DROP_DURATION_SECONDS' => '20',
    'MG_REWARD_DROP_MIN_PLAY_SECONDS' => '8',
    'MG_REWARD_DROP_REWARD_COOLDOWN_HOURS' => '24',
];
