<?php
declare(strict_types=1);

require_once __DIR__ . '/_media.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission($method === 'GET' ? 'catalog.products.view' : 'fulfillment.rules.manage');
$pdo = mg_db();

if ($method === 'GET') {
    $productId = trim((string) ($_GET['product_id'] ?? ''));
    $stmt = $pdo->prepare(
        'SELECT dfr.public_id, cp.public_id AS product_id, cpv.public_id AS version_id,
                ca.public_id AS asset_id, ca.original_filename, ca.mime_type,
                dfr.access_mode, dfr.max_downloads, dfr.access_duration_seconds,
                dfr.filename_override, dfr.disposition, dfr.status, dfr.created_at, dfr.updated_at
         FROM digital_fulfillment_rules dfr
         INNER JOIN catalog_product_versions cpv ON cpv.id = dfr.product_version_id
         INNER JOIN catalog_products cp ON cp.id = cpv.product_id
         INNER JOIN catalog_assets ca ON ca.id = dfr.asset_id
         WHERE cp.merchant_user_id = ? AND (? = ? OR cp.public_id = ?)
         ORDER BY dfr.updated_at DESC, dfr.id DESC'
    );
    $stmt->execute([(int) $user['id'], $productId, '', $productId]);
    mg_ok(['rules' => $stmt->fetchAll()]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$productVersionId = strtolower(trim((string) ($input['product_version_id'] ?? '')));
$assetId = strtolower(trim((string) ($input['asset_id'] ?? '')));
$accessMode = trim((string) ($input['access_mode'] ?? 'download'));
$disposition = trim((string) ($input['disposition'] ?? 'attachment'));
$maxDownloads = isset($input['max_downloads']) && $input['max_downloads'] !== '' ? max(1, (int) $input['max_downloads']) : null;
$duration = isset($input['access_duration_seconds']) && $input['access_duration_seconds'] !== '' ? max(60, (int) $input['access_duration_seconds']) : null;
$filenameOverride = trim((string) ($input['filename_override'] ?? '')) ?: null;
if (!in_array($accessMode, ['stream','download','both'], true)) mg_fail('Invalid fulfillment access mode.', 422);
if (!in_array($disposition, ['inline','attachment'], true)) mg_fail('Invalid fulfillment disposition.', 422);
if ($filenameOverride !== null && mb_strlen($filenameOverride) > 255) mg_fail('Invalid download filename.', 422);

$versionStmt = $pdo->prepare(
    "SELECT cpv.id, cp.id AS product_id FROM catalog_product_versions cpv
     INNER JOIN catalog_products cp ON cp.id = cpv.product_id
     WHERE cpv.public_id = ? AND cp.merchant_user_id = ? AND cpv.version_status = 'published'
     LIMIT 1"
);
$versionStmt->execute([$productVersionId, (int) $user['id']]);
$version = $versionStmt->fetch();
if (!$version) mg_fail('Published product version not found.', 404);
$assetStmt = $pdo->prepare("SELECT id FROM catalog_assets WHERE public_id = ? AND owner_user_id = ? AND status = 'ready' AND moderation_status NOT IN ('blocked','takedown') LIMIT 1");
$assetStmt->execute([$assetId, (int) $user['id']]);
$assetDbId = $assetStmt->fetchColumn();
if (!$assetDbId) mg_fail('Eligible fulfillment asset not found.', 404);

$publicId = mg_feed_uuid();
$pdo->prepare(
    "INSERT INTO digital_fulfillment_rules
     (public_id, product_version_id, asset_id, access_mode, max_downloads,
      access_duration_seconds, filename_override, disposition, status, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
     ON DUPLICATE KEY UPDATE access_mode = VALUES(access_mode), max_downloads = VALUES(max_downloads),
       access_duration_seconds = VALUES(access_duration_seconds), filename_override = VALUES(filename_override),
       disposition = VALUES(disposition), status = 'active', updated_at = NOW()"
)->execute([$publicId, (int) $version['id'], (int) $assetDbId, $accessMode, $maxDownloads, $duration, $filenameOverride, $disposition]);

$stmt = $pdo->prepare('SELECT public_id FROM digital_fulfillment_rules WHERE product_version_id = ? AND asset_id = ? LIMIT 1');
$stmt->execute([(int) $version['id'], (int) $assetDbId]);
$ruleId = (string) $stmt->fetchColumn();
mg_audit('fulfillment.rule_saved', 'digital_fulfillment_rule', ['rule_id' => $ruleId, 'product_version_id' => $productVersionId], (int) $user['id']);
mg_ok(['rule_id' => $ruleId, 'status' => 'active'], 'Fulfillment rule saved.', 201);
