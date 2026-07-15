<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/admin-permission-matrix.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-game-cover-images.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'HEAD'], true)) mg_fail('Method not allowed.', 405);

$gamePublicId = strtolower(trim((string)($_GET['game'] ?? '')));
$assetPublicId = strtolower(trim((string)($_GET['asset'] ?? '')));
if (preg_match('/^[a-f0-9-]{36}$/', $gamePublicId) !== 1 || preg_match('/^[a-f0-9-]{36}$/', $assetPublicId) !== 1) {
    mg_fail('Invalid hosted game cover reference.', 422);
}

$pdo = mg_db();
if (!mg_hosted_game_schema_ready($pdo) || !mg_hosted_game_table_exists($pdo, 'catalog_assets')) {
    mg_fail('Hosted game cover unavailable.', 404);
}
$game = mg_hosted_game_by_public_id($pdo, $gamePublicId, false);
if (!$game || !mg_hosted_game_cover_reference_matches((string)($game['cover_url'] ?? ''), $gamePublicId, $assetPublicId)) {
    mg_fail('Hosted game cover not found.', 404);
}

$user = mg_current_user();
$userId = (int)($user['id'] ?? 0);
$isOwner = $userId > 0 && $userId === (int)$game['merchant_user_id'];
$isAdmin = is_array($user) && (
    mg_admin_permission_user_has($user, 'admin.hosted_games.view')
    || mg_admin_permission_user_has($user, 'admin.hosted_games.manage')
    || mg_admin_permission_user_has($user, 'admin.settings.manage')
);
$isPublic = (string)$game['status'] === 'active';
if (!$isPublic && !$isOwner && !$isAdmin) mg_fail('Hosted game cover not found.', 404);

$stmt = $pdo->prepare(
    "SELECT public_id,owner_user_id,storage_provider,storage_key,original_filename,mime_type,byte_size,
            checksum_sha256,width_px,height_px,status
     FROM catalog_assets
     WHERE public_id=? AND owner_user_id=? AND asset_type='image' AND status='ready'
     LIMIT 1"
);
$stmt->execute([$assetPublicId, (int)$game['merchant_user_id']]);
$asset = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$asset || (string)$asset['storage_provider'] !== 'private_local') mg_fail('Hosted game cover not found.', 404);

try {
    $path = mg_hosted_game_storage_path((string)$asset['storage_key']);
} catch (Throwable) {
    mg_fail('Hosted game cover unavailable.', 404);
}
if (!is_file($path) || !is_readable($path)) mg_fail('Hosted game cover unavailable.', 404);
$size = filesize($path);
if ($size === false || $size < 1 || ((int)($asset['byte_size'] ?? 0) > 0 && (int)$asset['byte_size'] !== (int)$size)) {
    mg_fail('Hosted game cover unavailable.', 404);
}
$checksum = strtolower(trim((string)($asset['checksum_sha256'] ?? '')));
$etag = '"hgc-' . (preg_match('/^[a-f0-9]{64}$/', $checksum) === 1 ? substr($checksum, 0, 32) : substr(hash('sha256', $assetPublicId . '|' . $size), 0, 32)) . '"';
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    header('ETag: ' . $etag);
    http_response_code(304);
    exit;
}

$mime = (string)($asset['mime_type'] ?: 'application/octet-stream');
$filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename((string)($asset['original_filename'] ?: 'hosted-game-cover'))) ?: 'hosted-game-cover';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (int)$size);
header('Content-Disposition: inline; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode((string)($asset['original_filename'] ?: $filename)));
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');
header('Referrer-Policy: no-referrer');
header('ETag: ' . $etag);
header('Vary: Cookie, Authorization');
header($isPublic ? 'Cache-Control: public, max-age=300, stale-while-revalidate=60' : 'Cache-Control: private, no-store');
if ($method === 'HEAD') exit;

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
while (ob_get_level() > 0) ob_end_clean();
readfile($path);
exit;
