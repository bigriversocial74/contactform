<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-game-diagnostics-export.php';

mg_require_method('GET');
$user = mg_merchant_require_permission('merchant.hosted_games.diagnostics.manage');
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo,$user);
$merchantUserId = (int)$workspace['merchant_user_id'];
$gamePublicId = trim((string)($_GET['game_id'] ?? ''));
if ($gamePublicId === '') mg_fail('Hosted game is required.',422);
try {
    $game = mg_hosted_game_for_merchant($pdo,$merchantUserId,$gamePublicId,false);
    mg_hosted_game_diagnostics_export($pdo,$game,$_GET);
} catch (InvalidArgumentException|MgHostedGameException $error) {
    mg_fail($error->getMessage(),422);
} catch (Throwable $error) {
    mg_security_log('error','merchant.hosted_game.diagnostics_export_failed','Unable to export hosted game diagnostics.',['game_id'=>$gamePublicId,'message'=>$error->getMessage()],$merchantUserId);
    mg_fail('Unable to export hosted game diagnostics.',500);
}
