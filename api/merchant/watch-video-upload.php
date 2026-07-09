<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/storage.php';

function mg_watch_video_upload_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}
function mg_watch_video_upload_key(int $merchantId, string $publicId, string $extension): string
{
    $extension = strtolower(trim($extension));
    if ($merchantId < 1 || preg_match('/^[a-f0-9-]{36}$/i', $publicId) !== 1 || preg_match('/^[a-z0-9]{2,8}$/', $extension) !== 1) {
        throw new InvalidArgumentException('Invalid watch video storage parameters.');
    }
    return mg_storage_normalize_key('campaigns/watch-video/' . gmdate('Y/m') . '/merchant-' . $merchantId . '/' . str_replace('-', '', strtolower($publicId)) . '.' . $extension);
}
function mg_watch_video_upload_file_array(string $field): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) return [];
    $file = $_FILES[$field];
    return ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) ? [] : $file;
}

mg_require_method('POST');
$user = mg_merchant_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);
if (function_exists('mg_rate_limit')) mg_rate_limit('merchant.watch_video_upload', 'user:' . $merchantId, 12, 60);

$file = mg_watch_video_upload_file_array('video');
if ($file === []) mg_fail('Choose a video to upload.', 422);
$error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($error !== UPLOAD_ERR_OK) {
    $messages = [UPLOAD_ERR_INI_SIZE => 'The video exceeds the server upload limit.', UPLOAD_ERR_FORM_SIZE => 'The video exceeds the allowed upload size.', UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.', UPLOAD_ERR_NO_FILE => 'Choose a video to upload.', UPLOAD_ERR_NO_TMP_DIR => 'The upload service is temporarily unavailable.', UPLOAD_ERR_CANT_WRITE => 'The video could not be stored.', UPLOAD_ERR_EXTENSION => 'The upload was blocked by the server.'];
    mg_fail($messages[$error] ?? 'The video upload did not complete.', 422);
}
$tmp = (string)($file['tmp_name'] ?? '');
$size = (int)($file['size'] ?? 0);
if ($tmp === '' || !is_uploaded_file($tmp) || $size < 1 || $size > 262144000) mg_fail('Video must be 250MB or smaller.', 422);
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = strtolower((string)$finfo->file($tmp));
$types = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];
if (!isset($types[$mime])) mg_fail('Use MP4, WebM, or MOV video files.', 422);
$publicId = mg_watch_video_upload_uuid();
$storageKey = mg_watch_video_upload_key($merchantId, $publicId, $types[$mime]);
try {
    $absolutePath = mg_storage_store_uploaded_file($tmp, $storageKey);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_security_log('error', 'watch_video.upload_storage_unavailable', 'Persistent watch video storage is unavailable.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Persistent media storage is unavailable. The video was not saved.', 503);
}
$checksum = hash_file('sha256', $absolutePath) ?: null;
$original = preg_replace('/[\x00-\x1F\x7F]+/u', '', basename((string)($file['name'] ?? 'watch-video'))) ?: 'watch-video';
$original = mb_substr($original, 0, 255);
$metadata = json_encode(['source' => 'watch_video_reward', 'storage_class' => 'persistent', 'uploaded_at' => gmdate('c'), 'usage' => 'campaign_watch_reward'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
try {
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO catalog_assets (public_id,owner_user_id,asset_type,storage_provider,storage_key,original_filename,mime_type,byte_size,checksum_sha256,width_px,height_px,duration_ms,status,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NULL,NULL,NULL,'ready',?,NOW(),NOW())")
        ->execute([$publicId, $merchantId, 'video', 'persistent_local', $storageKey, $original, $mime, $size, $checksum, $metadata]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_storage_delete_asset_file('persistent_local', $storageKey);
    mg_security_log('error', 'watch_video.upload_registration_failed', 'Watch Video Reward upload registration failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to register the uploaded video.', 500);
}
mg_audit('watch_video.uploaded', 'catalog_asset', ['asset_id' => $publicId, 'byte_size' => $size, 'mime_type' => $mime], $merchantId);
mg_ok(['asset_id' => $publicId, 'url' => mg_storage_asset_public_url($publicId), 'provider' => 'uploaded', 'original_filename' => $original, 'mime_type' => $mime, 'byte_size' => $size], 'Video uploaded.', 201);
