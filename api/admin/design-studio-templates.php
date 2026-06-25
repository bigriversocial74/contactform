<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function mg_admin_design_has(array $user, string $permission): bool
{
    return mg_api_user_has_permission($user, $permission) || mg_api_user_has_permission($user, 'admin.catalog.view');
}

function mg_admin_design_require(): array
{
    $user = mg_require_api_user();
    if (!mg_admin_design_has($user, 'merchant.design_templates.admin')) {
        mg_security_log('warning', 'admin.design_templates.denied', 'Design template admin access denied.', [], (int) $user['id']);
        mg_fail('Permission denied.', 403);
    }
    return $user;
}

function mg_admin_design_status(mixed $value): string
{
    $status = strtolower(trim((string) $value));
    return in_array($status, ['pending','approved','rejected','changes_requested'], true) ? $status : 'pending';
}

function mg_admin_design_text(mixed $value, int $max = 1000): string
{
    $text = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
    return mb_substr($text, 0, $max);
}

function mg_admin_design_template_row(array $row): array
{
    return [
        'id' => (string) $row['public_id'],
        'template_scope' => (string) $row['template_scope'],
        'template_type' => (string) $row['template_type'],
        'category_key' => $row['category_key'] ?? null,
        'format_key' => (string) $row['format_key'],
        'name' => (string) $row['name'],
        'status' => (string) $row['status'],
        'review_status' => (string) $row['review_status'],
        'is_featured' => (bool) $row['is_featured'],
        'is_presigned' => (bool) $row['is_presigned'],
        'signature_hash' => $row['signature_hash'] ?? null,
        'submitted_at' => $row['submitted_at'] ?? null,
        'approved_at' => $row['approved_at'] ?? null,
        'published_at' => $row['published_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

$user = mg_admin_design_require();
$pdo = mg_db();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $status = strtolower(trim((string) ($_GET['review_status'] ?? 'pending')));
    $params = [];
    $where = '1=1';
    if (in_array($status, ['not_submitted','pending','approved','rejected','changes_requested'], true)) {
        $where .= ' AND review_status=?';
        $params[] = $status;
    }
    $stmt = $pdo->prepare("SELECT * FROM merchant_design_templates WHERE {$where} ORDER BY updated_at DESC LIMIT 100");
    $stmt->execute($params);
    mg_ok(['templates' => array_map('mg_admin_design_template_row', $stmt->fetchAll())]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$action = strtolower(trim((string) ($input['action'] ?? 'review_template')));

if ($action === 'review_template') {
    $templateId = trim((string) ($input['template_id'] ?? ''));
    if ($templateId === '') mg_fail('Template is required.', 422);
    $reviewStatus = mg_admin_design_status($input['review_status'] ?? 'pending');
    $notes = mg_admin_design_text($input['notes'] ?? '', 2000);
    $feature = !empty($input['is_featured']) ? 1 : 0;
    $publish = !empty($input['publish']);

    $lookup = $pdo->prepare('SELECT id,status,review_status FROM merchant_design_templates WHERE public_id=? LIMIT 1 FOR UPDATE');
    $pdo->beginTransaction();
    try {
        $lookup->execute([$templateId]);
        $template = $lookup->fetch();
        if (!$template) mg_fail('Template not found.', 404);
        $reviewId = mg_merchant_uuid();
        $pdo->prepare('INSERT INTO merchant_design_template_reviews (public_id,template_id,review_status,reviewer_user_id,notes,checklist_json,reviewed_at,created_at,updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW(),NOW())')
            ->execute([$reviewId, (int) $template['id'], $reviewStatus, (int) $user['id'], $notes ?: null, json_encode(is_array($input['checklist'] ?? null) ? $input['checklist'] : [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
        $nextStatus = $reviewStatus === 'approved' ? 'active' : (string) $template['status'];
        $pdo->prepare('UPDATE merchant_design_templates SET review_status=?,status=?,is_featured=?,approved_by_user_id=IF(?="approved",?,approved_by_user_id),approved_at=IF(?="approved",COALESCE(approved_at,NOW()),approved_at),published_at=IF(?=1,COALESCE(published_at,NOW()),published_at),updated_by_user_id=?,updated_at=NOW() WHERE id=?')
            ->execute([$reviewStatus, $nextStatus, $feature, $reviewStatus, (int) $user['id'], $reviewStatus, $publish ? 1 : 0, (int) $user['id'], (int) $template['id']]);
        $pdo->commit();
        mg_audit('admin.design_template_reviewed', 'merchant_design_template', ['template_id' => $templateId, 'review_status' => $reviewStatus, 'review_id' => $reviewId], (int) $user['id']);
        mg_ok(['template_id' => $templateId, 'review_id' => $reviewId, 'review_status' => $reviewStatus], 'Template reviewed.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_security_log('error', 'admin.design_template_review_failed', 'Design template review failed.', ['exception_type' => get_class($e)], (int) $user['id']);
        mg_fail('Unable to review template.', 500);
    }
}

mg_fail('Unsupported admin design action.', 422);
