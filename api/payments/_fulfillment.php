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

    $issuerUserId=(int)$order['merchant_user_id'];
    $eventActorUserId=$actorUserId ?: (int)$order['buyer_user_id'];
    $lineStmt = $pdo->prepare('SELECT * FROM commerce_order_items WHERE order_id=? AND pppm_issuance_request_id IS NULL ORDER BY id FOR UPDATE');
    $lineStmt->execute([$orderDbId]);
    $lines = $lineStmt->fetchAll();
    if (!$lines) {
        $pdo->prepare("UPDATE commerce_orders SET fulfillment_status=IF(fulfillment_status='pending','issued',fulfillment_status),updated_at=NOW() WHERE id=?")->execute([$orderDbId]);
        $entitlements = mg_entitlement_grant_for_order($pdo,$orderDbId,$eventActorUserId);
        return ['issued_count'=>0,'skipped'=>true,'entitlements'=>$entitlements];
    }
    $source = mg_payment_pppm_source($pdo, $issuerUserId);
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
        $requestStmt->execute([$requestPublicId,(int)$source['id'],$sourceEventId,$issuerUserId,$issuerUserId,(string)$order['public_id'],(string)$line['public_id'],(int)$line['quantity'],(int)$line['unit_amount_cents'],(string)$line['currency'],(int)$order['buyer_user_id'],(string)$line['title_snapshot'],'Purchased through commerce checkout.',$metadataJson]);
        $requestDbId = (int)$pdo->lastInsertId();
        for ($sequence=1; $sequence <= (int)$line['quantity']; $sequence++) {
            $itemStmt->execute([mg_pppm_item_id(),$requestDbId,(int)$source['id'],$sequence,'gift','customer_purchase',$issuerUserId,$issuerUserId,(int)$order['buyer_user_id'],(string)$order['public_id'],(string)$line['public_id'],(string)$line['title_snapshot'],'Purchased through commerce checkout.',(int)$line['unit_amount_cents'],(string)$line['currency'],$metadataJson]);
            $item = $pdo->query('SELECT * FROM pppm_items WHERE id='.(int)$pdo->lastInsertId())->fetch();
            mg_pppm_record_event($pdo, $item, 'issued_from_paid_order', null, 'available', $eventActorUserId, $sourceEventId, ['commerce_order_id'=>(string)$order['public_id'],'commerce_order_item_id'=>(string)$line['public_id'],'issuance_request_id'=>$requestPublicId,'unit_sequence'=>$sequence]);
            $entitlementResult = mg_entitlement_grant_for_pppm_item($pdo,$item,$eventActorUserId);
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
    $canonical=$pdo->prepare(
        "SELECT mv.public_id
         FROM catalog_pppm_templates cpt
         INNER JOIN microgift_template_versions mv ON mv.id=cpt.microgift_template_version_id
         INNER JOIN microgift_templates mt ON mt.id=mv.template_id
         WHERE cpt.product_version_id=? AND cpt.status='active'
           AND mv.status='published' AND mt.status='active' AND mt.owner_user_id=?
         LIMIT 1 FOR UPDATE"
    );
    $canonical->execute([(int)$line['product_version_id'],(int)$order['merchant_user_id']]);
    $canonicalPublic=(string)($canonical->fetchColumn()?:'');
    if($canonicalPublic!=='')return $canonicalPublic;

    $existing=$pdo->prepare("SELECT v.id,v.public_id FROM microgift_template_versions v INNER JOIN microgift_templates t ON t.id=v.template_id WHERE v.product_version_id=? AND v.status='published' AND t.owner_user_id=? AND t.status='active' ORDER BY v.id DESC LIMIT 1 FOR UPDATE");
    $existing->execute([(int)$line['product_version_id'],(int)$order['merchant_user_id']]);
    $existingRow=$existing->fetch(PDO::FETCH_ASSOC);
    if($existingRow){
        $pdo->prepare('UPDATE catalog_pppm_templates SET microgift_template_version_id=?,updated_at=NOW() WHERE product_version_id=?')
            ->execute([(int)$existingRow['id'],(int)$line['product_version_id']]);
        return (string)$existingRow['public_id'];
    }

    $productVersion=$pdo->prepare('SELECT cpv.*,cp.public_id product_public_id,cp.slug product_slug,cp.product_type FROM catalog_product_versions cpv INNER JOIN catalog_products cp ON cp.id=cpv.product_id WHERE cpv.id=? LIMIT 1 FOR UPDATE');
    $productVersion->execute([(int)$line['product_version_id']]);
    $version=$productVersion->fetch(PDO::FETCH_ASSOC);
    if(!$version)throw new RuntimeException('Catalog product version not found for Microgift issuance.');

    $locationStmt=$pdo->prepare(
        "SELECT ml.public_id
         FROM catalog_product_version_locations cpvl
         INNER JOIN merchant_locations ml ON ml.id=cpvl.merchant_location_id
         WHERE cpvl.product_version_id=? AND cpvl.availability_status='available' AND ml.status='active'
         ORDER BY cpvl.is_primary DESC,cpvl.id"
    );
    $locationStmt->execute([(int)$line['product_version_id']]);
    $locationIds=array_map('strval',$locationStmt->fetchAll(PDO::FETCH_COLUMN));

    $template=mg_microgift_create_template($pdo,(int)$order['merchant_user_id'],[
        'owner_type'=>'merchant','name'=>(string)$line['title_snapshot'],'gift_type'=>'product','visibility'=>'public','default_currency'=>(string)$line['currency'],'slug'=>(string)($version['product_slug']??('commerce-'.$line['public_id'])),'description'=>'Commerce checkout Microgift template.',
    ]);
    $created=mg_microgift_create_version($pdo,(int)$order['merchant_user_id'],(string)$template['template_id'],[
        'title'=>(string)$line['title_snapshot'],'description'=>(string)($version['description']??'Purchased through commerce checkout.'),'currency'=>(string)$line['currency'],'face_value_cents'=>(int)$line['unit_amount_cents'],'product_id'=>(int)$line['product_id'],'product_version_id'=>(int)$line['product_version_id'],'recipient_policy'=>'purchaser','claim_policy'=>['mode'=>'purchaser_owned'],'redemption_policy'=>['mode'=>'merchant_location'],'location_policy'=>$locationIds === [] ? ['mode'=>'unrestricted'] : ['mode'=>'selected_locations','location_ids'=>$locationIds],'expiration_policy'=>$version['expiration_policy_json']?json_decode((string)$version['expiration_policy_json'],true):[],'terms_snapshot'=>$version['terms_json']?json_decode((string)$version['terms_json'],true):[],'future_demand_metadata'=>['source'=>'commerce_checkout_legacy_recovery','catalog_product_id'=>(string)($version['product_public_id']??'')],
    ]);
    $published=mg_microgift_publish_version($pdo,(int)$order['merchant_user_id'],(string)$created['version_id']);
    $microgiftVersionStmt=$pdo->prepare('SELECT id FROM microgift_template_versions WHERE public_id=? LIMIT 1');
    $microgiftVersionStmt->execute([(string)$published['version_id']]);
    $microgiftVersionId=(int)$microgiftVersionStmt->fetchColumn();

    $itemType=in_array((string)$version['product_type'],['gift','prize','reward','voucher','entitlement','reservation','credit'],true) ? (string)$version['product_type'] : 'other';
    $defaults=json_encode(['title'=>(string)$line['title_snapshot'],'description'=>$version['description']??null,'value_cents'=>(int)$line['unit_amount_cents'],'currency'=>(string)$line['currency'],'location_ids'=>$locationIds,'recovered_at_checkout'=>true],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $pdo->prepare("INSERT INTO catalog_pppm_templates (public_id,product_version_id,microgift_template_version_id,item_type,default_funding_type,issuance_defaults_json,status,created_at,updated_at) VALUES (?,?,?,?, 'customer_purchase',?,'active',NOW(),NOW()) ON DUPLICATE KEY UPDATE microgift_template_version_id=VALUES(microgift_template_version_id), item_type=VALUES(item_type),default_funding_type='customer_purchase',issuance_defaults_json=VALUES(issuance_defaults_json),status='active',updated_at=NOW()")
        ->execute([mg_microgift_uuid(),(int)$line['product_version_id'],$microgiftVersionId,$itemType,$defaults]);
    return (string)$published['version_id'];
}

function mg_payment_issue_commerce_microgift(PDO $pdo,array $order,array $line,string $templateVersion,int $sequence,int $actorUserId): array
{
    $idempotencyKey='commerce-order-item:'.$line['public_id'].':microgift:'.$sequence;
    $duplicate=mg_microgift_existing_issue($pdo,$idempotencyKey,(int)$order['merchant_user_id'],$templateVersion,'commerce_order_item',(string)$line['public_id']);
    if($duplicate!==null)return $duplicate;
    $versionStmt=$pdo->prepare("SELECT v.*,t.owner_user_id,t.id AS resolved_template_id FROM microgift_template_versions v INNER JOIN microgift_templates t ON t.id=v.template_id WHERE v.public_id=? AND v.status='published' AND t.status='active' LIMIT 1 FOR UPDATE");
    $versionStmt->execute([$templateVersion]);
    $version=$versionStmt->fetch(PDO::FETCH_ASSOC);
    if(!$version || (int)$version['owner_user_id']!==(int)$order['merchant_user_id'])throw new RuntimeException('Published commerce Microgift template version not found.');
    $metadata=mg_microgift_json(['commerce_order_id'=>(string)$order['public_id'],'commerce_order_item_id'=>(string)$line['public_id'],'unit_sequence'=>$sequence]);
    $instancePublicId=mg_microgift_uuid();
    $expiresAt=null;
    $pdo->prepare("INSERT INTO microgift_instances (public_id,template_id,template_version_id,status,source_type,source_reference,idempotency_key,issuer_user_id,owner_user_id,recipient_user_id,recipient_reference,commerce_order_item_id,legacy_gift_id,title_snapshot,description_snapshot,currency,face_value_cents,product_id,product_version_id,recipient_policy,claim_policy_json,redemption_policy_json,location_policy_json,expiration_policy_json,terms_snapshot_json,metadata_json,issued_at,expires_at,created_at,updated_at) VALUES (?,?,?,'issued',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())")
        ->execute([$instancePublicId,(int)$version['resolved_template_id'],(int)$version['id'],'commerce_order_item',(string)$line['public_id'],$idempotencyKey,(int)$order['merchant_user_id'],(int)$order['buyer_user_id'],(int)$order['buyer_user_id'],null,(int)$line['id'],null,(string)$version['title'],$version['description'],(string)$version['currency'],$version['face_value_cents'],$version['product_id'],$version['product_version_id'],(string)$version['recipient_policy'],$version['claim_policy_json'],$version['redemption_policy_json'],$version['location_policy_json'],$version['expiration_policy_json'],$version['terms_snapshot_json'],$metadata,$expiresAt]);
    $instanceId=(int)$pdo->lastInsertId();
    $credential=(string)$version['recipient_policy']!=='purchaser'?mg_microgift_create_credential($pdo,$instanceId,'claim',$actorUserId,$expiresAt):null;
    mg_microgift_event($pdo,'microgift.instance_issued',$instanceId,(int)$version['resolved_template_id'],$actorUserId,'commerce_order_item',(string)$line['public_id'],['template_version_id'=>$templateVersion,'owner_user_id'=>(int)$order['buyer_user_id'],'recipient_user_id'=>(int)$order['buyer_user_id']]);
    return ['instance_id'=>$instancePublicId,'status'=>'issued','duplicate'=>false,'credential'=>$credential];
}

function mg_payment_issue_order_microgifts(PDO $pdo, int $orderDbId, ?int $actorUserId = null): array
{
    $orderStmt=$pdo->prepare('SELECT * FROM commerce_orders WHERE id=? LIMIT 1 FOR UPDATE');
    $orderStmt->execute([$orderDbId]);
    $order=$orderStmt->fetch(PDO::FETCH_ASSOC);
    if(!$order || (string)$order['payment_status']!=='paid')return ['issued_count'=>0,'skipped'=>true];

    $eventActorUserId=$actorUserId ?: (int)$order['buyer_user_id'];
    if(mg_payment_order_items_have_merchant($pdo)){
        $pdo->prepare('UPDATE commerce_order_items SET merchant_user_id=? WHERE order_id=? AND merchant_user_id IS NULL')->execute([(int)$order['merchant_user_id'],$orderDbId]);
    }
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

            $result=mg_payment_issue_commerce_microgift($pdo,$order,$line,$templateVersion,$sequence,$eventActorUserId);
            if(!empty($result['duplicate']))$duplicates++;else$issued++;

            $instanceStmt=$pdo->prepare('SELECT * FROM microgift_instances WHERE public_id=? LIMIT 1 FOR UPDATE');
            $instanceStmt->execute([(string)$result['instance_id']]);
            $instance=$instanceStmt->fetch(PDO::FETCH_ASSOC);
            if(!$instance)throw new RuntimeException('Issued commerce Microgift instance was not found.');
            $existingPppmId=(int)($instance['pppm_item_id']??0);
            if($existingPppmId>0&&$existingPppmId!==$pppmItemId){throw new RuntimeException('Commerce Microgift is linked to a different PPPM item.');}
            if($existingPppmId===0){
                $pdo->prepare('UPDATE microgift_instances SET pppm_item_id=?,updated_at=NOW() WHERE id=?')->execute([$pppmItemId,(int)$instance['id']]);
                $instance['pppm_item_id']=$pppmItemId;
                $linked++;
            }
            $projected[]=mg_action_center_receive($pdo,(int)$instance['id'],(int)$order['buyer_user_id'],(int)$order['merchant_user_id'],['occurred_at'=>$instance['issued_at']??date('Y-m-d H:i:s')]);
        }
    }
    if($issued>0){mg_order_event($pdo,$orderDbId,'microgift.issued_from_paid_order',$eventActorUserId,['issued_count'=>$issued,'duplicate_count'=>$duplicates,'linked_count'=>$linked]);}
    return ['issued_count'=>$issued,'duplicate_count'=>$duplicates,'linked_count'=>$linked,'projected'=>$projected];
}
