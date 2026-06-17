<?php
declare(strict_types=1);

require_once __DIR__.'/_tips.php';

const MG_TIP_RECOVERY_EVENT_TYPES=[
    'charge.refunded','refund.succeeded','tip.refunded',
    'charge.dispute.created','dispute.created','tip.dispute_opened',
    'charge.dispute.closed','dispute.closed','tip.dispute_won','tip.dispute_lost',
    'charge.dispute.funds_withdrawn','tip.chargeback',
];

function mg_tip_is_recovery_event(string $type): bool
{
    return in_array($type,MG_TIP_RECOVERY_EVENT_TYPES,true);
}

function mg_tip_recovery_kind(string $type,array $object): string
{
    if(in_array($type,['charge.refunded','refund.succeeded','tip.refunded'],true))return 'refund';
    if(in_array($type,['charge.dispute.created','dispute.created','tip.dispute_opened'],true))return 'dispute_opened';
    if(in_array($type,['charge.dispute.funds_withdrawn','tip.chargeback'],true))return 'chargeback';
    if($type==='tip.dispute_won')return 'dispute_won';
    if($type==='tip.dispute_lost')return 'dispute_lost';
    $status=strtolower(trim((string)($object['status']??'')));
    if($status==='won')return 'dispute_won';
    if(in_array($status,['lost','warning_closed'],true))return 'dispute_lost';
    throw new InvalidArgumentException('Closed tip dispute event is missing a supported outcome.');
}

function mg_tip_recovery_context(PDO $pdo,string $provider,array $event): array
{
    $type=trim((string)($event['type']??''));
    $eventId=trim((string)($event['id']??''));
    $object=is_array($event['data']['object']??null)?$event['data']['object']:[];
    $metadata=is_array($object['metadata']??null)?$object['metadata']:[];
    $providerPaymentId=trim((string)($object['payment_intent']??$object['provider_payment_id']??$metadata['provider_payment_id']??''));
    $tipPublicId=trim((string)($metadata['tip_id']??$object['tip_id']??''));
    $providerReference=trim((string)($object['id']??$eventId));
    $amount=(int)($object['amount_refunded']??$object['amount']??0);
    $currency=strtoupper(trim((string)($object['currency']??'')));
    if($provider===''||$eventId===''||$type===''||$providerPaymentId===''||$providerReference===''||$amount<1||!preg_match('/^[A-Z]{3}$/',$currency)){
        throw new InvalidArgumentException('Tip recovery event is missing canonical provider fields.');
    }
    $sql='SELECT t.*,pi.provider_key intent_provider,pi.source_type intent_source_type,pi.source_reference intent_source_reference,pi.amount_cents intent_amount_cents,pi.currency intent_currency FROM tips t INNER JOIN payment_intents pi ON pi.id=t.payment_intent_id WHERE t.provider_payment_id=?';
    $params=[$providerPaymentId];
    if($tipPublicId!==''){$sql.=' AND t.public_id=?';$params[]=$tipPublicId;}
    $sql.=' LIMIT 1 FOR UPDATE';
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$tip=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$tip)throw new RuntimeException('Tip recovery event does not match a known tip.');
    if((string)$tip['intent_provider']!==$provider||(string)$tip['provider_key']!==$provider)throw new RuntimeException('Tip recovery provider does not match.');
    if((string)$tip['intent_source_type']!=='tip'||(string)$tip['intent_source_reference']!==(string)$tip['public_id'])throw new RuntimeException('Tip recovery payment source does not match.');
    if((string)$tip['intent_currency']!==$currency||(string)$tip['currency']!==$currency)throw new RuntimeException('Tip recovery currency does not match.');
    if($amount>(int)$tip['amount_cents'])throw new RuntimeException('Tip recovery amount exceeds the original tip.');
    return ['type'=>$type,'event_id'=>$eventId,'object'=>$object,'tip'=>$tip,'provider_reference'=>$providerReference,'amount_cents'=>$amount,'currency'=>$currency,'kind'=>mg_tip_recovery_kind($type,$object)];
}

function mg_tip_recovered_totals(PDO $pdo,int $tipId): array
{
    $stmt=$pdo->prepare("SELECT COALESCE(SUM(amount_cents),0),COALESCE(SUM(fee_cents),0) FROM tip_payment_recoveries WHERE tip_id=? AND recovery_type IN ('refund','dispute_lost','chargeback') AND status='recovered'");
    $stmt->execute([$tipId]);
    $row=$stmt->fetch(PDO::FETCH_NUM)?:[0,0];
    return ['amount_cents'=>(int)$row[0],'fee_cents'=>(int)$row[1]];
}

function mg_tip_recovery_components(PDO $pdo,array $tip,int $amount,string $kind): array
{
    $totals=['amount_cents'=>0,'fee_cents'=>0];
    if(in_array($kind,['refund','dispute_lost','chargeback'],true)){
        $totals=mg_tip_recovered_totals($pdo,(int)$tip['id']);
        if($totals['amount_cents']+$amount>(int)$tip['amount_cents'])throw new RuntimeException('Tip recovery amount exceeds the unrecovered balance.');
    }
    $grossAfter=$totals['amount_cents']+$amount;
    $feeAfter=$grossAfter===(int)$tip['amount_cents']?(int)$tip['fee_cents']:(int)floor(((int)$tip['fee_cents']*$grossAfter)/(int)$tip['amount_cents']);
    $fee=max(0,$feeAfter-$totals['fee_cents']);
    return ['amount_cents'=>$amount,'fee_cents'=>$fee,'net_cents'=>$amount-$fee];
}

function mg_tip_recovery_insert(PDO $pdo,array $context,array $parts,string $idempotencyKey): array
{
    $tip=$context['tip'];
    $existing=$pdo->prepare('SELECT * FROM tip_payment_recoveries WHERE provider_event_id=? OR (tip_id=? AND idempotency_key=?) ORDER BY id LIMIT 1');
    $existing->execute([$context['event_id'],(int)$tip['id'],$idempotencyKey]);
    if($row=$existing->fetch(PDO::FETCH_ASSOC))return $row+['duplicate'=>true];
    $public=mg_public_uuid();
    $pdo->prepare("INSERT INTO tip_payment_recoveries (public_id,tip_id,payment_intent_id,recovery_type,provider_reference,provider_event_id,amount_cents,net_cents,fee_cents,currency,status,idempotency_key,payload_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,'received',?,?,NOW(),NOW())")
        ->execute([$public,(int)$tip['id'],(int)$tip['payment_intent_id'],$context['kind'],$context['provider_reference'],$context['event_id'],$parts['amount_cents'],$parts['net_cents'],$parts['fee_cents'],$context['currency'],$idempotencyKey,json_encode($context['object'],JSON_THROW_ON_ERROR)]);
    return ['id'=>(int)$pdo->lastInsertId(),'public_id'=>$public,'duplicate'=>false];
}

function mg_tip_dispute_hold(PDO $pdo,array $tip,array $parts,string $providerReference): array
{
    $wallet=mg_wallet_resolve($pdo,(string)$tip['recipient_wallet_owner_type'],(int)$tip['recipient_wallet_owner_user_id'],(string)$tip['currency']);
    $available=mg_wallet_account_id($pdo,(int)$wallet['id'],'available',(string)$tip['currency']);
    $held=mg_wallet_account_id($pdo,(int)$wallet['id'],'held',(string)$tip['currency']);
    $key='tip-dispute-hold:'.$providerReference;
    $find=$pdo->prepare('SELECT * FROM payout_holds WHERE idempotency_key=? LIMIT 1');$find->execute([$key]);
    if($row=$find->fetch(PDO::FETCH_ASSOC))return $row;
    $group=mg_ledger_post($pdo,[
        'transaction_type'=>'tip_dispute_hold','source_type'=>'tip_dispute','source_reference'=>$providerReference,
        'idempotency_key'=>$key,'currency'=>$tip['currency'],'description'=>'Hold disputed tip proceeds',
        'metadata'=>['tip_id'=>$tip['public_id'],'provider_dispute_reference'=>$providerReference],
    ],[
        ['ledger_account_id'=>$available,'entry_type'=>'debit','amount_cents'=>$parts['net_cents'],'description'=>'Move disputed tip proceeds from available'],
        ['ledger_account_id'=>$held,'entry_type'=>'credit','amount_cents'=>$parts['net_cents'],'description'=>'Hold disputed tip proceeds'],
    ],null);
    $public=mg_public_uuid();
    $pdo->prepare("INSERT INTO payout_holds (public_id,wallet_id,source_type,source_reference,idempotency_key,amount_cents,currency,reason,status,hold_group_id,created_by_user_id,created_at) VALUES (?,?,'tip_dispute',?,?,?,?,?,'active',?,?,NOW())")
        ->execute([$public,(int)$wallet['id'],$providerReference,$key,$parts['net_cents'],$tip['currency'],'Provider dispute on tip',(int)$group['id'],(int)$tip['recipient_user_id']]);
    $find->execute([$key]);
    return $find->fetch(PDO::FETCH_ASSOC)?:['id'=>(int)$pdo->lastInsertId(),'public_id'=>$public,'hold_group_id'=>(int)$group['id']];
}

function mg_tip_release_dispute_hold(PDO $pdo,array $hold,string $reason): array
{
    if((string)$hold['status']!=='active')return ['duplicate'=>true,'group_id'=>$hold['release_group_id']??null];
    $stmt=$pdo->prepare('SELECT public_id FROM ledger_transaction_groups WHERE id=?');$stmt->execute([(int)$hold['hold_group_id']]);$public=(string)$stmt->fetchColumn();
    if($public==='')throw new RuntimeException('Tip dispute hold ledger group is missing.');
    $group=mg_ledger_reverse($pdo,$public,'tip-dispute-hold-release:'.(string)$hold['source_reference'],$reason,null);
    $pdo->prepare("UPDATE payout_holds SET status='released',release_group_id=?,released_at=NOW() WHERE id=? AND status='active'")->execute([(int)$group['id'],(int)$hold['id']]);
    return ['duplicate'=>false,'group_id'=>(int)$group['id']];
}

function mg_tip_post_recovery(PDO $pdo,array $tip,array $parts,string $kind,string $providerReference): array
{
    $wallet=mg_wallet_resolve($pdo,(string)$tip['recipient_wallet_owner_type'],(int)$tip['recipient_wallet_owner_user_id'],(string)$tip['currency']);
    $available=mg_wallet_account_id($pdo,(int)$wallet['id'],'available',(string)$tip['currency']);
    $feeAccount=mg_ledger_platform_account($pdo,'tip_fee_revenue','revenue','credit',(string)$tip['currency']);
    $processor=mg_ledger_platform_account($pdo,'processor_clearing','asset','debit',(string)$tip['currency']);
    $entries=[
        ['ledger_account_id'=>$available,'entry_type'=>'debit','amount_cents'=>$parts['net_cents'],'description'=>'Recover tip recipient proceeds'],
        ['ledger_account_id'=>$processor,'entry_type'=>'credit','amount_cents'=>$parts['amount_cents'],'description'=>'Return tip funds to provider'],
    ];
    if($parts['fee_cents']>0)$entries[]=['ledger_account_id'=>$feeAccount,'entry_type'=>'debit','amount_cents'=>$parts['fee_cents'],'description'=>'Reverse tip platform fee'];
    return mg_ledger_post($pdo,[
        'transaction_type'=>$kind==='refund'?'tip_refund':'tip_chargeback','source_type'=>'tip_recovery','source_reference'=>$providerReference,
        'idempotency_key'=>'tip-recovery:'.$kind.':'.$providerReference,'currency'=>$tip['currency'],'description'=>'Tip payment recovery',
        'metadata'=>['tip_id'=>$tip['public_id'],'recovery_type'=>$kind,'provider_reference'=>$providerReference],
    ],$entries,null);
}

function mg_tip_find_dispute(PDO $pdo,int $tipId,string $providerReference): ?array
{
    $stmt=$pdo->prepare('SELECT * FROM payment_disputes WHERE tip_id=? AND provider_dispute_reference=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$tipId,$providerReference]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}

function mg_tip_active_dispute(PDO $pdo,int $tipId): ?array
{
    $stmt=$pdo->prepare("SELECT * FROM payment_disputes WHERE tip_id=? AND status IN ('warning_needs_response','needs_response','under_review') ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$tipId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}

function mg_tip_process_recovery_event(PDO $pdo,string $provider,array $event): array
{
    $context=mg_tip_recovery_context($pdo,$provider,$event);$tip=$context['tip'];$kind=$context['kind'];
    if(!in_array((string)$tip['status'],['posted','disputed'],true)){
        if(in_array((string)$tip['status'],['refunded','reversed'],true))return $tip+['duplicate'=>true,'ignored'=>true,'recovery_type'=>$kind];
        throw new RuntimeException('Only posted or disputed tips can enter payment recovery.');
    }
    $idempotency='tip-recovery:'.$kind.':'.$context['provider_reference'];
    $existing=$pdo->prepare('SELECT * FROM tip_payment_recoveries WHERE provider_event_id=? OR (tip_id=? AND idempotency_key=?) ORDER BY id LIMIT 1');
    $existing->execute([$context['event_id'],(int)$tip['id'],$idempotency]);
    if($row=$existing->fetch(PDO::FETCH_ASSOC))return $tip+['duplicate'=>true,'ignored'=>false,'recovery_type'=>$kind,'recovery_id'=>$row['public_id']];
    $parts=mg_tip_recovery_components($pdo,$tip,$context['amount_cents'],$kind);
    $recovery=mg_tip_recovery_insert($pdo,$context,$parts,$idempotency);
    if(!empty($recovery['duplicate']))return $tip+['duplicate'=>true,'ignored'=>false,'recovery_type'=>$kind,'recovery_id'=>$recovery['public_id']];

    $refundId=null;$disputeId=null;$holdId=null;$groupId=null;
    if($kind==='dispute_opened'){
        $dispute=mg_tip_find_dispute($pdo,(int)$tip['id'],$context['provider_reference']);
        if(!$dispute){
            $public=mg_public_uuid();
            $pdo->prepare("INSERT INTO payment_disputes (public_id,order_id,source_type,source_reference,payment_intent_id,tip_id,merchant_user_id,provider_dispute_reference,provider_event_id,amount_cents,currency,reason,status,response_due_at,metadata_json,created_at,updated_at) VALUES (?,NULL,'tip',?,?,?,NULL,?,?,?,?,?,'needs_response',?,?,NOW(),NOW())")
                ->execute([$public,$tip['public_id'],(int)$tip['payment_intent_id'],(int)$tip['id'],$context['provider_reference'],$context['event_id'],$parts['amount_cents'],$context['currency'],(string)($context['object']['reason']??'provider_dispute'),$context['object']['response_due_at']??null,json_encode($context['object'],JSON_THROW_ON_ERROR)]);
            $disputeId=(int)$pdo->lastInsertId();
        }else{$disputeId=(int)$dispute['id'];}
        $hold=mg_tip_dispute_hold($pdo,$tip,$parts,$context['provider_reference']);$holdId=(int)$hold['id'];
        $pdo->prepare("UPDATE payment_disputes SET payout_hold_id=?,provider_event_id=?,status='needs_response',updated_at=NOW() WHERE id=?")->execute([$holdId,$context['event_id'],$disputeId]);
        $pdo->prepare("UPDATE tips SET status='disputed',disputed_at=COALESCE(disputed_at,NOW()),updated_at=NOW() WHERE id=?")->execute([(int)$tip['id']]);
        $pdo->prepare("UPDATE tip_payment_recoveries SET status='held',payment_dispute_id=?,payout_hold_id=?,processed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$disputeId,$holdId,(int)$recovery['id']]);
    }elseif($kind==='dispute_won'){
        $dispute=mg_tip_find_dispute($pdo,(int)$tip['id'],$context['provider_reference'])??mg_tip_active_dispute($pdo,(int)$tip['id']);
        if(!$dispute)throw new RuntimeException('Tip dispute record not found.');
        if(!empty($dispute['payout_hold_id'])){
            $stmt=$pdo->prepare('SELECT * FROM payout_holds WHERE id=? LIMIT 1 FOR UPDATE');$stmt->execute([(int)$dispute['payout_hold_id']]);$hold=$stmt->fetch(PDO::FETCH_ASSOC);
            if($hold)mg_tip_release_dispute_hold($pdo,$hold,'Tip dispute won');
        }
        $disputeId=(int)$dispute['id'];$holdId=(int)($dispute['payout_hold_id']??0);
        $pdo->prepare("UPDATE payment_disputes SET status='won',provider_event_id=?,resolved_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$context['event_id'],$disputeId]);
        $pdo->prepare("UPDATE tips SET status='posted',disputed_at=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$tip['id']]);
        $pdo->prepare("UPDATE tip_payment_recoveries SET status='released',payment_dispute_id=?,payout_hold_id=?,processed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$disputeId,$holdId?:null,(int)$recovery['id']]);
    }else{
        if(in_array($kind,['dispute_lost','chargeback'],true)){
            $dispute=mg_tip_find_dispute($pdo,(int)$tip['id'],$context['provider_reference'])??mg_tip_active_dispute($pdo,(int)$tip['id']);
            if($dispute&&!empty($dispute['payout_hold_id'])){
                $stmt=$pdo->prepare('SELECT * FROM payout_holds WHERE id=? LIMIT 1 FOR UPDATE');$stmt->execute([(int)$dispute['payout_hold_id']]);$hold=$stmt->fetch(PDO::FETCH_ASSOC);
                if($hold)mg_tip_release_dispute_hold($pdo,$hold,'Tip dispute lost; release before recovery');
                $holdId=(int)$dispute['payout_hold_id'];
            }
            if($dispute){$disputeId=(int)$dispute['id'];$pdo->prepare("UPDATE payment_disputes SET status='lost',provider_event_id=?,resolved_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$context['event_id'],$disputeId]);}
        }
        $group=mg_tip_post_recovery($pdo,$tip,$parts,$kind,$context['provider_reference']);$groupId=(int)$group['id'];
        mg_payment_record_intent_transaction($pdo,(int)$tip['payment_intent_id'],$context['provider_reference'],$parts['amount_cents'],$context['currency'],$kind==='refund'?'refund':'chargeback');
        if($kind==='refund'){
            $public=mg_public_uuid();
            $pdo->prepare("INSERT INTO payment_refunds (public_id,order_id,source_type,source_reference,payment_intent_id,tip_id,ledger_group_id,merchant_user_id,provider_refund_reference,provider_event_id,amount_cents,currency,reason,status,idempotency_key,requested_by_user_id,processed_at,created_at,updated_at) VALUES (?,NULL,'tip',?,?,?,?,NULL,?,?,?,?,'other','succeeded',?,NULL,NOW(),NOW(),NOW())")
                ->execute([$public,$tip['public_id'],(int)$tip['payment_intent_id'],(int)$tip['id'],$groupId,$context['provider_reference'],$context['event_id'],$parts['amount_cents'],$context['currency'],$idempotency]);
            $refundId=(int)$pdo->lastInsertId();
        }
        $totals=mg_tip_recovered_totals($pdo,(int)$tip['id']);
        $fullyRecovered=$totals['amount_cents']+$parts['amount_cents']>=(int)$tip['amount_cents'];
        $newStatus=$kind==='refund'&&$fullyRecovered?'refunded':($kind==='refund'?'posted':'disputed');
        $pdo->prepare("UPDATE tips SET status=?,refunded_at=IF(?='refunded',NOW(),refunded_at),disputed_at=IF(?='disputed',COALESCE(disputed_at,NOW()),disputed_at),updated_at=NOW() WHERE id=?")
            ->execute([$newStatus,$newStatus,$newStatus,(int)$tip['id']]);
        $pdo->prepare("UPDATE tip_payment_recoveries SET status='recovered',payment_refund_id=?,payment_dispute_id=?,ledger_group_id=?,payout_hold_id=?,processed_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([$refundId,$disputeId,$groupId,$holdId,(int)$recovery['id']]);
    }
    mg_tip_event($pdo,(int)$tip['id'],$kind,null,'payment_provider',$context['provider_reference'],$idempotency,['recovery_id'=>$recovery['public_id'],'amount_cents'=>$parts['amount_cents'],'net_cents'=>$parts['net_cents'],'fee_cents'=>$parts['fee_cents'],'currency'=>$context['currency']]);
    $stmt=$pdo->prepare('SELECT * FROM tips WHERE id=?');$stmt->execute([(int)$tip['id']]);$fresh=$stmt->fetch(PDO::FETCH_ASSOC)?:$tip;
    return $fresh+['duplicate'=>false,'ignored'=>false,'recovery_type'=>$kind,'recovery_id'=>$recovery['public_id'],'amount_cents'=>$parts['amount_cents'],'net_recovery_cents'=>$parts['net_cents'],'fee_recovery_cents'=>$parts['fee_cents']];
}
