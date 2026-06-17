<?php
declare(strict_types=1);

require_once __DIR__ . '/_prepaid_read.php';

function mg_prepaid_demand_reconcile(PDO $pdo,string|int $reference,?int $actorId=null):?array
{
    $instance=mg_prepaid_demand_instance($pdo,$reference,true);
    $linkStmt=$pdo->prepare('SELECT l.*,p.status psr_status,p.expected_from psr_expected_from,p.expected_to psr_expected_to FROM microgift_demand_commitment_links l INNER JOIN purchase_signal_records p ON p.id=l.purchase_signal_id WHERE l.microgift_instance_id=? LIMIT 1 FOR UPDATE');
    $linkStmt->execute([(int)$instance['id']]);
    $link=$linkStmt->fetch(PDO::FETCH_ASSOC);

    if(!mg_prepaid_demand_has_purchase_authority($instance)&&!$link)return null;
    $purchaser=mg_prepaid_demand_purchaser($instance);
    [$merchant,$location]=mg_prepaid_demand_scope($pdo,$instance);
    $value=(int)($instance['face_value_cents']??0);
    if($purchaser<1||$merchant<1||$value<1)return null;

    [$from,$to,$windowSource]=mg_prepaid_demand_window($instance);
    [$state,$status,$action,$actionAt]=mg_prepaid_demand_state($pdo,$instance);
    $redemptionStmt=$pdo->prepare("SELECT id,redeemed_at FROM microgift_redemptions WHERE instance_id=? AND status='completed' ORDER BY id DESC LIMIT 1");
    $redemptionStmt->execute([(int)$instance['id']]);
    $redemption=$redemptionStmt->fetch(PDO::FETCH_ASSOC)?:[];
    $metadata=[
        'prepaid'=>true,
        'payment_authority'=>'commerce_order',
        'payment_status'=>(string)($instance['order_payment_status']??''),
        'microgift_status'=>$instance['status'],
        'commitment_state'=>$state,
        'window_source'=>$windowSource,
        'recipient_assigned'=>!empty($instance['recipient_user_id'])||trim((string)($instance['recipient_reference']??''))!=='',
        'lifecycle_action'=>$action,
    ];
    $assetType=$instance['product_id']!==null?'product':'merchant';
    $assetReference=$instance['product_public_id']??(string)$instance['public_id'];
    $redeemedAt=$status==='redeemed'?($redemption['redeemed_at']??$instance['redeemed_at']):null;
    $canceledAt=$status==='canceled'?($actionAt??$instance['cancelled_at']??gmdate('Y-m-d H:i:s')):null;

    if(!$link){
        $insert=$pdo->prepare("INSERT INTO purchase_signal_records (public_id,user_id,merchant_user_id,location_id,product_id,asset_type,asset_reference,signal_type,status,quantity,estimated_value_cents,currency,confidence_score,expected_from,expected_to,source_type,source_reference,idempotency_key,redeemed_microgift_instance_id,redeemed_redemption_id,redeemed_at,canceled_at,expires_at,metadata_json,created_at,updated_at) VALUES (:public_id,:user_id,:merchant_id,:location_id,:product_id,:asset_type,:asset_reference,'committed_demand',:status,1,:value_cents,:currency,1.000000,:expected_from,:expected_to,:source_type,:source_reference,:idempotency_key,:redeemed_instance_id,:redemption_id,:redeemed_at,:canceled_at,:expires_at,:metadata,NOW(),NOW())");
        $insert->execute([
            'public_id'=>mg_public_uuid(),'user_id'=>$purchaser,'merchant_id'=>$merchant,'location_id'=>$location,'product_id'=>$instance['product_id'],
            'asset_type'=>$assetType,'asset_reference'=>$assetReference,'status'=>$status,'value_cents'=>$value,'currency'=>(string)$instance['currency'],
            'expected_from'=>$from->format('Y-m-d H:i:s'),'expected_to'=>$to?->format('Y-m-d H:i:s'),'source_type'=>MG_PREPAID_DEMAND_SOURCE,
            'source_reference'=>(string)$instance['public_id'],'idempotency_key'=>'microgift-demand:'.$instance['public_id'],
            'redeemed_instance_id'=>$status==='redeemed'?(int)$instance['id']:null,'redemption_id'=>$redemption?(int)$redemption['id']:null,
            'redeemed_at'=>$redeemedAt,'canceled_at'=>$canceledAt,'expires_at'=>$instance['expires_at'],
            'metadata'=>json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
        ]);
        $signalId=(int)$pdo->lastInsertId();
        mg_demand_event($pdo,$signalId,'created',null,$status,$actorId,$metadata);
        $pdo->prepare('INSERT INTO microgift_demand_commitment_links (public_id,microgift_instance_id,purchase_signal_id,lifecycle_state,expected_from_source,reconciled_at,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW(),NOW())')
            ->execute([mg_public_uuid(),(int)$instance['id'],$signalId,$state,$windowSource]);
    }else{
        $signalId=(int)$link['purchase_signal_id'];
        $previous=(string)$link['psr_status'];
        $pdo->prepare('UPDATE purchase_signal_records SET user_id=?,merchant_user_id=?,location_id=?,product_id=?,asset_type=?,asset_reference=?,status=?,estimated_value_cents=?,currency=?,confidence_score=1.000000,expected_from=?,expected_to=?,redeemed_microgift_instance_id=?,redeemed_redemption_id=?,redeemed_at=?,canceled_at=?,expires_at=?,metadata_json=?,updated_at=NOW() WHERE id=?')
            ->execute([$purchaser,$merchant,$location,$instance['product_id'],$assetType,$assetReference,$status,$value,(string)$instance['currency'],$from->format('Y-m-d H:i:s'),$to?->format('Y-m-d H:i:s'),$status==='redeemed'?(int)$instance['id']:null,$redemption?(int)$redemption['id']:null,$redeemedAt,$canceledAt,$instance['expires_at'],json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$signalId]);
        $changed=$previous!==$status||(string)$link['lifecycle_state']!==$state||(string)$link['psr_expected_from']!==$from->format('Y-m-d H:i:s')||(string)($link['psr_expected_to']??'')!==($to?->format('Y-m-d H:i:s')??'');
        if($changed)mg_demand_event($pdo,$signalId,$previous===$status?'updated':match($status){'redeemed'=>'redeemed','expired'=>'expired','canceled'=>'canceled',default=>'reopened'},$previous,$status,$actorId,$metadata);
        $pdo->prepare('UPDATE microgift_demand_commitment_links SET lifecycle_state=?,expected_from_source=?,reconciled_at=NOW(),updated_at=NOW() WHERE id=?')
            ->execute([$state,$windowSource,(int)$link['id']]);
    }

    $stmt=$pdo->prepare('SELECT p.*,l.public_id commitment_id,l.lifecycle_state,l.expected_from_source,l.reconciled_at FROM purchase_signal_records p INNER JOIN microgift_demand_commitment_links l ON l.purchase_signal_id=p.id WHERE p.id=?');
    $stmt->execute([$signalId]);
    return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
}

function mg_prepaid_demand_reconcile_batch(PDO $pdo,array $filters=[],int $limit=250):array
{
    $limit=max(1,min($limit,1000));
    $where=['i.face_value_cents>0',"(o.payment_status IN ('paid','partially_refunded','refunded','disputed') OR l.id IS NOT NULL)"];
    $params=[];
    if(!empty($filters['purchaser_user_id'])){$where[]='o.buyer_user_id=?';$params[]=(int)$filters['purchaser_user_id'];}
    if(!empty($filters['recipient_user_id'])){$where[]='i.recipient_user_id=?';$params[]=(int)$filters['recipient_user_id'];}
    if(!empty($filters['merchant_user_id'])){$where[]="(cp.merchant_user_id=? OR (t.owner_type='merchant' AND t.owner_user_id=?))";$params[]=(int)$filters['merchant_user_id'];$params[]=(int)$filters['merchant_user_id'];}
    if(!empty($filters['updated_after'])){$where[]='i.updated_at>=?';$params[]=(string)$filters['updated_after'];}
    $sql='SELECT i.id FROM microgift_instances i INNER JOIN microgift_templates t ON t.id=i.template_id LEFT JOIN catalog_products cp ON cp.id=i.product_id LEFT JOIN commerce_order_items oi ON oi.id=i.commerce_order_item_id LEFT JOIN commerce_orders o ON o.id=oi.order_id LEFT JOIN microgift_demand_commitment_links l ON l.microgift_instance_id=i.id WHERE '.implode(' AND ',$where).' ORDER BY i.updated_at,i.id LIMIT '.$limit;
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$ids=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));$created=0;$updated=0;$skipped=0;
    foreach($ids as $id){$check=$pdo->prepare('SELECT 1 FROM microgift_demand_commitment_links WHERE microgift_instance_id=?');$check->execute([$id]);$had=(bool)$check->fetchColumn();$row=mg_prepaid_demand_reconcile($pdo,$id);if(!$row)$skipped++;elseif($had)$updated++;else$created++;}
    return['processed'=>count($ids),'created'=>$created,'updated'=>$updated,'skipped'=>$skipped];
}
