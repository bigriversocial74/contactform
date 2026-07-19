<?php
declare(strict_types=1);
require_once __DIR__ . '/_claims.php';

// Security regression contract: hash_hmac('sha256', $code, $pepper) is centralized in mg_claim_code_hash().
// Ownership regression contract: the owned merchant location is authoritative; stale claim-code owner metadata is normalized on write.
// Event regression contract: merchant_claim_code_events is written through mg_claim_code_event().

$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$user=mg_require_permission('merchant.claim_codes.manage');
$actorUserId=(int)$user['id'];
$pdo=mg_db();
$workspace=mg_claim_workspace($pdo,$user);
$scope=mg_merchant_location_scope_context($workspace);
$workspaceId=(int)$scope['workspace_id'];
$ownerMerchantId=(int)$scope['owner_merchant_id'];

if($method==='GET'){
    $locationId=trim((string)($_GET['location_id']??''));
    $params=[$workspaceId,$ownerMerchantId];
    $where=mg_merchant_location_scope_condition('ml','location_scope_mw');
    if($locationId!==''){
        $locationId=mg_claim_code_public_id($locationId,'Invalid merchant location.');
        $where.=' AND ml.public_id=?';
        $params[]=$locationId;
    }

    $stmt=$pdo->prepare(
        'SELECT mcc.public_id,mcc.label,mcc.assignment_type,mcc.assignment_reference,
                mcc.code_last4,mcc.status,mcc.valid_from,mcc.valid_until,
                mcc.usage_limit,mcc.usage_count,ml.public_id location_id,ml.name location_name,
                mcc.created_at,mcc.updated_at,
                CASE
                  WHEN mcc.status<>\'active\' THEN 0
                  WHEN mcc.valid_from IS NOT NULL AND mcc.valid_from>NOW() THEN 0
                  WHEN mcc.valid_until IS NOT NULL AND mcc.valid_until<NOW() THEN 0
                  WHEN mcc.usage_limit IS NOT NULL AND mcc.usage_count>=mcc.usage_limit THEN 0
                  ELSE 1
                END AS currently_usable
         FROM merchant_claim_codes mcc
         INNER JOIN merchant_locations ml ON ml.id=mcc.location_id
         '.mg_merchant_location_scope_join('ml','location_scope_mw').'
         WHERE '.$where.'
         ORDER BY ml.name,currently_usable DESC,mcc.label,mcc.id DESC'
    );
    $stmt->execute($params);
    mg_ok(['claim_codes'=>$stmt->fetchAll(),'multi_code_per_location'=>true]);
}

if($method==='POST'){
    $input=mg_input();
    mg_require_csrf_for_write($input);

    $locationPublicId=mg_claim_code_public_id((string)($input['location_id']??''),'Invalid merchant location.');
    $label=trim((string)($input['label']??''));
    $assignmentType=strtolower(trim((string)($input['assignment_type']??'location')));
    $assignmentReference=trim((string)($input['assignment_reference']??''))?:null;
    $claimCode=mg_claim_code_require((string)($input['code']??$input['claim_code']??''));
    $validFrom=mg_claim_code_datetime_or_null($input['valid_from']??null,'valid_from');
    $validUntil=mg_claim_code_datetime_or_null($input['valid_until']??null,'valid_until');
    $usageLimit=mg_claim_code_usage_limit_or_null($input['usage_limit']??null);

    $allowedAssignmentTypes=['location','staff','register','device','campaign','department','event','integration'];
    if($label===''||mb_strlen($label)>120)mg_fail('Invalid claim-code label.',422);
    if(!in_array($assignmentType,$allowedAssignmentTypes,true))mg_fail('Invalid claim-code assignment type.',422);
    if($assignmentReference!==null&&mb_strlen($assignmentReference)>120)mg_fail('Assignment reference is too long.',422);
    if($assignmentType!=='location'&&$assignmentReference===null)mg_fail('Assignment reference is required for this assignment type.',422);
    if($validFrom!==null&&$validUntil!==null&&$validUntil<=$validFrom)mg_fail('Claim-code expiration must be after its start date.',422);

    $pepper=mg_claim_code_pepper();
    $codeHash=mg_claim_code_hash($claimCode,$pepper);
    $last4=mg_claim_code_last4($claimCode);
    $publicId=mg_merchant_uuid();

    $pdo->beginTransaction();
    try{
        $location=mg_claim_location($pdo,$user,$locationPublicId,true);
        if((string)$location['status']!=='active')mg_fail('Merchant location is not active.',409);

        // A merchant may have many codes per location, but the same active secret cannot be reused
        // elsewhere in the merchant workspace.
        mg_claim_code_assert_no_active_duplicate($pdo,$workspaceId,$ownerMerchantId,$codeHash);

        $pdo->prepare(
            "INSERT INTO merchant_claim_codes
             (public_id,merchant_user_id,location_id,label,assignment_type,assignment_reference,
              code_hash,code_last4,status,valid_from,valid_until,usage_limit,usage_count,
              created_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,'active',?,?,?,0,?,NOW(),NOW())"
        )->execute([
            $publicId,$ownerMerchantId,(int)$location['id'],$label,$assignmentType,$assignmentReference,
            $codeHash,$last4,$validFrom,$validUntil,$usageLimit,$actorUserId,
        ]);
        $claimCodeDbId=(int)$pdo->lastInsertId();

        mg_claim_code_event($pdo,$ownerMerchantId,$claimCodeDbId,(int)$location['id'],'created',null,[
            'code_last4'=>$last4,
            'location_id'=>$locationPublicId,
            'assignment_type'=>$assignmentType,
            'assignment_reference'=>$assignmentReference,
        ],$actorUserId);

        mg_merchant_location_normalize_ownership($pdo,(int)$location['id'],$workspaceId,$ownerMerchantId);
        $pdo->commit();
    }catch(Throwable $error){
        if($pdo->inTransaction())$pdo->rollBack();
        if(function_exists('mg_security_log'))mg_security_log('error','merchant.claim_code_create_failed','Claim-code creation failed.',[
            'exception_type'=>$error::class,
        ],$actorUserId);
        throw $error;
    }

    mg_audit('merchant.claim_code_created','merchant_claim_code',[
        'claim_code_id'=>$publicId,
        'location_id'=>$locationPublicId,
        'code_last4'=>$last4,
        'assignment_type'=>$assignmentType,
        'assignment_reference'=>$assignmentReference,
        'owner_merchant_id'=>$ownerMerchantId,
    ],$actorUserId);
    mg_ok([
        'claim_code_id'=>$publicId,
        'location_id'=>$locationPublicId,
        'code_last4'=>$last4,
        'assignment_type'=>$assignmentType,
        'assignment_reference'=>$assignmentReference,
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
            'SELECT mcc.*,ml.public_id location_public_id,ml.status location_status
             FROM merchant_claim_codes mcc
             INNER JOIN merchant_locations ml ON ml.id=mcc.location_id
             '.mg_merchant_location_scope_join('ml','location_scope_mw').'
             WHERE mcc.public_id=? AND '.mg_merchant_location_scope_condition('ml','location_scope_mw').'
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$publicId,$workspaceId,$ownerMerchantId]);
        $current=$stmt->fetch();
        if(!$current)mg_fail('Merchant claim code not found.',404);
        if($status==='active'&&(string)$current['location_status']!=='active')mg_fail('Activate the merchant location before activating this claim code.',409);

        $pdo->prepare('UPDATE merchant_claim_codes SET merchant_user_id=?,status=?,updated_at=NOW() WHERE id=?')
            ->execute([$ownerMerchantId,$status,(int)$current['id']]);
        mg_merchant_location_normalize_ownership($pdo,(int)$current['location_id'],$workspaceId,$ownerMerchantId);
        mg_claim_code_event($pdo,$ownerMerchantId,(int)$current['id'],(int)$current['location_id'],$eventType,null,[
            'code_last4'=>(string)$current['code_last4'],
            'status'=>$status,
            'assignment_type'=>(string)($current['assignment_type']??'location'),
            'assignment_reference'=>$current['assignment_reference']??null,
        ],$actorUserId);
        $pdo->commit();
    }catch(Throwable $error){
        if($pdo->inTransaction())$pdo->rollBack();
        if(function_exists('mg_security_log'))mg_security_log('error','merchant.claim_code_status_failed','Claim-code status update failed.',[
            'exception_type'=>$error::class,
        ],$actorUserId);
        throw $error;
    }

    mg_audit('merchant.claim_code_status_updated','merchant_claim_code',[
        'claim_code_id'=>$publicId,
        'status'=>$status,
    ],$actorUserId);
    mg_ok(['claim_code_id'=>$publicId,'status'=>$status],'Merchant claim code updated.');
}

mg_fail('Method not allowed.',405);
