<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/admin-permission-matrix.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-game-analytics-report.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_api_user();
$actorId = (int)$user['id'];
$permission = $method === 'GET' ? 'admin.hosted_games.analytics.view' : 'admin.hosted_games.diagnostics.manage';
$allowed = mg_admin_permission_user_has($user,$permission) || mg_admin_permission_user_has($user,'admin.settings.manage');
if (!$allowed) mg_fail('Hosted Games analytics permission is required.',403);
$pdo = mg_db();
if (!mg_hosted_game_schema_ready($pdo)) mg_fail('Hosted Games setup is incomplete.',503);
if (!mg_hosted_game_observability_schema_ready($pdo)) mg_fail('Hosted Games analytics setup is incomplete. Import database/hosted_games_analytics_diagnostics_v1.sql.',503);

if ($method === 'GET') {
    $gamePublicId = trim((string)($_GET['game_id'] ?? ''));
    if ($gamePublicId === '') mg_fail('Hosted game is required.',422);
    try {
        $game = mg_hosted_game_by_public_id($pdo,$gamePublicId,false);
        if (!$game) mg_fail('Hosted game not found.',404);
        mg_ok(mg_hosted_game_analytics_payload($pdo,$game,$_GET));
    } catch (InvalidArgumentException|MgHostedGameException $error) {
        mg_fail($error->getMessage(),422);
    } catch (Throwable $error) {
        mg_security_log('error','admin.hosted_game.analytics_failed','Unable to load hosted game analytics.',['game_id'=>$gamePublicId,'message'=>$error->getMessage()],$actorId);
        mg_fail('Unable to load hosted game analytics.',500);
    }
}

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
if (function_exists('mg_rate_limit')) mg_rate_limit('admin.hosted.game.diagnostics', 'user:' . $actorId, 180, 300);
$gamePublicId = trim((string)($input['game_id'] ?? ''));
$diagnosticPublicId = trim((string)($input['diagnostic_id'] ?? ''));
$status = strtolower(trim((string)($input['status'] ?? 'resolved')));
if ($gamePublicId === '' || $diagnosticPublicId === '') mg_fail('Hosted game and diagnostic are required.',422);

try {
    $pdo->beginTransaction();
    $game = mg_hosted_game_by_public_id($pdo,$gamePublicId,true);
    if (!$game) throw new MgHostedGameException('Hosted game not found.');
    mg_hosted_game_observability_resolve($pdo,(int)$game['id'],$diagnosticPublicId,$actorId,$status);
    $pdo->commit();
    mg_audit('admin.hosted_game.diagnostic_status_changed','hosted_game_diagnostic',['game_id'=>$gamePublicId,'diagnostic_id'=>$diagnosticPublicId,'status'=>$status],$actorId);
    mg_ok(['diagnostic_id'=>$diagnosticPublicId,'status'=>$status],'Diagnostic status updated.');
} catch (InvalidArgumentException|MgHostedGameException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(),422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','admin.hosted_game.diagnostic_update_failed','Unable to update hosted game diagnostic.',['game_id'=>$gamePublicId,'diagnostic_id'=>$diagnosticPublicId,'message'=>$error->getMessage()],$actorId);
    mg_fail('Unable to update hosted game diagnostic.',500);
}
