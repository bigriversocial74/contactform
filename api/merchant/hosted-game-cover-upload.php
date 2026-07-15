<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-game-cover-images.php';

mg_require_method('POST');
$user = mg_merchant_require_permission('merchant.hosted_games.manage');
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$merchantUserId = (int)$workspace['merchant_user_id'];

if (!mg_hosted_game_schema_ready($pdo) || !mg_hosted_game_table_exists($pdo, 'catalog_assets')) {
    mg_fail('Hosted Games cover storage is not ready.', 503);
}
mg_require_csrf_for_write($_POST);
if (function_exists('mg_rate_limit')) {
    mg_rate_limit('merchant.hosted_games.cover_upload', 'user:' . $merchantUserId, 30, 300);
}

$gamePublicId = strtolower(trim((string)($_POST['game_id'] ?? '')));
if ($gamePublicId === '' || preg_match('/^[a-f0-9-]{36}$/', $gamePublicId) !== 1) {
    mg_fail('Hosted game is required.', 422);
}
if (!isset($_FILES['cover_image']) || !is_array($_FILES['cover_image'])) {
    mg_fail('Choose a cover image to upload.', 422);
}

try {
    $pdo->beginTransaction();
    $game = mg_hosted_game_for_merchant($pdo, $merchantUserId, $gamePublicId, true);
    $cover = mg_hosted_game_store_cover_image($pdo, $game, $_FILES['cover_image'], $merchantUserId);
    $pdo->commit();

    mg_audit('merchant.hosted_game.cover_uploaded', 'hosted_game', [
        'game_id' => $gamePublicId,
        'asset_id' => $cover['asset_id'],
        'mime_type' => $cover['mime_type'],
        'byte_size' => $cover['byte_size'],
        'width' => $cover['width'],
        'height' => $cover['height'],
    ], $merchantUserId);

    mg_ok(['game_id' => $gamePublicId, 'cover' => $cover], 'Hosted game cover image uploaded.', 201);
} catch (InvalidArgumentException|MgHostedGameException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.hosted_game.cover_upload_failed', 'Hosted game cover upload failed.', [
        'game_id' => $gamePublicId,
        'exception_type' => $error::class,
        'message' => $error->getMessage(),
    ], $merchantUserId);
    mg_fail('Unable to upload the hosted game cover image.', 500);
}
