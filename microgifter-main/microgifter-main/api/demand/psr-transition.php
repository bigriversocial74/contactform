<?php
declare(strict_types=1);
require_once __DIR__ . '/_demand.php';
mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();mg_require_csrf_for_write($input);
$publicId=trim((string)($input['psr_id']??''));$action=trim((string)($input['action']??''));
if($publicId===''||!in_array($action,['cancel','redeem','reopen'],true))mg_fail('Purchase signal and valid transition are required.',422);
$pdo=mg_db();$pdo->beginTransaction();
try{
    $stmt=$pdo->prepare('SELECT * FROM purchase_signal_records WHERE public_id=? LIMIT 1 FOR UPDATE');$stmt->execute([$publicId]);$signal=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$signal)throw new RuntimeException('Purchase signal not found.');
    $isOwner=(int)$signal['user_id']===(int)$user['id'];
    $isMerchant=(int)$signal['merchant_user_id']===(int)$user['id'];
    if(!$isOwner&&!$isMerchant)throw new RuntimeException('Purchase signal is not available to this user.');
    if(in_array($action,['cancel','reopen'],true)&&!$isOwner)throw new RuntimeException('Only the signal owner can perform this transition.');
    if($action==='redeem'&&!$isMerchant)throw new RuntimeException('Only the matching merchant can redeem this purchase signal.');
    $updated=mg_demand_transition_psr($pdo,$signal,$action,(int)$user['id'],$input);
    $pdo->commit();
    mg_audit('demand.psr_'.$action,'purchase_signal',['psr_id'=>$publicId,'from_status'=>$signal['status'],'to_status'=>$updated['status']],(int)$user['id']);
    mg_ok(['psr_id'=>$publicId,'status'=>$updated['status']],'Purchase signal updated.');
}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),409);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to update purchase signal.',500);}
