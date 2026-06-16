<?php
declare(strict_types=1);
require_once __DIR__ . '/_entitlements.php';

function mg_entitlement_policy_once(PDO $pdo,string $type,string $sourceType,string $sourceReference,string $key,array $refs,int $affected,?int $actor,array $payload=[]): array
{
    $stmt=$pdo->prepare('SELECT * FROM entitlement_policy_actions WHERE idempotency_key=? LIMIT 1');
    $stmt->execute([$key]);
    if($row=$stmt->fetch())return $row;
    $public=mg_entitlement_uuid();
    $pdo->prepare('INSERT INTO entitlement_policy_actions (public_id,action_type,source_type,source_reference,idempotency_key,commerce_order_id,pppm_item_id,asset_id,affected_count,actor_user_id,payload_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())')
        ->execute([$public,$type,$sourceType,$sourceReference,$key,$refs['commerce_order_id']??null,$refs['pppm_item_id']??null,$refs['asset_id']??null,$affected,$actor,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
    return ['public_id'=>$public,'affected_count'=>$affected];
}

function mg_entitlements_restore_for_order(PDO $pdo,int $orderId,string $suspensionReason,string $restoreReason,?int $actor=null): int
{
    $stmt=$pdo->prepare("SELECT e.* FROM entitlements e INNER JOIN commerce_order_items oi ON oi.id=e.commerce_order_item_id WHERE oi.order_id=? AND e.status='suspended' AND (SELECT ee.reason_code FROM entitlement_events ee WHERE ee.entitlement_id=e.id ORDER BY ee.id DESC LIMIT 1)=? FOR UPDATE");
    $stmt->execute([$orderId,$suspensionReason]);
    $count=0;
    foreach($stmt->fetchAll() as $e){
        $pdo->prepare("UPDATE entitlements SET status='active',suspended_at=NULL,updated_at=NOW() WHERE id=? AND status='suspended'")->execute([(int)$e['id']]);
        mg_entitlement_event($pdo,(int)$e['id'],'entitlement.restored','suspended','active',$actor,$restoreReason,[]);
        $count++;
    }
    return $count;
}

function mg_entitlements_revoke_for_order_states(PDO $pdo,int $orderId,array $states,string $reason,?int $actor=null): int
{
    $marks=implode(',',array_fill(0,count($states),'?'));
    $stmt=$pdo->prepare("SELECT e.* FROM entitlements e INNER JOIN commerce_order_items oi ON oi.id=e.commerce_order_item_id WHERE oi.order_id=? AND e.status IN ($marks) FOR UPDATE");
    $stmt->execute(array_merge([$orderId],$states));
    $count=0;
    foreach($stmt->fetchAll() as $e){
        $from=(string)$e['status'];
        $pdo->prepare("UPDATE entitlements SET status='revoked',revoked_at=NOW(),revocation_reason=?,updated_at=NOW() WHERE id=?")->execute([$reason,(int)$e['id']]);
        mg_entitlement_event($pdo,(int)$e['id'],'entitlement.revoked',$from,'revoked',$actor,$reason,[]);
        $count++;
    }
    return $count;
}

function mg_entitlements_apply_dispute(PDO $pdo,int $orderId,string $outcome,string $reference,?int $actor=null): array
{
    if(!in_array($outcome,['opened','merchant_won','customer_won'],true))throw new InvalidArgumentException('Invalid dispute outcome.');
    $key='dispute:'.$reference.':'.$outcome;
    $existing=$pdo->prepare('SELECT * FROM entitlement_policy_actions WHERE idempotency_key=? LIMIT 1');$existing->execute([$key]);
    if($row=$existing->fetch())return ['action_id'=>$row['public_id'],'affected'=>(int)$row['affected_count'],'duplicate'=>true];
    if($outcome==='opened'){$affected=mg_entitlements_suspend_for_order($pdo,$orderId,'dispute_opened',$actor);$type='dispute_opened';}
    elseif($outcome==='merchant_won'){$affected=mg_entitlements_restore_for_order($pdo,$orderId,'dispute_opened','dispute_merchant_won',$actor);$type='dispute_won';}
    else{$affected=mg_entitlements_revoke_for_order_states($pdo,$orderId,['active','suspended'],'dispute_customer_won',$actor);$type='dispute_lost';}
    $action=mg_entitlement_policy_once($pdo,$type,'payment_dispute',$reference,$key,['commerce_order_id'=>$orderId],$affected,$actor,['outcome'=>$outcome]);
    mg_event('entitlement.dispute_policy_applied',['order_id'=>$orderId,'outcome'=>$outcome,'affected'=>$affected],$actor);
    return ['action_id'=>$action['public_id'],'affected'=>$affected,'duplicate'=>false];
}

function mg_entitlements_sync_pppm_owner(PDO $pdo,string $pppmPublicId,int $newOwner,string $sourceType,string $reference,?int $actor=null): array
{
    if($newOwner<1)throw new InvalidArgumentException('New owner is required.');
    $item=$pdo->prepare('SELECT * FROM pppm_items WHERE public_id=? LIMIT 1 FOR UPDATE');$item->execute([$pppmPublicId]);$pppm=$item->fetch();
    if(!$pppm)throw new RuntimeException('PPPM item not found.');
    $key='owner-sync:'.$pppmPublicId.':'.$newOwner.':'.$reference;
    $existing=$pdo->prepare('SELECT * FROM entitlement_transfers WHERE idempotency_key=? LIMIT 1');$existing->execute([$key]);
    if($row=$existing->fetch())return ['transfer_id'=>$row['public_id'],'duplicate'=>true];
    $oldOwner=(int)($pppm['owner_user_id']??0);
    $active=$pdo->prepare("SELECT * FROM entitlements WHERE pppm_item_id=? AND status IN ('active','suspended') FOR UPDATE");$active->execute([(int)$pppm['id']]);
    $revoked=0;$granted=0;
    foreach($active->fetchAll() as $e){
        if((int)$e['entitled_user_id']===$newOwner)continue;
        $from=(string)$e['status'];
        $pdo->prepare("UPDATE entitlements SET status='revoked',revoked_at=NOW(),revocation_reason='ownership_transferred',updated_at=NOW() WHERE id=?")->execute([(int)$e['id']]);
        mg_entitlement_event($pdo,(int)$e['id'],'entitlement.transferred_out',$from,'revoked',$actor,'ownership_transferred',['to_user_id'=>$newOwner]);
        $target=$pdo->prepare('SELECT * FROM entitlements WHERE pppm_item_id=? AND entitled_user_id=? AND asset_id=? AND entitlement_type=? LIMIT 1 FOR UPDATE');
        $target->execute([(int)$pppm['id'],$newOwner,(int)$e['asset_id'],$e['entitlement_type']]);
        if($prior=$target->fetch()){
            $pdo->prepare("UPDATE entitlements SET status='active',suspended_at=NULL,revoked_at=NULL,revocation_reason=NULL,source_type=?,source_reference=?,updated_at=NOW() WHERE id=?")->execute([$sourceType,$reference,(int)$prior['id']]);
            $newId=(int)$prior['id'];$newPublic=(string)$prior['public_id'];
        }else{
            $newPublic=mg_entitlement_uuid();
            $newKey='transfer:'.$pppmPublicId.':asset:'.$e['asset_id'].':user:'.$newOwner;
            $pdo->prepare("INSERT INTO entitlements (public_id,entitlement_type,status,pppm_item_id,commerce_order_item_id,product_version_id,asset_id,entitled_user_id,merchant_user_id,source_type,source_reference,idempotency_key,starts_at,expires_at,policy_json,metadata_json,created_at,updated_at) VALUES (?,?,'active',?,?,?,?,?,?,?,?,?,NOW(),?,?,?,NOW(),NOW())")
                ->execute([$newPublic,$e['entitlement_type'],(int)$pppm['id'],$e['commerce_order_item_id'],$e['product_version_id'],(int)$e['asset_id'],$newOwner,(int)$e['merchant_user_id'],$sourceType,$reference,$newKey,$e['expires_at'],$e['policy_json'],$e['metadata_json']]);
            $newId=(int)$pdo->lastInsertId();
        }
        mg_entitlement_event($pdo,$newId,'entitlement.transferred_in',null,'active',$actor,'ownership_transferred',['from_user_id'=>$oldOwner,'source_entitlement_id'=>$e['public_id'],'entitlement_id'=>$newPublic]);
        $revoked++;$granted++;
    }
    $transfer=mg_entitlement_uuid();
    $pdo->prepare("INSERT INTO entitlement_transfers (public_id,pppm_item_id,from_user_id,to_user_id,source_type,source_reference,idempotency_key,status,transferred_by_user_id,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,'completed',?,?,NOW())")
        ->execute([$transfer,(int)$pppm['id'],$oldOwner?:null,$newOwner,$sourceType,$reference,$key,$actor,json_encode(['revoked'=>$revoked,'granted'=>$granted],JSON_UNESCAPED_SLASHES)]);
    mg_entitlement_policy_once($pdo,'owner_sync',$sourceType,$reference,$key.':policy',['pppm_item_id'=>(int)$pppm['id']],$granted,$actor,['from_user_id'=>$oldOwner,'to_user_id'=>$newOwner]);
    return ['transfer_id'=>$transfer,'revoked'=>$revoked,'granted'=>$granted,'duplicate'=>false];
}

function mg_entitlements_apply_asset_policy(PDO $pdo,string $assetPublicId,string $action,string $reference,?int $actor=null): array
{
    if(!in_array($action,['removed','restored'],true))throw new InvalidArgumentException('Invalid asset policy action.');
    $assetStmt=$pdo->prepare('SELECT * FROM catalog_assets WHERE public_id=? LIMIT 1 FOR UPDATE');$assetStmt->execute([$assetPublicId]);$asset=$assetStmt->fetch();
    if(!$asset)throw new RuntimeException('Asset not found.');
    $key='asset-policy:'.$assetPublicId.':'.$action.':'.$reference;
    $exists=$pdo->prepare('SELECT * FROM entitlement_policy_actions WHERE idempotency_key=? LIMIT 1');$exists->execute([$key]);
    if($row=$exists->fetch())return ['action_id'=>$row['public_id'],'affected'=>(int)$row['affected_count'],'duplicate'=>true];
    if($action==='removed'){
        $stmt=$pdo->prepare("SELECT * FROM entitlements WHERE asset_id=? AND status='active' FOR UPDATE");$stmt->execute([(int)$asset['id']]);$affected=0;
        foreach($stmt->fetchAll() as $e){$pdo->prepare("UPDATE entitlements SET status='suspended',suspended_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$e['id']]);mg_entitlement_event($pdo,(int)$e['id'],'entitlement.suspended','active','suspended',$actor,'asset_removed',[]);mg_entitlement_create_review($pdo,'asset_removed','Protected asset was removed or disabled.',['user_id'=>(int)$e['entitled_user_id'],'merchant_user_id'=>(int)$e['merchant_user_id'],'pppm_item_id'=>(int)$e['pppm_item_id'],'entitlement_id'=>(int)$e['id']],['asset_id'=>$assetPublicId]);$affected++;}
        $type='asset_removed';
    }else{
        $stmt=$pdo->prepare("SELECT e.* FROM entitlements e WHERE e.asset_id=? AND e.status='suspended' AND (SELECT ee.reason_code FROM entitlement_events ee WHERE ee.entitlement_id=e.id ORDER BY ee.id DESC LIMIT 1)='asset_removed' FOR UPDATE");$stmt->execute([(int)$asset['id']]);$affected=0;
        foreach($stmt->fetchAll() as $e){$pdo->prepare("UPDATE entitlements SET status='active',suspended_at=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$e['id']]);mg_entitlement_event($pdo,(int)$e['id'],'entitlement.restored','suspended','active',$actor,'asset_restored',[]);$affected++;}
        $type='asset_restored';
    }
    $policy=mg_entitlement_policy_once($pdo,$type,'catalog_asset',$assetPublicId,$key,['asset_id'=>(int)$asset['id']],$affected,$actor,['source_reference'=>$reference]);
    return ['action_id'=>$policy['public_id'],'affected'=>$affected,'duplicate'=>false];
}

function mg_entitlement_delivery_response(array $grant): array
{
    $secret=trim((string)(getenv('MG_ASSET_DELIVERY_SECRET')?:''));
    $template=trim((string)(getenv('MG_ASSET_SIGNED_URL_TEMPLATE')?:''));
    if($secret!==''&&$template!==''&&isset($grant['storage_key'])){
        $expires=time()+300;
        $signature=hash_hmac('sha256',(string)$grant['storage_key'].'|'.$expires,$secret);
        $url=str_replace(['{storage_key}','{expires}','{signature}'],[rawurlencode((string)$grant['storage_key']),(string)$expires,$signature],$template);
        return ['delivery_mode'=>'signed_url','url'=>$url,'expires_at'=>gmdate('c',$expires)];
    }
    return ['delivery_mode'=>'metadata_only','message'=>'Protected delivery authorized. Storage paths remain private.'];
}
