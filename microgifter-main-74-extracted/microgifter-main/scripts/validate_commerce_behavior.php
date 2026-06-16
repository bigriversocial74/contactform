<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}

require_once dirname(__DIR__) . '/api/payments/_capture.php';

function mg_commerce_assert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}

function mg_commerce_scalar(PDO $pdo,string $sql,array $params=[]): mixed
{
    $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchColumn();
}

function mg_commerce_required_value(array $column,array $context): mixed
{
    $name=(string)$column['Field'];
    if(array_key_exists($name,$context))return $context[$name];
    if(str_ends_with($name,'_json'))return '{}';
    if(str_ends_with($name,'_at'))return gmdate('Y-m-d H:i:s');
    if(str_contains($name,'email'))return $context['buyer_email'];
    if(str_contains($name,'number'))return 'RCPT-'.$context['run_id'];
    if(str_contains($name,'title')||str_contains($name,'name'))return 'Behavior receipt';
    $type=strtolower((string)$column['Type']);
    if(str_starts_with($type,'enum(')){
        if(str_contains($type,"'pending'"))return 'pending';
        preg_match("/'([^']+)'/",$type,$match);return $match[1]??'';
    }
    if(preg_match('/int|decimal|float|double/',$type))return 0;
    return 'behavior-'.$context['run_id'];
}

function mg_commerce_insert_receipt(PDO $pdo,array $context): int
{
    $columns=$pdo->query('SHOW COLUMNS FROM receipts')->fetchAll(PDO::FETCH_ASSOC);
    $insertColumns=[];$values=[];
    foreach($columns as $column){
        if(str_contains((string)$column['Extra'],'auto_increment'))continue;
        $name=(string)$column['Field'];
        if(array_key_exists($name,$context)){
            $insertColumns[]=$name;$values[]=$context[$name];continue;
        }
        if((string)$column['Null']==='NO'&&$column['Default']===null){
            $insertColumns[]=$name;$values[]=mg_commerce_required_value($column,$context);
        }
    }
    $quoted=array_map(static fn(string $name):string=>'`'.str_replace('`','',$name).'`',$insertColumns);
    $sql='INSERT INTO receipts ('.implode(',',$quoted).') VALUES ('.implode(',',array_fill(0,count($values),'?')).')';
    $pdo->prepare($sql)->execute($values);
    return (int)$pdo->lastInsertId();
}

function mg_commerce_create_order(PDO $pdo,array $fixture,string $suffix): array
{
    $orderPublic=mg_public_uuid();$linePublic=mg_public_uuid();$intentPublic=mg_public_uuid();
    $pdo->prepare("INSERT INTO commerce_orders (public_id,buyer_user_id,merchant_user_id,currency,subtotal_cents,discount_cents,tax_cents,platform_fee_cents,total_cents,payment_status,fulfillment_status,source_type,source_reference,idempotency_key,metadata_json,created_at,updated_at) VALUES (?,?,?,'USD',2500,0,0,0,2500,'unpaid','pending','checkout',?,?,?,NOW(),NOW())")
        ->execute([$orderPublic,$fixture['buyer_id'],$fixture['merchant_id'],'behavior-'.$suffix,'behavior:order:'.$fixture['run_id'].':'.$suffix,json_encode(['run_id'=>$fixture['run_id']],JSON_THROW_ON_ERROR)]);
    $orderId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO commerce_order_items (public_id,order_id,product_id,product_version_id,title_snapshot,quantity,unit_amount_cents,discount_cents,tax_cents,line_total_cents,currency,created_at) VALUES (?,?,?,?,?,2,1250,0,0,2500,'USD',NOW())")
        ->execute([$linePublic,$orderId,$fixture['product_id'],$fixture['version_id'],'Behavior product']);
    $lineId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO payment_intents (public_id,order_id,provider_key,amount_cents,currency,status,capture_method,idempotency_key,created_at,updated_at) VALUES (?,?,'sandbox',2500,'USD','created','automatic',?,NOW(),NOW())")
        ->execute([$intentPublic,$orderId,'behavior:intent:'.$fixture['run_id'].':'.$suffix]);
    $intentId=(int)$pdo->lastInsertId();
    $receiptId=mg_commerce_insert_receipt($pdo,[
        'run_id'=>$fixture['run_id'].':'.$suffix,
        'public_id'=>mg_public_uuid(),
        'order_id'=>$orderId,
        'buyer_user_id'=>$fixture['buyer_id'],
        'merchant_user_id'=>$fixture['merchant_id'],
        'buyer_email'=>$fixture['buyer_email'],
        'currency'=>'USD',
        'subtotal_cents'=>2500,
        'discount_cents'=>0,
        'tax_cents'=>0,
        'platform_fee_cents'=>0,
        'total_cents'=>2500,
        'status'=>'pending',
    ]);
    return ['order_id'=>$orderId,'order_public'=>$orderPublic,'line_id'=>$lineId,'line_public'=>$linePublic,'intent_id'=>$intentId,'intent_public'=>$intentPublic,'receipt_id'=>$receiptId];
}

$pdo=mg_db();$runId='commerce_'.bin2hex(random_bytes(8));
$summary=[
    'suite'=>'commerce_capture_behavior','run_id'=>$runId,
    'capture_success'=>false,'ledger_balanced'=>false,'pppm_issued'=>false,'entitlements_granted'=>false,
    'receipt_finalized'=>false,'notifications_created_once'=>false,'exact_replay'=>false,
    'midflow_failure_rolled_back'=>false,'fixtures_clean'=>false,
];

$pdo->beginTransaction();
try{
    $buyerEmail=$runId.'-buyer@example.test';$merchantEmail=$runId.'-merchant@example.test';
    $password=password_hash('BehaviorPassword123!',PASSWORD_DEFAULT);
    $userStmt=$pdo->prepare("INSERT INTO users (email,password_hash,full_name,display_name,status,email_verified_at,created_at,updated_at) VALUES (?,?,?,?,'active',NOW(),NOW(),NOW())");
    $userStmt->execute([$buyerEmail,$password,'Behavior Buyer','Behavior Buyer']);$buyerId=(int)$pdo->lastInsertId();
    $userStmt->execute([$merchantEmail,$password,'Behavior Merchant','Behavior Merchant']);$merchantId=(int)$pdo->lastInsertId();

    $productPublic=mg_public_uuid();$versionPublic=mg_public_uuid();$assetPublic=mg_public_uuid();
    $pdo->prepare("INSERT INTO catalog_products (public_id,merchant_user_id,product_type,slug,status,created_by_user_id,published_at,created_at,updated_at) VALUES (?,?,'digital_product',?,'published',?,NOW(),NOW(),NOW())")
        ->execute([$productPublic,$merchantId,'behavior-'.$runId,$merchantId]);
    $productId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO catalog_product_versions (public_id,product_id,version_number,version_status,title,description,unit_value_cents,currency,checksum,created_by_user_id,published_at,created_at) VALUES (?, ?,1,'published','Behavior product','Behavioral checkout fixture',1250,'USD',?,?,NOW(),NOW())")
        ->execute([$versionPublic,$productId,hash('sha256',$runId),$merchantId]);
    $versionId=(int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE catalog_products SET current_version_id=? WHERE id=?')->execute([$versionId,$productId]);
    $pdo->prepare("INSERT INTO catalog_assets (public_id,owner_user_id,asset_type,storage_provider,storage_key,original_filename,mime_type,byte_size,status,created_at,updated_at) VALUES (?,?,'download','behavior',?,'behavior.txt','text/plain',32,'ready',NOW(),NOW())")
        ->execute([$assetPublic,$merchantId,'behavior/'.$runId.'/asset.txt']);
    $assetId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO catalog_product_version_assets (product_version_id,asset_id,role,sort_order,created_at) VALUES (?,?,'download',0,NOW())")
        ->execute([$versionId,$assetId]);

    $fixture=['run_id'=>$runId,'buyer_id'=>$buyerId,'merchant_id'=>$merchantId,'buyer_email'=>$buyerEmail,'product_id'=>$productId,'version_id'=>$versionId];
    $success=mg_commerce_create_order($pdo,$fixture,'success');
    $providerReference='provider-'.$runId;
    $result=mg_finance_record_paid_order($pdo,$success['order_id'],$success['intent_id'],$providerReference,$buyerId);

    mg_commerce_assert($result['payment_transitioned']===true,'Order did not transition to paid.');
    mg_commerce_assert((int)$result['issued_count']===2,'Expected two PPPM items.');
    mg_commerce_assert((string)$result['fulfillment_status']==='issued','Order was not fully issued.');
    mg_commerce_assert((string)mg_commerce_scalar($pdo,'SELECT payment_status FROM commerce_orders WHERE id=?',[$success['order_id']])==='paid','Order payment state is not paid.');
    mg_commerce_assert((string)mg_commerce_scalar($pdo,'SELECT status FROM payment_intents WHERE id=?',[$success['intent_id']])==='succeeded','Payment intent did not succeed.');
    mg_commerce_assert((int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM payment_transactions WHERE payment_intent_id=?',[$success['intent_id']])===1,'Payment transaction count is incorrect.');
    $summary['capture_success']=true;

    $groupStmt=$pdo->prepare("SELECT * FROM ledger_transaction_groups WHERE idempotency_key=? LIMIT 1");
    $groupStmt->execute(['order:paid:'.$success['order_public']]);$group=$groupStmt->fetch(PDO::FETCH_ASSOC);
    mg_commerce_assert(is_array($group),'Paid-order ledger group is missing.');
    $entryStmt=$pdo->prepare('SELECT entry_type,SUM(amount_cents) total FROM ledger_entries WHERE transaction_group_id=? GROUP BY entry_type');
    $entryStmt->execute([(int)$group['id']]);$sides=[];
    foreach($entryStmt->fetchAll(PDO::FETCH_ASSOC) as $row)$sides[(string)$row['entry_type']]=(int)$row['total'];
    mg_commerce_assert(($sides['debit']??0)===2500&&($sides['credit']??0)===2500,'Paid-order ledger group is not balanced.');
    $summary['ledger_balanced']=true;

    $requestId=(int)mg_commerce_scalar($pdo,'SELECT pppm_issuance_request_id FROM commerce_order_items WHERE id=?',[$success['line_id']]);
    mg_commerce_assert($requestId>0,'Order line is not linked to an issuance request.');
    mg_commerce_assert((int)mg_commerce_scalar($pdo,'SELECT issued_count FROM pppm_issuance_requests WHERE id=?',[$requestId])===2,'Issuance request count is incorrect.');
    mg_commerce_assert((int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM pppm_items WHERE issuance_request_id=?',[$requestId])===2,'PPPM item count is incorrect.');
    mg_commerce_assert((int)mg_commerce_scalar($pdo,"SELECT COUNT(*) FROM pppm_item_events pie INNER JOIN pppm_items pi ON pi.id=pie.pppm_item_id WHERE pi.issuance_request_id=? AND pie.event_type='issued_from_paid_order'",[$requestId])===2,'PPPM issuance events are missing.');
    $summary['pppm_issued']=true;

    mg_commerce_assert((int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM entitlements e INNER JOIN commerce_order_items oi ON oi.id=e.commerce_order_item_id WHERE oi.order_id=?',[$success['order_id']])===2,'Entitlement count is incorrect.');
    mg_commerce_assert((int)mg_commerce_scalar($pdo,"SELECT COUNT(*) FROM entitlement_events ee INNER JOIN entitlements e ON e.id=ee.entitlement_id INNER JOIN commerce_order_items oi ON oi.id=e.commerce_order_item_id WHERE oi.order_id=? AND ee.event_type='entitlement.granted'",[$success['order_id']])===2,'Entitlement grant events are missing.');
    $summary['entitlements_granted']=true;

    mg_commerce_assert((string)mg_commerce_scalar($pdo,'SELECT status FROM receipts WHERE id=?',[$success['receipt_id']])==='finalized','Receipt was not finalized.');
    $summary['receipt_finalized']=true;
    $notificationCount=(int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM notifications WHERE user_id IN (?,?) AND created_at>=DATE_SUB(NOW(),INTERVAL 5 MINUTE)',[$buyerId,$merchantId]);
    mg_commerce_assert($notificationCount===2,'Capture notifications were not created exactly once.');
    $summary['notifications_created_once']=true;

    $countsBefore=[
        'transactions'=>(int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM payment_transactions WHERE payment_intent_id=?',[$success['intent_id']]),
        'groups'=>(int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM ledger_transaction_groups WHERE idempotency_key=?',['order:paid:'.$success['order_public']]),
        'requests'=>(int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM pppm_issuance_requests WHERE source_reference=?',[$success['order_public']]),
        'items'=>(int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM pppm_items WHERE issuance_request_id=?',[$requestId]),
        'entitlements'=>(int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM entitlements e INNER JOIN commerce_order_items oi ON oi.id=e.commerce_order_item_id WHERE oi.order_id=?',[$success['order_id']]),
        'notifications'=>$notificationCount,
    ];
    $replay=mg_finance_record_paid_order($pdo,$success['order_id'],$success['intent_id'],$providerReference,$buyerId);
    mg_commerce_assert($replay['payment_transitioned']===false,'Exact replay transitioned payment twice.');
    mg_commerce_assert((int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM payment_transactions WHERE payment_intent_id=?',[$success['intent_id']])===$countsBefore['transactions'],'Replay duplicated payment transactions.');
    mg_commerce_assert((int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM ledger_transaction_groups WHERE idempotency_key=?',['order:paid:'.$success['order_public']])===$countsBefore['groups'],'Replay duplicated ledger groups.');
    mg_commerce_assert((int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM pppm_issuance_requests WHERE source_reference=?',[$success['order_public']])===$countsBefore['requests'],'Replay duplicated issuance requests.');
    mg_commerce_assert((int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM pppm_items WHERE issuance_request_id=?',[$requestId])===$countsBefore['items'],'Replay duplicated PPPM items.');
    mg_commerce_assert((int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM entitlements e INNER JOIN commerce_order_items oi ON oi.id=e.commerce_order_item_id WHERE oi.order_id=?',[$success['order_id']])===$countsBefore['entitlements'],'Replay duplicated entitlements.');
    mg_commerce_assert((int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM notifications WHERE user_id IN (?,?) AND created_at>=DATE_SUB(NOW(),INTERVAL 5 MINUTE)',[$buyerId,$merchantId])===$countsBefore['notifications'],'Replay duplicated notifications.');
    $summary['exact_replay']=true;

    $failure=mg_commerce_create_order($pdo,$fixture,'failure');
    $sourceId=(int)mg_commerce_scalar($pdo,"SELECT id FROM pppm_sources WHERE owner_user_id=? AND source_type='purchase' AND provider='commerce'",[$merchantId]);
    $externalEvent='commerce.order.'.$failure['order_public'].'.line.'.$failure['line_public'];
    $payload=json_encode(['forced_conflict'=>true],JSON_THROW_ON_ERROR);
    $pdo->prepare("INSERT INTO pppm_source_events (public_id,source_id,external_event_id,event_type,payload_json,payload_hash,processing_status,received_at,created_at,updated_at) VALUES (?,?,?,'behavior.conflict',?,?,'validated',NOW(),NOW(),NOW())")
        ->execute([mg_public_uuid(),$sourceId,$externalEvent,$payload,hash('sha256',$payload)]);
    $notificationsBeforeFailure=(int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM notifications');
    $pdo->exec('SAVEPOINT commerce_capture_failure');
    $failed=false;
    try{mg_finance_record_paid_order($pdo,$failure['order_id'],$failure['intent_id'],'provider-failure-'.$runId,$buyerId);}
    catch(Throwable $error){$failed=true;}
    mg_commerce_assert($failed,'Forced fulfillment conflict did not fail capture.');
    $pdo->exec('ROLLBACK TO SAVEPOINT commerce_capture_failure');
    mg_commerce_assert((string)mg_commerce_scalar($pdo,'SELECT payment_status FROM commerce_orders WHERE id=?',[$failure['order_id']])==='unpaid','Failed capture left the order paid.');
    mg_commerce_assert((string)mg_commerce_scalar($pdo,'SELECT status FROM payment_intents WHERE id=?',[$failure['intent_id']])==='created','Failed capture changed the payment intent.');
    mg_commerce_assert((int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM payment_transactions WHERE payment_intent_id=?',[$failure['intent_id']])===0,'Failed capture left a payment transaction.');
    mg_commerce_assert((int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM ledger_transaction_groups WHERE idempotency_key=?',['order:paid:'.$failure['order_public']])===0,'Failed capture left a ledger group.');
    mg_commerce_assert((int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM pppm_issuance_requests WHERE source_reference=?',[$failure['order_public']])===0,'Failed capture left an issuance request.');
    mg_commerce_assert((int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM entitlements e INNER JOIN commerce_order_items oi ON oi.id=e.commerce_order_item_id WHERE oi.order_id=?',[$failure['order_id']])===0,'Failed capture left entitlements.');
    mg_commerce_assert((string)mg_commerce_scalar($pdo,'SELECT status FROM receipts WHERE id=?',[$failure['receipt_id']])==='pending','Failed capture finalized the receipt.');
    mg_commerce_assert((int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM notifications')===$notificationsBeforeFailure,'Failed capture left notifications.');
    $summary['midflow_failure_rolled_back']=true;

    $pdo->rollBack();
    mg_commerce_assert((int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email IN (?,?)',[$buyerEmail,$merchantEmail])===0,'Behavior users were not rolled back.');
    mg_commerce_assert((int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM commerce_orders WHERE idempotency_key LIKE ?',['behavior:order:'.$runId.'%'])===0,'Behavior orders were not rolled back.');
    mg_commerce_assert((int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM catalog_products WHERE public_id=?',[$productPublic])===0,'Behavior product was not rolled back.');
    mg_commerce_assert((int)mg_commerce_scalar($pdo,'SELECT COUNT(*) FROM ledger_transaction_groups WHERE idempotency_key LIKE ?',['order:paid:%'.$runId.'%'])===0,'Behavior ledger groups were not rolled back.');
    $summary['fixtures_clean']=true;

    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    throw $error;
}
