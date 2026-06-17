<?php
declare(strict_types=1);

require_once __DIR__ . '/TipBehaviorFixture.php';

function mg_tip_payment_it_event(string $eventId,string $type,array $tip,array $overrides=[]): array
{
    $object=[
        'id'=>(string)$tip['provider_payment_id'],
        'amount'=>(int)$tip['amount_cents'],
        'amount_received'=>(int)$tip['amount_cents'],
        'currency'=>strtolower((string)$tip['currency']),
        'metadata'=>['tip_id'=>(string)$tip['public_id']],
    ];
    foreach($overrides as $key=>$value)$object[$key]=$value;
    return ['id'=>$eventId,'type'=>$type,'data'=>['object'=>$object]];
}

function mg_tip_payment_it_counts(PDO $pdo,string $tipPublicId): array
{
    return [
        'payment_intents'=>(int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM payment_intents WHERE source_type='tip' AND source_reference=?",[$tipPublicId]),
        'payment_transactions'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM payment_transactions pt INNER JOIN payment_intents pi ON pi.id=pt.payment_intent_id WHERE pi.source_type=\'tip\' AND pi.source_reference=?',[$tipPublicId]),
        'tip_groups'=>(int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM ledger_transaction_groups WHERE transaction_type='tip' AND source_reference=?",[$tipPublicId]),
        'tip_events'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM tip_events te INNER JOIN tips t ON t.id=te.tip_id WHERE t.public_id=?',[$tipPublicId]),
        'alerts'=>mg_tip_it_alert_count($pdo,$tipPublicId,'tip_received'),
        'posted_events'=>mg_tip_it_event_count($pdo,$tipPublicId,'tip.posted'),
        'received_events'=>mg_tip_it_event_count($pdo,$tipPublicId,'tip.received'),
    ];
}

function mg_tip_payment_it_intent(PDO $pdo,int $paymentIntentId): array
{
    $stmt=$pdo->prepare('SELECT * FROM payment_intents WHERE id=? LIMIT 1');
    $stmt->execute([$paymentIntentId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('Payment intent fixture was not found.');
    return $row;
}
