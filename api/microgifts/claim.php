<?php
declare(strict_types=1);

require_once __DIR__ . '/_claim_authority.php';

mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);
$pdo=mg_db();

try{
    $pdo->beginTransaction();
    $result=mg_microgift_claim_canonical($pdo,(int)$user['id'],$input);
    $pdo->commit();
    mg_audit('microgift.claim_completed','microgift_instance',[
        'instance_id'=>$result['instance_id'],
        'claim_id'=>$result['claim_id'],
        'duplicate'=>$result['duplicate']??false,
        'pppm_item_id'=>$result['pppm_item_id']??null,
        'lifecycle_status'=>$result['lifecycle_status']??null,
    ],(int)$user['id']);
    mg_ok($result,!empty($result['duplicate'])?'Existing Microgift claim returned.':'Microgift claimed.',!empty($result['duplicate'])?200:201);
}catch(MgMicrogiftClaimAuthorityException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),$error->httpStatus);
}catch(InvalidArgumentException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),422);
}catch(RuntimeException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),409);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','microgift.claim_failed','Microgift claim failed.',[
        'exception_type'=>$error::class,
    ],(int)$user['id']);
    mg_fail('Unable to claim this Microgift.',500);
}
