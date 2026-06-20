<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/microgifts/_claim_operations.php';

mg_require_method('POST');
$user=mg_require_permission('merchant.location_claim.execute');
$input=mg_input();
mg_require_csrf_for_write($input);
$pdo=mg_db();

$instancePublicId=trim((string)($input['instance_id']??$input['identifier']??''));
$locationPublicId=strtolower(trim((string)($input['location_id']??'')));
$claimCode=(string)($input['claim_code']??$input['code']??'');
$idempotencyKey=trim((string)($input['idempotency_key']??''));
if($instancePublicId===''||$locationPublicId===''||$claimCode===''||$idempotencyKey===''){
    mg_fail('Microgift, location, claim code, and idempotency key are required.',422);
}
if(strlen($locationPublicId)!==36||preg_match('/^[a-f0-9-]{36}$/',$locationPublicId)!==1){
    mg_fail('Invalid merchant location.',422);
}
if(mb_strlen($idempotencyKey)>190)mg_fail('A valid idempotency key is required.',422);

try{
    $locationStmt=$pdo->prepare("SELECT merchant_user_id,name,status FROM merchant_locations WHERE public_id=? LIMIT 1");
    $locationStmt->execute([$locationPublicId]);
    $location=$locationStmt->fetch(PDO::FETCH_ASSOC);
    if(!$location||(string)$location['status']!=='active'){
        throw new MgLocationClaimAuthorityException('invalid_location','Location claim authority could not be verified.');
    }

    $input['instance_id']=$instancePublicId;
    $input['location_id']=$locationPublicId;
    $input['claim_code']=$claimCode;
    $input['idempotency_key']=$idempotencyKey;
    $input['source_reference']='merchant_location_claim';
    $input['correlation_id']=trim((string)($input['correlation_id']??''))?:mg_microgift_uuid();
    $input['network_fingerprint']='';
    unset($input['claimant_user_id']);

    $result=mg_claim_execute_operation(
        $pdo,
        (int)$user['id'],
        (int)$location['merchant_user_id'],
        $input
    );

    mg_audit('merchant.microgift_redemption.completed','microgift_redemption',[
        'redemption_id'=>$result['redemption_id']??null,
        'instance_id'=>$instancePublicId,
        'location_id'=>$locationPublicId,
        'merchant_user_id'=>(int)$location['merchant_user_id'],
        'actor_user_id'=>(int)$user['id'],
        'correlation_id'=>$result['correlation_id']??null,
        'customer_notification_id'=>$result['customer_notification_id']??null,
        'merchant_notification_id'=>$result['merchant_notification_id']??null,
        'duplicate'=>$result['duplicate']??false,
    ],(int)$user['id']);

    mg_ok(
        $result+[
            'merchant_user_id'=>(int)$location['merchant_user_id'],
            'location_id'=>$locationPublicId,
            'location_name'=>(string)$location['name'],
        ],
        !empty($result['duplicate'])?'Existing redemption confirmation returned.':'Microgift redeemed and both parties confirmed.',
        !empty($result['duplicate'])?200:201
    );
}catch(MgLocationClaimAuthorityException|MgMicrogiftLifecycleException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $status=match($error->resultCode){
        'rate_limited'=>429,
        'already_claimed','invalid_state','gift_expired'=>409,
        'unauthorized_claim_actor'=>403,
        default=>422,
    };
    mg_fail('Unable to complete merchant redemption.',$status,['reason'=>$error->resultCode]);
}catch(InvalidArgumentException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),422);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','merchant.microgift_redemption_failed','Merchant Microgift redemption failed.',[
        'instance_id'=>$instancePublicId,
        'location_id'=>$locationPublicId,
        'exception_class'=>$error::class,
    ],(int)$user['id']);
    mg_fail('Unable to complete merchant redemption.',500);
}
