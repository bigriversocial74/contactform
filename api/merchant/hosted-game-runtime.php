<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-game-runtime-toggle.php';

mg_require_method('POST');
$user = mg_merchant_require_permission('merchant.hosted_games.manage');
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$merchantUserId = (int)$workspace['merchant_user_id'];

if (!mg_hosted_game_schema_ready($pdo)) {
    mg_fail('Hosted Games setup is incomplete. Import database/hosted_games_management_v1.sql.', 503);
}

$input = mg_input();
mg_require_csrf_for_write($input);
if (function_exists('mg_rate_limit')) {
    mg_rate_limit('merchant.hosted_games.runtime', 'user:' . $merchantUserId, 60, 300);
}

$gamePublicId = strtolower(trim((string)($input['game_id'] ?? '')));
$enabledValue = $input['enabled'] ?? null;
$enabled = filter_var($enabledValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($gamePublicId === '' || $enabled === null) {
    mg_fail('Hosted game and enabled status are required.', 422);
}

try {
    $pdo->beginTransaction();
    $game = mg_hosted_game_for_merchant($pdo, $merchantUserId, $gamePublicId, true);
    $updated = mg_hosted_game_set_runtime_enabled($pdo, $game, $merchantUserId, $enabled);
    $runtime = mg_hosted_game_managed_runtime_state($pdo, $updated);
    $pdo->commit();

    mg_audit(
        $enabled ? 'merchant.hosted_game.enabled' : 'merchant.hosted_game.disabled',
        'hosted_game',
        [
            'game_id' => $gamePublicId,
            'configuration_source' => 'distribution_program',
            'credentials_preserved' => true,
        ],
        $merchantUserId
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
    error_log('Hosted game merchant runtime toggle failed: ' . $e->getMessage());
    mg_fail('Unable to change the hosted-game runtime status.', 500);
}
