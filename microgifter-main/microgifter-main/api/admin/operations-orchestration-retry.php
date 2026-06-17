<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/operations/_orchestration_retry.php';
mg_require_method('POST');
$user=mg_require_permission('operations.orchestrations.retry');
$input=mg_input();
mg_require_csrf_for_write($input);
$publicId=trim((string)($input['orchestration_id']??''));
$idempotencyKey=trim((string)($input['idempotency_key']??($_SERVER['HTTP_IDEMPOTENCY_KEY']??'')));
$reason=trim((string)($input['reason']??''));
if($publicId==='')mg_fail('Orchestration ID is required.',422);
$pdo=mg_db();
$pdo->beginTransaction();
try{
    $result=mg_operations_retry_demand_orchestration_coordinated($pdo,(int)$user['id'],$publicId,$idempotencyKey,$reason);
    $pdo->commit();
    mg_audit('operations.orchestration_retry','demand_orchestration',[
        'orchestration_id'=>$publicId,
        'attempt_id'=>$result['attempt_id']??null,
        'attempt_no'=>$result['attempt_no']??null,
        'duplicate'=>(bool)($result['duplicate']??false),
    ],(int)$user['id']);
    mg_ok(['retry'=>$result],!empty($result['duplicate'])?'Retry already recorded.':'Retry dispatched.');
}catch(InvalidArgumentException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($e->getMessage(),422);
}catch(RuntimeException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($e->getMessage(),409);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail('Unable to retry demand orchestration.',500);
}
