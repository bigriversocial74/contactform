<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__).'/api/commerce/_checkout.php';
require_once dirname(__DIR__).'/api/payments/_checkout_session.php';
require_once dirname(__DIR__).'/api/payments/_fulfillment.php';
require_once dirname(__DIR__).'/api/pppm/_ownership.php';
require_once dirname(__DIR__).'/api/microgifts/_atomic_merchant_redemption.php';
require_once dirname(__DIR__).'/api/messages/_messaging.php';
require_once dirname(__DIR__).'/tests/integration/CheckoutBehaviorFixture.php';

function mg_golden_audit_scalar(PDO $pdo,string $sql,array $params=[]): mixed
{
    $stmt=$pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function mg_golden_audit_pass(array &$report,string $key,string $summary,array $details=[]): void
{
    $report['checks'][]=['key'=>$key,'status'=>'pass','summary'=>$summary,'details'=>$details];
}

function mg_golden_audit_finding(array &$report,string $severity,string $key,string $summary,array $details=[]): void
{
    $report['checks'][]=['key'=>$key,'status'=>'finding','severity'=>$severity,'summary'=>$summary,'details'=>$details];
    $report['findings'][$severity]=($report['findings'][$severity]??0)+1;
}

function mg_golden_audit_catalog(PDO $pdo,int $merchantId,string $runId,string $suffix,string $title,int $unitValue,string $builderType): array
{
    $productPublic=mg_public_uuid();
    $versionPublic=mg_public_uuid();
    $slug='audit-'.$runId.'-'.$suffix;
    $pdo->prepare("INSERT INTO catalog_products
        (public_id,merchant_user_id,product_type,slug,status,created_by_user_id,published_at,created_at,updated_at)
        VALUES (?,?,'voucher',?,'published',?,NOW(),NOW(),NOW())")
        ->execute([$productPublic,$merchantId,$slug,$merchantId]);
    $productId=(int)$pdo->lastInsertId();
    $metadata=json_encode([
        'title'=>$title,
        'headline'=>$builderType==='greeting_card'?'A gift is waiting for you':'Local voucher',
        'message'=>$builderType==='greeting_card'?'Enjoy this local gift.':'Redeem this local voucher.',
        'merchant_name'=>'Golden Path Merchant',
        'product_category'=>'Voucher',
        'visibility'=>'published',
    ],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $fulfillment=json_encode(['builder_type'=>$builderType],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $pdo->prepare("INSERT INTO catalog_product_versions
        (public_id,product_id,version_number,version_status,title,description,unit_value_cents,currency,
         fulfillment_json,metadata_json,checksum,created_by_user_id,published_at,created_at)
        VALUES (?,?,1,'published',?,?,?,'USD',?,?,?, ?,NOW(),NOW())")
        ->execute([$versionPublic,$productId,$title,'Golden path audit product.',$unitValue,$fulfillment,$metadata,hash('sha256',$runId.'-'.$suffix),$merchantId]);
    $versionId=(int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE catalog_products SET current_version_id=? WHERE id=?')->execute([$versionId,$productId]);
    return [
        'product_id'=>$productId,
        'product_public'=>$productPublic,
        'version_id'=>$versionId,
        'version_public'=>$versionPublic,
        'slug'=>$slug,
        'title'=>$title,
        'unit_value_cents'=>$unitValue,
        'builder_type'=>$builderType,
    ];
}

function mg_golden_audit_checkout_draft(PDO $pdo,int $buyerId,int $merchantId,string $runId,array $lines): array
{
    $subtotal=0;
    foreach($lines as $line)$subtotal+=(int)$line['unit_value_cents']*(int)$line['quantity'];
    $cartPublic=mg_public_uuid();
    $pdo->prepare("INSERT INTO carts
        (public_id,user_id,status,currency,subtotal_cents,discount_cents,tax_cents,platform_fee_cents,total_cents,expires_at,created_at,updated_at)
        VALUES (?,?,'active','USD',?,0,0,0,?,DATE_ADD(NOW(),INTERVAL 30 DAY),NOW(),NOW())")
        ->execute([$cartPublic,$buyerId,$subtotal,$subtotal]);
    $cartId=(int)$pdo->lastInsertId();
    $items=[];
    foreach($lines as $index=>$line){
        $itemPublic=mg_public_uuid();
        $lineTotal=(int)$line['unit_value_cents']*(int)$line['quantity'];
        $pdo->prepare("INSERT INTO cart_items
            (public_id,cart_id,product_id,product_version_id,merchant_user_id,title_snapshot,unit_amount_cents,currency,quantity,line_total_cents,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,'USD',?,?,NOW(),NOW())")
            ->execute([$itemPublic,$cartId,(int)$line['product_id'],(int)$line['version_id'],$merchantId,(string)$line['title'],(int)$line['unit_value_cents'],(int)$line['quantity'],$lineTotal]);
        $items[]=[
            'item_id'=>$itemPublic,
            'product_id'=>(int)$line['product_id'],
            'product_version_id'=>(int)$line['version_id'],
            'merchant_user_id'=>$merchantId,
            'title_snapshot'=>(string)$line['title'],
            'quantity'=>(int)$line['quantity'],
            'unit_amount_cents'=>(int)$line['unit_value_cents'],
            'line_total_cents'=>$lineTotal,
            'currency'=>'USD',
            'sort_order'=>$index,
        ];
    }
    $draftPublic=mg_public_uuid();
    $pdo->prepare("INSERT INTO checkout_drafts
        (public_id,cart_id,buyer_user_id,merchant_user_id,currency,subtotal_cents,discount_cents,tax_cents,platform_fee_cents,total_cents,items_json,status,idempotency_key,expires_at,created_at,updated_at)
        VALUES (?,?,?,?,'USD',?,0,0,0,?,?,'open',?,DATE_ADD(NOW(),INTERVAL 30 MINUTE),NOW(),NOW())")
        ->execute([$draftPublic,$cartId,$buyerId,$merchantId,$subtotal,$subtotal,json_encode($items,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'audit:draft:'.$runId]);
    return ['draft_public'=>$draftPublic,'subtotal_cents'=>$subtotal,'items'=>$items];
}

$pdo=mg_db();
$runId='golden'.bin2hex(random_bytes(6));
$report=[
    'suite'=>'product_pppm_golden_path_audit',
    'run_id'=>$runId,
    'mode'=>'non_gating_audit',
    'checks'=>[],
    'findings'=>['critical'=>0,'high'=>0,'medium'=>0,'low'=>0],
    'rolled_back'=>false,
];

$pdo->beginTransaction();
try{
    $buyerId=mg_it_user($pdo,$runId.'-buyer@example.test','Golden Path Buyer');
    $merchantId=mg_it_user($pdo,$runId.'-merchant@example.test','Golden Path Merchant');
    $recipientA=mg_it_user($pdo,$runId.'-a@example.test','Golden Path Recipient A');
    $recipientB=mg_it_user($pdo,$runId.'-b@example.test','Golden Path Recipient B');
    $attackerId=mg_it_user($pdo,$runId.'-attacker@example.test','Golden Path Unauthorized Actor');

    $simple=mg_golden_audit_catalog($pdo,$merchantId,$runId,'simple','Simple coffee voucher',1250,'simple_product');
    $greeting=mg_golden_audit_catalog($pdo,$merchantId,$runId,'greeting','Greeting dinner voucher',2000,'greeting_card');
    $draft=mg_golden_audit_checkout_draft($pdo,$buyerId,$merchantId,$runId,[
        $simple+['quantity'=>2],
        $greeting+['quantity'=>3],
    ]);

    $order=mg_checkout_create_order($pdo,$buyerId,$draft['draft_public'],'audit-order-'.$runId);
    $orderPublic=(string)$order['order']['order_id'];
    $orderId=(int)mg_golden_audit_scalar($pdo,'SELECT id FROM commerce_orders WHERE public_id=?',[$orderPublic]);
    $session=mg_payment_create_checkout_session($pdo,$buyerId,$orderPublic,'audit-session-'.$runId);
    $intentId=(int)mg_golden_audit_scalar($pdo,'SELECT id FROM payment_intents WHERE public_id=?',[(string)$session['payment_intent_id']]);
    $capture=mg_finance_record_paid_order($pdo,$orderId,$intentId,'audit-provider-'.$runId,$buyerId);
    if((int)$capture['issued_count']===5&&(int)$capture['microgift_issued_count']===5){
        mg_golden_audit_pass($report,'multi_line_quantity_issuance','Two invoice lines issued five independent PPPM and Microgift units.',['line_quantities'=>[2,3],'issued_count'=>5]);
    }else{
        mg_golden_audit_finding($report,'critical','multi_line_quantity_issuance','Multi-line checkout did not issue one unit per purchased quantity.',['capture'=>$capture]);
    }

    $lineRows=$pdo->prepare('SELECT id,public_id,quantity,pppm_issuance_request_id FROM commerce_order_items WHERE order_id=? ORDER BY id');
    $lineRows->execute([$orderId]);
    $lines=$lineRows->fetchAll(PDO::FETCH_ASSOC);
    $lineAudit=[];
    foreach($lines as $line){
        $requestId=(int)($line['pppm_issuance_request_id']??0);
        $count=(int)mg_golden_audit_scalar($pdo,'SELECT COUNT(*) FROM pppm_items WHERE issuance_request_id=?',[$requestId]);
        $sequenceCount=(int)mg_golden_audit_scalar($pdo,'SELECT COUNT(DISTINCT unit_sequence) FROM pppm_items WHERE issuance_request_id=?',[$requestId]);
        $lineAudit[]=['line_id'=>$line['public_id'],'quantity'=>(int)$line['quantity'],'pppm_count'=>$count,'sequence_count'=>$sequenceCount];
    }
    $lineCountsValid=count($lineAudit)===2;
    foreach($lineAudit as $line)$lineCountsValid=$lineCountsValid&&$line['quantity']===$line['pppm_count']&&$line['quantity']===$line['sequence_count'];
    if($lineCountsValid)mg_golden_audit_pass($report,'line_unit_sequences','Each invoice line has a complete, unique unit sequence.',['lines'=>$lineAudit]);
    else mg_golden_audit_finding($report,'critical','line_unit_sequences','Invoice-line PPPM unit sequences are incomplete or duplicated.',['lines'=>$lineAudit]);

    $distinctPppm=(int)mg_golden_audit_scalar($pdo,"SELECT COUNT(DISTINCT p.public_id)
        FROM pppm_items p
        INNER JOIN microgift_instances mi ON mi.pppm_item_id=p.id
        INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference
        WHERE oi.order_id=? AND mi.source_type='commerce_order_item'",[$orderId]);
    $distinctInstances=(int)mg_golden_audit_scalar($pdo,"SELECT COUNT(DISTINCT mi.public_id)
        FROM microgift_instances mi
        INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference
        WHERE oi.order_id=? AND mi.source_type='commerce_order_item'",[$orderId]);
    if($distinctPppm===5&&$distinctInstances===5){
        mg_golden_audit_pass($report,'one_to_one_identity','Each purchased unit has one permanent PPPM ID and one Microgift instance.',['pppm_ids'=>$distinctPppm,'microgift_instances'=>$distinctInstances]);
    }else{
        mg_golden_audit_finding($report,'critical','one_to_one_identity','Purchased units are not linked one-to-one between PPPM and Microgift.',['pppm_ids'=>$distinctPppm,'microgift_instances'=>$distinctInstances]);
    }

    $buyerInbox=(int)mg_golden_audit_scalar($pdo,"SELECT COUNT(*) FROM microgift_inbox_items ac
        INNER JOIN microgift_instances mi ON mi.id=ac.instance_id
        INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference
        WHERE oi.order_id=? AND ac.user_id=? AND ac.folder='inbox'",[$orderId,$buyerId]);
    if($buyerInbox===5)mg_golden_audit_pass($report,'buyer_inbox_projection','All purchased units appear independently in the buyer Inbox.',['count'=>$buyerInbox]);
    else mg_golden_audit_finding($report,'critical','buyer_inbox_projection','Purchased units are missing or duplicated in the buyer Inbox.',['count'=>$buyerInbox]);

    $merchantSent=(int)mg_golden_audit_scalar($pdo,"SELECT COUNT(*) FROM microgift_inbox_items ac
        INNER JOIN microgift_instances mi ON mi.id=ac.instance_id
        INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference
        WHERE oi.order_id=? AND ac.user_id=? AND ac.folder='sent'",[$orderId,$merchantId]);
    if($merchantSent>0){
        mg_golden_audit_finding($report,'medium','purchase_projects_merchant_sent','A customer purchase immediately creates merchant Sent-tab rows, mixing sales with user-initiated sends.',['count'=>$merchantSent]);
    }else{
        mg_golden_audit_pass($report,'purchase_projects_merchant_sent','Customer purchase does not pollute the merchant Sent tab.');
    }

    $issuerMismatch=(int)mg_golden_audit_scalar($pdo,"SELECT COUNT(*)
        FROM microgift_instances mi
        INNER JOIN pppm_items p ON p.id=mi.pppm_item_id
        INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference
        WHERE oi.order_id=? AND mi.source_type='commerce_order_item' AND mi.issuer_user_id<>p.issuer_user_id",[$orderId]);
    if($issuerMismatch>0){
        mg_golden_audit_finding($report,'high','issuer_authority_mismatch','PPPM and Microgift disagree about the original issuer for purchased gifts.',['mismatched_units'=>$issuerMismatch]);
    }else{
        mg_golden_audit_pass($report,'issuer_authority_mismatch','PPPM and Microgift preserve the same original issuer.');
    }

    $units=$pdo->prepare("SELECT p.id pppm_id,p.public_id pppm_public,p.status pppm_status,p.owner_user_id pppm_owner,
            mi.id instance_id,mi.public_id instance_public,mi.status instance_status,mi.issuer_user_id,mi.owner_user_id,mi.recipient_user_id,mi.template_id,mi.pppm_item_id
        FROM pppm_items p
        INNER JOIN microgift_instances mi ON mi.pppm_item_id=p.id
        INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference
        WHERE oi.order_id=?
        ORDER BY p.id");
    $units->execute([$orderId]);
    $unitRows=$units->fetchAll(PDO::FETCH_ASSOC);

    $unauthorized=$unitRows[0];
    $unauthorizedTransferSucceeded=false;
    try{
        mg_pppm_transfer_owner_canonical($pdo,(string)$unauthorized['pppm_public'],$recipientA,'audit_unauthorized_transfer',$runId.'-unauthorized',$attackerId,[]);
        $unauthorizedTransferSucceeded=(int)mg_golden_audit_scalar($pdo,'SELECT owner_user_id FROM pppm_items WHERE id=?',[(int)$unauthorized['pppm_id']])===$recipientA;
    }catch(Throwable){}
    if($unauthorizedTransferSucceeded){
        mg_golden_audit_finding($report,'critical','transfer_actor_authorization','A non-owner can invoke the canonical PPPM owner transfer helper successfully.',['actor_user_id'=>$attackerId,'previous_owner_user_id'=>$buyerId,'new_owner_user_id'=>$recipientA]);
    }else{
        mg_golden_audit_pass($report,'transfer_actor_authorization','Canonical PPPM transfer rejects non-owner actors.');
    }

    $sticky=$unitRows[1];
    mg_pppm_transfer_owner_canonical($pdo,(string)$sticky['pppm_public'],$recipientA,'audit_send',$runId.'-to-a',$buyerId,[]);
    mg_pppm_transfer_owner_canonical($pdo,(string)$sticky['pppm_public'],$recipientB,'audit_send',$runId.'-to-b',$recipientA,[]);
    $stickyState=$pdo->prepare('SELECT owner_user_id,recipient_user_id,status FROM pppm_items WHERE id=?');
    $stickyState->execute([(int)$sticky['pppm_id']]);
    $stickyRow=$stickyState->fetch(PDO::FETCH_ASSOC);
    if((int)$stickyRow['owner_user_id']===$recipientB&&(int)$stickyRow['recipient_user_id']!==$recipientB){
        mg_golden_audit_finding($report,'high','current_recipient_projection','After a second transfer, PPPM owner changes but recipient_user_id remains the first recipient.',['owner_user_id'=>(int)$stickyRow['owner_user_id'],'recipient_user_id'=>(int)$stickyRow['recipient_user_id']]);
    }else{
        mg_golden_audit_pass($report,'current_recipient_projection','PPPM current recipient follows every ownership transfer.');
    }
    if((string)$stickyRow['status']==='available'){
        mg_golden_audit_finding($report,'high','pppm_delivery_state','PPPM remains available after transfers instead of recording sent/delivered lifecycle state.',['status'=>$stickyRow['status']]);
    }else{
        mg_golden_audit_pass($report,'pppm_delivery_state','PPPM lifecycle state advances when ownership is sent.',['status'=>$stickyRow['status']]);
    }

    $closed=$unitRows[2];
    $pdo->prepare("UPDATE pppm_items SET status='redeemed',redeemed_at=NOW() WHERE id=?")->execute([(int)$closed['pppm_id']]);
    $closedTransferSucceeded=false;
    try{
        mg_pppm_transfer_owner_canonical($pdo,(string)$closed['pppm_public'],$recipientA,'audit_closed_transfer',$runId.'-closed',$buyerId,[]);
        $closedTransferSucceeded=(int)mg_golden_audit_scalar($pdo,'SELECT owner_user_id FROM pppm_items WHERE id=?',[(int)$closed['pppm_id']])===$recipientA;
    }catch(Throwable){}
    if($closedTransferSucceeded){
        mg_golden_audit_finding($report,'critical','closed_gift_transfer','The canonical transfer helper allows a redeemed PPPM item to change owners.');
    }else{
        mg_golden_audit_pass($report,'closed_gift_transfer','Redeemed and closed PPPM items cannot be transferred.');
    }

    $claimUnit=$unitRows[3];
    $claimCredentialCount=(int)mg_golden_audit_scalar($pdo,"SELECT COUNT(*) FROM microgift_credentials WHERE instance_id=? AND purpose='claim' AND status='active'",[(int)$claimUnit['instance_id']]);
    if($claimCredentialCount===0){
        mg_golden_audit_finding($report,'critical','purchased_gift_claim_bridge','Purchased gifts issue no recipient claim credential, while merchant claim currently requires a prior claimed/redeemable state.',['active_claim_credentials'=>$claimCredentialCount,'instance_status'=>$claimUnit['instance_status']]);
    }else{
        mg_golden_audit_pass($report,'purchased_gift_claim_bridge','Purchased gifts have a complete path into merchant claim.');
    }

    $location=mg_it_location($pdo,$merchantId,$runId.'-claim');
    $merchantClaimSucceeded=false;
    $merchantClaimError='';
    try{
        mg_microgift_atomic_merchant_redeem($pdo,$merchantId,[
            'instance_id'=>(string)$claimUnit['instance_public'],
            'claimant_user_id'=>$buyerId,
            'merchant_user_id'=>$merchantId,
            'location_id'=>(string)$location['public_id'],
            'claim_code'=>(string)$location['code'],
            'idempotency_key'=>$runId.'-merchant-claim',
            'source_reference'=>'golden_path_audit',
        ]);
        $merchantClaimSucceeded=true;
    }catch(Throwable $error){
        $merchantClaimError=$error->getMessage();
    }
    if(!$merchantClaimSucceeded){
        mg_golden_audit_finding($report,'critical','direct_merchant_claim','An owner cannot move directly from received Inbox to merchant-location claim; an extra recipient claim state is required.',['instance_status'=>$claimUnit['instance_status'],'error'=>$merchantClaimError]);
    }else{
        mg_golden_audit_pass($report,'direct_merchant_claim','Merchant location code atomically claims a received gift without recipient acceptance.');
    }

    $policyInstance=['location_policy_json'=>json_encode(['mode'=>'selected_locations','location_ids'=>['ALLOWED-LOCATION']],JSON_THROW_ON_ERROR)];
    if(mg_microgift_location_allowed($policyInstance,'OTHER-LOCATION')){
        mg_golden_audit_finding($report,'critical','location_policy_enforcement','Published selected-location policy is not understood by the redemption validator, allowing an unlisted location identifier.');
    }else{
        mg_golden_audit_pass($report,'location_policy_enforcement','Redemption rejects locations outside the product publication policy.');
    }

    $messageUnit=$unitRows[4];
    $instanceStmt=$pdo->prepare('SELECT * FROM microgift_instances WHERE id=?');
    $instanceStmt->execute([(int)$messageUnit['instance_id']]);
    $messageInstance=$instanceStmt->fetch(PDO::FETCH_ASSOC);
    $preClaimMessageSucceeded=false;
    try{
        mg_message_send_microgift($pdo,$messageInstance,$merchantId,$buyerId,'Audit message before merchant claim.',$runId.'-message','golden-path-audit');
        $preClaimMessageSucceeded=true;
    }catch(Throwable){}
    if($preClaimMessageSucceeded){
        mg_golden_audit_finding($report,'medium','message_timing_policy','Microgift messaging is allowed before merchant claim, while the intended product flow enables post-claim messaging.');
    }else{
        mg_golden_audit_pass($report,'message_timing_policy','Messaging is gated to the intended post-claim stage.');
    }

    $sendSource=file_get_contents(dirname(__DIR__).'/api/account/action-center-send.php');
    if(is_string($sendSource)&&str_contains($sendSource,'SET issuer_user_id=?,owner_user_id=?,recipient_user_id=?')){
        mg_golden_audit_finding($report,'high','original_issuer_preservation','Sending a gift overwrites Microgift issuer_user_id, losing the original merchant issuer and changing message participants.');
    }else{
        mg_golden_audit_pass($report,'original_issuer_preservation','Sending preserves the immutable original issuer.');
    }

    $messageSource=file_get_contents(dirname(__DIR__).'/api/messages/_messaging.php');
    if(is_string($messageSource)&&str_contains($messageSource,"foreach(['issuer_user_id','owner_user_id','recipient_user_id'] as \$field)")){
        mg_golden_audit_finding($report,'high','post_claim_message_recipient','Message participants are derived only from mutable issuer/owner/recipient fields and do not explicitly include the selling merchant or redemption location.');
    }else{
        mg_golden_audit_pass($report,'post_claim_message_recipient','Post-claim message authority explicitly resolves the selling merchant and claimant.');
    }

    $pppmIds=(int)mg_golden_audit_scalar($pdo,"SELECT COUNT(*) FROM pppm_items p
        INNER JOIN microgift_instances mi ON mi.pppm_item_id=p.id
        INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference
        WHERE oi.order_id=? AND p.issued_at IS NOT NULL AND mi.issued_at IS NOT NULL",[$orderId]);
    if($pppmIds===5)mg_golden_audit_pass($report,'issuance_timestamps','Every purchased unit records PPPM and Microgift issuance timestamps.',['count'=>$pppmIds]);
    else mg_golden_audit_finding($report,'high','issuance_timestamps','One or more purchased units lacks an issuance timestamp.',['count'=>$pppmIds]);

    $pdo->rollBack();
    $report['rolled_back']=true;
    $report['finding_total']=array_sum($report['findings']);
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    fwrite(STDERR,'Golden path audit failed to execute: '.$error->getMessage().PHP_EOL);
    exit(1);
}
