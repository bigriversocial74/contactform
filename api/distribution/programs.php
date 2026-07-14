<?php
declare(strict_types=1);
require_once __DIR__ . '/_distribution.php';

function mg_distribution_program_metadata(PDO $pdo, int $merchantUserId, mixed $value): mixed
{
    if (!is_array($value) || !array_key_exists('campaign_ids', $value)) return $value;

    $campaignIds = $value['campaign_ids'];
    if (!is_array($campaignIds)) mg_fail('Invalid campaign selection.', 422);

    $campaignIds = array_values(array_unique(array_filter(array_map(
        static fn(mixed $campaignId): string => strtolower(trim((string)$campaignId)),
        $campaignIds
    ), static fn(string $campaignId): bool => $campaignId !== '')));

    if ($campaignIds === []) mg_fail('Select at least one merchant campaign.', 422);
    foreach ($campaignIds as $campaignId) {
        if (strlen($campaignId) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $campaignId) !== 1) {
            mg_fail('Invalid campaign selection.', 422);
        }
    }

    $placeholders = implode(',', array_fill(0, count($campaignIds), '?'));
    $params = $campaignIds;
    $params[] = $merchantUserId;
    $stmt = $pdo->prepare("SELECT public_id FROM campaigns WHERE public_id IN ($placeholders) AND merchant_user_id=? AND status<>'archived'");
    $stmt->execute($params);
    $available = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    sort($available);
    $expected = $campaignIds;
    sort($expected);
    if ($available !== $expected) mg_fail('One or more selected campaigns are unavailable.', 422);

    $value['campaign_ids'] = $campaignIds;
    $value['campaign_count'] = count($campaignIds);
    $value['campaign_source'] = 'developer_api_program_builder';
    return $value;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission($method === 'GET' ? 'distribution.analytics.view' : 'distribution.programs.manage');
$pdo = mg_db();

if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT public_id,name,program_type,status,starts_at,ends_at,budget_cents,reserved_cents,issued_cents,max_items,issued_items,per_recipient_limit,rules_json,metadata_json,created_at,updated_at FROM distribution_programs WHERE merchant_user_id = ? ORDER BY updated_at DESC,id DESC');
    $stmt->execute([(int) $user['id']]);
    mg_ok(['programs' => $stmt->fetchAll()]);
}
if ($method !== 'POST') mg_fail('Method not allowed.',405);
$input = mg_input();
mg_require_csrf_for_write($input);
$action = trim((string) ($input['action'] ?? 'save'));
$programId = trim((string) ($input['program_id'] ?? ''));
$name = trim((string) ($input['name'] ?? ''));
$type = trim((string) ($input['program_type'] ?? 'other'));
$status = trim((string) ($input['status'] ?? 'draft'));
$types = ['purchase','merchant_grant','contest','giveaway','fundraiser','workplace_reward','gaming','external_api','batch','other'];
$statuses = ['draft','scheduled','active','paused','completed','cancelled','archived'];
if ($name === '' || mb_strlen($name) > 180) mg_fail('Invalid program name.',422);
if (!in_array($type,$types,true) || !in_array($status,$statuses,true)) mg_fail('Invalid distribution program.',422);
$startsAt = trim((string) ($input['starts_at'] ?? '')) ?: null;
$endsAt = trim((string) ($input['ends_at'] ?? '')) ?: null;
if ($startsAt && strtotime($startsAt) === false) mg_fail('Invalid start date.',422);
if ($endsAt && strtotime($endsAt) === false) mg_fail('Invalid end date.',422);
if ($startsAt && $endsAt && strtotime($endsAt) <= strtotime($startsAt)) mg_fail('End date must be after start date.',422);
$budget = isset($input['budget_cents']) && $input['budget_cents'] !== '' ? max(0,(int)$input['budget_cents']) : null;
$maxItems = isset($input['max_items']) && $input['max_items'] !== '' ? max(1,(int)$input['max_items']) : null;
$perRecipient = isset($input['per_recipient_limit']) && $input['per_recipient_limit'] !== '' ? max(1,(int)$input['per_recipient_limit']) : null;
$rules = mg_distribution_json($input['rules'] ?? null);
$metadataInput = mg_distribution_program_metadata($pdo, (int)$user['id'], $input['metadata'] ?? null);
$metadata = mg_distribution_json($metadataInput);

$pdo->beginTransaction();
try {
    if ($programId === '') {
        $programId = mg_distribution_uuid();
        $pdo->prepare('INSERT INTO distribution_programs (public_id,merchant_user_id,name,program_type,status,starts_at,ends_at,budget_cents,max_items,per_recipient_limit,rules_json,metadata_json,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')
            ->execute([$programId,(int)$user['id'],$name,$type,$status,$startsAt,$endsAt,$budget,$maxItems,$perRecipient,$rules,$metadata,(int)$user['id']]);
    } else {
        $program = mg_distribution_program_for_update($pdo,(int)$user['id'],$programId);
        if (in_array((string)$program['status'],['completed','cancelled','archived'],true) && $action !== 'archive') mg_fail('Closed programs cannot be edited.',409);
        $pdo->prepare('UPDATE distribution_programs SET name=?,program_type=?,status=?,starts_at=?,ends_at=?,budget_cents=?,max_items=?,per_recipient_limit=?,rules_json=?,metadata_json=?,updated_at=NOW() WHERE id=?')
            ->execute([$name,$type,$status,$startsAt,$endsAt,$budget,$maxItems,$perRecipient,$rules,$metadata,(int)$program['id']]);
    }
    $pdo->commit();
    mg_audit('distribution.program_saved','distribution_program',['program_id'=>$programId,'status'=>$status,'campaign_count'=>is_array($metadataInput) ? (int)($metadataInput['campaign_count'] ?? 0) : 0],(int)$user['id']);
    mg_ok(['program_id'=>$programId,'status'=>$status],'Distribution program saved.',201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to save the distribution program.',500);
}
