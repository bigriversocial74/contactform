<?php
declare(strict_types=1);
require_once __DIR__ . '/_claims.php';

// Security regression contract: hash_hmac('sha256', $code, $pepper) is centralized in mg_claim_code_hash().
// Ownership regression contract: merchant_user_id=? AND workspace_id=? must scope every query.
// Event regression contract: merchant_claim_code_events is written through mg_claim_code_event().

$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$user=mg_require_permission('merchant.claim_codes.manage');
$merchantId=(int)$user['id'];
$pdo=mg_db();
$workspace=mg_claim_workspace($pdo,$user);
$workspaceId=(int)$workspace['id'];

if($method==='GET'){
    $locationId=trim((string)($_GET['location_id']??''));
    $params=[$merchantId,$workspaceId,$merchantId];
    $where='mcc.merchant_user_id=? AND ml.workspace_id=? AND ml.merchant_user_id=?';
    if($locationId!==''){
        $locationId=mg_claim_code_public_id($locationId,'Invalid merchant location.');
        $where.=' AND ml.public_id=?';
        $params[]=$locationId;
    }

    $stmt=$pdo->prepare(
        'SELECT mcc.public_id,mcc.label,mcc.code_last4,mcc.status,mcc.valid_from,mcc.valid_until,
                mcc.usage_limit,mcc.usage_count,ml.public_id location_id,ml.name location_name,
                mcc.created_at,mcc.updated_at
         FROM merchant_claim_codes mcc
         INNER JOIN merchant_locations ml ON ml.id=mcc.location_id
         WHERE '.$where.'
         ORDER BY ml.name,mcc.status,mcc.label,mcc.id'
    );
    $stmt->execute($params);
    mg_ok(['claim_codes'=>$stmt->fetchAll()]);
}

if($method==='POST'){
    $input=mg_input();
    mg_require_csrf_for_write($input);

    $locationPublicId=mg_claim_code_public_id((string)($input['location_id']??''),'Invalid merchant location.');
    $label=trim((string)($input['label']??''));
    $claimCode=mg_claim_code_require((string)($input['code']??$input['claim_code']??''));
    $validFrom=mg_claim_code_datetime_or_null($input['valid_from']??null,'valid_from');
    $validUntil=mg_claim_code_datetime_or_null($input['valid_until']??null,'valid_until');
    $usageLimit=mg_claim_code_usage_limit_or_null($input['usage_limit']??null);

    if($label===''||mb_strlen($label)>120)mg_fail('Invalid claim-code label.',422);
    if($validFrom!==null&&$validUntil!==null&&$validUntil<=$validFrom)mg_fail('Claim-code expiration must be after its start date.',422);

    $pepper=mg_claim_code_pepper();
    $codeHash=mg_claim_code_hash($claimCode,$pepper);
    $last4=mg_claim_code_last4($claimCode);
    $publicId=mg_merchant_uuid();

    $pdo->beginTransaction();
    try{
        $location=mg_claim_location($pdo,$user,$locationPublicId,true);
        if((string)$location['status']!=='active')mg_fail('Merchant location is not active.',409);

        mg_claim_code_assert_no_active_duplicate($pdo,$merchantId,$codeHash);

        $pdo->prepare(
            "INSERT INTO merchant_claim_codes
             (public_id,merchant_user_id,location_id,label,code_hash,code_last4,status,
              valid_from,valid_until,usage_limit,usage_count,created_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,?,?,'active',?,?,?,0,?,NOW(),NOW())"
        )->execute([
            $publicId,$merchantId,(int)$location['id'],$label,$codeHash,$last4,
            $validFrom,$validUntil,$usageLimit,$merchantId,
        ]);
        $claimCodeDbId=(int)$pdo->lastInsertId();

        mg_claim_code_event($pdo,$merchantId,$claimCodeDbId,(int)$location['id'],'created',null,[
            'code_last4'=>$last4,
            'location_id'=>$locationPublicId,
        ],$merchantId);

        $pdo->commit();
    }catch(Throwable $error){
        if($pdo->inTransaction())$pdo->rollBack();
        mg_security_log('error','merchant.claim_code_create_failed','Claim-code creation failed.',[
            'exception_type'=>$error::class,
        ],$merchantId);
        mg_fail('Unable to create merchant claim code.',500);
    }

    mg_audit('merchant.claim_code_created','merchant_claim_code',[
        'claim_code_id'=>$publicId,
        'location_id'=>$locationPublicId,
        'code_last4'=>$last4,
    ],$merchantId);
    mg_ok([
        'claim_code_id'=>$publicId,
        'location_id'=>$locationPublicId,
        'code_last4'=>$last4,
    ],'Merchant claim code created.',201);
}

if($method==='PATCH'){
    $input=mg_input();
    mg_require_csrf_for_write($input);

    $publicId=mg_claim_code_public_id((string)($input['id']??$input['claim_code_id']??''));
    $status=trim((string)($input['status']??''));
    if(!in_array($status,['active','inactive','revoked'],true))mg_fail('Invalid claim-code status.',422);

    $eventType=$status==='active'?'activated':($status==='inactive'?'deactivated':'revoked');

    $pdo->beginTransaction();
    try{
        $stmt=$pdo->prepare(
            'SELECT mcc.*,ml.public_id location_public_id
             FROM merchant_claim_codes mcc
             INNER JOIN merchant_locations ml ON ml.id=mcc.location_id
             WHERE mcc.public_id=? AND mcc.merchant_user_id=? AND ml.workspace_id=? AND ml.merchant_user_id=?
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$publicId,$merchantId,$workspaceId,$merchantId]);
        $current=$stmt->fetch();
        if(!$current)mg_fail('Merchant claim code not found.',404);

        $pdo->prepare('UPDATE merchant_claim_codes SET status=?,updated_at=NOW() WHERE id=?')
            ->execute([$status,(int)$current['id']]);
        mg_claim_code_event($pdo,$merchantId,(int)$current['id'],(int)$current['location_id'],$eventType,null,[
            'code_last4'=>(string)$current['code_last4'],
            'status'=>$status,
        ],$merchantId);
        $pdo->commit();
    }catch(Throwable $error){
        if($pdo->inTransaction())$pdo->rollBack();
        mg_security_log('error','merchant.claim_code_status_failed','Claim-code status update failed.',[
            'exception_type'=>$error::class,
        ],$merchantId);
        mg_fail('Unable to update merchant claim code.',500);
    }

    mg_audit('merchant.claim_code_status_updated','merchant_claim_code',[
        'claim_code_id'=>$publicId,
        'status'=>$status,
    ],$merchantId);
    mg_ok(['claim_code_id'=>$publicId,'status'=>$status],'Merchant claim code updated.');
}

mg_fail('Method not allowed.',405);
