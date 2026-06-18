<?php
declare(strict_types=1);
require_once __DIR__ . '/_payments.php';
require_once dirname(__DIR__) . '/finance/_cashouts.php';
mg_require_method('POST');
$provider=trim((string)($_GET['provider']??''));
$payload=file_get_contents('php://input')?:'';
$signature=(string)($_SERVER['HTTP_X_MG_SIGNATURE']??'');
$event=json_decode($payload,true);
if($provider===''||!is_array($event)||!mg_payment_verify_signature($provider,$payload,$signature))mg_fail('Invalid payout webhook.',401);
$pdo=mg_db();
$pdo->beginTransaction();
try{
    $result=mg_payout_process_event($pdo,$provider,$event,$payload);
    $pdo->commit();
    mg_ok(['received'=>true]+$result,!empty($result['duplicate'])?'Payout webhook already received.':'Payout webhook processed.');
}catch(MgCashoutWorkflowException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($e->getMessage(),$e->httpStatus);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail('Unable to process payout webhook.',500);
}
