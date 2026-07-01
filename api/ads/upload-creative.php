<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_ads.php';

mg_require_method('POST');
$user = mg_require_api_user();
$pdo = mg_db();
$input = mg_input();
mg_require_csrf_for_write($input);
mg_ads_require_merchant_user($user, $pdo);

function mg_ads_upload_base_dir(): string
{
    return dirname(__DIR__, 2) . '/uploads/ad-creatives';
}

function mg_ads_upload_public_url(int $merchantId, string $filename): string
{
    return '/uploads/ad-creatives/' . $merchantId . '/' . rawurlencode($filename);
}

function mg_ads_upload_file_array(string $field): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) return [];
    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [];
    return $file;
}

function mg_ads_upload_detect_mime(string $path): string
{
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    if ($finfo) {
        $mime = (string)finfo_file($finfo, $path);
        finfo_close($finfo);
        return $mime;
    }
    return '';
}

try {
    if (function_exists('mg_rate_limit')) mg_rate_limit('ads.creative_upload', 'user:' . (int)$user['id'], 30, 60);
    $file = mg_ads_upload_file_array('creative_image');
    if ($file === []) mg_fail('Creative image file is required.', 422);

    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) mg_fail('Unable to upload creative image.', 422);

    $original = trim((string)($file['name'] ?? 'creative')) ?: 'creative';
    $size = (int)($file['size'] ?? 0);
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($size < 1 || $size > 8 * 1024 * 1024) mg_fail('Creative image must be 8MB or smaller.', 422);
    if ($tmp === '' || !is_uploaded_file($tmp)) mg_fail('Creative image upload is invalid.', 422);

    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($extension, $allowedExtensions, true)) mg_fail('Unsupported creative image file type.', 422);

    $mime = mg_ads_upload_detect_mime($tmp);
    $allowedMimes = ['image/jpeg','image/png','image/gif','image/webp'];
    if ($mime !== '' && !in_array($mime, $allowedMimes, true)) mg_fail('Uploaded file is not a supported image.', 422);

    if (function_exists('getimagesize')) {
        $imageInfo = @getimagesize($tmp);
        if (!is_array($imageInfo)) mg_fail('Uploaded file is not a valid image.', 422);
        $width = (int)($imageInfo[0] ?? 0);
        $height = (int)($imageInfo[1] ?? 0);
        if ($width < 100 || $height < 100) mg_fail('Creative image is too small. Use at least 100x100 pixels.', 422);
        if ($width > 6000 || $height > 6000) mg_fail('Creative image dimensions are too large.', 422);
    } else {
        $width = null;
        $height = null;
    }

    $merchantId = (int)$user['id'];
    $dir = mg_ads_upload_base_dir() . '/' . $merchantId;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) mg_fail('Unable to prepare creative upload storage.', 500);

    $safeBase = preg_replace('/[^a-z0-9_-]+/i', '-', pathinfo($original, PATHINFO_FILENAME)) ?: 'creative';
    $filename = strtolower(trim($safeBase, '-')) . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.' . ($extension === 'jpeg' ? 'jpg' : $extension);
    $target = $dir . '/' . $filename;
    if (!move_uploaded_file($tmp, $target)) mg_fail('Unable to store creative image.', 500);
    @chmod($target, 0644);

    mg_ok([
        'url' => mg_ads_upload_public_url($merchantId, $filename),
        'filename' => $filename,
        'original_name' => $original,
        'mime_type' => $mime,
        'size_bytes' => $size,
        'width' => $width,
        'height' => $height,
    ], 'Creative image uploaded.');
} catch (Throwable $error) {
    mg_security_log('warning', 'ads.creative_upload_failed', 'Campaign Ads creative upload failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], (int)($user['id'] ?? 0));
    if (function_exists('mg_fail')) mg_fail($error->getMessage(), 422);
    throw $error;
}
