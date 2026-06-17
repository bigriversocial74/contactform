<?php
declare(strict_types=1);

require_once __DIR__.'/TipPaymentBehaviorFixture.php';

function mg_tip_recovery_it_event(string $eventId,string $type,array $tip,string $providerReference,int $amount,array $overrides=[]): array
{
    $object=[
        'id'=>$providerReference,
        'payment_intent'=>(string)$tip['provider_payment_id'],
        'amount'=>$amount,
        'currency'=>strtolower((string)$tip['currency']),
        'metadata'=>['tip_id'=>(string)$tip['public_id']],
    ];
    foreach($overrides as $key=>$value)$object[$key]=$value;
    return ['id'=>$eventId,'type'=>$type,'data'=>['object'=>$object]];
}

function mg_tip_recovery_it_counts(PDO $pdo,string $tipPublicId): array
{
    return [
        'recoveries'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM tip_payment_recoveries r INNER JOIN tips t ON t.id=r.tip_id WHERE t.public_id=?',[$tipPublicId]),
        'refunds'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM payment_refunds r INNER JOIN tips t ON t.id=r.tip_id WHERE t.public_id=?',[$tipPublicId]),
        'disputes'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM payment_disputes d INNER JOIN tips t ON t.id=d.tip_id WHERE t.public_id=?',[$tipPublicId]),
        'holds'=>(int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM payout_holds h WHERE h.source_type='tip_dispute' AND h.source_reference IN (SELECT provider_dispute_reference FROM payment_disputes d INNER JOIN tips t ON t.id=d.tip_id WHERE t.public_id=?)",[$tipPublicId]),
        'transactions'=>(int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM payment_transactions pt INNER JOIN payment_intents pi ON pi.id=pt.payment_intent_id WHERE pi.source_type='tip' AND pi.source_reference=? AND pt.transaction_type IN ('refund','chargeback')",[$tipPublicId]),
        'recovery_groups'=>(int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM ledger_transaction_groups WHERE source_type='tip_recovery' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.tip_id'))=?",[$tipPublicId]),
    ];
}

function mg_tip_recovery_it_status(PDO $pdo,int $tipId): string
{
    return (string)mg_it_scalar($pdo,'SELECT status FROM tips WHERE id=?',[$tipId]);
}
