<?php
declare(strict_types=1);
require_once __DIR__ . '/_capture.php';
mg_require_method('POST');
$user=mg_require_permission('commerce.checkout.create');
$input=mg_input();
mg_require_csrf_for_write($input);
if(mg_payment_is_live()||mg_payment_provider_key()!=='sandbox')mg_fail('Sandbox confirmation is unavailable.',403);
$sessionId=trim((string)($input['session_id']??''));
$pdo=mg_db();
$pdo->beginTransaction();
try{
 $stmt=$pdo->prepare("SELECT cs.id session_db_id,cs.status session_status,o.id order_db_id,o.public_id order_id,o.payment_status,pi.id intent_db_id FROM checkout_sessions cs INNER JOIN commerce_orders o ON o.id=cs.order_id INNER JOIN payment_intents pi ON pi.order_id=o.id WHERE cs.public_id=? AND o.buyer_user_id=? LIMIT 1 FOR UPDATE");
 $stmt->execute([$sessionId,(int)$user['id']]);
 $row=$stmt->fetch();
 if(!$row)mg_fail('Checkout session not found.',404);
 if($row['payment_status']==='paid'){$pdo->commit();mg_ok(['order_id'=>$row['order_id'],'paid'=>true],'Order already paid.');}
 $providerRef='sandbox_'.bin2hex(random_bytes(8));
 $pdo->prepare("UPDATE checkout_sessions SET provider_session_reference=?,status='completed',completed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$providerRef,(int)$row['session_db_id']]);
 $result=mg_finance_record_paid_order($pdo,(int)$row['order_db_id'],(int)$row['intent_db_id'],$providerRef,(int)$user['id']);
 $pdo->commit();
 mg_audit('commerce.payment_succeeded','commerce_order',['order_id'=>$row['order_id'],'provider'=>'sandbox','issued_count'=>$result['issued_count']],(int)$user['id']);
 mg_ok(['order_id'=>$row['order_id'],'paid'=>true,'provider_reference'=>$providerRef,'issued_count'=>$result['issued_count'],'fulfillment_status'=>$result['fulfillment_status']],'Sandbox payment completed.');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to confirm sandbox payment.',500);}
