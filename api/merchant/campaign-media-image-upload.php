<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/storage.php';

function mg_campaign_media_image_upload_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_campaign_media_image_upload_key(int $merchantId, string $publicId, string $extension): string
{
    $extension = strtolower(trim($extension));
    if ($merchantId < 1 || preg_match('/^[a-f0-9-]{36}$/i', $publicId) !== 1 || preg_match('/^[a-z0-9]{2,8}$/', $extension) !== 1) {
        throw new InvalidArgumentException('Invalid campaign image storage parameters.');
    }
    return mg_storage_normalize_key('campaigns/media-artwork/' . gmdate('Y/m') . '/merchant-' . $merchantId . '/' . str_replace('-', '', strtolower($publicId)) . '.' . $extension);
}

function mg_campaign_media_image_file_array(string $field): array
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
if (function_exists('mg_rate_limit')) mg_rate_limit('merchant.campaign_media_image_upload', 'user:' . $merchantId, 30, 60);

$file = mg_campaign_media_image_file_array('image');
if ($file === []) mg_fail('Choose an image to upload.', 422);
$error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($error !== UPLOAD_ERR_OK) {
    $messages = [UPLOAD_ERR_INI_SIZE => 'The image exceeds the server upload limit.', UPLOAD_ERR_FORM_SIZE => 'The image exceeds the allowed upload size.', UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.', UPLOAD_ERR_NO_FILE => 'Choose an image to upload.', UPLOAD_ERR_NO_TMP_DIR => 'The upload service is temporarily unavailable.', UPLOAD_ERR_CANT_WRITE => 'The image could not be stored.', UPLOAD_ERR_EXTENSION => 'The upload was blocked by the server.'];
    mg_fail($messages[$error] ?? 'The image upload did not complete.', 422);
}
$tmp = (string)($file['tmp_name'] ?? '');
$size = (int)($file['size'] ?? 0);
if ($tmp === '' || !is_uploaded_file($tmp) || $size < 1 || $size > 8388608) mg_fail('Image must be 8MB or smaller.', 422);
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = strtolower((string)$finfo->file($tmp));
$types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($types[$mime])) mg_fail('Use JPG, PNG, or WebP campaign artwork.', 422);
$dimensions = @getimagesize($tmp);
$width = is_array($dimensions) ? (int)($dimensions[0] ?? 0) : null;
$height = is_array($dimensions) ? (int)($dimensions[1] ?? 0) : null;
$publicId = mg_campaign_media_image_upload_uuid();
$storageKey = mg_campaign_media_image_upload_key($merchantId, $publicId, $types[$mime]);
try {
    $absolutePath = mg_storage_store_uploaded_file($tmp, $storageKey);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_security_log('error', 'campaign_media_image.upload_storage_unavailable', 'Persistent campaign image storage is unavailable.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Persistent media storage is unavailable. The image was not saved.', 503);
}
$checksum = hash_file('sha256', $absolutePath) ?: null;
$original = preg_replace('/[\x00-\x1F\x7F]+/u', '', basename((string)($file['name'] ?? 'campaign-artwork'))) ?: 'campaign-artwork';
$original = mb_substr($original, 0, 255);
$metadata = json_encode(['source' => 'campaign_media_artwork', 'storage_class' => 'persistent', 'uploaded_at' => gmdate('c'), 'usage' => 'watch_listen_campaign_artwork'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
try {
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO catalog_assets (public_id,owner_user_id,asset_type,storage_provider,storage_key,original_filename,mime_type,byte_size,checksum_sha256,width_px,height_px,duration_ms,status,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NULL,'ready',?,NOW(),NOW())")
        ->execute([$publicId, $merchantId, 'image', 'persistent_local', $storageKey, $original, $mime, $size, $checksum, $width, $height, $metadata]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_storage_delete_asset_file('persistent_local', $storageKey);
    mg_security_log('error', 'campaign_media_image.upload_registration_failed', 'Campaign image upload registration failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to register the uploaded image.', 500);
}
mg_audit('campaign_media_image.uploaded', 'catalog_asset', ['asset_id' => $publicId, 'byte_size' => $size, 'mime_type' => $mime], $merchantId);
mg_ok(['asset_id' => $publicId, 'url' => mg_storage_asset_public_url($publicId), 'provider' => 'uploaded', 'original_filename' => $original, 'mime_type' => $mime, 'byte_size' => $size, 'width_px' => $width, 'height_px' => $height], 'Campaign image uploaded.', 201);
