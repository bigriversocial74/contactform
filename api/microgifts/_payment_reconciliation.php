<?php
declare(strict_types=1);

require_once __DIR__ . '/_lifecycle.php';
require_once __DIR__ . '/_action_center_projection.php';
require_once dirname(__DIR__) . '/pppm/_pppm.php';
require_once dirname(__DIR__) . '/entitlements/_entitlements.php';

function mg_microgift_payment_instances_for_order(PDO $pdo,int $orderId): array
{
    $stmt=$pdo->prepare("SELECT mi.* FROM microgift_instances mi INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference WHERE oi.order_id=? AND mi.source_type='commerce_order_item' ORDER BY mi.id FOR UPDATE");
    $stmt->execute([$orderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mg_microgift_payment_action_existing(PDO $pdo,int $instanceId,string $key,string $action,string $sourceType,string $sourceReference): ?array
{
    $stmt=$pdo->prepare('SELECT * FROM microgift_lifecycle_actions WHERE idempotency_key=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$key]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)return null;
    $exact=(int)$row['instance_id']===$instanceId&&(string)$row['action_type']===$action&&(string)$row['source_type']===$sourceType&&(string)$row['source_reference']===$sourceReference;
    if(!$exact)throw new RuntimeException('Microgift payment reconciliation idempotency conflict.');
    return $row;
}

function mg_microgift_payment_record_action(PDO $pdo,array $instance,string $action,string $from,string $to,string $sourceType,string $sourceReference,string $key,?int $actorUserId,string $reason,array $payload=[]): array
{
    $existing=mg_microgift_payment_action_existing($pdo,(int)$instance['id'],$key,$action,$sourceType,$sourceReference);
    if($existing)return ['action_id'=>(string)$existing['public_id'],'status'=>(string)$existing['to_status'],'duplicate'=>true];
    $publicId=mg_microgift_uuid();
    $pdo->prepare('INSERT INTO microgift_lifecycle_actions (public_id,instance_id,action_type,from_status,to_status,source_type,source_reference,idempotency_key,actor_user_id,reason,payload_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())')
        ->execute([$publicId,(int)$instance['id'],$action,$from,$to,$sourceType,$sourceReference,$key,$actorUserId,$reason?:null,mg_microgift_json($payload)]);
    return ['action_id'=>$publicId,'status'=>$to,'duplicate'=>false];
}

function mg_microgift_payment_pppm_event(PDO $pdo,array $item,string $eventType,string $from,string $to,?int $actorUserId,array $metadata): void
{
    $pdo->prepare('UPDATE pppm_items SET status=?,version_no=version_no+1,updated_at=NOW() WHERE id=?')->execute([$to,(int)$item['id']]);
    mg_pppm_record_event($pdo,mg_pppm_refresh($pdo,(int)$item['id']),$eventType,$from,$to,$actorUserId,null,$metadata);
}

function mg_microgift_payment_prior_pppm_status(PDO $pdo,int $pppmItemId,string $disputeId): ?string
{
    $stmt=$pdo->prepare("SELECT from_status,metadata_json FROM pppm_item_events WHERE pppm_item_id=? AND event_type='payment_dispute_suspended' ORDER BY id DESC");
    $stmt->execute([$pppmItemId]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $metadata=!empty($row['metadata_json'])?json_decode((string)$row['metadata_json'],true):[];
        if(is_array($metadata)&&(string)($metadata['dispute_id']??'')===$disputeId)return (string)$row['from_status'];
    }
    return null;
}

function mg_microgift_payment_review_type(string $detailType): string
{
    return match($detailType){
        'microgift_partial_refund'=>'partial_refund',
        'microgift_partial_dispute'=>'dispute',
        default=>'policy_exception',
    };
}

function mg_microgift_payment_review_existing(PDO $pdo,int $orderId,string $detailType,string $sourceReference,?string $instancePublicId=null): ?array
{
    $sql="SELECT * FROM entitlement_review_items WHERE commerce_order_id=? AND review_type=? AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.microgift_review_type'))=? AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.source_reference'))=?";
    $params=[$orderId,mg_microgift_payment_review_type($detailType),$detailType,$sourceReference];
    if($instancePublicId!==null){
        $sql.=" AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.microgift_instance_id'))=?";
        $params[]=$instancePublicId;
    }
    $sql.=' ORDER BY id DESC LIMIT 1 FOR UPDATE';
    $stmt=$pdo->prepare($sql);$stmt->execute($params);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}

function mg_microgift_payment_review_once(PDO $pdo,array $order,string $detailType,string $sourceReference,string $reason,array $payload=[]): bool
{
    $instancePublicId=isset($payload['microgift_instance_id'])?(string)$payload['microgift_instance_id']:null;
    if(mg_microgift_payment_review_existing($pdo,(int)$order['order_db_id'],$detailType,$sourceReference,$instancePublicId))return false;
    mg_entitlement_create_review($pdo,mg_microgift_payment_review_type($detailType),$reason,[
        'user_id'=>(int)$order['buyer_user_id'],
        'merchant_user_id'=>(int)$order['merchant_user_id'],
        'commerce_order_id'=>(int)$order['order_db_id'],
    ],['microgift_review_type'=>$detailType,'source_reference'=>$sourceReference]+$payload);
    return true;
}

function mg_microgift_payment_resolve_review(PDO $pdo,array $order,string $detailType,string $sourceReference,?string $instancePublicId,?int $actorUserId): int
{
    $sql="UPDATE entitlement_review_items SET status='resolved',resolved_by_user_id=?,resolved_at=NOW(),updated_at=NOW() WHERE commerce_order_id=? AND review_type=? AND status='open' AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.microgift_review_type'))=? AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.source_reference'))=?";
    $params=[$actorUserId,(int)$order['order_db_id'],mg_microgift_payment_review_type($detailType),$detailType,$sourceReference];
    if($instancePublicId!==null){
        $sql.=" AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.microgift_instance_id'))=?";
        $params[]=$instancePublicId;
    }
    $stmt=$pdo->prepare($sql);$stmt->execute($params);
    return $stmt->rowCount();
}

function mg_microgift_payment_refresh_action_center(PDO $pdo,array $instance): int
{
    mg_action_center_require_transaction($pdo);
    $state=mg_action_center_state($instance);
    $recipientFolder=mg_action_center_recipient_folder($instance);
    $stmt=$pdo->prepare("UPDATE microgift_inbox_items
        SET folder=CASE WHEN folder='sent' THEN 'sent' ELSE ? END,
            state=?,
            claimed_at=COALESCE(?,claimed_at),
            redeemed_at=COALESCE(?,redeemed_at),
            updated_at=NOW()
        WHERE instance_id=?");
    $stmt->execute([
        $recipientFolder,
        $state,
        $instance['claimed_at']??null,
        $instance['redeemed_at']??null,
        (int)$instance['id'],
    ]);
    return $stmt->rowCount();
}

function mg_microgift_payment_reconcile_order(PDO $pdo,array $order,string $operation,string $sourceReference,?int $actorUserId=null,array $context=[]): array
{
    if(!$pdo->inTransaction())throw new LogicException('Microgift payment reconciliation requires the owning payment transaction.');
    $operations=['partial_refund','full_refund','dispute_opened','dispute_opened_partial','dispute_won','dispute_won_partial','dispute_lost_partial','dispute_lost_full'];
    if(!in_array($operation,$operations,true))throw new InvalidArgumentException('Unsupported Microgift payment reconciliation operation.');
    if(trim($sourceReference)==='')throw new InvalidArgumentException('Microgift payment reconciliation source is required.');

    $instances=mg_microgift_payment_instances_for_order($pdo,(int)$order['order_db_id']);
    $result=['operation'=>$operation,'instances'=>count($instances),'revoked'=>0,'suspended'=>0,'restored'=>0,'reviewed'=>0,'resolved_reviews'=>0,'duplicates'=>0];
    if(!$instances)return $result;

    if($operation==='partial_refund'){
        $created=mg_microgift_payment_review_once($pdo,$order,'microgift_partial_refund',$sourceReference,'Partial refund requires deterministic Microgift unit review.',[
            'operation'=>$operation,'amount_cents'=>(int)($context['amount_cents']??0),'microgift_count'=>count($instances),
        ]);
        $result['reviewed']=$created?1:0;
        return $result;
    }

    if(in_array($operation,['dispute_opened_partial','dispute_lost_partial'],true)){
        $created=mg_microgift_payment_review_once($pdo,$order,'microgift_partial_dispute',$sourceReference,'Partial dispute requires deterministic Microgift unit review.',[
            'operation'=>$operation,'amount_cents'=>(int)($context['amount_cents']??0),'microgift_count'=>count($instances),
        ]);
        $result['reviewed']=$created?1:0;
        return $result;
    }

    if($operation==='dispute_won_partial'){
        $result['resolved_reviews']=mg_microgift_payment_resolve_review($pdo,$order,'microgift_partial_dispute',$sourceReference,null,$actorUserId);
        return $result;
    }

    foreach($instances as $instance){
        $status=(string)$instance['status'];
        $instancePublicId=(string)$instance['public_id'];
        $key='payment-reconciliation:'.$operation.':'.$sourceReference.':'.$instancePublicId;
        $sourceType=str_starts_with($operation,'dispute_')?'payment_dispute':'payment_refund';

        if($operation==='dispute_won'){
            if($status==='redeemed'){
                $result['resolved_reviews']+=mg_microgift_payment_resolve_review($pdo,$order,'microgift_recovery',$sourceReference,$instancePublicId,$actorUserId);
                continue;
            }
            $open=$pdo->prepare("SELECT * FROM microgift_lifecycle_actions WHERE instance_id=? AND action_type='dispute_opened' AND source_reference=? ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $open->execute([(int)$instance['id'],$sourceReference]);$openAction=$open->fetch(PDO::FETCH_ASSOC);
            if(!$openAction){
                $created=mg_microgift_payment_review_once($pdo,$order,'microgift_dispute_restore',$sourceReference,'Dispute win could not find the original Microgift suspension state.',['microgift_instance_id'=>$instancePublicId]);
                if($created)$result['reviewed']++;
                continue;
            }
            $restoreStatus=(string)$openAction['from_status'];
            $action=mg_microgift_payment_record_action($pdo,$instance,'dispute_won',$status,$restoreStatus,$sourceType,$sourceReference,$key,$actorUserId,'payment_dispute_won',['restored_from_action_id'=>(string)$openAction['public_id']]);
            if($action['duplicate']){$result['duplicates']++;continue;}
            $pdo->prepare('UPDATE microgift_instances SET status=?,revoked_at=NULL,updated_at=NOW() WHERE id=?')->execute([$restoreStatus,(int)$instance['id']]);
            if(!empty($instance['pppm_item_id'])){
                $item=mg_pppm_locked_by_id($pdo,(int)$instance['pppm_item_id']);
                $prior=mg_microgift_payment_prior_pppm_status($pdo,(int)$item['id'],$sourceReference);
                if((string)$item['status']==='voided'&&$prior!==null&&$prior!=='redeemed')mg_microgift_payment_pppm_event($pdo,$item,'payment_dispute_restored','voided',$prior,$actorUserId,['dispute_id'=>$sourceReference,'microgift_instance_id'=>$instancePublicId]);
            }
            $instance['status']=$restoreStatus;$instance['revoked_at']=null;
            mg_microgift_event($pdo,'microgift.payment_dispute_restored',(int)$instance['id'],(int)$instance['template_id'],$actorUserId,$sourceType,$sourceReference,['restored_status'=>$restoreStatus]);
            mg_microgift_payment_refresh_action_center($pdo,$instance);$result['restored']++;
            continue;
        }

        if($status==='redeemed'){
            $created=mg_microgift_payment_review_once($pdo,$order,'microgift_recovery',$sourceReference,'A redeemed Microgift requires financial recovery review instead of lifecycle reversal.',[
                'operation'=>$operation,'microgift_instance_id'=>$instancePublicId,'pppm_item_id'=>$instance['pppm_item_id']??null,'amount_cents'=>(int)($context['amount_cents']??0),
            ]);
            if($created)$result['reviewed']++;
            continue;
        }

        $temporary=$operation==='dispute_opened';
        $actionType=$temporary?'dispute_opened':($operation==='full_refund'?'refund':'dispute_lost');
        $reason=$temporary?'payment_dispute_opened':($operation==='full_refund'?'full_refund':'payment_dispute_lost');
        $action=mg_microgift_payment_record_action($pdo,$instance,$actionType,$status,'revoked',$sourceType,$sourceReference,$key,$actorUserId,$reason,['temporary'=>$temporary,'amount_cents'=>(int)($context['amount_cents']??0)]);
        if($action['duplicate']){$result['duplicates']++;continue;}

        $pdo->prepare("UPDATE microgift_instances SET status='revoked',revoked_at=COALESCE(revoked_at,NOW()),updated_at=NOW() WHERE id=?")->execute([(int)$instance['id']]);
        if(!$temporary)$pdo->prepare("UPDATE microgift_credentials SET status='revoked',updated_at=NOW() WHERE instance_id=? AND status IN ('active','verified','locked')")->execute([(int)$instance['id']]);
        if(!empty($instance['pppm_item_id'])){
            $item=mg_pppm_locked_by_id($pdo,(int)$instance['pppm_item_id']);$from=(string)$item['status'];
            if($from!=='redeemed'&&!in_array($from,['cancelled','expired','refunded'],true)){
                $to=$temporary?'voided':'refunded';
                mg_microgift_payment_pppm_event($pdo,$item,$temporary?'payment_dispute_suspended':($operation==='full_refund'?'payment_refund_revoked':'payment_dispute_revoked'),$from,$to,$actorUserId,[
                    'source_reference'=>$sourceReference,'dispute_id'=>str_starts_with($operation,'dispute_')?$sourceReference:null,'microgift_instance_id'=>$instancePublicId,
                ]);
            }
        }
        $instance['status']='revoked';
        mg_microgift_event($pdo,$temporary?'microgift.payment_dispute_suspended':'microgift.payment_reversal_revoked',(int)$instance['id'],(int)$instance['template_id'],$actorUserId,$sourceType,$sourceReference,['operation'=>$operation,'temporary'=>$temporary]);
        mg_microgift_payment_refresh_action_center($pdo,$instance);
        if($temporary)$result['suspended']++;else$result['revoked']++;
    }
    return $result;
}
