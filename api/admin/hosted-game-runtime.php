<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/admin-permission-matrix.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-game-runtime-toggle.php';

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
    mg_rate_limit('admin.hosted_games.runtime', 'user:' . $actorId, 60, 300);
}

$gamePublicId = strtolower(trim((string)($input['game_id'] ?? '')));
$enabledValue = $input['enabled'] ?? null;
$enabled = filter_var($enabledValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($gamePublicId === '' || $enabled === null) {
    mg_fail('Hosted game and enabled status are required.', 422);
}

try {
    $pdo->beginTransaction();
    $game = mg_hosted_game_by_public_id($pdo, $gamePublicId, true);
    if (!$game) throw new MgHostedGameException('Hosted game not found.');

    $updated = mg_hosted_game_set_runtime_enabled($pdo, $game, $actorId, $enabled);
    $runtime = mg_hosted_game_managed_runtime_state($pdo, $updated);
    $pdo->commit();

    mg_audit(
        $enabled ? 'admin.hosted_game.enabled' : 'admin.hosted_game.disabled',
        'hosted_game',
        [
            'game_id' => $gamePublicId,
            'merchant_user_id' => (int)$updated['merchant_user_id'],
            'configuration_source' => 'distribution_program',
            'credentials_preserved' => true,
        ],
        $actorId
    );

    mg_ok([
        'game_id' => $gamePublicId,
        'runtime' => $runtime,
    ], $enabled ? 'Hosted game enabled.' : 'Hosted game disabled.');
} catch (MgHostedGameException|InvalidArgumentException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($e->getMessage(), 409);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Hosted game admin runtime toggle failed: ' . $e->getMessage());
    mg_fail('Unable to change the hosted-game runtime status.', 500);
}
