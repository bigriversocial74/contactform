<?php
declare(strict_types=1);
require_once __DIR__.'/_payments.php';
require_once __DIR__.'/_dispute_entitlements.php';
require_once dirname(__DIR__).'/finance/_posting.php';
require_once dirname(__DIR__).'/entitlements/_entitlements.php';
require_once dirname(__DIR__).'/communications/_communications.php';
require_once dirname(__DIR__).'/microgifts/_payment_reconciliation.php';

final class MgDisputeWorkflowException extends RuntimeException
{
    public function __construct(string $message,public readonly int $httpStatus=409){parent::__construct($message);}
}

function mg_dispute_restore_entitlements(PDO $pdo,int $orderId,?int $actorUserId=null): int
{
    $stmt=$pdo->prepare("SELECT e.* FROM entitlements e INNER JOIN commerce_order_items oi ON oi.id=e.commerce_order_item_id WHERE oi.order_id=? AND e.status='suspended' FOR UPDATE");
    $stmt->execute([$orderId]);$count=0;
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $pdo->prepare("UPDATE entitlements SET status='active',suspended_at=NULL,updated_at=NOW() WHERE id=? AND status='suspended'")->execute([(int)$row['id']]);
        mg_entitlement_event($pdo,(int)$row['id'],'entitlement.restored','suspended','active',$actorUserId,'dispute_won',[]);$count++;
    }
    return $count;
}

function mg_dispute_wallet_context(PDO $pdo,array $order): array
{
    $wallet=mg_wallet_resolve($pdo,'merchant',(int)$order['merchant_user_id'],(string)$order['currency']);
    return ['wallet'=>$wallet,'available'=>mg_wallet_account_id($pdo,(int)$wallet['id'],'available',(string)$wallet['currency']),'held'=>mg_wallet_account_id($pdo,(int)$wallet['id'],'held',(string)$wallet['currency']),'processor'=>mg_ledger_platform_account($pdo,'processor_clearing','asset','debit',(string)$order['currency']),'fee_revenue'=>mg_ledger_platform_account($pdo,'dispute_fee_revenue','revenue','credit',(string)$order['currency'])];
}

function mg_dispute_post_reserve(PDO $pdo,array $order,string $publicId,int $amount): array
{
    $ctx=mg_dispute_wallet_context($pdo,$order);
    return mg_ledger_post($pdo,['transaction_type'=>'chargeback_reserve','source_type'=>'payment_dispute','source_reference'=>$publicId,'idempotency_key'=>'dispute:reserve:'.$publicId,'currency'=>$order['currency'],'description'=>'Reserve merchant funds for payment dispute','metadata'=>['order_id'=>$order['public_id'],'dispute_id'=>$publicId]],[['ledger_account_id'=>$ctx['available'],'entry_type'=>'debit','amount_cents'=>$amount],['ledger_account_id'=>$ctx['held'],'entry_type'=>'credit','amount_cents'=>$amount]]);
}

function mg_dispute_post_won(PDO $pdo,array $order,string $publicId,int $amount): array
{
    $ctx=mg_dispute_wallet_context($pdo,$order);
    return mg_ledger_post($pdo,['transaction_type'=>'chargeback_release','source_type'=>'payment_dispute','source_reference'=>$publicId,'idempotency_key'=>'dispute:won:'.$publicId,'currency'=>$order['currency'],'description'=>'Release won dispute reserve'],[['ledger_account_id'=>$ctx['held'],'entry_type'=>'debit','amount_cents'=>$amount],['ledger_account_id'=>$ctx['available'],'entry_type'=>'credit','amount_cents'=>$amount]]);
}

function mg_dispute_post_lost(PDO $pdo,array $order,string $publicId,int $amount,int $fee): array
{
    $ctx=mg_dispute_wallet_context($pdo,$order);
    $entries=[['ledger_account_id'=>$ctx['held'],'entry_type'=>'debit','amount_cents'=>$amount],['ledger_account_id'=>$ctx['processor'],'entry_type'=>'credit','amount_cents'=>$amount]];
    if($fee>0){$entries[]=['ledger_account_id'=>$ctx['available'],'entry_type'=>'debit','amount_cents'=>$fee];$entries[]=['ledger_account_id'=>$ctx['fee_revenue'],'entry_type'=>'credit','amount_cents'=>$fee];}
    return mg_ledger_post($pdo,['transaction_type'=>'chargeback','source_type'=>'payment_dispute','source_reference'=>$publicId,'idempotency_key'=>'dispute:lost:'.$publicId,'currency'=>$order['currency'],'description'=>'Finalize lost payment dispute','metadata'=>['fee_cents'=>$fee]],$entries);
}

function mg_dispute_notify(PDO $pdo,array $order,string $type,string $severity,string $title,string $body,array $context=[]): void
{
    mg_create_operational_alert($pdo,(int)$order['merchant_user_id'],$type,$severity,$title,$body,'/merchant-payments.php',['merchant_user_id'=>(int)$order['merchant_user_id'],'order_id'=>$order['public_id']]+$context);
}

function mg_dispute_load_order(PDO $pdo,string $orderPublicId,string $intentPublicId): array
{
    $stmt=$pdo->prepare("SELECT o.id order_db_id,o.public_id,o.buyer_user_id,o.merchant_user_id,o.total_cents,o.currency,o.payment_status,pi.id intent_db_id,pi.public_id intent_public_id,pi.status intent_status FROM commerce_orders o INNER JOIN payment_intents pi ON pi.order_id=o.id WHERE (o.public_id=? OR pi.public_id=?) LIMIT 1 FOR UPDATE");
    $stmt->execute([$orderPublicId,$intentPublicId]);$order=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$order)throw new MgDisputeWorkflowException('Disputed payment was not found.',404);
    if((string)$order['intent_status']!=='succeeded')throw new MgDisputeWorkflowException('Only succeeded payments can be disputed.',409);
    return $order;
}

function mg_dispute_apply_event(PDO $pdo,array $order,array $data,string $type,?callable $failureHook=null): array
{
    $reference=trim((string)($data['dispute_id']??$data['provider_dispute_reference']??$data['id']??''));
    $amount=(int)($data['amount_cents']??0);$fee=max(0,(int)($data['fee_cents']??0));
    if($reference===''||$amount<1||$amount>(int)$order['total_cents'])throw new MgDisputeWorkflowException('Invalid dispute amount or reference.',422);
    $find=$pdo->prepare('SELECT * FROM payment_disputes WHERE provider_dispute_reference=? LIMIT 1 FOR UPDATE');$find->execute([$reference]);$dispute=$find->fetch(PDO::FETCH_ASSOC);
    $open=in_array($type,['charge.dispute.created','payment.dispute.created','dispute.opened'],true);
    $won=in_array($type,['charge.dispute.closed.won','payment.dispute.won','dispute.won'],true);
    $lost=in_array($type,['charge.dispute.closed.lost','payment.dispute.lost','dispute.lost'],true);
    if(!$open&&!$won&&!$lost)throw new MgDisputeWorkflowException('Unsupported dispute event.',422);

    if(!$dispute){
        if(!$open)throw new MgDisputeWorkflowException('Dispute resolution arrived before dispute creation.',409);
        $public=mg_public_uuid();
        $pdo->prepare("INSERT INTO payment_disputes (public_id,order_id,payment_intent_id,merchant_user_id,provider_dispute_reference,amount_cents,currency,reason,status,response_due_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,'needs_response',?,NOW(),NOW())")->execute([$public,(int)$order['order_db_id'],(int)$order['intent_db_id'],(int)$order['merchant_user_id'],$reference,$amount,$order['currency'],mb_substr((string)($data['reason']??'provider_dispute'),0,100),$data['response_due_at']??null]);
        $group=mg_dispute_post_reserve($pdo,$order,$public,$amount);if($failureHook)$failureHook('after_ledger',['dispute_id'=>$public,'group'=>$group]);
        $pdo->prepare("UPDATE commerce_orders SET payment_status='disputed',updated_at=NOW() WHERE id=?")->execute([(int)$order['order_db_id']]);
        if($amount>=(int)$order['total_cents'])$policy=['policy'=>'full_dispute_suspend','suspended'=>mg_entitlements_suspend_for_order($pdo,(int)$order['order_db_id'],'payment_dispute',null)];
        else{$policy=['policy'=>'partial_dispute_review'];mg_entitlement_create_review($pdo,'dispute','Partial dispute requires entitlement review.',['user_id'=>(int)$order['buyer_user_id'],'merchant_user_id'=>(int)$order['merchant_user_id'],'commerce_order_id'=>(int)$order['order_db_id']],['phase'=>'opened','dispute_id'=>$public,'amount_cents'=>$amount]);}
        $microgifts=mg_microgift_payment_reconcile_order($pdo,$order,$amount>=(int)$order['total_cents']?'dispute_opened':'dispute_opened_partial',$public,null,['amount_cents'=>$amount,'phase'=>'opened']);
        if($failureHook)$failureHook('after_microgift_reconciliation',['dispute_id'=>$public,'microgifts'=>$microgifts]);
        $pdo->prepare("INSERT INTO payment_transactions (public_id,payment_intent_id,transaction_type,provider_reference,amount_cents,currency,status,metadata_json,created_at) VALUES (?,?,'chargeback',?,?,?,'pending',?,NOW())")->execute([mg_public_uuid(),(int)$order['intent_db_id'],$reference,$amount,$order['currency'],json_encode(['dispute_id'=>$public,'phase'=>'opened','microgift_reconciliation'=>$microgifts],JSON_THROW_ON_ERROR)]);
        mg_dispute_notify($pdo,$order,'payment_dispute_opened','critical','Payment dispute opened','A payment dispute was opened and merchant funds were reserved.',['dispute_id'=>$public,'amount_cents'=>$amount]);
        return ['dispute_id'=>$public,'status'=>'needs_response','phase'=>'opened','duplicate'=>false,'entitlement_policy'=>$policy,'microgift_reconciliation'=>$microgifts];
    }

    if((int)$dispute['order_id']!==(int)$order['order_db_id']||(int)$dispute['amount_cents']!==$amount)throw new MgDisputeWorkflowException('Dispute event conflicts with the recorded dispute.',409);
    if($open)return ['dispute_id'=>$dispute['public_id'],'status'=>$dispute['status'],'phase'=>'opened','duplicate'=>true];
    if(in_array((string)$dispute['status'],['won','lost'],true)){
        $target=$won?'won':'lost';
        if((string)$dispute['status']!==$target)throw new MgDisputeWorkflowException('Dispute terminal event conflicts with the recorded outcome.',409);
        return ['dispute_id'=>$dispute['public_id'],'status'=>$target,'phase'=>'resolved','duplicate'=>true];
    }

    if($won){
        $group=mg_dispute_post_won($pdo,$order,(string)$dispute['public_id'],$amount);if($failureHook)$failureHook('after_ledger',['dispute_id'=>$dispute['public_id'],'group'=>$group]);
        $pdo->prepare("UPDATE payment_disputes SET status='won',resolved_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$dispute['id']]);
        $pdo->prepare("UPDATE commerce_orders SET payment_status='paid',updated_at=NOW() WHERE id=?")->execute([(int)$order['order_db_id']]);
        $restored=mg_dispute_restore_entitlements($pdo,(int)$order['order_db_id']);
        $microgiftOperation=$amount>=(int)$order['total_cents']?'dispute_won':'dispute_won_partial';
        $microgifts=mg_microgift_payment_reconcile_order($pdo,$order,$microgiftOperation,(string)$dispute['public_id'],null,['amount_cents'=>$amount,'phase'=>'won']);
        if($failureHook)$failureHook('after_microgift_reconciliation',['dispute_id'=>$dispute['public_id'],'microgifts'=>$microgifts]);
        $pdo->prepare("UPDATE payment_transactions SET status='reversed',metadata_json=? WHERE payment_intent_id=? AND transaction_type='chargeback' AND provider_reference=?")->execute([json_encode(['dispute_id'=>$dispute['public_id'],'outcome'=>'won','microgift_reconciliation'=>$microgifts],JSON_THROW_ON_ERROR),(int)$order['intent_db_id'],$reference]);
        mg_dispute_notify($pdo,$order,'payment_dispute_won','info','Payment dispute won','The dispute reserve was released back to the merchant balance.',['dispute_id'=>$dispute['public_id']]);
        return ['dispute_id'=>$dispute['public_id'],'status'=>'won','phase'=>'resolved','duplicate'=>false,'restored_entitlements'=>$restored,'microgift_reconciliation'=>$microgifts];
    }

    $group=mg_dispute_post_lost($pdo,$order,(string)$dispute['public_id'],$amount,$fee);if($failureHook)$failureHook('after_ledger',['dispute_id'=>$dispute['public_id'],'group'=>$group]);
    $pdo->prepare("UPDATE payment_disputes SET status='lost',resolved_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$dispute['id']]);
    $full=$amount>=(int)$order['total_cents'];
    $pdo->prepare("UPDATE commerce_orders SET payment_status=?,updated_at=NOW() WHERE id=?")->execute([$full?'refunded':'partially_refunded',(int)$order['order_db_id']]);
    $revoked=$full?mg_dispute_revoke_entitlements($pdo,(int)$order['order_db_id'],null):0;
    if(!$full)mg_entitlement_create_review($pdo,'dispute','Partial lost dispute requires entitlement review.',['user_id'=>(int)$order['buyer_user_id'],'merchant_user_id'=>(int)$order['merchant_user_id'],'commerce_order_id'=>(int)$order['order_db_id']],['phase'=>'lost','dispute_id'=>$dispute['public_id'],'amount_cents'=>$amount]);
    $microgifts=mg_microgift_payment_reconcile_order($pdo,$order,$full?'dispute_lost_full':'dispute_lost_partial',(string)$dispute['public_id'],null,['amount_cents'=>$amount,'phase'=>'lost']);
    if($failureHook)$failureHook('after_microgift_reconciliation',['dispute_id'=>$dispute['public_id'],'microgifts'=>$microgifts]);
    $pdo->prepare("UPDATE payment_transactions SET status='succeeded',metadata_json=? WHERE payment_intent_id=? AND transaction_type='chargeback' AND provider_reference=?")->execute([json_encode(['dispute_id'=>$dispute['public_id'],'outcome'=>'lost','fee_cents'=>$fee,'microgift_reconciliation'=>$microgifts],JSON_THROW_ON_ERROR),(int)$order['intent_db_id'],$reference]);
    mg_dispute_notify($pdo,$order,'payment_dispute_lost','critical','Payment dispute lost','The chargeback was finalized against the merchant balance.',['dispute_id'=>$dispute['public_id'],'fee_cents'=>$fee]);
    return ['dispute_id'=>$dispute['public_id'],'status'=>'lost','phase'=>'resolved','duplicate'=>false,'revoked_entitlements'=>$revoked,'fee_cents'=>$fee,'microgift_reconciliation'=>$microgifts];
}

function mg_dispute_process_webhook(PDO $pdo,string $provider,array $event,string $payload,?callable $failureHook=null): array
{
    $eventId=trim((string)($event['id']??''));$type=trim((string)($event['type']??''));$data=is_array($event['data']??null)?$event['data']:[];
    if($eventId===''||$type==='')throw new MgDisputeWorkflowException('Invalid dispute webhook event.',422);
    $hash=hash('sha256',$payload);
    $existing=$pdo->prepare('SELECT * FROM payment_webhook_events WHERE provider_key=? AND provider_event_id=? LIMIT 1 FOR UPDATE');$existing->execute([$provider,$eventId]);
    if($row=$existing->fetch(PDO::FETCH_ASSOC)){
        if(!hash_equals((string)$row['payload_hash'],$hash)||(string)$row['event_type']!==$type)throw new MgDisputeWorkflowException('Dispute webhook event conflicts with the recorded payload.',409);
        if(in_array((string)$row['status'],['processed','ignored'],true))return ['duplicate'=>true,'event_id'=>$eventId,'status'=>$row['status']];
    }else{
        $pdo->prepare("INSERT INTO payment_webhook_events (public_id,provider_key,provider_event_id,event_type,signature_valid,status,payload_hash,payload_json,received_at) VALUES (?,?,?,?,1,'received',?,?,NOW())")->execute([mg_public_uuid(),$provider,$eventId,$type,$hash,$payload]);
    }
    $order=mg_dispute_load_order($pdo,trim((string)($data['order_id']??$data['metadata']['order_id']??'')),trim((string)($data['payment_intent_id']??$data['metadata']['payment_intent_id']??'')));
    $result=mg_dispute_apply_event($pdo,$order,$data,$type,$failureHook);
    $pdo->prepare("UPDATE payment_webhook_events SET status='processed',processed_at=NOW(),failure_message=NULL WHERE provider_key=? AND provider_event_id=?")->execute([$provider,$eventId]);
    return ['duplicate'=>false,'event_id'=>$eventId,'result'=>$result];
}
