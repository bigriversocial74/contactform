<?php
declare(strict_types=1);
require_once __DIR__ . '/_distribution.php';

mg_require_method('POST');
$user = mg_require_permission('distribution.events.ingest');
$input = mg_input();
mg_require_csrf_for_write($input);
$sourceId = trim((string)($input['source_id'] ?? ''));
$programId = trim((string)($input['program_id'] ?? '')) ?: null;
$event = mg_distribution_normalize_event($input);
$pdo = mg_db();
$pdo->beginTransaction();
try {
    $connectionDbId = null;
    if ($sourceId) {
        $source = mg_distribution_connection_for_update($pdo,(int)$user['id'],$sourceId);
        if ((string)$source['status'] !== 'active') mg_fail('Distribution source is not active.',409);
        $connectionDbId = (int)$source['id'];
        if (!$programId && !empty($source['program_id'])) {
            $stmt=$pdo->prepare('SELECT public_id FROM distribution_programs WHERE id=? LIMIT 1');
            $stmt->execute([(int)$source['program_id']]);
            $programId=(string)$stmt->fetchColumn();
        }
    }
    $programDbId = null;
    if ($programId) {
        $program = mg_distribution_program_for_update($pdo,(int)$user['id'],$programId);
        if (!mg_distribution_program_is_open($program) && (string)$program['status'] !== 'draft') mg_fail('Distribution program is not accepting events.',409);
        $programDbId = (int)$program['id'];
    }
    $existing=$pdo->prepare('SELECT public_id,status,payload_checksum FROM distribution_source_events WHERE merchant_user_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE');
    $existing->execute([(int)$user['id'],$event['idempotency_key']]);
    $duplicate=$existing->fetch();
    if ($duplicate) {
        if (!hash_equals((string)$duplicate['payload_checksum'],$event['payload_checksum'])) mg_fail('Idempotency key conflict: event payload changed.',409);
        $pdo->commit();
        mg_ok(['event_id'=>$duplicate['public_id'],'status'=>$duplicate['status'],'duplicate'=>true],'Source event already accepted.');
    }
    $publicId=mg_distribution_uuid();
    $pdo->prepare("INSERT INTO distribution_source_events (public_id,connection_id,merchant_user_id,program_id,source_type,external_event_id,event_type,idempotency_key,payload_json,payload_checksum,status,received_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,'validated',NOW(),NOW(),NOW())")
        ->execute([$publicId,$connectionDbId,(int)$user['id'],$programDbId,$event['source_type'],$event['external_event_id'],$event['event_type'],$event['idempotency_key'],$event['payload_json'],$event['payload_checksum']]);
    if ($connectionDbId) $pdo->prepare('UPDATE distribution_source_connections SET last_event_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$connectionDbId]);
    $pdo->commit();
    mg_audit('distribution.event_ingested','distribution_source_event',['event_id'=>$publicId,'source_type'=>$event['source_type']],(int)$user['id']);
    mg_ok(['event_id'=>$publicId,'status'=>'validated','duplicate'=>false],'Source event accepted.',202);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to ingest the source event.',500);
}
