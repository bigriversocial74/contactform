<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/microgifts/_claim_operations.php';

mg_require_method('GET');
$user=mg_require_permission('merchant.claims.view');
$pdo=mg_db();
$instanceId=trim((string)($_GET['instance_id']??$_GET['id']??''));
$locationId=strtolower(trim((string)($_GET['location_id']??'')));
if($instanceId===''||$locationId==='')mg_fail('Microgift and location are required.',422);

$locationStmt=$pdo->prepare("SELECT id,public_id,merchant_user_id,name,status FROM merchant_locations WHERE public_id=? LIMIT 1");
$locationStmt->execute([$locationId]);
$location=$locationStmt->fetch(PDO::FETCH_ASSOC);
if(!$location||(string)$location['status']!=='active')mg_fail('Merchant location not found.',404);
if(!mg_location_claim_actor_authorized($pdo,(int)$location['merchant_user_id'],(int)$location['id'],(int)$user['id'])){
    mg_fail('You are not authorized for this merchant location.',403);
}

$stmt=$pdo->prepare("SELECT i.*,p.public_id pppm_id,
        COALESCE(o.merchant_user_id,CASE WHEN t.owner_type='merchant' THEN t.owner_user_id ELSE NULL END) canonical_merchant_user_id,
        o.payment_status
    FROM microgift_instances i
    INNER JOIN microgift_templates t ON t.id=i.template_id
    LEFT JOIN pppm_items p ON p.id=i.pppm_item_id
    LEFT JOIN commerce_order_items oi ON oi.id=i.commerce_order_item_id
    LEFT JOIN commerce_orders o ON o.id=oi.order_id
    WHERE i.public_id=? LIMIT 1");
$stmt->execute([$instanceId]);
$instance=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$instance)mg_fail('Microgift not found.',404);
if((int)($instance['canonical_merchant_user_id']??0)!==(int)$location['merchant_user_id'])mg_fail('Microgift is not eligible for this merchant.',403);

$paid=empty($instance['commerce_order_item_id'])
    ? in_array((string)$instance['source_type'],['merchant','administrator','enterprise','workplace','agent'],true)
    : ((string)$instance['source_type']==='commerce_order_item'&&(string)$instance['payment_status']==='paid');
$available=in_array((string)$instance['status'],['issued','delivered','claim_pending','claimed','redeemable'],true);
$notExpired=$instance['expires_at']===null||strtotime((string)$instance['expires_at'])>time();
$locationAllowed=mg_microgift_location_allowed($instance,$locationId);

$redemptionStmt=$pdo->prepare('SELECT public_id,status,redeemed_at,amount_cents,currency FROM microgift_redemptions WHERE instance_id=? ORDER BY id DESC LIMIT 1');
$redemptionStmt->execute([(int)$instance['id']]);

mg_ok([
    'microgift'=>[
        'instance_id'=>(string)$instance['public_id'],
        'pppm_id'=>(string)($instance['pppm_id']??''),
        'title'=>(string)$instance['title_snapshot'],
        'status'=>(string)$instance['status'],
        'value_cents'=>(int)$instance['face_value_cents'],
        'currency'=>(string)$instance['currency'],
        'expires_at'=>$instance['expires_at'],
        'redeemable'=>$paid&&$available&&$notExpired&&$locationAllowed&&(int)$instance['owner_user_id']>0,
    ],
    'location'=>['location_id'=>(string)$location['public_id'],'name'=>(string)$location['name']],
    'redemption'=>$redemptionStmt->fetch(PDO::FETCH_ASSOC)?:null,
]);
