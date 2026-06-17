<?php
declare(strict_types=1);
require_once __DIR__ . '/_distribution.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission($method === 'GET' ? 'distribution.analytics.view' : 'distribution.sources.manage');
$pdo = mg_db();
if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT dsc.public_id,dsc.source_type,dsc.provider_key,dsc.display_name,dsc.status,dsc.last_event_at,dp.public_id AS program_id,dp.name AS program_name,dsc.created_at,dsc.updated_at FROM distribution_source_connections dsc LEFT JOIN distribution_programs dp ON dp.id=dsc.program_id WHERE dsc.merchant_user_id=? ORDER BY dsc.updated_at DESC');
    $stmt->execute([(int)$user['id']]);
    mg_ok(['sources'=>$stmt->fetchAll()]);
}
if ($method !== 'POST') mg_fail('Method not allowed.',405);
$input = mg_input();
mg_require_csrf_for_write($input);
$sourceId = trim((string)($input['source_id'] ?? ''));
$programId = trim((string)($input['program_id'] ?? '')) ?: null;
$sourceType = trim((string)($input['source_type'] ?? 'api'));
$providerKey = strtolower(trim((string)($input['provider_key'] ?? '')));
$displayName = trim((string)($input['display_name'] ?? ''));
$status = trim((string)($input['status'] ?? 'active'));
$secret = trim((string)($input['webhook_secret'] ?? '')) ?: null;
$types = ['ecommerce','merchant','contest','giveaway','fundraiser','workplace','gaming','webhook','api','csv','other'];
if (!in_array($sourceType,$types,true) || !preg_match('/^[a-z0-9_.:-]{2,120}$/',$providerKey) || $displayName === '') mg_fail('Invalid distribution source.',422);
if (!in_array($status,['active','paused','revoked'],true)) mg_fail('Invalid source status.',422);
$programDbId = null;
if ($programId) {
    $stmt = $pdo->prepare('SELECT id FROM distribution_programs WHERE public_id=? AND merchant_user_id=? LIMIT 1');
    $stmt->execute([$programId,(int)$user['id']]);
    $programDbId = $stmt->fetchColumn();
    if (!$programDbId) mg_fail('Distribution program not found.',404);
}
$config = mg_distribution_json($input['configuration'] ?? null);
$secretHash = $secret ? password_hash($secret,PASSWORD_DEFAULT) : null;
if ($sourceId === '') {
    $sourceId = mg_distribution_uuid();
    $pdo->prepare('INSERT INTO distribution_source_connections (public_id,merchant_user_id,program_id,source_type,provider_key,display_name,status,secret_hash,configuration_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())')
        ->execute([$sourceId,(int)$user['id'],$programDbId,$sourceType,$providerKey,$displayName,$status,$secretHash,$config]);
} else {
    $source = mg_distribution_connection_for_update($pdo,(int)$user['id'],$sourceId);
    $sql = 'UPDATE distribution_source_connections SET program_id=?,source_type=?,provider_key=?,display_name=?,status=?,configuration_json=?,updated_at=NOW()';
    $params = [$programDbId,$sourceType,$providerKey,$displayName,$status,$config];
    if ($secretHash) { $sql .= ',secret_hash=?'; $params[] = $secretHash; }
    $sql .= ' WHERE id=?'; $params[] = (int)$source['id'];
    $pdo->prepare($sql)->execute($params);
}
mg_audit('distribution.source_saved','distribution_source',['source_id'=>$sourceId,'source_type'=>$sourceType],(int)$user['id']);
mg_ok(['source_id'=>$sourceId,'status'=>$status],'Distribution source saved.',201);
