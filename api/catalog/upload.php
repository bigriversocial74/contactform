<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog.php';

const MG_CATALOG_UPLOAD_MAX_IMAGE_DIMENSION = 12000;
const MG_CATALOG_UPLOAD_MAX_IMAGE_PIXELS = 60000000;
const MG_CATALOG_UPLOAD_MAX_FILENAME_LENGTH = 180;

function mg_catalog_upload_error_message(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The selected media file is too large.',
        UPLOAD_ERR_PARTIAL => 'The media upload was incomplete. Please try again.',
        UPLOAD_ERR_NO_FILE => 'No media file was provided.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The media upload could not be stored by the server.',
        default => 'The media upload failed.',
    };
}

function mg_catalog_upload_safe_filename(string $name, string $extension): string
{
    $name = trim(basename($name));
    if ($name === '' || $name === '.' || $name === '..') {
        $name = 'upload.' . $extension;
    }
    $name = preg_replace('/[\x00-\x1F\x7F]+/', '', $name) ?? '';
    $name = preg_replace('/[^A-Za-z0-9._() -]+/', '-', $name) ?? '';
    $name = trim($name, " .-\t\n\r\0\x0B");
    if ($name === '') {
        $name = 'upload.' . $extension;
    }
    if (!str_contains($name, '.')) {
        $name .= '.' . $extension;
    }
    if (strlen($name) > MG_CATALOG_UPLOAD_MAX_FILENAME_LENGTH) {
        $base = pathinfo($name, PATHINFO_FILENAME) ?: 'upload';
        $name = substr($base, 0, 140) . '.' . $extension;
    }
    return $name;
}

function mg_catalog_upload_validate_image(string $path, string $mime): array
{
    $dimensions = @getimagesize($path);
    if (!is_array($dimensions)) {
        mg_fail('The selected image could not be validated.', 422);
    }

    $width = (int)($dimensions[0] ?? 0);
    $height = (int)($dimensions[1] ?? 0);
    if ($width < 1 || $height < 1) {
        mg_fail('The selected image dimensions are invalid.', 422);
    }
    if ($width > MG_CATALOG_UPLOAD_MAX_IMAGE_DIMENSION || $height > MG_CATALOG_UPLOAD_MAX_IMAGE_DIMENSION || ($width * $height) > MG_CATALOG_UPLOAD_MAX_IMAGE_PIXELS) {
        mg_fail('The selected image dimensions are too large.', 422);
    }

    $imageType = (int)($dimensions[2] ?? 0);
    $typeMap = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png' => IMAGETYPE_PNG,
        'image/webp' => IMAGETYPE_WEBP,
        'image/gif' => IMAGETYPE_GIF,
    ];
    if (!isset($typeMap[$mime]) || $typeMap[$mime] !== $imageType) {
        mg_fail('The selected image format could not be verified.', 422);
    }

    return [$width, $height];
}

function mg_catalog_upload_cleanup(?string $path): void
{
    if ($path && is_file($path)) {
        @unlink($path);
    }
}

mg_require_method('POST');
$user = mg_require_permission('catalog.assets.manage');
mg_require_csrf_for_write($_POST);
if (function_exists('mg_rate_limit')) {
    mg_rate_limit('catalog.asset_upload', 'user:' . (int)$user['id'], 60, 60);
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    mg_fail('No media file was provided.', 422);
}
$file = $_FILES['file'];
$uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($uploadError !== UPLOAD_ERR_OK) {
    mg_fail(mg_catalog_upload_error_message($uploadError), $uploadError === UPLOAD_ERR_NO_FILE ? 422 : 413);
}
$tmpName = (string)($file['tmp_name'] ?? '');
if ($tmpName === '' || !is_uploaded_file($tmpName) || !is_file($tmpName)) {
    mg_fail('Invalid uploaded file.', 422);
}

$role = trim((string)($_POST['role'] ?? 'other'));
$roleTypes = [
    'thumbnail' => ['image'],
    'cover' => ['image'],
    'inside_cover' => ['image'],
    'audio' => ['audio'],
    'video' => ['video'],
    'storefront_logo' => ['image'],
    'storefront_cover' => ['image'],
    'product_gallery' => ['image'],
];
if (!isset($roleTypes[$role])) mg_fail('Invalid media role.', 422);

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string)$finfo->file($tmpName);
$mimeMap = [
    'image/jpeg' => ['image','jpg',15728640],
    'image/png' => ['image','png',15728640],
    'image/webp' => ['image','webp',15728640],
    'image/gif' => ['image','gif',15728640],
    'audio/mpeg' => ['audio','mp3',31457280],
    'audio/mp4' => ['audio','m4a',31457280],
    'audio/wav' => ['audio','wav',31457280],
    'audio/x-wav' => ['audio','wav',31457280],
    'audio/ogg' => ['audio','ogg',31457280],
    'video/mp4' => ['video','mp4',157286400],
    'video/webm' => ['video','webm',157286400],
    'video/quicktime' => ['video','mov',157286400],
];
if (!isset($mimeMap[$mime])) mg_fail('Unsupported media format.', 422);
[$assetType,$extension,$maxBytes] = $mimeMap[$mime];
if (!in_array($assetType,$roleTypes[$role],true)) mg_fail('The selected file does not match this media role.',422);

$reportedSize = (int)($file['size'] ?? 0);
$actualSize = filesize($tmpName);
if ($actualSize === false) {
    mg_fail('The uploaded media file could not be measured.', 422);
}
$size = (int)$actualSize;
if ($size < 1 || $size > $maxBytes || ($reportedSize > 0 && $reportedSize !== $size)) {
    mg_fail('The selected media file is too large or invalid.',422);
}

$width = null;
$height = null;
if ($assetType === 'image') {
    [$width, $height] = mg_catalog_upload_validate_image($tmpName, $mime);
}

$assetId = mg_catalog_uuid();
$relativeKey = 'catalog/' . (int)$user['id'] . '/' . $assetId . '.' . $extension;
$storageRoot = dirname(__DIR__,2) . '/storage/private';
$directory = $storageRoot . '/catalog/' . (int)$user['id'];
if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) {
    mg_fail('Unable to prepare secure media storage.',500);
}
$rootReal = realpath($storageRoot);
$directoryReal = realpath($directory);
if ($rootReal === false || $directoryReal === false || !str_starts_with($directoryReal, $rootReal . DIRECTORY_SEPARATOR)) {
    mg_fail('Unable to validate secure media storage.',500);
}
$destination = $directoryReal . DIRECTORY_SEPARATOR . $assetId . '.' . $extension;

try {
    if (!move_uploaded_file($tmpName,$destination)) {
        mg_fail('Unable to store the uploaded media.',500);
    }
    @chmod($destination,0600);

    $checksum = hash_file('sha256',$destination);
    if (!is_string($checksum) || strlen($checksum) !== 64) {
        mg_catalog_upload_cleanup($destination);
        mg_fail('Unable to verify the uploaded media.',500);
    }
    $originalName = mg_catalog_upload_safe_filename((string)($file['name'] ?? 'upload.' . $extension), $extension);

    $stmt = mg_db()->prepare(
        "INSERT INTO catalog_assets
         (public_id,owner_user_id,asset_type,storage_provider,storage_key,original_filename,
          mime_type,byte_size,checksum_sha256,width_px,height_px,status,metadata_json,created_at,updated_at)
         VALUES (?,?,?,'private_local',?,?,?,?,?,?,?,'ready',?,NOW(),NOW())"
    );
    $stmt->execute([
        $assetId,(int)$user['id'],$assetType,$relativeKey,$originalName,$mime,$size,$checksum,$width,$height,
        json_encode(['builder_role'=>$role,'validation'=>'catalog_upload_hardened_v1'],JSON_UNESCAPED_SLASHES),
    ]);
} catch (Throwable $e) {
    mg_catalog_upload_cleanup($destination ?? null);
    mg_fail_unexpected(
        $e,
        'catalog.asset_upload_failed',
        'Unable to register the uploaded media.',
        500,
        [
            'role'=>$role,
            'mime'=>$mime,
            'asset_type'=>$assetType ?? null,
            'byte_size'=>$size ?? null,
        ],
        (int)$user['id']
    );
}

mg_audit('catalog.asset_uploaded','catalog_asset',[
    'asset_id'=>$assetId,'role'=>$role,'mime_type'=>$mime,'byte_size'=>$size,
],(int)$user['id']);
mg_ok([
    'asset_id'=>$assetId,
    'role'=>$role,
    'asset_type'=>$assetType,
    'mime_type'=>$mime,
    'byte_size'=>$size,
    'filename'=>$originalName,
    'width_px'=>$width,
    'height_px'=>$height,
    'preview_url'=>'/api/catalog/asset-file.php?id=' . rawurlencode($assetId),
],'Media uploaded.',201);
