<?php
declare(strict_types=1);

require_once __DIR__ . '/_media.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission($method === 'GET' ? 'fulfillment.analytics.view' : 'catalog.assets.manage');
$pdo = mg_db();

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT mpj.public_id, ca.public_id AS asset_id, cav.public_id AS variant_id,
                mpj.job_type, mpj.status, mpj.attempts, mpj.max_attempts,
                mpj.next_attempt_at, mpj.failure_message, mpj.completed_at,
                mpj.created_at, mpj.updated_at
         FROM media_processing_jobs mpj
         INNER JOIN catalog_assets ca ON ca.id = mpj.source_asset_id
         LEFT JOIN catalog_asset_variants cav ON cav.id = mpj.variant_id
         WHERE ca.owner_user_id = ? ORDER BY mpj.created_at DESC, mpj.id DESC LIMIT 200'
    );
    $stmt->execute([(int) $user['id']]);
    mg_ok(['jobs' => $stmt->fetchAll()]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$assetId = strtolower(trim((string) ($input['asset_id'] ?? '')));
$jobTypes = is_array($input['job_types'] ?? null) ? $input['job_types'] : [];
$allowedJobs = ['transcode','thumbnail','poster','normalize_audio','scan','metadata'];
$jobTypes = array_values(array_unique(array_filter(array_map('strval', $jobTypes), static fn(string $type): bool => in_array($type, $allowedJobs, true))));
if ($assetId === '' || !$jobTypes) mg_fail('Asset and processing jobs are required.', 422);

$assetStmt = $pdo->prepare("SELECT id, asset_type FROM catalog_assets WHERE public_id = ? AND owner_user_id = ? AND status IN ('pending','ready') LIMIT 1");
$assetStmt->execute([$assetId, (int) $user['id']]);
$asset = $assetStmt->fetch();
if (!$asset) mg_fail('Asset not found.', 404);

$created = [];
foreach ($jobTypes as $jobType) {
    if ($jobType === 'transcode' && (string) $asset['asset_type'] !== 'video') continue;
    if ($jobType === 'normalize_audio' && (string) $asset['asset_type'] !== 'audio') continue;
    if (in_array($jobType, ['thumbnail','poster'], true) && !in_array((string) $asset['asset_type'], ['image','video'], true)) continue;
    $publicId = mg_feed_uuid();
    $pdo->prepare(
        "INSERT INTO media_processing_jobs
         (public_id, source_asset_id, job_type, status, attempts, max_attempts, next_attempt_at, payload_json, created_at, updated_at)
         VALUES (?, ?, ?, 'queued', 0, 5, NOW(), ?, NOW(), NOW())"
    )->execute([$publicId, (int) $asset['id'], $jobType, json_encode(['requested_by' => (int) $user['id']], JSON_UNESCAPED_SLASHES)]);
    $created[] = ['job_id' => $publicId, 'job_type' => $jobType];
}
if (!$created) mg_fail('No compatible processing jobs were selected.', 422);
mg_audit('media.processing_queued', 'catalog_asset', ['asset_id' => $assetId, 'jobs' => $created], (int) $user['id']);
mg_ok(['asset_id' => $assetId, 'jobs' => $created], 'Media processing queued.', 201);
