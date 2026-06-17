<?php
declare(strict_types=1);

require_once __DIR__ . '/_demand.php';
require_once __DIR__ . '/_window.php';

const MG_PREPAID_DEMAND_SOURCE = 'microgift_prepaid';
const MG_PREPAID_DEMAND_DEFAULT_HORIZON = 30;
const MG_PREPAID_DEMAND_MAX_HORIZON = 365;
const MG_PREPAID_DEMAND_MIN_COHORT = 5;

function mg_prepaid_demand_json(mixed $value):array{if(is_array($value))return $value;if(!is_string($value)||trim($value)==='')return[];$decoded=json_decode($value,true);return is_array($decoded)?$decoded:[];}
function mg_prepaid_demand_date(mixed $value):?DateTimeImmutable{if(trim((string)$value)==='')return null;try{return new DateTimeImmutable((string)$value,new DateTimeZone('UTC'));}catch(Throwable){return null;}}
function mg_prepaid_demand_instance(PDO $pdo,string|int $reference,bool $lock=false):array{
    $column=is_int($reference)||ctype_digit((string)$reference)?'i.id':'i.public_id';
    $stmt=$pdo->prepare("SELECT i.*,t.owner_type,t.owner_user_id template_owner_user_id,v.future_demand_metadata_json,
      cp.merchant_user_id product_merchant_user_id,cp.public_id product_public_id,
      oi.order_id commerce_order_id,o.buyer_user_id order_buyer_user_id,o.payment_status order_payment_status,o.paid_at order_paid_at
      FROM microgift_instances i
      INNER JOIN microgift_templates t ON t.id=i.template_id
      INNER JOIN microgift_template_versions v ON v.id=i.template_version_id
      LEFT JOIN catalog_products cp ON cp.id=i.product_id
      LEFT JOIN commerce_order_items oi ON oi.id=i.commerce_order_item_id
      LEFT JOIN commerce_orders o ON o.id=oi.order_id
      WHERE {$column}=? LIMIT 1".($lock?' FOR UPDATE':''));
    $stmt->execute([$reference]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Microgift is not available.');return $row;
}
function mg_prepaid_demand_has_purchase_authority(array $instance):bool{
    return (int)($instance['commerce_order_item_id']??0)>0
      && (int)($instance['commerce_order_id']??0)>0
      && (int)($instance['order_buyer_user_id']??0)>0
      && in_array((string)($instance['order_payment_status']??''),['paid','partially_refunded','refunded','disputed'],true);
}
function mg_prepaid_demand_purchaser(array $instance):int{return (int)($instance['order_buyer_user_id']??0);}
function mg_prepaid_demand_scope(PDO $pdo,array $instance):array{
    $merchant=(int)($instance['product_merchant_user_id']??0);if($merchant<1&&(string)$instance['owner_type']==='merchant')$merchant=(int)$instance['template_owner_user_id'];if($merchant<1)return[0,null];
    $policy=mg_prepaid_demand_json($instance['location_policy_json']??null);$metadata=mg_prepaid_demand_json($instance['metadata_json']??null);$ref=trim((string)($policy['location_id']??$policy['location_public_id']??$metadata['location_id']??$metadata['location_public_id']??''));if($ref==='')return[$merchant,null];
    $stmt=$pdo->prepare("SELECT ml.id FROM merchant_locations ml INNER JOIN merchant_workspaces mw ON mw.id=ml.workspace_id WHERE ml.public_id=? AND mw.merchant_user_id=? AND ml.status='active' AND mw.status='active' LIMIT 1");$stmt->execute([$ref,$merchant]);$location=(int)($stmt->fetchColumn()?:0);return[$merchant,$location>0?$location:null];
}
function mg_prepaid_demand_window(array $instance):array{
    $metadata=array_merge(mg_prepaid_demand_json($instance['future_demand_metadata_json']??null),mg_prepaid_demand_json($instance['metadata_json']??null));$from=null;$source='issued_at';
    foreach(['scheduled_for','delivery_at','occasion_at','occasion_date','expected_from'] as $key){$candidate=mg_prepaid_demand_date($metadata[$key]??null);if($candidate){$from=$candidate;$source=$key;break;}}
    if(!$from){$from=mg_prepaid_demand_date($instance['delivered_at']??null)??mg_prepaid_demand_date($instance['issued_at']??null)??new DateTimeImmutable('now',new DateTimeZone('UTC'));$source=!empty($instance['delivered_at'])?'delivered_at':'issued_at';}
    $to=mg_prepaid_demand_date($metadata['expected_to']??$metadata['redemption_window_end']??null)??mg_prepaid_demand_date($instance['expires_at']??null);if($to&&$to<$from)$to=$from;return[$from,$to,$source];
}
function mg_prepaid_demand_state(PDO $pdo,array $instance):array{
    $state=match((string)$instance['status']){'issued'=>'purchased','delivered','claim_pending'=>'sent','claimed','redeemable'=>'claimed','redeemed'=>'redeemed','expired'=>'expired','replaced'=>'replaced','cancelled','revoked'=>'cancelled',default=>'purchased'};
    $stmt=$pdo->prepare('SELECT action_type,created_at FROM microgift_lifecycle_actions WHERE instance_id=? ORDER BY id DESC LIMIT 1');$stmt->execute([(int)$instance['id']]);$action=$stmt->fetch(PDO::FETCH_ASSOC)?:[];$kind=$action['action_type']??'';
    $paymentStatus=(string)($instance['order_payment_status']??'');
    if($paymentStatus==='refunded'||in_array($kind,['refund','dispute_lost'],true))$state='refunded';elseif($paymentStatus==='cancelled'||$kind==='replace')$state=$kind==='replace'?'replaced':'cancelled';elseif(in_array($kind,['cancel','revoke'],true))$state='cancelled';elseif($kind==='expire')$state='expired';
    $status=match($state){'redeemed'=>'redeemed','expired'=>'expired','cancelled','refunded','replaced'=>'canceled',default=>'outstanding'};return[$state,$status,$kind?:null,$action['created_at']??null];
}
function mg_prepaid_demand_project(array $row):array{
    $metadata=mg_prepaid_demand_json($row['metadata_json']??null);return['id'=>(string)$row['commitment_id'],'signal_id'=>(string)$row['public_id'],'microgift_id'=>(string)$row['microgift_public_id'],'title'=>(string)$row['title_snapshot'],'state'=>(string)$row['lifecycle_state'],'signal_status'=>(string)$row['status'],'value_cents'=>(int)$row['estimated_value_cents'],'currency'=>(string)$row['currency'],'expected_from'=>(string)$row['expected_from'],'expected_to'=>$row['expected_to']!==null?(string)$row['expected_to']:null,'window_source'=>(string)$row['expected_from_source'],'expires_at'=>$row['expires_at']!==null?(string)$row['expires_at']:null,'merchant'=>['id'=>$row['merchant_public_id']!==null?(string)$row['merchant_public_id']:null,'name'=>(string)($row['merchant_name']?:'Merchant'),'profile_slug'=>$row['merchant_profile_slug']!==null?(string)$row['merchant_profile_slug']:null],'product'=>$row['product_public_id']!==null?['id'=>(string)$row['product_public_id'],'title'=>(string)($row['product_title']?:'Product')]:null,'recipient'=>['assigned'=>(bool)($metadata['recipient_assigned']??false),'name'=>$row['recipient_user_id']!==null?(string)($row['recipient_name']?:'Recipient'):null],'role'=>(string)$row['viewer_role'],'updated_at'=>(string)$row['updated_at']];
}
function mg_prepaid_demand_user_commitments(PDO $pdo,int $userId,string $status,?string $cursor,int $limit):array{
    if(!in_array($status,['','outstanding','redeemed','expired','canceled'],true))throw new InvalidArgumentException('Invalid commitment status.');$limit=max(1,min($limit,50));$where=['(o.buyer_user_id=? OR i.recipient_user_id=?)'];$params=[$userId,$userId];if($status!==''){$where[]='p.status=?';$params[]=$status;}if($cursor!==null&&ctype_digit($cursor)){$where[]='l.id<?';$params[]=(int)$cursor;}
    $sql="SELECT p.*,l.id link_sort_id,l.public_id commitment_id,l.lifecycle_state,l.expected_from_source,i.public_id microgift_public_id,i.title_snapshot,i.recipient_user_id,IF(o.buyer_user_id=?,'purchaser','recipient') viewer_role,ru.display_name recipient_name,mpp.public_id merchant_public_id,mu.display_name merchant_name,mpp.slug merchant_profile_slug,cp.public_id product_public_id,cpv.title product_title FROM microgift_demand_commitment_links l INNER JOIN purchase_signal_records p ON p.id=l.purchase_signal_id INNER JOIN microgift_instances i ON i.id=l.microgift_instance_id INNER JOIN commerce_order_items oi ON oi.id=i.commerce_order_item_id INNER JOIN commerce_orders o ON o.id=oi.order_id INNER JOIN users mu ON mu.id=p.merchant_user_id LEFT JOIN users ru ON ru.id=i.recipient_user_id LEFT JOIN public_profiles mpp ON mpp.user_id=p.merchant_user_id AND mpp.status='active' LEFT JOIN catalog_products cp ON cp.id=p.product_id LEFT JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id WHERE ".implode(' AND ',$where).' ORDER BY l.id DESC LIMIT '.($limit+1);
    $stmt=$pdo->prepare($sql);$stmt->execute(array_merge([$userId],$params));$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);$more=count($rows)>$limit;if($more)array_pop($rows);return['items'=>array_map('mg_prepaid_demand_project',$rows),'next_cursor'=>$more&&$rows?(string)$rows[array_key_last($rows)]['link_sort_id']:null,'has_more'=>$more,'limit'=>$limit,'status'=>$status?:'all'];
}
