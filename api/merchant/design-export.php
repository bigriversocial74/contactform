<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_export_user_can(array $user, string $permission): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    if (array_intersect($roles, ['merchant', 'admin', 'super_admin'])) return true;
    $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
    return in_array($permission, $permissions, true) || in_array('merchant.manage', $permissions, true);
}

function mg_export_require(array $user, string $permission): void
{
    if (!mg_export_user_can($user, $permission)) mg_fail('Permission denied.', 403);
}

function mg_export_clean_text(mixed $value, string $fallback, int $max): string
{
    $text = trim((string) $value);
    if ($text === '') $text = $fallback;
    $text = preg_replace('/\s+/', ' ', $text) ?: $fallback;
    return mb_substr($text, 0, $max);
}

function mg_export_json(mixed $value, int $maxBytes = 1048576): ?string
{
    if ($value === null || $value === '' || $value === []) return null;
    if (!is_array($value)) mg_fail('Export payload must be an object.', 422);
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || strlen($json) > $maxBytes) mg_fail('Export payload is too large.', 422);
    return $json;
}

function mg_export_type(mixed $value): string
{
    $type = strtolower(trim((string) $value));
    return in_array($type, ['print_pdf','social_png','qr_svg','qr_png','zip_package','proof'], true) ? $type : 'proof';
}

function mg_export_asset_type(mixed $value): string
{
    $type = strtolower(trim((string) $value));
    return in_array($type, ['logo','image','ai_image','qr_svg','qr_png','proof_pdf','print_pdf','social_png','zip_package','thumbnail','other'], true) ? $type : 'other';
}

function mg_export_project(PDO $pdo, int $workspaceId, ?string $publicId): ?array
{
    if (!$publicId) return null;
    $stmt = $pdo->prepare('SELECT * FROM merchant_design_projects WHERE public_id=? AND workspace_id=? LIMIT 1');
    $stmt->execute([$publicId, $workspaceId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function mg_export_qr(PDO $pdo, int $workspaceId, ?string $publicId): ?array
{
    if (!$publicId) return null;
    $stmt = $pdo->prepare("SELECT * FROM merchant_qr_codes WHERE public_id=? AND workspace_id=? AND status <> 'archived' LIMIT 1");
    $stmt->execute([$publicId, $workspaceId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function mg_export_format_asset(array $row): array
{
    return [
        'id' => (string) $row['public_id'],
        'asset_type' => (string) $row['asset_type'],
        'name' => (string) $row['name'],
        'status' => (string) $row['status'],
        'storage_driver' => (string) $row['storage_driver'],
        'source_url' => $row['source_url'] ?? null,
        'public_url' => $row['public_url'] ?? null,
        'mime_type' => $row['mime_type'] ?? null,
        'width_px' => $row['width_px'] !== null ? (int) $row['width_px'] : null,
        'height_px' => $row['height_px'] !== null ? (int) $row['height_px'] : null,
        'metadata' => json_decode((string) ($row['metadata_json'] ?? ''), true) ?: [],
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_export_format_job(array $row): array
{
    return [
        'id' => (string) $row['public_id'],
        'export_type' => (string) $row['export_type'],
        'status' => (string) $row['status'],
        'options' => json_decode((string) ($row['options_json'] ?? ''), true) ?: [],
        'manifest' => json_decode((string) ($row['manifest_json'] ?? ''), true) ?: [],
        'error_message' => $row['error_message'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

$user = mg_require_api_user();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$workspaceId = (int) $workspace['id'];
$userId = (int) $user['id'];

if ($method === 'GET') {
    mg_export_require($user, 'merchant.design_assets.view');
    $assets = $pdo->prepare('SELECT * FROM merchant_design_assets WHERE workspace_id=? AND status <> "archived" ORDER BY updated_at DESC LIMIT 100');
    $assets->execute([$workspaceId]);
    $jobs = $pdo->prepare('SELECT * FROM merchant_design_export_jobs WHERE workspace_id=? ORDER BY created_at DESC LIMIT 50');
    $jobs->execute([$workspaceId]);
    mg_ok(['assets' => array_map('mg_export_format_asset', $assets->fetchAll()), 'export_jobs' => array_map('mg_export_format_job', $jobs->fetchAll())]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$action = strtolower(trim((string) ($input['action'] ?? 'queue_export')));
mg_export_require($user, 'merchant.design_assets.manage');

if ($action === 'queue_export') {
    $project = mg_export_project($pdo, $workspaceId, trim((string) ($input['project_id'] ?? '')) ?: null);
    $exportType = mg_export_type($input['export_type'] ?? 'proof');
    $jobId = mg_merchant_uuid();
    $manifest = is_array($input['manifest'] ?? null) ? $input['manifest'] : [];
    if ($project) {
        $manifest['project_id'] = (string) $project['public_id'];
        $manifest['format_key'] = (string) $project['format_key'];
        $manifest['project_type'] = (string) $project['project_type'];
    }
    $manifest['renderer_status'] = 'queued_for_renderer';
    $pdo->prepare("INSERT INTO merchant_design_export_jobs (public_id,workspace_id,merchant_user_id,project_id,export_type,status,options_json,manifest_json,requested_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,'queued',?,?,?,NOW(),NOW())")
        ->execute([$jobId, $workspaceId, $userId, $project ? (int) $project['id'] : null, $exportType, mg_export_json($input['options'] ?? []), mg_export_json($manifest), $userId]);
    mg_audit('merchant.design_export_queued', 'merchant_design_export_job', ['job_id' => $jobId, 'export_type' => $exportType], $userId);
    mg_ok(['job_id' => $jobId, 'status' => 'queued', 'export_type' => $exportType], 'Export job queued.', 201);
}

if ($action === 'create_qr_asset') {
    $qr = mg_export_qr($pdo, $workspaceId, trim((string) ($input['qr_code_id'] ?? '')) ?: null);
    if (!$qr) mg_fail('QR code not found.', 404);
    $assetType = mg_export_asset_type($input['asset_type'] ?? 'qr_svg');
    if (!in_array($assetType, ['qr_svg','qr_png'], true)) $assetType = 'qr_svg';
    $assetId = mg_merchant_uuid();
    $assetName = mg_export_clean_text($input['name'] ?? ((string) $qr['label'] . ' QR Asset'), 'QR Asset', 180);
    $metadata = [
        'payload_url' => (string) $qr['qr_payload_url'],
        'destination_url' => (string) $qr['destination_url'],
        'renderer_required' => true,
        'render_note' => 'Renderer should create a scannable QR image from payload_url.',
    ];
    $pdo->prepare("INSERT INTO merchant_design_assets (public_id,workspace_id,merchant_user_id,qr_code_id,asset_type,name,status,storage_driver,source_url,mime_type,metadata_json,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,? ,?,'candidate','generated',?,?,?, ?,?,NOW(),NOW())")
        ->execute([$assetId, $workspaceId, $userId, (int) $qr['id'], $assetType, $assetName, (string) $qr['qr_payload_url'], $assetType === 'qr_svg' ? 'image/svg+xml' : 'image/png', mg_export_json($metadata), $userId, $userId]);
    $jobId = mg_merchant_uuid();
    $pdo->prepare("INSERT INTO merchant_design_export_jobs (public_id,workspace_id,merchant_user_id,output_asset_id,export_type,status,options_json,manifest_json,requested_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,'queued',?,?,?,NOW(),NOW())")
        ->execute([$jobId, $workspaceId, $userId, (int) $pdo->lastInsertId(), $assetType, mg_export_json($input['options'] ?? []), mg_export_json($metadata), $userId]);
    mg_audit('merchant.qr_asset_queued', 'merchant_design_asset', ['asset_id' => $assetId, 'qr_id' => (string) $qr['public_id'], 'asset_type' => $assetType], $userId);
    mg_ok(['asset_id' => $assetId, 'job_id' => $jobId, 'status' => 'queued', 'asset_type' => $assetType], 'QR asset queued.', 201);
}

if ($action === 'save_asset') {
    $assetId = mg_merchant_uuid();
    $assetType = mg_export_asset_type($input['asset_type'] ?? 'other');
    $name = mg_export_clean_text($input['name'] ?? 'Design asset', 'Design asset', 180);
    $project = mg_export_project($pdo, $workspaceId, trim((string) ($input['project_id'] ?? '')) ?: null);
    $pdo->prepare("INSERT INTO merchant_design_assets (public_id,workspace_id,merchant_user_id,project_id,asset_type,name,status,storage_driver,source_url,public_url,mime_type,width_px,height_px,metadata_json,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,'candidate',?,?,?,?,?,?,?,?,?,NOW(),NOW())")
        ->execute([$assetId, $workspaceId, $userId, $project ? (int) $project['id'] : null, $assetType, $name, in_array((string) ($input['storage_driver'] ?? 'external_url'), ['external_url','local','s3','generated','pending'], true) ? (string) $input['storage_driver'] : 'external_url', trim((string) ($input['source_url'] ?? '')) ?: null, trim((string) ($input['public_url'] ?? '')) ?: null, trim((string) ($input['mime_type'] ?? '')) ?: null, !empty($input['width_px']) ? (int) $input['width_px'] : null, !empty($input['height_px']) ? (int) $input['height_px'] : null, mg_export_json($input['metadata'] ?? []), $userId, $userId]);
    mg_ok(['asset_id' => $assetId, 'asset_type' => $assetType], 'Design asset saved.', 201);
}

if ($action === 'link_campaign') {
    $project = mg_export_project($pdo, $workspaceId, trim((string) ($input['project_id'] ?? '')) ?: null);
    $qr = mg_export_qr($pdo, $workspaceId, trim((string) ($input['qr_code_id'] ?? '')) ?: null);
    $campaignType = strtolower(trim((string) ($input['campaign_type'] ?? 'custom')));
    if (!in_array($campaignType, ['promotional_crm','newsletter','contest','landing_page','distribution','product','custom'], true)) $campaignType = 'custom';
    $campaignRef = mg_export_clean_text($input['campaign_ref'] ?? '', '', 180);
    if ($campaignRef === '') mg_fail('Campaign reference is required.', 422);
    $linkId = mg_merchant_uuid();
    $pdo->prepare('INSERT INTO merchant_design_campaign_links (public_id,workspace_id,merchant_user_id,project_id,qr_code_id,campaign_type,campaign_ref,label,metadata_json,created_by_user_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())')
        ->execute([$linkId, $workspaceId, $userId, $project ? (int) $project['id'] : null, $qr ? (int) $qr['id'] : null, $campaignType, $campaignRef, mg_export_clean_text($input['label'] ?? '', '', 180) ?: null, mg_export_json($input['metadata'] ?? []), $userId]);
    mg_audit('merchant.design_campaign_linked', 'merchant_design_campaign_link', ['link_id' => $linkId, 'campaign_type' => $campaignType, 'campaign_ref' => $campaignRef], $userId);
    mg_ok(['link_id' => $linkId], 'Campaign link saved.', 201);
}

mg_fail('Unsupported export action.', 422);
