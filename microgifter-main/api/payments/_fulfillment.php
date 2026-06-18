<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/commerce/_foundation.php';
require_once dirname(__DIR__) . '/pppm/_pppm.php';
require_once dirname(__DIR__) . '/entitlements/_entitlements.php';
require_once dirname(__DIR__) . '/microgifts/_engine.php';
require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php';

function mg_payment_order_items_have_merchant(PDO $pdo): bool
{
    static $hasColumn = null;
    if ($hasColumn !== null) return $hasColumn;
    $stmt=$pdo->prepare("SHOW COLUMNS FROM commerce_order_items LIKE 'merchant_user_id'");
    $stmt->execute();
    $hasColumn=(bool)$stmt->fetch();
    return $hasColumn;
}

function mg_payment_pppm_source(PDO $pdo, int $merchantUserId): array
{
    $stmt = $pdo->prepare("SELECT * FROM pppm_sources WHERE owner_user_id=? AND source_type='purchase' AND provider='commerce' AND status='active' LIMIT 1 FOR UPDATE");
    $stmt->execute([$merchantUserId]);
    $source = $stmt->fetch();
    if ($source) return $source;

    try {
        $pdo->prepare("INSERT INTO pppm_sources (public_id,owner_user_id,source_type,provider,name,status,created_at,updated_at) VALUES (?,?,'purchase','commerce','Commerce purchases','active',NOW(),NOW())")
            ->execute([mg_pppm_uuid(), $merchantUserId]);
    } catch (Throwable $error) {
        if (!str_contains($error->getMessage(), 'Duplicate')) {
            throw $error;
        }
    }

    $stmt->execute([$merchantUserId]);
    $source = $stmt->fetch();
    if (!$source) throw new RuntimeException('Unable to create commerce PPPM source.');
    return $source;
}

function mg_payment_issue_order_pppm(PDO $pdo, int $orderDbId, ?int $actorUserId = null): array
{
    $orderStmt = $pdo->prepare('SELECT * FROM commerce_orders WHERE id=? LIMIT 1 FOR UPDATE');
    $orderStmt->execute([$orderDbId]);
    $order = $orderStmt->fetch();
    if (!$order || (string)$order['payment_status'] !== 'paid') return ['issued_count'=>0,'skipped'=>true];
    $lineStmt = $pdo->prepare('SELECT * FROM commerce_order_items WHERE order_id=? AND pppm_issuance_request_id IS NULL ORDER BY id FOR UPDATE');
    $lineStmt->execute([$orderDbId]);
    $lines = $lineStmt->fetchAll();
    if (!$lines) {
        $pdo->prepare("UPDATE commerce_orders SET fulfillment_status=IF(fulfillment_status='pending','issued',fulfillment_status),updated_at=NOW() WHERE id=?")->execute([$orderDbId]);
        $entitlements = mg_entitlement_grant_for_order($pdo,$orderDbId,$actorUserId ?: (int)$order['buyer_user_id']);
        return ['issued_count'=>0,'skipped'=>true,'entitlements'=>$entitlements];
    }
    $source = mg_payment_pppm_source($pdo, (int)$order['merchant_user_id']);
    $eventStmt = $pdo->prepare("INSERT INTO pppm_source_events (public_id,source_id,external_event_id,event_type,payload_json,payload_hash,processing_status,received_at,created_at,updated_at) VALUES (?,?,?,?,?,?,'validated',NOW(),NOW(),NOW())");
    $requestStmt = $pdo->prepare("INSERT INTO pppm_issuance_requests (public_id,source_id,source_event_id,issuer_user_id,merchant_user_id,source_reference,source_line_reference,item_type,funding_type,quantity,unit_value_cents,currency,recipient_user_id,recipient_external_id,recipient_name,title,description,terms_snapshot_json,metadata_json,status,issued_count,requested_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,'gift','customer_purchase',?,?,?,?,NULL,NULL,?,?,NULL,?,'issuing',0,NOW(),NOW(),NOW())");
    $itemStmt = $pdo->prepare("INSERT INTO pppm_items (public_id,issuance_request_id,source_id,unit_sequence,item_type,funding_type,issuer_user_id,merchant_user_id,owner_user_id,recipient_user_id,recipient_external_id,source_reference,source_line_reference,title_snapshot,description_snapshot,value_cents_snapshot,currency_snapshot,terms_snapshot_json,metadata_snapshot_json,status,version_no,issued_at,assigned_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NULL,NULL,?,?,?,?,?,?,NULL,?,'available',1,NOW(),NULL,NOW(),NOW())");
    $issuedTotal = 0;
    $entitlementTotals = ['created'=>0,'existing'=>0];
    foreach ($lines as $line) {
        $payload = ['order_id'=>(string)$order['public_id'],'order_line_id'=>(string)$line['public_id'],'quantity'=>(int)$line['quantity'],'payment_status'=>'paid'];
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $eventStmt->execute([mg_pppm_uuid(), (int)$source['id'], 'commerce.order.'.$order['public_id'].'.line.'.$line['public_id'], 'commerce.purchase.paid', $payloadJson, hash('sha256',(string)$payloadJson)]);
        $sourceEventId = (int)$pdo->lastInsertId();
        $requestPublicId = mg_pppm_uuid();
        $metadataJson = json_encode(['commerce_order_id'=>(string)$order['public_id'],'commerce_order_item_id'=>(string)$line['public_id']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $requestStmt->execute([$requestPublicId,(int)$source['id'],$sourceEventId,$actorUserId ?: (int)$order['buyer_user_id'],(int)$order['merchant_user_id'],(string)$order['public_id'],(string)$line['public_id'],(int)$line['quantity'],(int)$line['unit_amount_cents'],(string)$line['currency'],(int)$order['buyer_user_id'],(string)$line['title_snapshot'],'Purchased through commerce checkout.',$metadataJson]);
        $requestDbId = (int)$pdo->lastInsertId();
        for ($sequence=1; $sequence <= (int)$line['quantity']; $sequence++) {
            $itemStmt->execute([mg_pppm_item_id(),$requestDbId,(int)$source['id'],$sequence,'gift','customer_purchase',$actorUserId ?: (int)$order['buyer_user_id'],(int)$order['merchant_user_id'],(int)$order['buyer_user_id'],(string)$order['public_id'],(string)$line['public_id'],(string)$line['title_snapshot'],'Purchased through commerce checkout.',(int)$line['unit_amount_cents'],(string)$line['currency'],$metadataJson]);
            $item = $pdo->query('SELECT * FROM pppm_items WHERE id='.(int)$pdo->lastInsertId())->fetch();
            mg_pppm_record_event($pdo, $item, 'issued_from_paid_order', null, 'available', $actorUserId ?: (int)$order['buyer_user_id'], $sourceEventId, ['commerce_order_id'=>(string)$order['public_id'],'commerce_order_item_id'=>(string)$line['public_id'],'issuance_request_id'=>$requestPublicId,'unit_sequence'=>$sequence]);
            $entitlementResult = mg_entitlement_grant_for_pppm_item($pdo,$item,$actorUserId ?: (int)$order['buyer_user_id']);
            $entitlementTotals['created'] += (int)($entitlementResult['created'] ?? 0);
            $entitlementTotals['existing'] += (int)($entitlementResult['existing'] ?? 0);
            $issuedTotal++;
        }
        $pdo->prepare("UPDATE pppm_issuance_requests SET status='issued',issued_count=?,completed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$line['quantity'],$requestDbId]);
        $pdo->prepare("UPDATE pppm_source_events SET processing_status='processed',processed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$sourceEventId]);
        $pdo->prepare('UPDATE commerce_order_items SET pppm_issuance_request_id=? WHERE id=?')->execute([$requestDbId,(int)$line['id']]);
    }
    $remaining = $pdo->prepare('SELECT COUNT(*) FROM commerce_order_items WHERE order_id=? AND pppm_issuance_request_id IS NULL');
    $remaining->execute([$orderDbId]);
    $fulfillment = ((int)$remaining->fetchColumn() === 0) ? 'issued' : 'partial';
    $pdo->prepare('UPDATE commerce_orders SET fulfillment_status=?,updated_at=NOW() WHERE id=?')->execute([$fulfillment,$orderDbId]);
    return ['issued_count'=>$issuedTotal,'fulfillment_status'=>$fulfillment,'entitlements'=>$entitlementTotals];
}

function mg_payment_microgift_template_version_for_line(PDO $pdo, array $order, array $line): string
{
    $existing=$pdo->prepare("SELECT v.public_id FROM microgift_template_versions v INNER JOIN microgift_templates t ON t.id=v.template_id WHERE v.product_version_id=? AND v.status='published' AND t.owner_user_id=? AND t.status='active' ORDER BY v.id DESC LIMIT 1 FOR UPDATE");
    $existing->execute([(int)$line['product_version_id'],(int)$order['merchant_user_id']]);
    $public=(string)($existing->fetchColumn()?:'');
    if($public!=='')return $public;

    $productVersion=$pdo->prepare('SELECT cpv.*,cp.public_id product_public_id,cp.slug product_slug FROM catalog_product_versions cpv INNER JOIN catalog_products cp ON cp.id=cpv.product_id WHERE cpv.id=? LIMIT 1 FOR UPDATE');
    $productVersion->execute([(int)$line['product_version_id']]);
    $version=$productVersion->fetch(PDO::FETCH_ASSOC);
    if(!$version)throw new RuntimeException('Catalog product version not found for Microgift issuance.');

    $template=mg_microgift_create_template($pdo,(int)$order['merchant_user_id'],[
        'owner_type'=>'merchant',
        'name'=>(string)$line['title_snapshot'],
        'gift_type'=>'product',
        'visibility'=>'unlisted',
        'default_currency'=>(string)$line['currency'],
        'slug'=>(string)($version['product_slug']??('commerce-'.$line['public_id'])),
        'description'=>'Commerce checkout Microgift template.',
    ]);
    $created=mg_microgift_create_version($pdo,(int)$order['merchant_user_id'],(string)$template['template_id'],[
        'title'=>(string)$line['title_snapshot'],
        'description'=>(string)($version['description']??'Purchased through commerce checkout.'),
        'currency'=>(string)$line['currency'],
        'face_value_cents'=>(int)$line['unit_amount_cents'],
        'product_id'=>(int)$line['product_id'],
        'product_version_id'=>(int)$line['product_version_id'],
        'recipient_policy'=>'purchaser',
        'claim_policy'=>['mode'=>'purchaser_owned'],
        'redemption_policy'=>['mode'=>'merchant_location'],
        'location_policy'=>['mode'=>'unrestricted'],
        'expiration_policy'=>$version['expiration_policy_json']?json_decode((string)$version['expiration_policy_json'],true):[],
        'terms_snapshot'=>$version['terms_json']?json_decode((string)$version['terms_json'],true):[],
        'future_demand_metadata'=>['source'=>'commerce_checkout','catalog_product_id'=>(string)($version['product_public_id']??'')],
    ]);
    $published=mg_microgift_publish_version($pdo,(int)$order['merchant_user_id'],(string)$created['version_id']);
    return (string)$published['version_id'];
}

function mg_payment_issue_order_microgifts(PDO $pdo, int $orderDbId, ?int $actorUserId = null): array
{
    if(!mg_payment_order_items_have_merchant($pdo)){
        return ['issued_count'=>0,'skipped'=>true,'reason'=>'commerce_order_items.merchant_user_id_missing'];
    }
    $orderStmt=$pdo->prepare('SELECT * FROM commerce_orders WHERE id=? LIMIT 1 FOR UPDATE');
    $orderStmt->execute([$orderDbId]);
    $order=$orderStmt->fetch(PDO::FETCH_ASSOC);
    if(!$order || (string)$order['payment_status']!=='paid')return ['issued_count'=>0,'skipped'=>true];

    $pdo->prepare('UPDATE commerce_order_items SET merchant_user_id=? WHERE order_id=? AND merchant_user_id IS NULL')->execute([(int)$order['merchant_user_id'],$orderDbId]);
    $lineStmt=$pdo->prepare('SELECT * FROM commerce_order_items WHERE order_id=? ORDER BY id FOR UPDATE');
    $lineStmt->execute([$orderDbId]);
    $lines=$lineStmt->fetchAll(PDO::FETCH_ASSOC);
    $pppmStmt=$pdo->prepare('SELECT id FROM pppm_items WHERE source_reference=? AND source_line_reference=? AND unit_sequence=? LIMIT 1 FOR UPDATE');
    $issued=0;$duplicates=0;$projected=[];$linked=0;
    foreach($lines as $line){
        $templateVersion=mg_payment_microgift_template_version_for_line($pdo,$order,$line);
        for($sequence=1;$sequence<=(int)$line['quantity'];$sequence++){
            $pppmStmt->execute([(string)$order['public_id'],(string)$line['public_id'],$sequence]);
            $pppmItemId=(int)($pppmStmt->fetchColumn()?:0);
            if($pppmItemId<1)throw new RuntimeException('PPPM item not found for commerce Microgift issuance.');

            $result=mg_microgift_issue($pdo,(int)$order['merchant_user_id'],[
                'template_version_id'=>$templateVersion,
                'source_type'=>'commerce_order_item',
                'source_reference'=>(string)$line['public_id'],
                'idempotency_key'=>'commerce-order-item:'.$line['public_id'].':microgift:'.$sequence,
                'recipient_user_id'=>(int)$order['buyer_user_id'],
                'metadata'=>[
                    'commerce_order_id'=>(string)$order['public_id'],
                    'commerce_order_item_id'=>(string)$line['public_id'],
                    'unit_sequence'=>$sequence,
                    'pppm_item_id'=>$pppmItemId,
                ],
            ]);
            if(!empty($result['duplicate']))$duplicates++;else$issued++;

            $instanceStmt=$pdo->prepare('SELECT * FROM microgift_instances WHERE public_id=? LIMIT 1 FOR UPDATE');
            $instanceStmt->execute([(string)$result['instance_id']]);
            $instance=$instanceStmt->fetch(PDO::FETCH_ASSOC);
            if(!$instance)throw new RuntimeException('Issued commerce Microgift instance was not found.');
            $existingPppmId=(int)($instance['pppm_item_id']??0);
            if($existingPppmId>0&&$existingPppmId!==$pppmItemId){
                throw new RuntimeException('Commerce Microgift is linked to a different PPPM item.');
            }
            if($existingPppmId===0){
                $pdo->prepare('UPDATE microgift_instances SET pppm_item_id=?,updated_at=NOW() WHERE id=?')->execute([$pppmItemId,(int)$instance['id']]);
                $instance['pppm_item_id']=$pppmItemId;
                $linked++;
            }
            $projected[]=mg_action_center_project_lifecycle($pdo,$instance);
        }
    }
    if($issued>0){
        mg_order_event($pdo,$orderDbId,'microgift.issued_from_paid_order',$actorUserId ?: (int)$order['buyer_user_id'],['issued_count'=>$issued,'duplicate_count'=>$duplicates,'linked_count'=>$linked]);
    }
    return ['issued_count'=>$issued,'duplicate_count'=>$duplicates,'linked_count'=>$linked,'projected'=>$projected];
}
