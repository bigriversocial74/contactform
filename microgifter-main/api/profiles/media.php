<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/catalog/_catalog.php';
require_once dirname(__DIR__, 2) . '/includes/profiles.php';

$user = mg_require_api_user();
$userId = (int)$user['id'];
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

function mg_profile_media_asset(PDO $pdo, int $userId, string $assetId): array
{
    if (preg_match('/^[a-f0-9-]{36}$/', $assetId) !== 1) mg_fail('Invalid media identifier.', 422);
    $stmt = $pdo->prepare(
        "SELECT public_id,storage_provider,storage_key,original_filename,mime_type,byte_size,width_px,height_px,status
         FROM catalog_assets WHERE public_id=? AND owner_user_id=? AND asset_type='image' LIMIT 1"
    );
    $stmt->execute([$assetId, $userId]);
    $asset = $stmt->fetch();
    if (!$asset) mg_fail('Profile media not found.', 404);
    return $asset;
}

function mg_profile_media_serve(array $asset): never
{
    if ((string)$asset['status'] !== 'ready' || (string)$asset['storage_provider'] !== 'private_local') mg_fail('Profile media unavailable.', 404);
    $root = realpath(dirname(__DIR__, 2) . '/storage/private');
    $path = realpath(dirname(__DIR__, 2) . '/storage/private/' . ltrim((string)$asset['storage_key'], '/'));
    if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) mg_fail('Profile media unavailable.', 404);
    $size = filesize($path);
    if ($size === false) mg_fail('Profile media unavailable.', 404);
    header('Content-Type: ' . ((string)$asset['mime_type'] ?: 'application/octet-stream'));
    header('Content-Length: ' . $size);
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline; filename="' . rawurlencode((string)($asset['original_filename'] ?: 'profile-media')) . '"');
    readfile($path);
    exit;
}

$pdo = mg_db();
if ($method === 'GET') {
    $assetId = strtolower(trim((string)($_GET['asset'] ?? '')));
    mg_profile_media_serve(mg_profile_media_asset($pdo, $userId, $assetId));
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
mg_require_csrf_for_write($_POST);
$role = strtolower(trim((string)($_POST['role'] ?? '')));
if (!in_array($role, ['avatar', 'cover'], true)) mg_fail('Invalid profile media role.', 422);
if (!isset($_FILES['file']) || !is_array($_FILES['file'])) mg_fail('Choose an image to upload.', 422);
$file = $_FILES['file'];
if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) mg_fail('The profile image upload failed.', 422);
$tmp = (string)($file['tmp_name'] ?? '');
if ($tmp === '' || !is_uploaded_file($tmp)) mg_fail('Invalid uploaded image.', 422);
$maxBytes = $role === 'avatar' ? 5 * 1024 * 1024 : 10 * 1024 * 1024;
$size = (int)($file['size'] ?? 0);
if ($size < 1 || $size > $maxBytes) mg_fail('The image exceeds the allowed file size.', 422);

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string)$finfo->file($tmp);
$extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
if (!isset($extensions[$mime])) mg_fail('Use a JPEG, PNG, WebP, or GIF image.', 422);
$dimensions = @getimagesize($tmp);
if (!is_array($dimensions) || (int)$dimensions[0] < 1 || (int)$dimensions[1] < 1) mg_fail('The uploaded file is not a valid image.', 422);
if ((int)$dimensions[0] > 12000 || (int)$dimensions[1] > 12000) mg_fail('The image dimensions are too large.', 422);

$assetId = mg_catalog_uuid();
$relative = 'profile/' . $userId . '/' . $assetId . '.' . $extensions[$mime];
$directory = dirname(__DIR__, 2) . '/storage/private/profile/' . $userId;
if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) mg_fail('Unable to prepare profile media storage.', 500);
$target = dirname(__DIR__, 2) . '/storage/private/' . $relative;
if (!move_uploaded_file($tmp, $target)) mg_fail('Unable to store the profile image.', 500);
@chmod($target, 0640);

try {
    $stmt = $pdo->prepare(
        "INSERT INTO catalog_assets
         (public_id,owner_user_id,asset_type,storage_provider,storage_key,original_filename,mime_type,byte_size,
          checksum_sha256,width_px,height_px,status,metadata_json,created_at,updated_at)
         VALUES (?,?, 'image','private_local',?,?,?,?,?,?,?,'ready',?,NOW(),NOW())"
    );
    $stmt->execute([
        $assetId,
        $userId,
        $relative,
        mb_substr(trim((string)($file['name'] ?? 'profile-image')), 0, 255),
        $mime,
        $size,
        hash_file('sha256', $target),
        (int)$dimensions[0],
        (int)$dimensions[1],
        json_encode(['profile_role' => $role], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
} catch (Throwable $error) {
    @unlink($target);
    throw $error;
}

mg_audit('profile.media_uploaded', 'catalog_asset', ['asset_id' => $assetId, 'role' => $role], $userId);
mg_ok([
    'asset' => [
        'id' => $assetId,
        'role' => $role,
        'mime_type' => $mime,
        'byte_size' => $size,
        'width' => (int)$dimensions[0],
        'height' => (int)$dimensions[1],
        'public_url' => '/api/public/media.php?asset=' . rawurlencode($assetId),
        'preview_url' => '/api/profiles/media.php?asset=' . rawurlencode($assetId),
    ],
], 'Profile image uploaded.', 201);
