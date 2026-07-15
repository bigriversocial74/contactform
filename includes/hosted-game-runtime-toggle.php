<?php
declare(strict_types=1);

require_once __DIR__ . '/hosted-games.php';

/**
 * Determine whether a game has every durable dependency required to enable.
 * The Developer App may currently be paused because disabling a game pauses it.
 */
function mg_hosted_game_runtime_can_enable(array $game, array $readiness): bool
{
    return (string)($game['integration_status'] ?? '') === 'ready'
        && (bool)($readiness['release_ready'] ?? false)
        && (bool)($readiness['database_ready'] ?? false)
        && (bool)($readiness['api_key_ready'] ?? false)
        && (bool)($readiness['program_ready'] ?? false)
        && (bool)($readiness['campaign_ready'] ?? false)
        && (bool)($readiness['reward_ready'] ?? false)
        && (bool)($readiness['api_credential_ready'] ?? false)
        && (bool)($readiness['webhook_secret_ready'] ?? false)
        && (bool)($readiness['state_secret_ready'] ?? false);
}

/**
 * Return the public, non-secret runtime configuration state for a hosted game.
 * Raw API credentials, webhook secrets, and state secrets are never returned.
 */
function mg_hosted_game_managed_runtime_state(PDO $pdo, array $game): array
{
    $readiness = mg_hosted_game_readiness($pdo, $game);
    $secrets = mg_hosted_game_secrets($pdo, (int)$game['id']);

    return [
        'enabled' => (string)($game['status'] ?? '') === 'active',
        'status' => (string)($game['status'] ?? 'draft'),
        'can_enable' => mg_hosted_game_runtime_can_enable($game, $readiness),
        'configuration_source' => 'distribution_program',
        'credentials_managed' => true,
        'api_credential_configured' => trim((string)($secrets['api_credential'] ?? '')) !== '',
        'webhook_secret_configured' => trim((string)($secrets['webhook_secret'] ?? '')) !== '',
        'state_secret_configured' => trim((string)($secrets['state_secret'] ?? '')) !== '',
        'program_configured' => !empty($game['distribution_program_id']),
        'campaign_configured' => !empty($game['campaign_id']),
        'reward_template_configured' => !empty($game['pppm_template_id']),
        'readiness' => $readiness,
    ];
}

/**
 * Enable or disable one hosted game while preserving its encrypted credentials.
 * Enabling requires the complete release, Distribution Program integration,
 * signed webhook, and isolated database readiness contract.
 */
function mg_hosted_game_set_runtime_enabled(PDO $pdo, array $game, int $actorUserId, bool $enabled): array
{
    $gameId = (int)($game['id'] ?? 0);
    $merchantUserId = (int)($game['merchant_user_id'] ?? 0);
    if ($gameId < 1 || $merchantUserId < 1) {
        throw new MgHostedGameException('Hosted game runtime record is invalid.');
    }
    if ((string)($game['status'] ?? '') === 'archived') {
        throw new MgHostedGameException('Archived games cannot be enabled or disabled.');
    }

    if ($enabled) {
        if (!empty($game['developer_app_id'])) {
            $pdo->prepare(
                "UPDATE merchant_developer_apps
                 SET status='active',environment='live',updated_at=NOW()
                 WHERE id=? AND merchant_user_id=?"
            )->execute([(int)$game['developer_app_id'], $merchantUserId]);
        }

        $freshGame = mg_hosted_game_by_public_id($pdo, (string)$game['public_id'], false);
        if (!$freshGame) throw new MgHostedGameException('Hosted game could not be reloaded for readiness validation.');
        $readiness = mg_hosted_game_readiness($pdo, $freshGame);
        if (!(bool)($readiness['publish_ready'] ?? false)) {
            throw new MgHostedGameException('Complete the game ZIP, Distribution Program integration, signed webhook, and isolated database before enabling this game.');
        }

        $pdo->prepare(
            "UPDATE hosted_games
             SET status='active',published_at=COALESCE(published_at,NOW()),archived_at=NULL,updated_by_user_id=?,updated_at=NOW()
             WHERE id=?"
        )->execute([$actorUserId, $gameId]);
    } else {
        $pdo->prepare(
            "UPDATE hosted_games
             SET status='paused',updated_by_user_id=?,updated_at=NOW()
             WHERE id=?"
        )->execute([$actorUserId, $gameId]);

        if (!empty($game['developer_app_id'])) {
            $pdo->prepare(
                "UPDATE merchant_developer_apps
                 SET status='paused',updated_at=NOW()
                 WHERE id=? AND merchant_user_id=?"
            )->execute([(int)$game['developer_app_id'], $merchantUserId]);
        }
    }

    $updated = mg_hosted_game_by_public_id($pdo, (string)$game['public_id'], false);
    if (!$updated) {
        throw new MgHostedGameException('Hosted game could not be reloaded after changing runtime status.');
    }
    return $updated;
}
