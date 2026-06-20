<?php
declare(strict_types=1);

if(!defined('MG_MICROGIFT_REDEMPTION_REPLAY_CONFLICT')){
    define('MG_MICROGIFT_REDEMPTION_REPLAY_CONFLICT','Redemption idempotency key is already bound to a different request.');
}

require_once __DIR__ . '/_engine.php';
require_once __DIR__ . '/_idempotency.php';
require_once dirname(__DIR__) . '/pppm/_pppm.php';
require_once dirname(__DIR__) . '/pppm/_ownership.php';
require_once dirname(__DIR__) . '/entitlements/_lifecycle.php';

function mg_microgift_load_instance(PDO $pdo, string $publicId): array
{
    $stmt=$pdo->prepare('SELECT * FROM microgift_instances WHERE public_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$publicId]);
    $row=$stmt->fetch();
    if(!$row) throw new RuntimeException('Microgift instance not found.');
    return $row;
}

function mg_microgift_expire_if_needed(PDO $pdo, array $instance, ?int $actor=null): array
{
    if($instance['expires_at']!==null && strtotime((string)$instance['expires_at'])<=time() && !in_array((string)$instance['status'],['redeemed','expired','cancelled','revoked','replaced'],true)){
        $pdo->prepare("UPDATE microgift_instances SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int)$instance['id']]);
        $pdo->prepare("UPDATE microgift_credentials SET status='expired',updated_at=NOW() WHERE instance_id=? AND status IN ('active','verified','locked')")->execute([(int)$instance['id']]);
        mg_microgift_event($pdo,'microgift.instance_expired',(int)$instance['id'],(int)$instance['template_id'],$actor,'expiration','automatic',[]);
        $instance['status']='expired';
    }
    return $instance;
}

function mg_microgift_verify_credential(PDO $pdo,string $instancePublicId,string $rawCode,string $purpose,int $actorUserId): array
{
    $instance=mg_microgift_expire_if_needed($pdo,mg_microgift_load_instance($pdo,$instancePublicId),$actorUserId);
    if(!in_array((string)$instance['status'],['issued','delivered','claim_pending','claimed','redeemable'],true)) throw new RuntimeException('Microgift is not eligible for this action.');
    $normalized=mg_microgift_normalize_code($rawCode);
    if(strlen($normalized)<12) throw new InvalidArgumentException('Invalid credential.');
    $prefix=substr($normalized,0,6);$last4=substr($normalized,-4);
    $stmt=$pdo->prepare("SELECT * FROM microgift_credentials WHERE instance_id=? AND purpose=? AND code_prefix=? AND code_last4=? AND status IN ('active','verified','locked') ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([(int)$instance['id'],$purpose,$prefix,$last4]);
    $credential=$stmt->fetch();
    if(!$credential || (string)$credential['status']==='locked') throw new RuntimeException('Invalid or locked credential.');
    if($credential['expires_at']!==null && strtotime((string)$credential['expires_at'])<=time()){
        $pdo->prepare("UPDATE microgift_credentials SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int)$credential['id']]);
        throw new RuntimeException('Credential expired.');
    }
    if(!hash_equals((string)$credential['code_hash'],mg_microgift_code_hash($normalized))){
        $attempts=(int)$credential['failed_attempts']+1;
        $locked=$attempts>=(int)$credential['max_attempts'];
        $pdo->prepare("UPDATE microgift_credentials SET failed_attempts=?,status=?,locked_at=IF(?=1,NOW(),locked_at),updated_at=NOW() WHERE id=?")
            ->execute([$attempts,$locked?'locked':'active',$locked?1:0,(int)$credential['id']]);
        mg_microgift_event($pdo,'microgift.credential_failed',(int)$instance['id'],(int)$instance['template_id'],$actorUserId,'credential',$credential['public_id'],['attempts'=>$attempts,'locked'=>$locked]);
        throw new RuntimeException('Invalid or locked credential.');
    }
    $pdo->prepare("UPDATE microgift_credentials SET status='verified',verified_at=NOW(),failed_attempts=0,locked_at=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$credential['id']]);
    $credential['status']='verified';
    return ['instance'=>$instance,'credential'=>$credential];
}

function mg_microgift_claim(PDO $pdo,int $claimantUserId,array $input): array
{
    $instancePublicId=trim((string)($input['instance_id']??''));
    $code=(string)($input['code']??'');
    $key=trim((string)($input['idempotency_key']??''));
    if($instancePublicId===''||$code===''||$key==='') throw new InvalidArgumentException('Instance, credential, and idempotency key are required.');
    $existing=$pdo->prepare('SELECT public_id,status FROM microgift_claims WHERE idempotency_key=? LIMIT 1');$existing->execute([$key]);
    if($row=$existing->fetch()) return ['claim_id'=>$row['public_id'],'status'=>$row['status'],'duplicate'=>true];
    $verified=mg_microgift_verify_credential($pdo,$instancePublicId,$code,'claim',$claimantUserId);
    $instance=$verified['instance'];$credential=$verified['credential'];
    if((string)$instance['recipient_policy']==='named_user' && (int)$instance['recipient_user_id']!==$claimantUserId) throw new RuntimeException('Microgift is assigned to another recipient.');
    $previous=(int)($instance['owner_user_id']??0);
    $claimPublic=mg_microgift_uuid();
    $transfer=null;
    if(!empty($instance['pppm_item_id'])){
        $pppm=$pdo->prepare('SELECT public_id FROM pppm_items WHERE id=? LIMIT 1');$pppm->execute([(int)$instance['pppm_item_id']]);$pppmPublic=(string)$pppm->fetchColumn();
        if($pppmPublic!=='') $transfer=mg_pppm_transfer_owner_canonical($pdo,$pppmPublic,$claimantUserId,'microgift_claim',$claimPublic,$claimantUserId,['microgift_instance_id'=>$instancePublicId]);
    }
    $pdo->prepare("INSERT INTO microgift_claims (public_id,instance_id,credential_id,claimant_user_id,status,idempotency_key,source_reference,previous_owner_user_id,pppm_item_id,entitlement_transfer_id,verified_at,completed_at,metadata_json,created_at) VALUES (?,?,?,?,'completed',?,?,?,?,?,NOW(),NOW(),?,NOW())")
        ->execute([$claimPublic,(int)$instance['id'],(int)$credential['id'],$claimantUserId,$key,$instancePublicId,$previous?:null,$instance['pppm_item_id'],$transfer['transfer_id']??null,mg_microgift_json(['recipient_policy'=>$instance['recipient_policy']])]);
    $pdo->prepare("UPDATE microgift_instances SET owner_user_id=?,recipient_user_id=COALESCE(recipient_user_id,?),status='redeemable',claimed_at=NOW(),updated_at=NOW() WHERE id=?")
        ->execute([$claimantUserId,$claimantUserId,(int)$instance['id']]);
    $pdo->prepare("UPDATE microgift_credentials SET status='consumed',consumed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$credential['id']]);
    mg_microgift_event($pdo,'microgift.claim_completed',(int)$instance['id'],(int)$instance['template_id'],$claimantUserId,'microgift_claim',$claimPublic,['previous_owner_user_id'=>$previous,'new_owner_user_id'=>$claimantUserId,'pppm_transfer'=>$transfer]);
    return ['claim_id'=>$claimPublic,'instance_id'=>$instancePublicId,'status'=>'completed','pppm_transfer'=>$transfer,'duplicate'=>false];
}

function mg_microgift_location_allowed(array $instance,?string $location): bool
{
    $policy=json_decode((string)($instance['location_policy_json']??'{}'),true);
    if(!is_array($policy)||$policy===[]) return true;
    $mode=strtolower(trim((string)($policy['mode']??'unrestricted')));
    if($mode==='unrestricted') return true;
    $allowed=array_values(array_unique(array_map('strval',array_merge(
        (array)($policy['allowed_locations']??[]),
        (array)($policy['location_ids']??[])
    ))));
    $excluded=array_map('strval',(array)($policy['excluded_locations']??[]));
    if($location===null||$location==='') return false;
    if(in_array($location,$excluded,true)) return false;
    if(in_array($mode,['allow_list','selected_locations'],true)) return $allowed!==[]&&in_array($location,$allowed,true);
    if(in_array($mode,['exclude_list','all_except'],true)) return true;
    return false;
}

function mg_microgift_canonical_merchant(PDO $pdo,array $instance): int
{
    $stmt=$pdo->prepare("SELECT COALESCE(o.merchant_user_id,CASE WHEN t.owner_type='merchant' THEN t.owner_user_id ELSE NULL END) merchant_user_id
        FROM microgift_instances mi
        INNER JOIN microgift_templates t ON t.id=mi.template_id
        LEFT JOIN commerce_order_items oi ON oi.id=mi.commerce_order_item_id
        LEFT JOIN commerce_orders o ON o.id=oi.order_id
        WHERE mi.id=? LIMIT 1 FOR UPDATE");
    $stmt->execute([(int)$instance['id']]);
    return (int)($stmt->fetchColumn()?:0);
}

function mg_microgift_redeem(PDO $pdo,int $userId,array $input,?callable $failureHook=null): array
{
    $instancePublicId=trim((string)($input['instance_id']??''));$key=trim((string)($input['idempotency_key']??''));$source=trim((string)($input['source_reference']??''));
    $merchantId=(int)($input['merchant_user_id']??0);$location=trim((string)($input['location_reference']??''));
    if($instancePublicId===''||$key===''||$source===''||$merchantId<1) throw new InvalidArgumentException('Instance, merchant, source reference, and idempotency key are required.');
    $existing=$pdo->prepare('SELECT r.*,mi.public_id instance_public_id FROM microgift_redemptions r INNER JOIN microgift_instances mi ON mi.id=r.instance_id WHERE r.idempotency_key=? LIMIT 1 FOR UPDATE');
    $existing->execute([$key]);
    if($row=$existing->fetch(PDO::FETCH_ASSOC)){
        mg_microgift_assert_redemption_replay($row,$instancePublicId,$userId,$merchantId,$source,$location?:null);
        return ['redemption_id'=>$row['public_id'],'instance_id'=>$instancePublicId,'status'=>$row['status'],'amount_cents'=>(int)$row['amount_cents'],'currency'=>$row['currency'],'financial_policy'=>'merchant_wallet_precredited_at_payment','duplicate'=>true];
    }
    $instance=mg_microgift_expire_if_needed($pdo,mg_microgift_load_instance($pdo,$instancePublicId),$userId);
    if((int)$instance['owner_user_id']!==$userId || !in_array((string)$instance['status'],['claimed','redeemable'],true)) throw new RuntimeException('Microgift is not redeemable by this user.');
    $canonicalMerchantId=mg_microgift_canonical_merchant($pdo,$instance);
    if($canonicalMerchantId<1||$canonicalMerchantId!==$merchantId)throw new RuntimeException('Microgift is not redeemable by this merchant.');
    if(!mg_microgift_location_allowed($instance,$location?:null)) throw new RuntimeException('Microgift is not eligible at this location.');
    $redemptionPublic=mg_microgift_uuid();
    $metadata=(array)($input['metadata']??[]);
    $metadata['financial_policy']='merchant_wallet_precredited_at_payment';
    $pdo->prepare("INSERT INTO microgift_redemptions (public_id,instance_id,claimant_user_id,merchant_user_id,location_reference,amount_cents,currency,status,idempotency_key,source_reference,redeemed_at,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,'completed',?,?,NOW(),?,NOW())")
        ->execute([$redemptionPublic,(int)$instance['id'],$userId,$merchantId,$location?:null,$instance['face_value_cents'],$instance['currency'],$key,$source,mg_microgift_json($metadata)]);
    $pdo->prepare("UPDATE microgift_instances SET status='redeemed',redeemed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$instance['id']]);
    $pppmRedemption=null;
    if(!empty($instance['pppm_item_id'])) $pppmRedemption=mg_pppm_redeem($pdo,(int)$instance['pppm_item_id'],$userId,'microgift_redemption',$redemptionPublic,['microgift_instance_id'=>$instancePublicId,'location_reference'=>$location?:null]);
    mg_microgift_event($pdo,'microgift.redemption_completed',(int)$instance['id'],(int)$instance['template_id'],$userId,'redemption',$redemptionPublic,['merchant_user_id'=>$merchantId,'location_reference'=>$location?:null,'pppm_redemption'=>$pppmRedemption,'financial_policy'=>'merchant_wallet_precredited_at_payment']);
    if($failureHook)$failureHook('after_redemption',['instance'=>$instance,'redemption_id'=>$redemptionPublic,'pppm_redemption'=>$pppmRedemption]);
    return ['redemption_id'=>$redemptionPublic,'instance_id'=>$instancePublicId,'status'=>'completed','amount_cents'=>(int)$instance['face_value_cents'],'currency'=>(string)$instance['currency'],'financial_policy'=>'merchant_wallet_precredited_at_payment','pppm_redemption'=>$pppmRedemption,'duplicate'=>false];
}

function mg_microgift_apply_lifecycle(PDO $pdo,array $instance,string $action,string $sourceType,string $sourceReference,string $key,?int $actor,string $reason=''): array
{
    $existing=$pdo->prepare('SELECT public_id,to_status FROM microgift_lifecycle_actions WHERE idempotency_key=? LIMIT 1');$existing->execute([$key]);
    if($row=$existing->fetch()) return ['action_id'=>$row['public_id'],'status'=>$row['to_status'],'duplicate'=>true];
    $map=['cancel'=>'cancelled','revoke'=>'revoked','expire'=>'expired','dispute_opened'=>'revoked','dispute_lost'=>'revoked','dispute_won'=>'issued','refund'=>'revoked'];
    if(!isset($map[$action])) throw new InvalidArgumentException('Invalid lifecycle action.');
    $from=(string)$instance['status'];$to=$map[$action];
    if(in_array($from,['redeemed','cancelled','revoked','expired','replaced'],true)&&$action!=='dispute_won') throw new RuntimeException('Microgift lifecycle transition is not allowed.');
    $public=mg_microgift_uuid();
    $pdo->prepare('INSERT INTO microgift_lifecycle_actions (public_id,instance_id,action_type,from_status,to_status,source_type,source_reference,idempotency_key,actor_user_id,reason,payload_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())')
        ->execute([$public,(int)$instance['id'],$action,$from,$to,$sourceType,$sourceReference,$key,$actor,$reason?:null,mg_microgift_json([])]);
    $timestamp=in_array($to,['cancelled','revoked'],true)?($to.'_at'):'updated_at';
    if($timestamp==='updated_at') $pdo->prepare('UPDATE microgift_instances SET status=?,updated_at=NOW() WHERE id=?')->execute([$to,(int)$instance['id']]);
    else $pdo->prepare("UPDATE microgift_instances SET status=?,{$timestamp}=NOW(),updated_at=NOW() WHERE id=?")->execute([$to,(int)$instance['id']]);
    if(in_array($to,['cancelled','revoked','expired'],true)) $pdo->prepare("UPDATE microgift_credentials SET status='revoked',updated_at=NOW() WHERE instance_id=? AND status IN ('active','verified','locked')")->execute([(int)$instance['id']]);
    mg_microgift_event($pdo,'microgift.instance_'.$to,(int)$instance['id'],(int)$instance['template_id'],$actor,$sourceType,$sourceReference,['reason'=>$reason]);
    return ['action_id'=>$public,'status'=>$to,'duplicate'=>false];
}

function mg_microgift_rotate_credential(PDO $pdo,array $instance,string $purpose,int $actor): array
{
    $old=$pdo->prepare("SELECT * FROM microgift_credentials WHERE instance_id=? AND purpose=? AND status IN ('active','verified','locked') ORDER BY id DESC LIMIT 1 FOR UPDATE");$old->execute([(int)$instance['id'],$purpose]);$prior=$old->fetch();
    if($prior) $pdo->prepare("UPDATE microgift_credentials SET status='rotated',updated_at=NOW() WHERE id=?")->execute([(int)$prior['id']]);
    $new=mg_microgift_create_credential($pdo,(int)$instance['id'],$purpose,$actor,$instance['expires_at']);
    if($prior) $pdo->prepare('UPDATE microgift_credentials SET rotated_to_credential_id=? WHERE id=?')->execute([(int)$pdo->lastInsertId(),(int)$prior['id']]);
    mg_microgift_event($pdo,'microgift.credential_rotated',(int)$instance['id'],(int)$instance['template_id'],$actor,'credential_rotation',$new['credential_id'],['purpose'=>$purpose]);
    return $new;
}
