<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-game-upload.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-game-standard-v1.php';

mg_require_method('POST');
$user = mg_merchant_require_permission('merchant.hosted_games.manage');
mg_require_csrf_for_write($_POST);
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$merchantUserId = (int)$workspace['merchant_user_id'];
if (!mg_hosted_game_release_schema_ready($pdo)) mg_fail('Hosted Games Release and QA setup is incomplete. Import database/hosted_games_release_qa_foundation_v1.sql.', 503);
if (function_exists('mg_rate_limit')) mg_rate_limit('merchant.hosted_game.upload', 'user:' . $merchantUserId, 8, 600);

$gamePublicId = trim((string)($_POST['game_id'] ?? ''));
if ($gamePublicId === '') mg_fail('Save the hosted game record before uploading a ZIP.', 422);

try {
    $game = mg_hosted_game_for_merchant($pdo, $merchantUserId, $gamePublicId, false);
    if (!isset($_FILES['game_zip']) || !is_array($_FILES['game_zip'])) throw new MgHostedGameException('Select a game ZIP to upload.');
    $standardManifest = mg_hosted_game_standard_preflight_upload($_FILES['game_zip'], $game);
    $result = mg_hosted_game_process_upload($pdo, $game, $merchantUserId, 'merchant.hosted_game.release_uploaded', (string)($_POST['release_notes'] ?? ''));
    $result = mg_hosted_game_standard_finalize_release($pdo, $game, $result, $standardManifest, $merchantUserId);
    mg_ok($result, 'Game ZIP uploaded and validated as a draft release. Test and activate it from Release history.', 201);
} catch (InvalidArgumentException|MgHostedGameException $error) {
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    mg_security_log('error','merchant.hosted_game.upload_failed','Hosted game ZIP upload failed.',[
        'game_id'=>$gamePublicId,
        'filename'=>isset($_FILES['game_zip']['name']) ? basename((string)$_FILES['game_zip']['name']) : null,
        'byte_size'=>(int)($_FILES['game_zip']['size'] ?? 0),
        'message'=>$error->getMessage(),
    ],$merchantUserId);
    mg_fail('Unable to upload the game ZIP.',500);
}
