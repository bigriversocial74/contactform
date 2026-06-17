<?php
declare(strict_types=1);
require_once __DIR__ . '/_demand.php';
$user=mg_require_api_user();
$pdo=mg_db();
if($_SERVER['REQUEST_METHOD']==='GET'){
    mg_require_permission('demand.psr.manage_own');
    $status=trim((string)($_GET['status']??'outstanding'));
    if(!in_array($status,['outstanding','redeemed','expired','canceled','all'],true))mg_fail('Invalid purchase signal status.',422);
    $limit=max(1,min((int)($_GET['limit']??100),200));
    $sql='SELECT public_id,merchant_user_id,location_id,product_id,asset_type,asset_reference,signal_type,status,quantity,estimated_value_cents,currency,confidence_score,expected_from,expected_to,source_type,source_reference,redeemed_at,canceled_at,expires_at,created_at,updated_at FROM purchase_signal_records WHERE user_id=?';
    $params=[(int)$user['id']];
    if($status!=='all'){$sql.=' AND status=?';$params[]=$status;}
    $sql.=' ORDER BY id DESC LIMIT '.$limit;
    $stmt=$pdo->prepare($sql);$stmt->execute($params);
    mg_ok(['status'=>$status,'signals'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}
mg_require_method('POST');
mg_require_permission('demand.psr.create');
$input=mg_input();mg_require_csrf_for_write($input);$pdo->beginTransaction();
try{
    $signal=mg_demand_create_psr($pdo,(int)$user['id'],$input);
    $pdo->commit();
    mg_audit('demand.psr_created','purchase_signal',['psr_id'=>$signal['public_id'],'signal_type'=>$signal['signal_type'],'merchant_user_id'=>(int)$signal['merchant_user_id'],'duplicate'=>(bool)$signal['duplicate']],(int)$user['id']);
    mg_event('demand.psr_created',['psr_id'=>$signal['public_id'],'signal_type'=>$signal['signal_type'],'merchant_user_id'=>(int)$signal['merchant_user_id'],'estimated_value_cents'=>(int)$signal['estimated_value_cents']],(int)$user['id']);
    mg_ok(['signal'=>$signal],$signal['duplicate']?'Existing purchase signal returned.':'Purchase signal created.',$signal['duplicate']?200:201);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),422);}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),409);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_security_log('error','demand.psr_create_failed','Purchase signal creation failed.',['exception'=>$e->getMessage()],(int)$user['id']);mg_fail('Unable to create purchase signal.',500);}
