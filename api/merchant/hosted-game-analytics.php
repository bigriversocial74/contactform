<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-game-analytics-report.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_merchant_require_permission($method === 'GET' ? 'merchant.hosted_games.analytics.view' : 'merchant.hosted_games.diagnostics.manage');
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$merchantUserId = (int)$workspace['merchant_user_id'];
if (!mg_hosted_game_schema_ready($pdo)) mg_fail('Hosted Games setup is incomplete.', 503);
if (!mg_hosted_game_observability_schema_ready($pdo)) mg_fail('Hosted Games analytics setup is incomplete. Import database/hosted_games_analytics_diagnostics_v1.sql.', 503);

if ($method === 'GET') {
    $gamePublicId = trim((string)($_GET['game_id'] ?? ''));
    if ($gamePublicId === '') mg_fail('Hosted game is required.', 422);
    try {
        $game = mg_hosted_game_for_merchant($pdo,$merchantUserId,$gamePublicId,false);
        mg_ok(mg_hosted_game_analytics_payload($pdo,$game,$_GET));
    } catch (InvalidArgumentException|MgHostedGameException $error) {
        mg_fail($error->getMessage(),422);
    } catch (Throwable $error) {
        mg_security_log('error','merchant.hosted_game.analytics_failed','Unable to load hosted game analytics.',['game_id'=>$gamePublicId,'message'=>$error->getMessage()],$merchantUserId);
        mg_fail('Unable to load hosted game analytics.',500);
    }
}

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
if (function_exists('mg_rate_limit')) mg_rate_limit('merchant.hosted_game.diagnostics', 'user:' . $merchantUserId, 120, 300);
$gamePublicId = trim((string)($input['game_id'] ?? ''));
$diagnosticPublicId = trim((string)($input['diagnostic_id'] ?? ''));
$status = strtolower(trim((string)($input['status'] ?? 'resolved')));
if ($gamePublicId === '' || $diagnosticPublicId === '') mg_fail('Hosted game and diagnostic are required.',422);

try {
    $pdo->beginTransaction();
    $game = mg_hosted_game_for_merchant($pdo,$merchantUserId,$gamePublicId,true);
    $diagnostic = mg_hosted_game_observability_resolve($pdo,(int)$game['id'],$diagnosticPublicId,$merchantUserId,$status);
    $pdo->commit();
    mg_audit('merchant.hosted_game.diagnostic_status_changed','hosted_game_diagnostic',['game_id'=>$gamePublicId,'diagnostic_id'=>$diagnosticPublicId,'status'=>$status],$merchantUserId);
    mg_ok(['diagnostic_id'=>$diagnosticPublicId,'status'=>$status],'Diagnostic status updated.');
} catch (InvalidArgumentException|MgHostedGameException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(),422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','merchant.hosted_game.diagnostic_update_failed','Unable to update hosted game diagnostic.',['game_id'=>$gamePublicId,'diagnostic_id'=>$diagnosticPublicId,'message'=>$error->getMessage()],$merchantUserId);
    mg_fail('Unable to update hosted game diagnostic.',500);
}
