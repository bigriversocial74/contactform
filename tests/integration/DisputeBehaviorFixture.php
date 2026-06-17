<?php
declare(strict_types=1);

require_once __DIR__.'/MicrogiftBehaviorFixture.php';
require_once dirname(__DIR__,2).'/api/payments/_capture.php';

function mg_dispute_fixture_required(array $column,array $context): mixed
{
    $name=(string)$column['Field'];
    if(array_key_exists($name,$context))return $context[$name];
    if(str_ends_with($name,'_json'))return '{}';
    if(str_ends_with($name,'_at'))return gmdate('Y-m-d H:i:s');
    if(str_contains($name,'email'))return $context['buyer_email'];
    if(str_contains($name,'number'))return 'RCPT-'.$context['run_id'];
    $type=strtolower((string)$column['Type']);
    if(str_starts_with($type,'enum(')){preg_match("/'([^']+)'/",$type,$match);return $match[1]??'';}
    if(preg_match('/int|decimal|float|double/',$type))return 0;
    return 'dispute-'.$context['run_id'];
}

function mg_dispute_fixture_receipt(PDO $pdo,array $context): int
{
    $columns=$pdo->query('SHOW COLUMNS FROM receipts')->fetchAll(PDO::FETCH_ASSOC);
    $names=[];$values=[];
    foreach($columns as $column){
        if(str_contains((string)$column['Extra'],'auto_increment'))continue;
        $name=(string)$column['Field'];
        if(array_key_exists($name,$context)){$names[]=$name;$values[]=$context[$name];continue;}
        if((string)$column['Null']==='NO'&&$column['Default']===null){$names[]=$name;$values[]=mg_dispute_fixture_required($column,$context);}
    }
    $quoted=array_map(static fn(string $name):string=>'`'.str_replace('`','',$name).'`',$names);
    $pdo->prepare('INSERT INTO receipts ('.implode(',',$quoted).') VALUES ('.implode(',',array_fill(0,count($values),'?')).')')->execute($values);
    return (int)$pdo->lastInsertId();
}

function mg_dispute_fixture_catalog(PDO $pdo,int $merchantId,string $runId): array
{
    $productPublic=mg_public_uuid();$versionPublic=mg_public_uuid();$assetPublic=mg_public_uuid();
    $pdo->prepare("INSERT INTO catalog_products (public_id,merchant_user_id,product_type,slug,status,created_by_user_id,published_at,created_at,updated_at) VALUES (?,?,'digital_product',?,'published',?,NOW(),NOW(),NOW())")
        ->execute([$productPublic,$merchantId,'dispute-'.$runId,$merchantId]);
    $productId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO catalog_product_versions (public_id,product_id,version_number,version_status,title,description,unit_value_cents,currency,checksum,created_by_user_id,published_at,created_at) VALUES (?, ?,1,'published','Dispute behavior product','Dispute validation',1250,'USD',?,?,NOW(),NOW())")
        ->execute([$versionPublic,$productId,hash('sha256',$runId),$merchantId]);
    $versionId=(int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE catalog_products SET current_version_id=? WHERE id=?')->execute([$versionId,$productId]);
    $pdo->prepare("INSERT INTO catalog_assets (public_id,owner_user_id,asset_type,storage_provider,storage_key,original_filename,mime_type,byte_size,status,created_at,updated_at) VALUES (?,?,'download','behavior',?,'dispute.txt','text/plain',32,'ready',NOW(),NOW())")
        ->execute([$assetPublic,$merchantId,'dispute/'.$runId.'/asset.txt']);
    $assetId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO catalog_product_version_assets (product_version_id,asset_id,role,sort_order,created_at) VALUES (?,?,'download',0,NOW())")
        ->execute([$versionId,$assetId]);
    return ['product_id'=>$productId,'version_id'=>$versionId];
}

function mg_dispute_fixture_order(PDO $pdo,array $fixture,string $suffix): array
{
    $orderPublic=mg_public_uuid();$linePublic=mg_public_uuid();$intentPublic=mg_public_uuid();
    $pdo->prepare("INSERT INTO commerce_orders (public_id,buyer_user_id,merchant_user_id,currency,subtotal_cents,discount_cents,tax_cents,platform_fee_cents,total_cents,payment_status,fulfillment_status,source_type,source_reference,idempotency_key,metadata_json,created_at,updated_at) VALUES (?,?,?,'USD',2500,0,0,0,2500,'unpaid','pending','checkout',?,?,?,NOW(),NOW())")
        ->execute([$orderPublic,$fixture['buyer_id'],$fixture['merchant_id'],'dispute-'.$suffix,'dispute:order:'.$fixture['run_id'].':'.$suffix,json_encode(['run_id'=>$fixture['run_id']],JSON_THROW_ON_ERROR)]);
    $orderId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO commerce_order_items (public_id,order_id,product_id,product_version_id,title_snapshot,quantity,unit_amount_cents,discount_cents,tax_cents,line_total_cents,currency,created_at) VALUES (?,?,?,?,?,2,1250,0,0,2500,'USD',NOW())")
        ->execute([$linePublic,$orderId,$fixture['product_id'],$fixture['version_id'],'Dispute behavior product']);
    $lineId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO payment_intents (public_id,order_id,provider_key,amount_cents,currency,status,capture_method,idempotency_key,created_at,updated_at) VALUES (?,?,'sandbox',2500,'USD','created','automatic',?,NOW(),NOW())")
        ->execute([$intentPublic,$orderId,'dispute:intent:'.$fixture['run_id'].':'.$suffix]);
    $intentId=(int)$pdo->lastInsertId();
    $receiptId=mg_dispute_fixture_receipt($pdo,['run_id'=>$fixture['run_id'].':'.$suffix,'public_id'=>mg_public_uuid(),'order_id'=>$orderId,'buyer_user_id'=>$fixture['buyer_id'],'merchant_user_id'=>$fixture['merchant_id'],'buyer_email'=>$fixture['buyer_email'],'currency'=>'USD','subtotal_cents'=>2500,'discount_cents'=>0,'tax_cents'=>0,'platform_fee_cents'=>0,'total_cents'=>2500,'status'=>'pending']);
    mg_finance_record_paid_order($pdo,$orderId,$intentId,'provider-'.$fixture['run_id'].'-'.$suffix,$fixture['buyer_id']);
    return ['order_id'=>$orderId,'order_public'=>$orderPublic,'intent_id'=>$intentId,'intent_public'=>$intentPublic,'line_id'=>$lineId,'receipt_id'=>$receiptId];
}

function mg_dispute_fixture_event(string $eventId,string $type,array $order,string $reference,int $amount,int $fee=0): array
{
    return ['id'=>$eventId,'type'=>$type,'data'=>['order_id'=>$order['order_public'],'payment_intent_id'=>$order['intent_public'],'dispute_id'=>$reference,'amount_cents'=>$amount,'fee_cents'=>$fee,'reason'=>'fraudulent']];
}
