<?php
declare(strict_types=1);

require_once __DIR__ . '/_lifecycle.php';
require_once __DIR__ . '/_location_claim_authority.php';
require_once __DIR__ . '/_operational_outbox.php';

final class MgMicrogiftLifecycleException extends RuntimeException
{
    public function __construct(public string $resultCode,string $message)
    {
        parent::__construct($message);
    }
}

function mg_microgift_attempt_result(Throwable $error): string
{
    if ($error instanceof MgLocationClaimAuthorityException) return $error->resultCode;
    if ($error instanceof MgMicrogiftLifecycleException) return $error->resultCode;
    return 'internal_error';
}

function mg_microgift_source_is_paid(PDO $pdo,array $instance): bool
{
    $sourceType=(string)($instance['source_type']??'');
    if(empty($instance['commerce_order_item_id'])){
        return in_array($sourceType,['merchant','administrator','enterprise','workplace','agent'],true);
    }
    if($sourceType!=='commerce_order_item')return false;
    $stmt=$pdo->prepare("SELECT 1 FROM commerce_order_items oi INNER JOIN commerce_orders o ON o.id=oi.order_id WHERE oi.id=? AND o.payment_status='paid' LIMIT 1 FOR UPDATE");
    $stmt->execute([(int)$instance['commerce_order_item_id']]);
    return (bool)$stmt->fetchColumn();
}

function mg_microgift_existing_redemption(PDO $pdo,string $idempotencyKey,string $instancePublicId,int $claimantUserId,int $merchantUserId,string $locationPublicId,string $sourceReference): ?array
{
    $existing=$pdo->prepare(
        'SELECT r.public_id,r.status,r.claimant_user_id,r.merchant_user_id,r.location_reference,r.source_reference,
                i.public_id AS instance_public_id
         FROM microgift_redemptions r
         INNER JOIN microgift_instances i ON i.id=r.instance_id
         WHERE r.idempotency_key=?
         LIMIT 1 FOR UPDATE'
    );
    $existing->execute([$idempotencyKey]);
    $row=$existing->fetch(PDO::FETCH_ASSOC);
    if(!$row)return null;

    $sameRequest=hash_equals((string)$row['instance_public_id'],$instancePublicId)
        && (int)$row['claimant_user_id']===$claimantUserId
        && (int)$row['merchant_user_id']===$merchantUserId
        && hash_equals((string)$row['location_reference'],$locationPublicId)
        && hash_equals((string)$row['source_reference'],$sourceReference);
    if(!$sameRequest){
        throw new MgMicrogiftLifecycleException('idempotency_conflict','Idempotency key is already bound to a different redemption request.');
    }

    return ['redemption_id'=>$row['public_id'],'status'=>$row['status'],'duplicate'=>true];
}

function mg_microgift_upsert_inbox_redeemed(PDO $pdo,array $instance,int $claimantUserId,int $redemptionId,array $authority): string
{
    $stmt=$pdo->prepare('SELECT id,public_id FROM microgift_inbox_items WHERE instance_id=? AND user_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([(int)$instance['id'],$claimantUserId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if($row){
        $pdo->prepare("UPDATE microgift_inbox_items SET folder='claimed',state='redeemed',redemption_id=?,merchant_user_id=?,location_id=?,can_tip=1,claimed_at=COALESCE(claimed_at,NOW()),redeemed_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([$redemptionId,(int)$authority['merchant_user_id'],(int)$authority['location_id'],(int)$row['id']]);
        return (string)$row['public_id'];
    }
    $publicId=mg_microgift_uuid();
    $pdo->prepare("INSERT INTO microgift_inbox_items (public_id,instance_id,user_id,folder,state,redemption_id,merchant_user_id,location_id,can_tip,first_received_at,claimed_at,redeemed_at,metadata_json,created_at,updated_at) VALUES (?,?,?,'claimed','redeemed',?,?,?,1,NOW(),NOW(),NOW(),?,NOW(),NOW())")
        ->execute([$publicId,(int)$instance['id'],$claimantUserId,$redemptionId,(int)$authority['merchant_user_id'],(int)$authority['location_id'],mg_microgift_json([])]);
    return $publicId;
}

function mg_microgift_atomic_merchant_redeem(PDO $pdo,int $actorUserId,array $input): array
{
    $instancePublicId=trim((string)($input['instance_id']??''));
    $claimantUserId=(int)($input['claimant_user_id']??0);
    $merchantUserId=(int)($input['merchant_user_id']??0);
    $locationPublicId=trim((string)($input['location_id']??''));
    $claimCode=(string)($input['claim_code']??'');
    $idempotencyKey=trim((string)($input['idempotency_key']??''));
    $sourceReference=trim((string)($input['source_reference']??'merchant_location_claim'));
    $correlationId=trim((string)($input['correlation_id']??''))?:mg_microgift_uuid();
    if($instancePublicId===''||$claimantUserId<1||$merchantUserId<1||$locationPublicId===''||$claimCode===''||$idempotencyKey===''){
        throw new InvalidArgumentException('Instance, claimant, merchant, location, claim code, and idempotency key are required.');
    }

    $context=['merchant_user_id'=>$merchantUserId,'actor_user_id'=>$actorUserId,'idempotency_key'=>$idempotencyKey,'correlation_id'=>$correlationId];
    $started=!$pdo->inTransaction();
    if($started)$pdo->beginTransaction();

    try{
        $duplicate=mg_microgift_existing_redemption($pdo,$idempotencyKey,$instancePublicId,$claimantUserId,$merchantUserId,$locationPublicId,$sourceReference);
        if($duplicate!==null){
            if($started)$pdo->commit();
            return $duplicate+['correlation_id'=>$correlationId];
        }

        $instance=mg_microgift_expire_if_needed($pdo,mg_microgift_load_instance($pdo,$instancePublicId),$actorUserId);
        $context['instance_id']=(int)$instance['id'];
        if(!mg_microgift_source_is_paid($pdo,$instance))throw new MgMicrogiftLifecycleException('gift_not_paid','A verified paid or authorized issuance source is required.');
        if((int)$instance['owner_user_id']!==$claimantUserId)throw new MgMicrogiftLifecycleException('invalid_state','Microgift claimant ownership is invalid.');
        if(!in_array((string)$instance['status'],['claimed','redeemable'],true)){
            $code=(string)$instance['status']==='redeemed'?'already_claimed':'invalid_state';
            throw new MgMicrogiftLifecycleException($code,'Microgift is not in an eligible state.');
        }
        if($instance['expires_at']!==null&&strtotime((string)$instance['expires_at'])<=time())throw new MgMicrogiftLifecycleException('gift_expired','Microgift is expired.');

        $template=$pdo->prepare('SELECT owner_user_id FROM microgift_templates WHERE id=? LIMIT 1 FOR UPDATE');
        $template->execute([(int)$instance['template_id']]);
        if((int)$template->fetchColumn()!==$merchantUserId)throw new MgLocationClaimAuthorityException('merchant_mismatch','Merchant authority could not be verified.');

        $authority=mg_location_claim_resolve_authority($pdo,$merchantUserId,$locationPublicId,$actorUserId,$claimCode);
        $context+=['location_id'=>$authority['location_id'],'merchant_claim_code_id'=>$authority['merchant_claim_code_id']];
        if(!mg_microgift_location_allowed($instance,(string)$authority['location_public_id']))throw new MgLocationClaimAuthorityException('location_not_allowed','Microgift is not eligible at this location.');

        $attemptPublicId=mg_location_claim_record_attempt($pdo,$context+['result'=>'approved','reason_code'=>'approved']);
        $attemptId=(int)$pdo->lastInsertId();
        $redemptionPublicId=mg_microgift_uuid();
        $pdo->prepare("INSERT INTO microgift_redemptions (public_id,instance_id,claimant_user_id,merchant_user_id,location_id,merchant_claim_code_id,claim_attempt_id,can_tip,location_reference,amount_cents,currency,status,idempotency_key,source_reference,redeemed_at,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,1,?,?,?,'completed',?,?,NOW(),?,NOW())")
            ->execute([$redemptionPublicId,(int)$instance['id'],$claimantUserId,$merchantUserId,(int)$authority['location_id'],(int)$authority['merchant_claim_code_id'],$attemptId,(string)$authority['location_public_id'],$instance['face_value_cents'],$instance['currency'],$idempotencyKey,$sourceReference,mg_microgift_json((array)($input['metadata']??[]))]);
        $redemptionId=(int)$pdo->lastInsertId();

        $pppmRedemption=null;
        if(!empty($instance['pppm_item_id'])){
            $pppmRedemption=mg_pppm_redeem($pdo,(int)$instance['pppm_item_id'],$claimantUserId,'microgift_redemption',$redemptionPublicId,['microgift_instance_id'=>$instancePublicId,'location_id'=>$authority['location_public_id']]);
        }

        $stateUpdate=$pdo->prepare("UPDATE microgift_instances SET status='redeemed',redeemed_at=NOW(),updated_at=NOW() WHERE id=? AND status IN ('claimed','redeemable')");
        $stateUpdate->execute([(int)$instance['id']]);
        if($stateUpdate->rowCount()!==1)throw new MgMicrogiftLifecycleException('already_claimed','Microgift state update failed because the gift was already changed.');

        mg_location_claim_increment_usage($pdo,(int)$authority['merchant_claim_code_id']);
        $inboxPublicId=mg_microgift_upsert_inbox_redeemed($pdo,$instance,$claimantUserId,$redemptionId,$authority);
        $payload=['correlation_id'=>$correlationId,'merchant_user_id'=>$merchantUserId,'location_id'=>$authority['location_public_id'],'attempt_id'=>$attemptPublicId,'redemption_id'=>$redemptionPublicId,'inbox_item_id'=>$inboxPublicId];
        mg_microgift_event($pdo,'gift.claim_attempted',(int)$instance['id'],(int)$instance['template_id'],$actorUserId,'claim_attempt',$attemptPublicId,$payload);
        mg_microgift_event($pdo,'gift.claimed',(int)$instance['id'],(int)$instance['template_id'],$actorUserId,'redemption',$redemptionPublicId,$payload);
        mg_microgift_event($pdo,'claim.approved',(int)$instance['id'],(int)$instance['template_id'],$actorUserId,'redemption',$redemptionPublicId,$payload);
        mg_microgift_event($pdo,'merchant_location.redemption_completed',(int)$instance['id'],(int)$instance['template_id'],$actorUserId,'redemption',$redemptionPublicId,$payload);
        mg_microgift_event($pdo,'inbox.item_moved_to_claimed',(int)$instance['id'],(int)$instance['template_id'],$claimantUserId,'inbox',$inboxPublicId,$payload);
        mg_microgift_event($pdo,'psr.redeemed_pending',(int)$instance['id'],(int)$instance['template_id'],$actorUserId,'redemption',$redemptionPublicId,$payload);
        mg_microgift_event($pdo,'microgift.redemption_completed',(int)$instance['id'],(int)$instance['template_id'],$actorUserId,'redemption',$redemptionPublicId,$payload+['pppm_redemption'=>$pppmRedemption]);
        $outboxPublicId=mg_claim_operational_outbox($pdo,'merchant_claim.completed','microgift_redemption',$redemptionPublicId,$payload);

        if($started)$pdo->commit();
        return ['redemption_id'=>$redemptionPublicId,'attempt_id'=>$attemptPublicId,'inbox_item_id'=>$inboxPublicId,'outbox_id'=>$outboxPublicId,'status'=>'completed','pppm_redemption'=>$pppmRedemption,'duplicate'=>false,'correlation_id'=>$correlationId];
    }catch(Throwable $error){
        if($started&&$pdo->inTransaction())$pdo->rollBack();
        $result=mg_microgift_attempt_result($error);
        try{
            $failedAttemptId=mg_location_claim_record_attempt($pdo,$context+['result'=>$result,'reason_code'=>$result]);
            if(!empty($context['instance_id'])){
                mg_microgift_event($pdo,'gift.claim_attempted',(int)$context['instance_id'],null,$actorUserId,'claim_attempt',$failedAttemptId,['correlation_id'=>$correlationId,'result'=>$result]);
                mg_microgift_event($pdo,'gift.claim_failed',(int)$context['instance_id'],null,$actorUserId,'claim_attempt',$failedAttemptId,['correlation_id'=>$correlationId,'result'=>$result]);
            }
        }catch(Throwable $auditError){
            error_log('Microgifter claim audit persistence failed correlation='.$correlationId.' error='.$auditError->getMessage());
        }
        throw $error;
    }
}
