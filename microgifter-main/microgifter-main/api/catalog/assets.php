<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission('catalog.assets.manage');

if ($method === 'GET') {
    $stmt = mg_db()->prepare(
        'SELECT public_id, asset_type, storage_provider, storage_key, original_filename, mime_type,
                byte_size, checksum_sha256, width_px, height_px, duration_ms, status, metadata_json,
                created_at, updated_at
         FROM catalog_assets WHERE owner_user_id = ? ORDER BY created_at DESC, id DESC'
    );
    $stmt->execute([(int) $user['id']]);
    mg_ok(['assets' => $stmt->fetchAll()]);
}

if ($method !== 'POST') {
    mg_fail('Method not allowed.', 405);
}

$input = mg_input();
mg_require_csrf_for_write($input);
$assetType = trim((string) ($input['asset_type'] ?? ''));
$provider = trim((string) ($input['storage_provider'] ?? ''));
$storageKey = trim((string) ($input['storage_key'] ?? ''));
$allowedTypes = ['image','audio','video','document','download','qr_template','other'];

if (!in_array($assetType, $allowedTypes, true)) {
    mg_fail('Invalid asset type.', 422);
}
if ($provider === '' || mb_strlen($provider) > 80) {
    mg_fail('Invalid storage provider.', 422);
}
if ($storageKey === '' || mb_strlen($storageKey) > 500 || str_contains($storageKey, '..')) {
    mg_fail('Invalid storage key.', 422);
}

$publicId = mg_catalog_uuid();
$stmt = mg_db()->prepare(
    "INSERT INTO catalog_assets
     (public_id, owner_user_id, asset_type, storage_provider, storage_key, original_filename,
      mime_type, byte_size, checksum_sha256, width_px, height_px, duration_ms, status,
      metadata_json, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())"
);
$stmt->execute([
    $publicId,
    (int) $user['id'],
    $assetType,
    $provider,
    $storageKey,
    trim((string) ($input['original_filename'] ?? '')) ?: null,
    trim((string) ($input['mime_type'] ?? '')) ?: null,
    isset($input['byte_size']) ? max(0, (int) $input['byte_size']) : null,
    trim((string) ($input['checksum_sha256'] ?? '')) ?: null,
    isset($input['width_px']) ? max(0, (int) $input['width_px']) : null,
    isset($input['height_px']) ? max(0, (int) $input['height_px']) : null,
    isset($input['duration_ms']) ? max(0, (int) $input['duration_ms']) : null,
    mg_catalog_json($input['metadata'] ?? null),
]);

mg_audit('catalog.asset_registered', 'catalog_asset', ['asset_id' => $publicId], (int) $user['id']);
mg_ok(['asset_id' => $publicId, 'status' => 'pending'], 'Asset metadata registered.', 201);
