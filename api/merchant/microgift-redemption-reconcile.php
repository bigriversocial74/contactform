<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/microgifts/_redemption_reconciliation.php';
require_once dirname(__DIR__) . '/microgifts/_location_claim_authority.php';

mg_require_method('POST');
$user=mg_require_permission('merchant.location_claim.execute');
$input=mg_input();
mg_require_csrf_for_write($input);
$redemptionId=strtolower(trim((string)($input['redemption_id']??'')));
if(strlen($redemptionId)!==36||preg_match('/^[a-f0-9-]{36}$/',$redemptionId)!==1)mg_fail('A valid redemption is required.',422);

$pdo=mg_db();
try{
    $authority=$pdo->prepare(
        'SELECT r.merchant_user_id,r.location_id,ml.public_id location_public_id
         FROM microgift_redemptions r
         INNER JOIN merchant_locations ml ON ml.id=r.location_id
         WHERE r.public_id=? AND r.status=\'completed\' LIMIT 1'
    );
    $authority->execute([$redemptionId]);
    $row=$authority->fetch(PDO::FETCH_ASSOC);
    if(!$row)mg_fail('Completed redemption not found.',404);
    if(!mg_location_claim_actor_authorized($pdo,(int)$row['merchant_user_id'],(int)$row['location_id'],(int)$user['id'])){
        mg_fail('You are not authorized to reconcile this redemption.',403);
    }

    $pdo->beginTransaction();
    $result=mg_microgift_reconcile_completed_redemption($pdo,$redemptionId,(int)$user['id']);
    $pdo->commit();
    mg_audit('merchant.microgift_redemption.reconciled','microgift_redemption',[
        'redemption_id'=>$redemptionId,
        'instance_id'=>$result['instance_id']??null,
        'merchant_user_id'=>$result['merchant_user_id']??null,
        'location_id'=>$result['location_id']??null,
    ],(int)$user['id']);
    mg_ok($result,'Redemption state and confirmations verified.');
}catch(MgMicrogiftRedemptionReconciliationException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),$error->httpStatus);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','merchant.redemption_reconcile_failed','Merchant redemption reconciliation failed.',[
        'redemption_id'=>$redemptionId,
        'exception_type'=>$error::class,
    ],(int)$user['id']);
    mg_fail('Unable to reconcile this redemption.',500);
}
