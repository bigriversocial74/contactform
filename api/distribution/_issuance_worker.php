<?php
declare(strict_types=1);
require_once __DIR__ . '/_distribution.php';
require_once dirname(__DIR__) . '/pppm/_pppm.php';

function mg_distribution_worker_source(PDO $pdo, int $merchantUserId): array
{
    $stmt = $pdo->prepare("SELECT * FROM pppm_sources WHERE owner_user_id=? AND source_type='distribution' AND provider='microgifter' AND status='active' LIMIT 1");
    $stmt->execute([$merchantUserId]);
    $source = $stmt->fetch();
    if ($source) return $source;
    $sourceId = mg_pppm_uuid();
    $pdo->prepare("INSERT INTO pppm_sources (public_id,owner_user_id,source_type,provider,name,status,created_at,updated_at) VALUES (?,?,'distribution','microgifter','Microgifter Distribution','active',NOW(),NOW())")
        ->execute([$sourceId,$merchantUserId]);
    $stmt = $pdo->prepare('SELECT * FROM pppm_sources WHERE public_id=? LIMIT 1');
    $stmt->execute([$sourceId]);
    return $stmt->fetch();
}

function mg_distribution_worker_load_job(PDO $pdo, string $jobId): array
{
    $stmt = $pdo->prepare("SELECT dij.*,da.public_id AS allocation_public_id,da.program_id,da.source_event_id,da.recipient_id,da.program_product_id,da.quantity,da.unit_value_cents,da.status AS allocation_status,dp.public_id AS program_public_id,dp.merchant_user_id,dr.user_id AS recipient_user_id,dr.external_recipient_id,cpt.public_id AS template_public_id,cpt.item_type,cpt.default_funding_type,cpt.issuance_defaults_json,cpv.title,cpv.description,cpv.currency,cpv.terms_json,cpv.metadata_json,dse.public_id AS source_event_public_id,dse.source_type AS distribution_source_type FROM distribution_issuance_jobs dij INNER JOIN distribution_allocations da ON da.id=dij.allocation_id INNER JOIN distribution_programs dp ON dp.id=da.program_id INNER JOIN distribution_recipients dr ON dr.id=da.recipient_id INNER JOIN distribution_program_products dpp ON dpp.id=da.program_product_id INNER JOIN catalog_pppm_templates cpt ON cpt.id=dpp.pppm_template_id INNER JOIN catalog_product_versions cpv ON cpv.id=cpt.product_version_id LEFT JOIN distribution_source_events dse ON dse.id=da.source_event_id WHERE dij.public_id=? LIMIT 1 FOR UPDATE");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();
    if (!$job) throw new RuntimeException('Issuance job not found.');
    return $job;
}

function mg_distribution_worker_source_event(PDO $pdo, array $source, array $job): int
{
    $externalEvent = 'distribution:' . (string)$job['allocation_public_id'];
    $stmt = $pdo->prepare('SELECT id FROM pppm_source_events WHERE source_id=? AND external_event_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([(int)$source['id'],$externalEvent]);
    $id = $stmt->fetchColumn();
    if ($id) return (int)$id;
    $payload = ['distribution_allocation_id'=>$job['allocation_public_id'],'program_id'=>$job['program_public_id'],'source_event_id'=>$job['source_event_public_id'] ?? null];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    $pdo->prepare("INSERT INTO pppm_source_events (public_id,source_id,external_event_id,event_type,payload_json,payload_hash,processing_status,received_at,created_at,updated_at) VALUES (?,?,?,?,?,?,'validated',NOW(),NOW(),NOW())")
        ->execute([mg_pppm_uuid(),(int)$source['id'],$externalEvent,'distribution_issuance',$json,hash('sha256',$json)]);
    return (int)$pdo->lastInsertId();
}

function mg_distribution_worker_request(PDO $pdo, array $source, int $sourceEventId, array $job): array
{
    $stmt = $pdo->prepare('SELECT * FROM pppm_issuance_requests WHERE source_id=? AND source_reference=? LIMIT 1 FOR UPDATE');
    $stmt->execute([(int)$source['id'],(string)$job['allocation_public_id']]);
    $request = $stmt->fetch();
    if ($request) return $request;
    $requestId = mg_pppm_uuid();
    $metadata = ['distribution_program_id'=>$job['program_public_id'],'distribution_allocation_id'=>$job['allocation_public_id'],'template_id'=>$job['template_public_id']];
    $pdo->prepare("INSERT INTO pppm_issuance_requests (public_id,source_id,source_event_id,issuer_user_id,merchant_user_id,source_reference,source_line_reference,item_type,funding_type,quantity,unit_value_cents,currency,recipient_user_id,recipient_external_id,recipient_name,title,description,terms_snapshot_json,metadata_json,status,issued_count,requested_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'issuing',0,NOW(),NOW(),NOW())")
        ->execute([$requestId,(int)$source['id'],$sourceEventId,(int)$job['merchant_user_id'],(int)$job['merchant_user_id'],(string)$job['allocation_public_id'],(string)$job['template_public_id'],(string)$job['item_type'],(string)$job['default_funding_type'],(int)$job['quantity'],(int)$job['unit_value_cents'],(string)$job['currency'],(int)$job['recipient_user_id'],$job['external_recipient_id'] ?? null,null,(string)$job['title'],$job['description'] ?? null,$job['terms_json'] ?? null,json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
    $stmt = $pdo->prepare('SELECT * FROM pppm_issuance_requests WHERE public_id=? LIMIT 1');
    $stmt->execute([$requestId]);
    return $stmt->fetch();
}

function mg_distribution_worker_mark_failed(PDO $pdo, array $job, string $message): array
{
    $attempts = (int)$job['attempts'] + 1;
    $dead = $attempts >= (int)$job['max_attempts'];
    $next = $dead ? null : gmdate('Y-m-d H:i:s', time() + min(3600, 60 * (2 ** max(0, $attempts - 1))));
    $pdo->prepare("UPDATE distribution_issuance_jobs SET status=?,attempts=?,next_attempt_at=?,failure_message=?,locked_at=NULL,locked_by=NULL,updated_at=NOW() WHERE id=?")
        ->execute([$dead?'dead_letter':'failed',$attempts,$next,substr($message,0,500),(int)$job['id']]);
    if ($dead) {
        $pdo->prepare("UPDATE distribution_allocations SET status='failed',failure_message=?,updated_at=NOW() WHERE id=? AND status<>'issued'")
            ->execute([substr($message,0,500),(int)$job['allocation_id']]);
    }
    return ['job_id'=>(string)$job['public_id'],'status'=>$dead?'dead_letter':'failed','next_attempt_at'=>$next,'message'=>$message];
}

function mg_distribution_worker_record_failure(PDO $pdo, string $jobId, string $message): array
{
    if ($pdo->inTransaction()) $pdo->rollBack();
    $pdo->beginTransaction();
    try {
        $job = mg_distribution_worker_load_job($pdo, $jobId);
        $result = mg_distribution_worker_mark_failed($pdo, $job, $message);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['job_id'=>$jobId,'status'=>'failed','message'=>$message];
    }
}

function mg_distribution_worker_process_job(PDO $pdo, string $jobId, string $workerId = 'distribution-worker'): array
{
    $pdo->beginTransaction();
    try {
        $job = mg_distribution_worker_load_job($pdo, $jobId);
        if ((string)$job['status'] === 'issued' && !empty($job['pppm_item_id'])) {
            $pdo->commit();
            return ['job_id'=>$jobId,'status'=>'issued','duplicate'=>true];
        }
        if (!in_array((string)$job['status'], ['queued','failed'], true)) throw new RuntimeException('Issuance job is not available.');
        if (!empty($job['next_attempt_at']) && strtotime((string)$job['next_attempt_at']) > time()) throw new RuntimeException('Issuance job is not ready.');
        if ((int)$job['attempts'] >= (int)$job['max_attempts']) throw new RuntimeException('Issuance job has exhausted retries.');
        if (empty($job['recipient_user_id'])) throw new RuntimeException('Recipient is not linked to a Microgifter user.');
        $pdo->prepare("UPDATE distribution_issuance_jobs SET status='processing',attempts=attempts+1,locked_at=NOW(),locked_by=?,updated_at=NOW() WHERE id=?")
            ->execute([$workerId,(int)$job['id']]);
        $source = mg_distribution_worker_source($pdo, (int)$job['merchant_user_id']);
        $sourceEventId = mg_distribution_worker_source_event($pdo, $source, $job);
        $request = mg_distribution_worker_request($pdo, $source, $sourceEventId, $job);
        $itemPublicId = mg_pppm_item_id();
        $metadata = ['distribution_job_id'=>$job['public_id'],'distribution_allocation_id'=>$job['allocation_public_id'],'distribution_program_id'=>$job['program_public_id'],'template_id'=>$job['template_public_id'],'inbox_delivery'=>true];
        $pdo->prepare("INSERT INTO pppm_items (public_id,issuance_request_id,source_id,unit_sequence,item_type,funding_type,issuer_user_id,merchant_user_id,owner_user_id,recipient_user_id,recipient_external_id,source_reference,source_line_reference,title_snapshot,description_snapshot,value_cents_snapshot,currency_snapshot,terms_snapshot_json,metadata_snapshot_json,status,version_no,issued_at,assigned_at,delivered_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'delivered',1,NOW(),NOW(),NOW(),NOW(),NOW())")
            ->execute([$itemPublicId,(int)$request['id'],(int)$source['id'],(int)$job['item_sequence'],(string)$job['item_type'],(string)$job['default_funding_type'],(int)$job['merchant_user_id'],(int)$job['merchant_user_id'],(int)$job['merchant_user_id'],(int)$job['recipient_user_id'],$job['external_recipient_id'] ?? null,(string)$job['allocation_public_id'],(string)$job['public_id'],(string)$job['title'],$job['description'] ?? null,(int)$job['unit_value_cents'],(string)$job['currency'],$job['terms_json'] ?? null,json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
        $pppmItemDbId = (int)$pdo->lastInsertId();
        $item = $pdo->query('SELECT * FROM pppm_items WHERE id=' . $pppmItemDbId)->fetch();
        mg_pppm_record_event($pdo, $item, 'distribution_inbox_delivered', null, 'delivered', (int)$job['merchant_user_id'], $sourceEventId, $metadata);
        $assignmentId = mg_pppm_uuid();
        $pdo->prepare("INSERT INTO pppm_assignments (public_id,pppm_item_id,assignment_type,from_user_id,to_user_id,to_external_id,to_name,status,created_by_user_id,accepted_by_user_id,accepted_at,created_at,updated_at) VALUES (?,?, 'api', ?, ?, ?, NULL, 'accepted', ?, ?, NOW(), NOW(), NOW())")
            ->execute([$assignmentId,$pppmItemDbId,(int)$job['merchant_user_id'],(int)$job['recipient_user_id'],$job['external_recipient_id'] ?? null,(int)$job['merchant_user_id'],(int)$job['recipient_user_id']]);
        $deliveryId = mg_pppm_uuid();
        $pdo->prepare("INSERT INTO pppm_deliveries (public_id,pppm_item_id,channel,destination,status,provider,queued_at,sent_at,delivered_at,created_at,updated_at) VALUES (?,?,'api',?,'delivered','microgifter_inbox',NOW(),NOW(),NOW(),NOW(),NOW())")
            ->execute([$deliveryId,$pppmItemDbId,'user:' . (int)$job['recipient_user_id']]);
        $deliveryDbId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO pppm_delivery_attempts (public_id,delivery_id,schedule_id,attempt_number,provider,status,attempted_at,completed_at,created_at,updated_at) VALUES (?,?,NULL,1,'microgifter_inbox','delivered',NOW(),NOW(),NOW(),NOW())")
            ->execute([mg_pppm_uuid(),$deliveryDbId]);
        $pdo->prepare("UPDATE distribution_issuance_jobs SET status='issued',pppm_item_id=?,failure_message=NULL,locked_at=NULL,locked_by=NULL,updated_at=NOW() WHERE id=?")
            ->execute([$pppmItemDbId,(int)$job['id']]);
        $pdo->prepare("UPDATE distribution_programs SET reserved_cents=GREATEST(0,reserved_cents-?),issued_cents=issued_cents+?,issued_items=issued_items+1,updated_at=NOW() WHERE id=?")
            ->execute([(int)$job['unit_value_cents'],(int)$job['unit_value_cents'],(int)$job['program_id']]);
        $pdo->prepare("UPDATE distribution_program_products SET quantity_issued=quantity_issued+1,status=IF(quantity_limit IS NOT NULL AND quantity_issued+1>=quantity_limit,'exhausted',status) WHERE id=?")
            ->execute([(int)$job['program_product_id']]);
        $pdo->prepare("UPDATE pppm_issuance_requests SET issued_count=issued_count+1,updated_at=NOW() WHERE id=?")
            ->execute([(int)$request['id']]);
        $remaining = $pdo->prepare("SELECT COUNT(*) FROM distribution_issuance_jobs WHERE allocation_id=? AND status<>'issued'");
        $remaining->execute([(int)$job['allocation_id']]);
        if ((int)$remaining->fetchColumn() === 0) {
            $pdo->prepare("UPDATE distribution_allocations SET status='issued',issued_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$job['allocation_id']]);
            $pdo->prepare("UPDATE distribution_recipients SET eligibility_status='fulfilled',updated_at=NOW() WHERE id=?")->execute([(int)$job['recipient_id']]);
            $pdo->prepare("UPDATE pppm_issuance_requests SET status='issued',completed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$request['id']]);
            if (!empty($job['source_event_id'])) $pdo->prepare("UPDATE distribution_source_events SET status='processed',processed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$job['source_event_id']]);
        } else {
            $pdo->prepare("UPDATE distribution_allocations SET status='issuing',updated_at=NOW() WHERE id=?")->execute([(int)$job['allocation_id']]);
            $pdo->prepare("UPDATE distribution_recipients SET eligibility_status='allocated',updated_at=NOW() WHERE id=? AND eligibility_status<>'fulfilled'")->execute([(int)$job['recipient_id']]);
        }
        $pdo->prepare("UPDATE pppm_source_events SET processing_status='processed',processed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$sourceEventId]);
        $pdo->prepare("INSERT INTO distribution_daily_metrics (metric_date,merchant_user_id,program_id,source_type,items_issued,issued_value_cents,updated_at) VALUES (CURRENT_DATE(),?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE items_issued=items_issued+VALUES(items_issued),issued_value_cents=issued_value_cents+VALUES(issued_value_cents),updated_at=NOW()")
            ->execute([(int)$job['merchant_user_id'],(int)$job['program_id'],(string)($job['distribution_source_type'] ?: 'api'),1,(int)$job['unit_value_cents']]);
        $pdo->commit();
        return ['job_id'=>$jobId,'status'=>'issued','pppm_item_id'=>$itemPublicId,'delivery_id'=>$deliveryId,'assignment_id'=>$assignmentId];
    } catch (Throwable $e) {
        return mg_distribution_worker_record_failure($pdo, $jobId, $e->getMessage());
    }
}

function mg_distribution_worker_claimable_jobs(PDO $pdo, int $limit = 25): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->query("SELECT public_id FROM distribution_issuance_jobs WHERE status IN ('queued','failed') AND (next_attempt_at IS NULL OR next_attempt_at<=NOW()) AND attempts<max_attempts ORDER BY created_at ASC,id ASC LIMIT " . $limit);
    return array_column($stmt->fetchAll(), 'public_id');
}

function mg_distribution_worker_run(PDO $pdo, int $limit = 25, string $workerId = 'distribution-worker'): array
{
    $jobs = mg_distribution_worker_claimable_jobs($pdo, $limit);
    $results = [];
    foreach ($jobs as $jobId) $results[] = mg_distribution_worker_process_job($pdo, (string)$jobId, $workerId);
    return ['requested_limit'=>max(1,min(100,$limit)),'claimed'=>count($jobs),'results'=>$results];
}
