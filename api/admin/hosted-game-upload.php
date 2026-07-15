<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/admin-permission-matrix.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-game-upload.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-game-standard-v1.php';

mg_require_method('POST');
$user = mg_require_api_user();
$actorId = (int)$user['id'];
$canManage = mg_admin_permission_user_has($user, 'admin.hosted_games.manage')
    || mg_admin_permission_user_has($user, 'admin.settings.manage');
if (!$canManage) mg_fail('Hosted Games management permission is required.', 403);
mg_require_csrf_for_write($_POST);

$pdo = mg_db();
if (!mg_hosted_game_schema_ready($pdo)) mg_fail('Hosted Games setup is incomplete. Import database/hosted_games_management_v1.sql.', 503);
if (function_exists('mg_rate_limit')) mg_rate_limit('admin.hosted_game.upload', 'user:' . $actorId, 12, 600);

$gamePublicId = trim((string)($_POST['game_id'] ?? ''));
if ($gamePublicId === '') mg_fail('Save the hosted game record before uploading a ZIP.', 422);

try {
    $game = mg_hosted_game_by_public_id($pdo, $gamePublicId, false);
    if (!$game) mg_fail('Hosted game not found.', 404);
    if (!isset($_FILES['game_zip']) || !is_array($_FILES['game_zip'])) throw new MgHostedGameException('Select a game ZIP to upload.');
    $standardManifest = mg_hosted_game_standard_preflight_upload($_FILES['game_zip'], $game);
    $result = mg_hosted_game_process_upload($pdo, $game, $actorId, 'admin.hosted_game.release_uploaded');
    $result = mg_hosted_game_standard_finalize_release($pdo, $game, $result, $standardManifest, $actorId);
    mg_ok($result, 'Game ZIP uploaded, standardized, and activated by Microgifter Admin.', 201);
} catch (InvalidArgumentException|MgHostedGameException $error) {
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    mg_security_log('error','admin.hosted_game.upload_failed','Hosted game ZIP upload failed.',[
        'game_id'=>$gamePublicId,
        'filename'=>isset($_FILES['game_zip']['name']) ? basename((string)$_FILES['game_zip']['name']) : null,
        'byte_size'=>(int)($_FILES['game_zip']['size'] ?? 0),
        'message'=>$error->getMessage(),
    ],$actorId);
    mg_fail('Unable to upload the game ZIP.',500);
}
