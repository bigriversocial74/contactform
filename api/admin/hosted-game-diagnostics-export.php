<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/admin-permission-matrix.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-game-diagnostics-export.php';

mg_require_method('GET');
$user = mg_require_api_user();
$actorId = (int)$user['id'];
$allowed = mg_admin_permission_user_has($user,'admin.hosted_games.diagnostics.manage') || mg_admin_permission_user_has($user,'admin.settings.manage');
if (!$allowed) mg_fail('Hosted Games diagnostics permission is required.',403);
$pdo = mg_db();
$gamePublicId = trim((string)($_GET['game_id'] ?? ''));
if ($gamePublicId === '') mg_fail('Hosted game is required.',422);
try {
    $game = mg_hosted_game_by_public_id($pdo,$gamePublicId,false);
    if (!$game) mg_fail('Hosted game not found.',404);
    mg_hosted_game_diagnostics_export($pdo,$game,$_GET);
} catch (InvalidArgumentException|MgHostedGameException $error) {
    mg_fail($error->getMessage(),422);
} catch (Throwable $error) {
    mg_security_log('error','admin.hosted_game.diagnostics_export_failed','Unable to export hosted game diagnostics.',['game_id'=>$gamePublicId,'message'=>$error->getMessage()],$actorId);
    mg_fail('Unable to export hosted game diagnostics.',500);
}
