<?php
declare(strict_types=1);

require_once __DIR__ . '/_stories.php';
require_once dirname(__DIR__) . '/social/_account_restrictions.php';

mg_require_method('POST');
$input = mg_input();
$user = mg_require_api_user();
mg_require_csrf_for_write($input);
$pdo = mg_db();
$userId = (int)$user['id'];
mg_rate_limit('stories.upload', 'user:' . $userId, 20, 60);

try {
    mg_stories_require_schema($pdo);
    mg_require_user_not_restricted($pdo, $userId, 'uploading');
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 409);
}

$file = $_FILES['media'] ?? null;
$kind = strtolower(trim((string)($input['media_type'] ?? '')));
if (!is_array($file) || !in_array($kind, ['image','video'], true)) mg_fail('Choose an image or video story file.', 422);

$error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($error !== UPLOAD_ERR_OK) {
    $messages = [UPLOAD_ERR_INI_SIZE => 'The file exceeds the server upload limit.', UPLOAD_ERR_FORM_SIZE => 'The file exceeds the allowed upload size.', UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.', UPLOAD_ERR_NO_FILE => 'Choose a story file to upload.', UPLOAD_ERR_NO_TMP_DIR => 'The upload service is temporarily unavailable.', UPLOAD_ERR_CANT_WRITE => 'The upload could not be stored.', UPLOAD_ERR_EXTENSION => 'The upload was blocked by the server.'];
    mg_fail($messages[$error] ?? 'The story upload did not complete.', 422);
}

$tmp = (string)($file['tmp_name'] ?? '');
$size = (int)($file['size'] ?? 0);
$limits = ['image' => MG_STORIES_IMAGE_MAX_BYTES, 'video' => MG_STORIES_VIDEO_MAX_BYTES];
if ($tmp === '' || !is_uploaded_file($tmp) || $size < 1 || $size > $limits[$kind]) mg_fail('The selected story file is not allowed.', 422);

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = strtolower((string)$finfo->file($tmp));
$types = ['image' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'], 'video' => ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov']];
if (!isset($types[$kind][$mime])) mg_fail('That story format is not supported.', 422);

$width = null; $height = null; $durationMs = null;
if ($kind === 'image') {
    $dimensions = @getimagesize($tmp);
    if (!is_array($dimensions)) mg_fail('The image could not be verified.', 422);
    $width = (int)($dimensions[0] ?? 0); $height = (int)($dimensions[1] ?? 0);
    if ($width < 1 || $height < 1 || $width > 12000 || $height > 12000 || ($width * $height) > 40000000) mg_fail('Image dimensions are not allowed.', 422);
} else {
    $clientDuration = isset($input['duration_seconds']) ? (float)$input['duration_seconds'] : 0.0;
    if ($clientDuration > (MG_STORIES_MAX_VIDEO_SECONDS + 0.25)) mg_fail('Stories must be 30 seconds or less.', 422);
    $durationSeconds = null;
    if (function_exists('shell_exec')) {
        $probe = @shell_exec('command -v ffprobe 2>/dev/null');
        if (is_string($probe) && trim($probe) !== '') {
            $cmd = trim($probe) . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($tmp) . ' 2>/dev/null';
            $out = @shell_exec($cmd);
            if (is_string($out) && trim($out) !== '' && is_numeric(trim($out))) $durationSeconds = (float)trim($out);
        }
    }
    if ($durationSeconds !== null) {
        if ($durationSeconds > (MG_STORIES_MAX_VIDEO_SECONDS + 0.25)) mg_fail('Stories must be 30 seconds or less.', 422);
        $durationMs = (int)round($durationSeconds * 1000);
    } elseif ($clientDuration > 0) {
        $durationMs = (int)round($clientDuration * 1000);
    }
}

$publicId = mg_stories_uuid();
$storageKey = mg_storage_feed_key($userId, $publicId, $types[$kind][$mime]);
try {
    $absolutePath = mg_storage_store_uploaded_file($tmp, $storageKey);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_security_log('error', 'stories.media_storage_unavailable', 'Persistent story media storage is unavailable.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $userId);
    mg_fail('Persistent media storage is unavailable. The upload was not saved.', 503);
}

$checksum = hash_file('sha256', $absolutePath) ?: null;
$original = preg_replace('/[\x00-\x1F\x7F]+/u', '', basename((string)($file['name'] ?? 'story'))) ?? 'story';
$original = mb_substr($original !== '' ? $original : 'story', 0, 255);
$metadata = json_encode(['source' => 'feed_story', 'story_state' => 'unattached', 'storage_class' => 'persistent', 'max_duration_seconds' => MG_STORIES_MAX_VIDEO_SECONDS, 'uploaded_at' => gmdate('c')], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

try {
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO catalog_assets (public_id,owner_user_id,asset_type,storage_provider,storage_key,original_filename,mime_type,byte_size,checksum_sha256,width_px,height_px,duration_ms,status,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'ready', ?, NOW(), NOW())")
        ->execute([$publicId, $userId, $kind, 'persistent_local', $storageKey, $original, $mime, $size, $checksum, $width, $height, $durationMs, $metadata]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_storage_delete_asset_file('persistent_local', $storageKey);
    mg_security_log('error', 'stories.media_upload_failed', 'Story media registration failed.', ['exception_class' => $error::class, 'media_type' => $kind], $userId);
    mg_fail('Unable to register the story media.', 500);
}

mg_audit('stories.media_uploaded', 'catalog_asset', ['asset_id' => $publicId, 'asset_type' => $kind, 'byte_size' => $size], $userId);
mg_ok(['asset_id' => $publicId, 'type' => $kind, 'url' => mg_storage_asset_public_url($publicId), 'original_filename' => $original, 'mime_type' => $mime, 'byte_size' => $size, 'width_px' => $width, 'height_px' => $height, 'duration_ms' => $durationMs, 'max_duration_seconds' => MG_STORIES_MAX_VIDEO_SECONDS], 'Story media uploaded.', 201);
