<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog.php';

mg_require_method('POST');
$user = mg_require_permission('catalog.assets.manage');
mg_require_csrf_for_write($_POST);

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    mg_fail('No media file was provided.', 422);
}
$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    mg_fail('The media upload failed.', 422);
}
if (!is_uploaded_file((string)$file['tmp_name'])) {
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
$mime = (string)$finfo->file((string)$file['tmp_name']);
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

$size = (int)($file['size'] ?? 0);
if ($size < 1 || $size > $maxBytes) mg_fail('The selected media file is too large.',422);

$assetId = mg_catalog_uuid();
$relativeKey = 'catalog/' . (int)$user['id'] . '/' . $assetId . '.' . $extension;
$storageRoot = dirname(__DIR__,2) . '/storage/private';
$directory = $storageRoot . '/catalog/' . (int)$user['id'];
if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) {
    mg_fail('Unable to prepare secure media storage.',500);
}
$destination = $storageRoot . '/' . $relativeKey;
if (!move_uploaded_file((string)$file['tmp_name'],$destination)) mg_fail('Unable to store the uploaded media.',500);
@chmod($destination,0600);

$checksum = hash_file('sha256',$destination);
$originalName = basename((string)($file['name'] ?? 'upload.' . $extension));
$width = null;
$height = null;
if ($assetType === 'image') {
    $dimensions = @getimagesize($destination);
    if (is_array($dimensions)) {
        $width = (int)($dimensions[0] ?? 0) ?: null;
        $height = (int)($dimensions[1] ?? 0) ?: null;
    }
}

try {
    $stmt = mg_db()->prepare(
        "INSERT INTO catalog_assets
         (public_id,owner_user_id,asset_type,storage_provider,storage_key,original_filename,
          mime_type,byte_size,checksum_sha256,width_px,height_px,status,metadata_json,created_at,updated_at)
         VALUES (?,?,?,'private_local',?,?,?,?,?,?,?,'ready',?,NOW(),NOW())"
    );
    $stmt->execute([
        $assetId,(int)$user['id'],$assetType,$relativeKey,$originalName,$mime,$size,$checksum,$width,$height,
        json_encode(['builder_role'=>$role],JSON_UNESCAPED_SLASHES),
    ]);
} catch (Throwable $e) {
    @unlink($destination);
    mg_security_log('error','catalog.asset_upload_failed','Catalog asset upload failed.',[
        'role'=>$role,'mime'=>$mime,'exception_type'=>get_class($e),
    ],(int)$user['id']);
    mg_fail('Unable to register the uploaded media.',500);
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
