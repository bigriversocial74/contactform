<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_crm_builder_json(mixed $raw): array
{
    if (is_array($raw)) return $raw;
    $raw = trim((string)$raw);
    if ($raw === '') return [];
    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    } catch (Throwable) {
        return [];
    }
}

function mg_crm_builder_slug(string $value, string $fallback): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9_-]+/', '_', $slug) ?: '';
    $slug = trim($slug, '_-');
    return substr($slug !== '' ? $slug : $fallback, 0, 80);
}

function mg_crm_builder_event(PDO $pdo, int $merchantId, string $type, array $context): array
{
    $publicId = function_exists('mg_merchant_uuid') ? mg_merchant_uuid() : bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,NOW())')
        ->execute([$publicId, $merchantId, null, null, $type, json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    return ['id' => $publicId, 'event_type' => $type, 'context' => $context];
}

function mg_crm_builder_state(PDO $pdo, int $merchantId): array
{
    $stmt = $pdo->prepare("SELECT public_id,event_type,event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type IN ('crm.segment.saved','crm.campaign_builder.draft','crm.campaign_builder.launched') ORDER BY created_at DESC,id DESC LIMIT 250");
    $stmt->execute([$merchantId]);
    $segments = [];
    $drafts = [];
    $launches = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ctx = mg_crm_builder_json($row['event_context_json'] ?? null);
        $ctx['event_id'] = (string)$row['public_id'];
        $ctx['created_at'] = $row['created_at'] ?? null;
        if ($row['event_type'] === 'crm.segment.saved') {
            $key = (string)($ctx['id'] ?? $ctx['segment_id'] ?? '');
            if ($key !== '' && !isset($segments[$key])) $segments[$key] = $ctx;
            continue;
        }
        if ($row['event_type'] === 'crm.campaign_builder.draft') {
            $key = (string)($ctx['draft_id'] ?? $ctx['id'] ?? '');
            if ($key !== '' && !isset($drafts[$key])) $drafts[$key] = $ctx;
            continue;
        }
        if ($row['event_type'] === 'crm.campaign_builder.launched') {
            $launches[] = $ctx;
        }
    }
    return ['segments' => array_values($segments), 'drafts' => array_values($drafts), 'launches' => array_slice($launches, 0, 20), 'schema_ready' => true];
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) mg_fail('Method not allowed.', 405);
$user = mg_require_permission($method === 'GET' ? 'merchant.campaigns.view' : 'merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

try {
    if ($method === 'GET') {
        mg_ok(mg_crm_builder_state($pdo, $merchantId));
    }

    $input = mg_input();
    mg_require_csrf_for_write($input);
    $action = strtolower(trim((string)($input['action'] ?? '')));

    if ($action === 'save_segment') {
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') mg_fail('Segment name is required.', 422);
        $segmentKey = mg_crm_builder_slug((string)($input['segment_key'] ?? $input['segment'] ?? 'all'), 'all');
        $id = mg_crm_builder_slug((string)($input['id'] ?? $name), 'segment_' . substr(hash('sha256', $name . microtime(true)), 0, 10));
        $event = mg_crm_builder_event($pdo, $merchantId, 'crm.segment.saved', [
            'id' => $id,
            'name' => substr($name, 0, 140),
            'segment_key' => $segmentKey,
            'campaign_id' => trim((string)($input['campaign_id'] ?? '')),
            'description' => substr(trim((string)($input['description'] ?? '')), 0, 500),
            'criteria' => is_array($input['criteria'] ?? null) ? $input['criteria'] : ['segment' => $segmentKey],
        ]);
        mg_ok(['segment' => $event['context'], 'state' => mg_crm_builder_state($pdo, $merchantId)], 'Saved CRM segment.');
    }

    if ($action === 'save_draft') {
        $name = trim((string)($input['campaign_name'] ?? $input['name'] ?? ''));
        if ($name === '') mg_fail('Campaign name is required.', 422);
        $draftId = mg_crm_builder_slug((string)($input['draft_id'] ?? $name), 'draft_' . substr(hash('sha256', $name . microtime(true)), 0, 10));
        $event = mg_crm_builder_event($pdo, $merchantId, 'crm.campaign_builder.draft', [
            'draft_id' => $draftId,
            'campaign_name' => substr($name, 0, 180),
            'segment_id' => trim((string)($input['segment_id'] ?? '')),
            'segment_key' => mg_crm_builder_slug((string)($input['segment_key'] ?? 'all'), 'all'),
            'message' => substr(trim((string)($input['message'] ?? '')), 0, 4000),
            'reward_template_id' => trim((string)($input['reward_template_id'] ?? '')),
            'note' => substr(trim((string)($input['note'] ?? '')), 0, 1000),
            'follow_up_due_at' => trim((string)($input['follow_up_due_at'] ?? '')),
            'follow_up_note' => substr(trim((string)($input['follow_up_note'] ?? '')), 0, 1000),
            'status' => 'draft',
        ]);
        mg_ok(['draft' => $event['context'], 'state' => mg_crm_builder_state($pdo, $merchantId)], 'Saved CRM campaign draft.');
    }

    if ($action === 'launch_record') {
        $name = trim((string)($input['campaign_name'] ?? 'Campaign launch')) ?: 'Campaign launch';
        $event = mg_crm_builder_event($pdo, $merchantId, 'crm.campaign_builder.launched', [
            'launch_id' => 'launch_' . substr(hash('sha256', $name . microtime(true)), 0, 14),
            'campaign_name' => substr($name, 0, 180),
            'segment_id' => trim((string)($input['segment_id'] ?? '')),
            'segment_key' => mg_crm_builder_slug((string)($input['segment_key'] ?? 'all'), 'all'),
            'contact_count' => max(0, (int)($input['contact_count'] ?? 0)),
            'summary' => is_array($input['summary'] ?? null) ? $input['summary'] : [],
            'status' => 'launched',
        ]);
        mg_ok(['launch' => $event['context'], 'state' => mg_crm_builder_state($pdo, $merchantId)], 'Recorded CRM campaign launch.');
    }

    mg_fail('Unknown campaign builder action.', 422);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.crm_campaign_builder.unavailable', 'CRM campaign builder unavailable.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_ok(['segments' => [], 'drafts' => [], 'launches' => [], 'schema_ready' => false], 'CRM campaign builder unavailable until campaign events are installed.');
}
