<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/admin-permission-matrix.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-games.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-game-program-integration.php';

mg_require_method('POST');
$user = mg_require_api_user();
$actorId = (int)$user['id'];
$canManage = mg_admin_permission_user_has($user, 'admin.hosted_games.manage')
    || mg_admin_permission_user_has($user, 'admin.settings.manage');
if (!$canManage) mg_fail('Hosted Games management permission is required.', 403);

$pdo = mg_db();
if (!mg_hosted_game_schema_ready($pdo)) {
    mg_fail('Hosted Games setup is incomplete. Import database/hosted_games_management_v1.sql.', 503);
}

$input = mg_input();
mg_require_csrf_for_write($input);
if (function_exists('mg_rate_limit')) {
    mg_rate_limit('admin.hosted_games.integration', 'user:' . $actorId, 40, 300);
}

$gamePublicId = strtolower(trim((string)($input['game_id'] ?? '')));
$programPublicId = strtolower(trim((string)($input['program_id'] ?? '')));
if ($gamePublicId === '' || $programPublicId === '') {
    mg_fail('Hosted game and Distribution Program are required.', 422);
}

try {
    $pdo->beginTransaction();
    $game = mg_hosted_game_by_public_id($pdo, $gamePublicId, true);
    if (!$game) throw new MgHostedGameException('Hosted game not found.');
    if ((string)$game['status'] === 'archived') {
        throw new MgHostedGameException('Archived games cannot be configured.');
    }

    $merchantUserId = (int)$game['merchant_user_id'];
    $resolved = mg_hosted_game_resolve_program_integration($pdo, $merchantUserId, $programPublicId, true);
    $program = $resolved['program'];
    $campaign = $resolved['campaign'];
    $reward = $resolved['reward'];

    mg_hosted_game_ensure_runtime_integration($pdo, $game, $actorId, (int)$program['id']);
    $pdo->prepare(
        "UPDATE hosted_games
         SET distribution_program_id=?,campaign_id=?,pppm_template_id=?,integration_status='ready',updated_by_user_id=?,updated_at=NOW()
         WHERE id=?"
    )->execute([(int)$program['id'], (int)$campaign['id'], (int)$reward['id'], $actorId, (int)$game['id']]);

    $pdo->commit();
    mg_audit('admin.hosted_game.integration_configured', 'hosted_game', [
        'game_id' => $gamePublicId,
        'merchant_user_id' => $merchantUserId,
        'program_id' => (string)$program['public_id'],
        'campaign_id' => (string)$campaign['public_id'],
        'reward_template_id' => (string)$reward['public_id'],
        'selection_source' => 'distribution_program',
    ], $actorId);

    mg_ok([
        'game_id' => $gamePublicId,
        'program' => ['id' => (string)$program['public_id'], 'name' => (string)$program['name']],
        'campaign' => ['id' => (string)$campaign['public_id'], 'title' => (string)$campaign['title']],
        'reward' => ['id' => (string)$reward['public_id'], 'title' => (string)$reward['title']],
    ], 'Distribution Program, campaign, reward inventory, and game API integration configured by Microgifter Admin.');
} catch (MgHostedGameException|InvalidArgumentException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($e->getMessage(), 409);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Hosted game admin integration failed: ' . $e->getMessage());
    mg_fail('Unable to configure the hosted-game Distribution Program.', 500);
}
