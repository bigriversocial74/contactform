<?php
declare(strict_types=1);
require_once __DIR__ . '/_lifecycle.php';
mg_require_method('POST');
$user=mg_require_permission('microgift.lifecycle.manage');
$input=mg_input();
mg_require_csrf_for_write($input);
$orderItemId=trim((string)($input['commerce_order_item_id']??''));
$policy=trim((string)($input['policy']??''));
$sourceReference=trim((string)($input['source_reference']??''));
$key=trim((string)($input['idempotency_key']??''));
$map=['full_refund'=>'refund','dispute_opened'=>'dispute_opened','dispute_merchant_won'=>'dispute_won','dispute_customer_won'=>'dispute_lost'];
if($orderItemId===''||$sourceReference===''||$key===''||!isset($map[$policy]))mg_fail('Invalid Microgift payment policy request.',422);
$pdo=mg_db();
try {
    $pdo->beginTransaction();
    $stmt=$pdo->prepare('SELECT i.* FROM microgift_instances i INNER JOIN commerce_order_items oi ON oi.id=i.commerce_order_item_id WHERE oi.public_id=? FOR UPDATE');
    $stmt->execute([$orderItemId]);
    $results=[];
    foreach($stmt->fetchAll() as $instance){
        $results[]=mg_microgift_apply_lifecycle($pdo,$instance,$map[$policy],'payment_policy',$sourceReference,$key.':'.$instance['public_id'],(int)$user['id'],$policy);
    }
    $pdo->commit();
    mg_audit('microgift.payment_policy_applied','commerce_order_item',['commerce_order_item_id'=>$orderItemId,'policy'=>$policy,'affected'=>count($results)],(int)$user['id']);
    mg_ok(['policy'=>$policy,'affected'=>count($results),'results'=>$results]);
} catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to apply Microgift payment policy.',409);}
