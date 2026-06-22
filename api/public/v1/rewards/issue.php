<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_public.php';
require_once dirname(__DIR__, 2) . '/distribution/_developer_webhooks.php';

mg_require_method('POST');
$context = mg_public_context('distribution:rewards.issue');
$pdo = $context['pdo'];
$input = mg_input();

$programPublicId = trim((string) ($input['program_id'] ?? ''));
$externalEventId = trim((string) ($input['external_event_id'] ?? ''));
$eventType = strtolower(trim((string) ($input['event_type'] ?? 'reward.issue')));
$recipientInput = is_array($input['recipient'] ?? null) ? $input['recipient'] : [];
$rewardInput = is_array($input['reward'] ?? null) ? $input['reward'] : [];
$linkedAccountId = trim((string) ($recipientInput['linked_account_id'] ?? ''));
$templateId = trim((string) ($rewardInput['template_id'] ?? ''));
$quantity = max(1, min(100, (int) ($rewardInput['quantity'] ?? 1)));

if ($programPublicId === '' || $externalEventId === '' || $linkedAccountId === '' || $templateId === '') {
    mg_public_log($pdo, $context, 422, 'invalid_request', 'Missing program, event, recipient, or reward template.');
    mg_fail('Program, external event, linked account, and reward template are required.', 422);
}
if (mb_strlen($externalEventId) > 180 || !preg_match('/^[a-z0-9_.:-]{2,100}$/', $eventType)) {
    mg_public_log($pdo, $context, 422, 'invalid_request', 'Invalid external event or event type.');
    mg_fail('Invalid reward event.', 422);
}

$pdo->beginTransaction();
try {
    $programStmt = $pdo->prepare("SELECT * FROM distribution_programs WHERE public_id=? AND merchant_user_id=? LIMIT 1 FOR UPDATE");
    $programStmt->execute([$programPublicId, (int) $context['merchant_user_id']]);
    $program = $programStmt->fetch();
    if (!$program) mg_fail('Distribution program not found.', 404);
    if (!mg_distribution_program_is_open($program)) mg_fail('Distribution program is not active.', 409);

    $productStmt = $pdo->prepare("SELECT dpp.id AS program_product_id,dpp.quantity_limit,dpp.quantity_issued,cpt.public_id AS template_id,cpv.unit_value_cents,cpv.currency,cpv.title FROM distribution_program_products dpp INNER JOIN catalog_pppm_templates cpt ON cpt.id=dpp.pppm_template_id INNER JOIN catalog_product_versions cpv ON cpv.id=cpt.product_version_id WHERE dpp.program_id=? AND cpt.public_id=? AND dpp.status='active' LIMIT 1 FOR UPDATE");
    $productStmt->execute([(int) $program['id'], $templateId]);
    $product = $productStmt->fetch();
    if (!$product) mg_fail('Program product is unavailable.', 404);
    if ($product['quantity_limit'] !== null && (int)$product['quantity_issued'] + $quantity > (int)$product['quantity_limit']) mg_fail('Program product limit reached.', 409);

    $linkStmt = $pdo->prepare("SELECT * FROM developer_app_user_links WHERE public_id=? AND app_id=? AND merchant_user_id=? AND status='active' LIMIT 1");
    $linkStmt->execute([$linkedAccountId, (int)$context['app_id'], (int)$context['merchant_user_id']]);
    $link = $linkStmt->fetch();
    if (!$link) mg_fail('Linked Microgifter account not found.', 404);

    mg_distribution_check_capacity($program, $quantity, (int)$product['unit_value_cents']);

    $allocationIdempotency = hash('sha256', (string)$context['app_public_id'] . '|' . $externalEventId . '|' . $templateId);
    $existingStmt = $pdo->prepare('SELECT public_id,status FROM distribution_allocations WHERE program_id=? AND idempotency_key=? LIMIT 1');
    $existingStmt->execute([(int)$program['id'], $allocationIdempotency]);
    $existing = $existingStmt->fetch();
    if ($existing) {
        $pdo->commit();
        mg_public_log($pdo, $context, 200, 'duplicate');
        mg_ok(['reward_id' => $existing['public_id'], 'status' => $existing['status'], 'duplicate' => true], 'Reward request already exists.');
    }

    $payload = ['program_id'=>$programPublicId,'external_event_id'=>$externalEventId,'event_type'=>$eventType,'recipient'=>$recipientInput,'reward'=>$rewardInput,'metadata'=>is_array($input['metadata'] ?? null) ? $input['metadata'] : []];
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($payloadJson) || strlen($payloadJson) > 1048576) mg_fail('Reward payload is too large.', 422);
    $eventId = mg_distribution_uuid();
    $pdo->prepare("INSERT INTO distribution_source_events (public_id,connection_id,merchant_user_id,program_id,source_type,external_event_id,event_type,idempotency_key,payload_json,payload_checksum,status,received_at,created_at,updated_at) VALUES (?,?,?,?, 'api', ?, ?, ?, ?, ?, 'queued', NOW(), NOW(), NOW())")
        ->execute([$eventId,$context['source_connection_id'],(int)$context['merchant_user_id'],(int)$program['id'],$externalEventId,$eventType,hash('sha256', (string)$context['app_public_id'].'|'.$externalEventId),$payloadJson,hash('sha256',$payloadJson)]);
    $eventDbId = (int)$pdo->lastInsertId();

    $recipientId = mg_distribution_uuid();
    $externalRecipientId = (string)$link['external_user_id'];
    $pdo->prepare("INSERT INTO distribution_recipients (public_id,program_id,user_id,external_recipient_id,display_name,eligibility_status,entries_count,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,'eligible',1,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE entries_count=entries_count+1,eligibility_status=IF(eligibility_status='pending','eligible',eligibility_status),updated_at=NOW()")
        ->execute([$recipientId,(int)$program['id'],(int)$link['microgifter_user_id'],$externalRecipientId,null,mg_distribution_json(['linked_account_id'=>$linkedAccountId,'app_id'=>$context['app_public_id']])]);
    $recipientStmt = $pdo->prepare('SELECT * FROM distribution_recipients WHERE program_id=? AND user_id=? LIMIT 1 FOR UPDATE');
    $recipientStmt->execute([(int)$program['id'],(int)$link['microgifter_user_id']]);
    $recipient = $recipientStmt->fetch();
    if (!$recipient) mg_fail('Recipient could not be prepared.', 500);

    if ($program['per_recipient_limit'] !== null) {
        $limitStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM distribution_allocations WHERE program_id=? AND recipient_id=? AND status NOT IN ('failed','cancelled','expired')");
        $limitStmt->execute([(int)$program['id'],(int)$recipient['id']]);
        if ((int)$limitStmt->fetchColumn() + $quantity > (int)$program['per_recipient_limit']) mg_fail('Recipient limit reached for this program.', 409);
    }

    $rewardId = mg_distribution_uuid();
    $unitValue = (int)$product['unit_value_cents'];
    $pdo->prepare("INSERT INTO distribution_allocations (public_id,program_id,source_event_id,recipient_id,program_product_id,quantity,unit_value_cents,status,allocation_method,selection_proof_json,idempotency_key,reserved_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,'queued','api',?,?,NOW(),NOW(),NOW())")
        ->execute([$rewardId,(int)$program['id'],$eventDbId,(int)$recipient['id'],(int)$product['program_product_id'],$quantity,$unitValue,mg_distribution_json(['app_id'=>$context['app_public_id'],'external_event_id'=>$externalEventId]),$allocationIdempotency]);
    $allocationDbId = (int)$pdo->lastInsertId();

    $jobStmt = $pdo->prepare("INSERT INTO distribution_issuance_jobs (public_id,allocation_id,item_sequence,status,request_json,created_at,updated_at) VALUES (?,?,?,'queued',?,NOW(),NOW())");
    for ($i=1; $i <= $quantity; $i++) {
        $jobStmt->execute([mg_distribution_uuid(),$allocationDbId,$i,mg_distribution_json(['template_id'=>$templateId,'title'=>$product['title'],'recipient_user_id'=>(int)$link['microgifter_user_id'],'linked_account_id'=>$linkedAccountId])]);
    }
    mg_dev_webhook_event($pdo,(int)$context['app_id'],(int)$context['merchant_user_id'],'reward.queued',['reward_id'=>$rewardId,'event_id'=>$eventId,'program_id'=>$programPublicId,'template_id'=>$templateId,'quantity'=>$quantity,'external_event_id'=>$externalEventId],$eventDbId,'reward',$rewardId);
    $pdo->prepare('UPDATE distribution_programs SET reserved_cents=reserved_cents+?,updated_at=NOW() WHERE id=?')->execute([$unitValue*$quantity,(int)$program['id']]);
    $pdo->prepare("UPDATE distribution_source_events SET status='queued',processed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$eventDbId]);
    $pdo->commit();
    mg_public_log($pdo, $context, 201, 'queued');
    mg_ok(['reward_id'=>$rewardId,'status'=>'queued','event_id'=>$eventId,'program_id'=>$programPublicId,'template_id'=>$templateId,'quantity'=>$quantity], 'Reward queued for issuance.', 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_public_log($pdo, $context, 500, 'failed', 'Reward issue failed.');
    mg_fail('Unable to issue reward.', 500);
}
