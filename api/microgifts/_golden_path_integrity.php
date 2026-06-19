<?php
declare(strict_types=1);

require_once __DIR__ . '/_lifecycle.php';
require_once __DIR__ . '/_idempotency.php';

function mg_microgift_integrity_claim_policy_mode(array $instance): string
{
    $policy=json_decode((string)($instance['claim_policy_json']??'{}'),true);
    if(!is_array($policy))$policy=[];
    return strtolower(trim((string)($policy['mode']??'')));
}

function mg_microgift_integrity_claim(PDO $pdo,int $claimantUserId,array $input): array
{
    $instancePublicId=trim((string)($input['instance_id']??''));
    $code=trim((string)($input['code']??''));
    $key=trim((string)($input['idempotency_key']??''));
    if($instancePublicId===''||$key==='')throw new InvalidArgumentException('Instance and idempotency key are required.');
    if(strlen($key)>190)throw new InvalidArgumentException('A valid idempotency key is required.');

    $existing=mg_microgift_assert_claim_replay($pdo,$key,$instancePublicId,$claimantUserId);
    if($existing)return ['claim_id'=>$existing['public_id'],'instance_id'=>$instancePublicId,'status'=>$existing['status'],'duplicate'=>true];

    $instance=mg_microgift_expire_if_needed($pdo,mg_microgift_load_instance($pdo,$instancePublicId),$claimantUserId);
    if(!in_array((string)$instance['status'],['issued','delivered','claim_pending'],true))throw new RuntimeException('Microgift is not in a claimable lifecycle state.');
    if((string)$instance['recipient_policy']==='named_user'&&(int)$instance['recipient_user_id']!==$claimantUserId)throw new RuntimeException('Microgift is assigned to another recipient.');
    if((int)($instance['recipient_user_id']??0)>0&&(int)$instance['recipient_user_id']!==$claimantUserId)throw new RuntimeException('You are not the recipient of this Microgift.');

    $mode=mg_microgift_integrity_claim_policy_mode($instance);
    $purchaserOwned=($mode==='purchaser_owned'||(string)$instance['recipient_policy']==='purchaser')
        &&(int)($instance['owner_user_id']??0)===$claimantUserId;
    $credential=null;
    $internalCredential=false;
    if($code!==''){
        $verified=mg_microgift_verify_credential($pdo,$instancePublicId,$code,'claim',$claimantUserId);
        $instance=$verified['instance'];
        $credential=$verified['credential'];
    }elseif($purchaserOwned){
        $generated=mg_microgift_create_credential($pdo,(int)$instance['id'],'claim',$claimantUserId,$instance['expires_at']);
        $credentialStmt=$pdo->prepare('SELECT * FROM microgift_credentials WHERE public_id=? LIMIT 1 FOR UPDATE');
        $credentialStmt->execute([(string)$generated['credential_id']]);
        $credential=$credentialStmt->fetch(PDO::FETCH_ASSOC);
        if(!$credential)throw new RuntimeException('Unable to create the purchaser claim record.');
        $pdo->prepare("UPDATE microgift_credentials SET status='verified',verified_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$credential['id']]);
        $internalCredential=true;
    }else{
        throw new InvalidArgumentException('A claim credential is required.');
    }

    $previous=(int)($instance['owner_user_id']??0);
    $claimPublic=mg_microgift_uuid();
    $transfer=null;
    if(!empty($instance['pppm_item_id'])){
        $pppm=$pdo->prepare('SELECT public_id,owner_user_id FROM pppm_items WHERE id=? LIMIT 1 FOR UPDATE');
        $pppm->execute([(int)$instance['pppm_item_id']]);
        $pppmRow=$pppm->fetch(PDO::FETCH_ASSOC);
        if($pppmRow&&(int)$pppmRow['owner_user_id']!==$claimantUserId){
            $transfer=mg_pppm_transfer_owner_canonical($pdo,(string)$pppmRow['public_id'],$claimantUserId,'microgift_claim',$claimPublic,$claimantUserId,['microgift_instance_id'=>$instancePublicId]);
        }
    }

    $pdo->prepare("INSERT INTO microgift_claims (public_id,instance_id,credential_id,claimant_user_id,status,idempotency_key,source_reference,previous_owner_user_id,pppm_item_id,entitlement_transfer_id,verified_at,completed_at,metadata_json,created_at) VALUES (?,?,?,?,'completed',?,?,?,?,?,NOW(),NOW(),?,NOW())")
        ->execute([$claimPublic,(int)$instance['id'],(int)$credential['id'],$claimantUserId,$key,$instancePublicId,$previous?:null,$instance['pppm_item_id'],$transfer['transfer_id']??null,mg_microgift_json(['recipient_policy'=>$instance['recipient_policy'],'claim_mode'=>$purchaserOwned?'purchaser_owned':'credential','internal_credential'=>$internalCredential])]);
    $pdo->prepare("UPDATE microgift_instances SET owner_user_id=?,recipient_user_id=COALESCE(recipient_user_id,?),status='redeemable',claimed_at=NOW(),updated_at=NOW() WHERE id=?")
        ->execute([$claimantUserId,$claimantUserId,(int)$instance['id']]);
    $pdo->prepare("UPDATE microgift_credentials SET status='consumed',consumed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$credential['id']]);
    mg_microgift_event($pdo,'microgift.claim_completed',(int)$instance['id'],(int)$instance['template_id'],$claimantUserId,'microgift_claim',$claimPublic,['previous_owner_user_id'=>$previous,'new_owner_user_id'=>$claimantUserId,'pppm_transfer'=>$transfer,'claim_mode'=>$purchaserOwned?'purchaser_owned':'credential','internal_credential'=>$internalCredential]);
    return ['claim_id'=>$claimPublic,'instance_id'=>$instancePublicId,'status'=>'completed','pppm_transfer'=>$transfer,'claim_mode'=>$purchaserOwned?'purchaser_owned':'credential','duplicate'=>false];
}

function mg_microgift_integrity_location_allowed(array $instance,?string $location): bool
{
    $policy=json_decode((string)($instance['location_policy_json']??'{}'),true);
    if(!is_array($policy)||$policy===[])return true;
    $mode=strtolower(trim((string)($policy['mode']??'unrestricted')));
    if($mode==='unrestricted')return true;
    $allowed=array_values(array_unique(array_map('strval',array_merge((array)($policy['allowed_locations']??[]),(array)($policy['location_ids']??[])))));
    $excluded=array_map('strval',(array)($policy['excluded_locations']??[]));
    if($location===null||$location==='')return false;
    if(in_array($location,$excluded,true))return false;
    if(in_array($mode,['allow_list','selected_locations'],true))return $allowed!==[]&&in_array($location,$allowed,true);
    if(in_array($mode,['exclude_list','all_except'],true))return true;
    return false;
}

function mg_microgift_integrity_redeem(PDO $pdo,int $userId,array $input,?callable $failureHook=null): array
{
    $instancePublicId=trim((string)($input['instance_id']??''));
    $location=trim((string)($input['location_reference']??''));
    if($instancePublicId==='')throw new InvalidArgumentException('Instance is required.');
    $instance=mg_microgift_expire_if_needed($pdo,mg_microgift_load_instance($pdo,$instancePublicId),$userId);
    if(!mg_microgift_integrity_location_allowed($instance,$location?:null))throw new RuntimeException('Microgift is not eligible at this location.');
    return mg_microgift_redeem($pdo,$userId,$input,$failureHook);
}
