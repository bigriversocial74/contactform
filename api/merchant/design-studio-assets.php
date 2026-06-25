<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_design_user_can(array $user, string $permission): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    if (array_intersect($roles, ['merchant', 'admin', 'super_admin'])) return true;
    $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
    return in_array($permission, $permissions, true) || in_array('merchant.manage', $permissions, true);
}

function mg_design_require(array $user, string $permission): void
{
    if (!mg_design_user_can($user, $permission)) mg_fail('Permission denied.', 403);
}

function mg_design_clean_text(mixed $value, string $fallback, int $max): string
{
    $text = trim((string) $value);
    if ($text === '') $text = $fallback;
    $text = preg_replace('/\s+/', ' ', $text) ?: $fallback;
    return mb_substr($text, 0, $max);
}

function mg_design_json(mixed $value, string $message = 'Design payload must be an object.', int $maxBytes = 1048576): string
{
    if (!is_array($value)) mg_fail($message, 422);
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || strlen($json) > $maxBytes) mg_fail('Design payload is too large.', 422);
    return $json;
}

function mg_design_optional_json(mixed $value, int $maxBytes = 1048576): ?string
{
    if ($value === null || $value === '' || $value === []) return null;
    return mg_design_json($value, 'Design payload must be an object.', $maxBytes);
}

function mg_design_template_type(mixed $value): string
{
    $type = strtolower(trim((string) $value));
    return in_array($type, ['print', 'social', 'digital'], true) ? $type : 'print';
}

function mg_design_status(mixed $value, array $allowed, string $fallback): string
{
    $status = strtolower(trim((string) $value));
    return in_array($status, $allowed, true) ? $status : $fallback;
}

function mg_design_int_or_null(mixed $value): ?int
{
    if ($value === null || $value === '') return null;
    $int = (int) $value;
    return $int > 0 ? $int : null;
}

function mg_design_decimal_or_null(mixed $value): ?float
{
    if ($value === null || $value === '') return null;
    return max(0.0, (float) $value);
}

function mg_design_lookup_template_id(PDO $pdo, int $workspaceId, ?string $publicId): ?int
{
    if (!$publicId) return null;
    $stmt = $pdo->prepare("SELECT id FROM merchant_design_templates WHERE public_id=? AND (workspace_id=? OR template_scope IN ('system','admin')) AND status <> 'archived' LIMIT 1");
    $stmt->execute([$publicId, $workspaceId]);
    $row = $stmt->fetch();
    return $row ? (int) $row['id'] : null;
}

function mg_design_lookup_qr_id(PDO $pdo, int $workspaceId, ?string $publicId): ?int
{
    if (!$publicId) return null;
    $stmt = $pdo->prepare("SELECT id FROM merchant_qr_codes WHERE public_id=? AND workspace_id=? AND status <> 'archived' LIMIT 1");
    $stmt->execute([$publicId, $workspaceId]);
    $row = $stmt->fetch();
    return $row ? (int) $row['id'] : null;
}

function mg_design_lookup_brand_kit_id(PDO $pdo, int $workspaceId, ?string $publicId): ?int
{
    if (!$publicId) return null;
    $stmt = $pdo->prepare("SELECT id FROM merchant_brand_kits WHERE public_id=? AND workspace_id=? AND status <> 'archived' LIMIT 1");
    $stmt->execute([$publicId, $workspaceId]);
    $row = $stmt->fetch();
    return $row ? (int) $row['id'] : null;
}

function mg_design_format_template(array $row): array
{
    return [
        'id' => (string) $row['public_id'],
        'brand_kit_id' => $row['brand_kit_public_id'] ?? null,
        'template_scope' => (string) $row['template_scope'],
        'template_type' => (string) $row['template_type'],
        'category_key' => $row['category_key'] ?? null,
        'format_key' => (string) $row['format_key'],
        'name' => (string) $row['name'],
        'description' => $row['description'] ?? null,
        'status' => (string) $row['status'],
        'review_status' => (string) ($row['review_status'] ?? 'not_submitted'),
        'width_px' => $row['width_px'] !== null ? (int) $row['width_px'] : null,
        'height_px' => $row['height_px'] !== null ? (int) $row['height_px'] : null,
        'print_width_in' => $row['print_width_in'] !== null ? (float) $row['print_width_in'] : null,
        'print_height_in' => $row['print_height_in'] !== null ? (float) $row['print_height_in'] : null,
        'bleed_in' => $row['bleed_in'] !== null ? (float) $row['bleed_in'] : null,
        'layout' => json_decode((string) $row['layout_json'], true) ?: [],
        'default_copy' => json_decode((string) ($row['default_copy_json'] ?? ''), true) ?: [],
        'render_config' => json_decode((string) ($row['render_config_json'] ?? ''), true) ?: [],
        'qr_required' => (bool) $row['qr_required'],
        'is_presigned' => (bool) $row['is_presigned'],
        'is_featured' => (bool) ($row['is_featured'] ?? false),
        'signature_hash' => $row['signature_hash'] ?? null,
        'signed_at' => $row['signed_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_design_format_project(array $row): array
{
    return [
        'id' => (string) $row['public_id'],
        'brand_kit_id' => $row['brand_kit_public_id'] ?? null,
        'template_id' => $row['template_public_id'] ?? null,
        'qr_code_id' => $row['qr_public_id'] ?? null,
        'project_type' => (string) $row['project_type'],
        'format_key' => (string) $row['format_key'],
        'name' => (string) $row['name'],
        'status' => (string) $row['status'],
        'canvas' => json_decode((string) $row['canvas_json'], true) ?: [],
        'copy' => json_decode((string) ($row['copy_json'] ?? ''), true) ?: [],
        'media' => json_decode((string) ($row['media_json'] ?? ''), true) ?: [],
        'print_options' => json_decode((string) ($row['print_options_json'] ?? ''), true) ?: [],
        'export_manifest' => json_decode((string) ($row['export_manifest_json'] ?? ''), true) ?: [],
        'last_exported_at' => $row['last_exported_at'] ?? null,
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
    mg_design_require($user, 'merchant.design_studio.view');
    $mode = strtolower(trim((string) ($_GET['mode'] ?? 'bootstrap')));
    $templateType = mg_design_template_type($_GET['template_type'] ?? 'print');

    $templatesStmt = $pdo->prepare("SELECT t.*,bk.public_id brand_kit_public_id FROM merchant_design_templates t LEFT JOIN merchant_brand_kits bk ON bk.id=t.brand_kit_id WHERE t.status <> 'archived' AND t.template_type=? AND (t.workspace_id=? OR t.template_scope IN ('system','admin')) ORDER BY FIELD(t.template_scope,'merchant','admin','system'), t.is_featured DESC, t.updated_at DESC LIMIT 100");
    $templatesStmt->execute([$templateType, $workspaceId]);
    $templates = array_map('mg_design_format_template', $templatesStmt->fetchAll());

    $projects = [];
    if ($mode === 'bootstrap' || $mode === 'projects') {
        $projectsStmt = $pdo->prepare("SELECT p.*,t.public_id template_public_id,q.public_id qr_public_id,bk.public_id brand_kit_public_id FROM merchant_design_projects p LEFT JOIN merchant_design_templates t ON t.id=p.template_id LEFT JOIN merchant_qr_codes q ON q.id=p.qr_code_id LEFT JOIN merchant_brand_kits bk ON bk.id=p.brand_kit_id WHERE p.workspace_id=? AND p.status <> 'archived' ORDER BY p.updated_at DESC LIMIT 50");
        $projectsStmt->execute([$workspaceId]);
        $projects = array_map('mg_design_format_project', $projectsStmt->fetchAll());
    }

    $presetsStmt = $pdo->prepare("SELECT public_id,preset_key,name,category_key,generation_type,prompt_template_json,safety_json FROM merchant_design_ai_presets WHERE status='active' AND (workspace_id IS NULL OR workspace_id=?) ORDER BY FIELD(template_scope,'merchant','admin','system'), category_key, name LIMIT 50");
    $presetsStmt->execute([$workspaceId]);
    $presets = [];
    foreach ($presetsStmt->fetchAll() as $preset) {
        $presets[] = [
            'id' => (string) $preset['public_id'],
            'preset_key' => (string) $preset['preset_key'],
            'name' => (string) $preset['name'],
            'category_key' => $preset['category_key'] ?? null,
            'generation_type' => (string) $preset['generation_type'],
            'prompt_template' => json_decode((string) $preset['prompt_template_json'], true) ?: [],
            'safety' => json_decode((string) ($preset['safety_json'] ?? ''), true) ?: [],
        ];
    }

    mg_ok(['templates' => $templates, 'projects' => $projects, 'ai_presets' => $presets, 'workspace_id' => (string) $workspace['public_id']]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$action = strtolower(trim((string) ($input['action'] ?? 'save_project')));

if ($action === 'save_template') {
    mg_design_require($user, 'merchant.design_templates.manage');
    $publicId = mg_merchant_uuid();
    $brandKitDbId = mg_design_lookup_brand_kit_id($pdo, $workspaceId, isset($input['brand_kit_id']) ? trim((string) $input['brand_kit_id']) : null);
    $type = mg_design_template_type($input['template_type'] ?? 'print');
    $category = mg_design_clean_text($input['category_key'] ?? $type, $type, 80);
    $formatKey = mg_design_clean_text($input['format_key'] ?? 'custom', 'custom', 80);
    $name = mg_design_clean_text($input['name'] ?? 'Saved template', 'Saved template', 180);
    $description = mg_design_clean_text($input['description'] ?? '', '', 500) ?: null;
    $status = mg_design_status($input['status'] ?? 'active', ['draft','active','archived'], 'active');
    $layoutJson = mg_design_json($input['layout'] ?? [], 'Template layout must be an object.');
    $copyJson = mg_design_optional_json($input['default_copy'] ?? null);
    $renderJson = mg_design_optional_json($input['render_config'] ?? null);
    $signatureHash = hash('sha256', $layoutJson . '|' . (string) $copyJson . '|' . (string) $renderJson . '|' . $formatKey . '|' . $type);

    $pdo->prepare('INSERT INTO merchant_design_templates (public_id,workspace_id,merchant_user_id,brand_kit_id,template_scope,template_type,category_key,format_key,name,description,status,review_status,width_px,height_px,print_width_in,print_height_in,bleed_in,layout_json,default_copy_json,render_config_json,qr_required,is_presigned,signature_hash,signed_at,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,"not_submitted",?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,NOW(),NOW())')
        ->execute([$publicId,$workspaceId,$userId,$brandKitDbId,'merchant',$type,$category,$formatKey,$name,$description,$status,mg_design_int_or_null($input['width_px'] ?? null),mg_design_int_or_null($input['height_px'] ?? null),mg_design_decimal_or_null($input['print_width_in'] ?? null),mg_design_decimal_or_null($input['print_height_in'] ?? null),mg_design_decimal_or_null($input['bleed_in'] ?? null),$layoutJson,$copyJson,$renderJson,!empty($input['qr_required']) ? 1 : 0,1,$signatureHash,$userId,$userId]);
    $stmt = $pdo->prepare('SELECT t.*,bk.public_id brand_kit_public_id FROM merchant_design_templates t LEFT JOIN merchant_brand_kits bk ON bk.id=t.brand_kit_id WHERE t.public_id=? LIMIT 1');
    $stmt->execute([$publicId]);
    mg_audit('merchant.design_template_saved', 'merchant_design_template', ['template_id' => $publicId, 'format_key' => $formatKey, 'template_type' => $type, 'brand_kit_id' => $input['brand_kit_id'] ?? null], $userId);
    mg_ok(['template' => mg_design_format_template($stmt->fetch())], 'Template saved.', 201);
}

if ($action === 'save_project') {
    mg_design_require($user, 'merchant.design_projects.manage');
    $projectId = trim((string) ($input['id'] ?? ''));
    $wasUpdate = $projectId !== '';
    $brandKitDbId = mg_design_lookup_brand_kit_id($pdo, $workspaceId, isset($input['brand_kit_id']) ? trim((string) $input['brand_kit_id']) : null);
    $type = mg_design_template_type($input['project_type'] ?? 'print');
    $formatKey = mg_design_clean_text($input['format_key'] ?? 'custom', 'custom', 80);
    $name = mg_design_clean_text($input['name'] ?? 'Design Studio project', 'Design Studio project', 180);
    $status = mg_design_status($input['status'] ?? 'draft', ['draft','ready_for_review','approved','exported','archived'], 'draft');
    $templateDbId = mg_design_lookup_template_id($pdo, $workspaceId, isset($input['template_id']) ? trim((string) $input['template_id']) : null);
    $qrDbId = mg_design_lookup_qr_id($pdo, $workspaceId, isset($input['qr_code_id']) ? trim((string) $input['qr_code_id']) : null);
    $canvasJson = mg_design_json($input['canvas'] ?? [], 'Project canvas must be an object.');
    $copyJson = mg_design_optional_json($input['copy'] ?? null);
    $mediaJson = mg_design_optional_json($input['media'] ?? null);
    $printOptionsJson = mg_design_optional_json($input['print_options'] ?? null);
    $exportJson = mg_design_optional_json($input['export_manifest'] ?? null);

    if ($wasUpdate) {
        $stmt = $pdo->prepare('SELECT id FROM merchant_design_projects WHERE public_id=? AND workspace_id=? LIMIT 1');
        $stmt->execute([$projectId, $workspaceId]);
        $existing = $stmt->fetch();
        if (!$existing) mg_fail('Design project not found.', 404);
        $pdo->prepare('UPDATE merchant_design_projects SET brand_kit_id=?,template_id=?,qr_code_id=?,project_type=?,format_key=?,name=?,status=?,canvas_json=?,copy_json=?,media_json=?,print_options_json=?,export_manifest_json=?,updated_by_user_id=?,updated_at=NOW(),archived_at=IF(?="archived",COALESCE(archived_at,NOW()),NULL) WHERE id=?')
            ->execute([$brandKitDbId,$templateDbId,$qrDbId,$type,$formatKey,$name,$status,$canvasJson,$copyJson,$mediaJson,$printOptionsJson,$exportJson,$userId,$status,(int) $existing['id']]);
    } else {
        $projectId = mg_merchant_uuid();
        $pdo->prepare('INSERT INTO merchant_design_projects (public_id,workspace_id,merchant_user_id,brand_kit_id,template_id,qr_code_id,project_type,format_key,name,status,canvas_json,copy_json,media_json,print_options_json,export_manifest_json,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')
            ->execute([$projectId,$workspaceId,$userId,$brandKitDbId,$templateDbId,$qrDbId,$type,$formatKey,$name,$status,$canvasJson,$copyJson,$mediaJson,$printOptionsJson,$exportJson,$userId,$userId]);
    }

    $fresh = $pdo->prepare('SELECT p.*,t.public_id template_public_id,q.public_id qr_public_id,bk.public_id brand_kit_public_id FROM merchant_design_projects p LEFT JOIN merchant_design_templates t ON t.id=p.template_id LEFT JOIN merchant_qr_codes q ON q.id=p.qr_code_id LEFT JOIN merchant_brand_kits bk ON bk.id=p.brand_kit_id WHERE p.public_id=? AND p.workspace_id=? LIMIT 1');
    $fresh->execute([$projectId, $workspaceId]);
    mg_audit('merchant.design_project_saved', 'merchant_design_project', ['project_id' => $projectId, 'format_key' => $formatKey, 'project_type' => $type, 'brand_kit_id' => $input['brand_kit_id'] ?? null], $userId);
    mg_ok(['project' => mg_design_format_project($fresh->fetch())], 'Project saved.', $wasUpdate ? 200 : 201);
}

if ($action === 'queue_ai_job') {
    mg_design_require($user, 'merchant.design_ai.generate');
    $promptJson = mg_design_json($input['prompt'] ?? [], 'AI prompt must be an object.');
    $projectDbId = null;
    $projectId = trim((string) ($input['project_id'] ?? ''));
    if ($projectId !== '') {
        $stmt = $pdo->prepare('SELECT id FROM merchant_design_projects WHERE public_id=? AND workspace_id=? LIMIT 1');
        $stmt->execute([$projectId, $workspaceId]);
        $project = $stmt->fetch();
        if ($project) $projectDbId = (int) $project['id'];
    }
    $brandKitDbId = mg_design_lookup_brand_kit_id($pdo, $workspaceId, isset($input['brand_kit_id']) ? trim((string) $input['brand_kit_id']) : null);
    $jobId = mg_merchant_uuid();
    $generationType = in_array((string) ($input['generation_type'] ?? 'image'), ['image','copy','layout','variation'], true) ? (string) $input['generation_type'] : 'image';
    $pdo->prepare("INSERT INTO merchant_design_ai_jobs (public_id,workspace_id,merchant_user_id,project_id,brand_kit_id,provider_key,model_key,generation_type,prompt_json,status,approval_status,requested_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,'queued','pending',?,NOW(),NOW())")
        ->execute([$jobId,$workspaceId,$userId,$projectDbId,$brandKitDbId,mg_design_clean_text($input['provider_key'] ?? '', '', 80) ?: null,mg_design_clean_text($input['model_key'] ?? '', '', 120) ?: null,$generationType,$promptJson,$userId]);
    mg_audit('merchant.design_ai_job_queued', 'merchant_design_ai_job', ['job_id' => $jobId, 'generation_type' => $generationType, 'brand_kit_id' => $input['brand_kit_id'] ?? null], $userId);
    mg_ok(['job_id' => $jobId, 'status' => 'queued', 'approval_status' => 'pending'], 'AI generation job queued.', 201);
}

mg_fail('Unsupported Design Studio action.', 422);
