<?php
declare(strict_types=1);
require_once __DIR__ . '/_lifecycle.php';
mg_require_method('POST');
$user=mg_require_permission('entitlements.lifecycle');
$input=mg_input();
mg_require_csrf_for_write($input);
$action=trim((string)($input['action']??''));
$reference=trim((string)($input['source_reference']??''));
if($reference==='')mg_fail('Source reference is required.',422);
$pdo=mg_db();
$pdo->beginTransaction();
try{
 if($action==='sync_owner'){
  $result=mg_entitlements_sync_pppm_owner($pdo,(string)($input['pppm_item_id']??''),(int)($input['new_owner_user_id']??0),'lifecycle_api',$reference,(int)$user['id']);
 }elseif($action==='asset_removed'||$action==='asset_restored'){
  $result=mg_entitlements_apply_asset_policy($pdo,(string)($input['asset_id']??''),$action==='asset_removed'?'removed':'restored',$reference,(int)$user['id']);
 }else{
  $order=$pdo->prepare('SELECT id FROM commerce_orders WHERE public_id=? LIMIT 1 FOR UPDATE');
  $order->execute([(string)($input['order_id']??'')]);
  $orderId=(int)$order->fetchColumn();
  if($orderId<1)throw new RuntimeException('Order not found.');
  $map=['dispute_opened'=>'opened','dispute_merchant_won'=>'merchant_won','dispute_customer_won'=>'customer_won'];
  if(!isset($map[$action]))throw new InvalidArgumentException('Invalid lifecycle action.');
  $result=mg_entitlements_apply_dispute($pdo,$orderId,$map[$action],$reference,(int)$user['id']);
 }
 $pdo->commit();mg_ok($result);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to apply entitlement policy.',409);}
