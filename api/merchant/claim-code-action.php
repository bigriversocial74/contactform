<?php
declare(strict_types=1);
require_once __DIR__ . '/_claims.php';

// Security regression contract: hash_hmac('sha256' is centralized in mg_claim_code_hash().

mg_require_method('POST');
$user=mg_require_permission('merchant.claim_codes.manage');
$input=mg_input();
mg_require_csrf_for_write($input);

$action=trim((string)($input['action']??''));
$publicId=mg_claim_code_public_id((string)($input['claim_code_id']??$input['id']??''));
$merchantId=(int)$user['id'];
$pdo=mg_db();
$workspace=mg_claim_workspace($pdo,$user);
$workspaceId=(int)$workspace['id'];

$stmt=$pdo->prepare(
    'SELECT mcc.*,ml.public_id location_public_id
     FROM merchant_claim_codes mcc
     INNER JOIN merchant_locations ml ON ml.id=mcc.location_id
     WHERE mcc.public_id=? AND mcc.merchant_user_id=? AND ml.workspace_id=? AND ml.merchant_user_id=?
     LIMIT 1 FOR UPDATE'
);

$pdo->beginTransaction();
try{
    $stmt->execute([$publicId,$merchantId,$workspaceId,$merchantId]);
    $current=$stmt->fetch();
    if(!$current)mg_fail('Claim code not found.',404);

    $event='';
    $resultPublicId=(string)$current['public_id'];
    $resultClaimCodeDbId=(int)$current['id'];
    $resultLast4=(string)$current['code_last4'];
    $metadata=['code_last4'=>$resultLast4];
    $previousClaimCodeId=null;

    if($action==='status'){
        $status=trim((string)($input['status']??''));
        if(!in_array($status,['active','inactive','revoked'],true))mg_fail('Invalid claim-code status.',422);

        $pdo->prepare('UPDATE merchant_claim_codes SET status=?,updated_at=NOW() WHERE id=?')
            ->execute([$status,(int)$current['id']]);
        $event=$status==='active'?'activated':($status==='inactive'?'deactivated':'revoked');
        $metadata['status']=$status;
    }elseif($action==='limit'){
        $limit=mg_claim_code_usage_limit_or_null($input['usage_limit']??null);
        $pdo->prepare('UPDATE merchant_claim_codes SET usage_limit=?,updated_at=NOW() WHERE id=?')
            ->execute([$limit,(int)$current['id']]);
        $event='limit_changed';
        $metadata['usage_limit']=$limit;
    }elseif($action==='rotate'){
        $replacementCode=mg_claim_code_require((string)($input['code']??$input['claim_code']??''),'Invalid replacement claim code.');
        $label=trim((string)($input['label']??$current['label']));
        if($label===''||mb_strlen($label)>120)mg_fail('Invalid claim-code label.',422);

        $validUntil=mg_claim_code_datetime_or_null($input['valid_until']??$current['valid_until']??null,'valid_until');
        $usageLimit=mg_claim_code_usage_limit_or_null($input['usage_limit']??$current['usage_limit']??null);
        $pepper=mg_claim_code_pepper();
        $replacementHash=mg_claim_code_hash($replacementCode,$pepper);
        $resultLast4=mg_claim_code_last4($replacementCode);

        mg_claim_code_assert_no_active_duplicate($pdo,$merchantId,$replacementHash,(int)$current['id']);

        $resultPublicId=mg_merchant_uuid();
        $pdo->prepare(
            "INSERT INTO merchant_claim_codes
             (public_id,merchant_user_id,location_id,label,code_hash,code_last4,status,
              valid_from,valid_until,usage_limit,usage_count,created_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,?,?,'active',NOW(),?,?,0,?,NOW(),NOW())"
        )->execute([
            $resultPublicId,$merchantId,(int)$current['location_id'],$label,$replacementHash,$resultLast4,
            $validUntil,$usageLimit,$merchantId,
        ]);
        $resultClaimCodeDbId=(int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE merchant_claim_codes SET status='revoked',updated_at=NOW() WHERE id=?")
            ->execute([(int)$current['id']]);

        $event='rotated';
        $previousClaimCodeId=(int)$current['id'];
        $metadata=['code_last4'=>$resultLast4,'previous_code_last4'=>(string)$current['code_last4']];
    }else{
        mg_fail('Invalid claim-code action.',422);
    }

    mg_claim_code_event(
        $pdo,$merchantId,$resultClaimCodeDbId,(int)$current['location_id'],$event,$previousClaimCodeId,$metadata,$merchantId
    );
    $pdo->commit();

    mg_audit('merchant.claim_code_'.$event,'merchant_claim_code',[
        'claim_code_id'=>$resultPublicId,
        'location_id'=>(string)$current['location_public_id'],
        'event'=>$event,
    ],$merchantId);
    mg_ok([
        'claim_code_id'=>$resultPublicId,
        'event'=>$event,
        'code_last4'=>$resultLast4,
    ],'Claim code updated.');
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','merchant.claim_code_action_failed','Claim-code action failed.',[
        'exception_type'=>$error::class,
    ],$merchantId);
    mg_fail('Unable to update claim code.',500);
}
