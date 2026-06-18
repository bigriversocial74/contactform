<?php
declare(strict_types=1);

require_once __DIR__ . '/_tips.php';

function mg_tip_normalize_payment_event(array $event): array
{
    $type=trim((string)($event['type']??''));
    if(!str_starts_with($type,'charge.'))return $event;
    if(!is_array($event['data']['object']??null))return $event;
    $object=$event['data']['object'];
    $paymentIntent=trim((string)($object['payment_intent']??''));
    if($paymentIntent==='')return $event;
    $object['provider_transaction_id']=$object['id']??null;
    $object['id']=$paymentIntent;
    $event['data']['object']=$object;
    return $event;
}

function mg_tip_process_payment_event_result(PDO $pdo,string $provider,array $event): array
{
    $event=mg_tip_normalize_payment_event($event);
    $result=mg_tip_process_payment_event($pdo,$provider,$event);
    $tipId=(int)($result['id']??0);
    if($tipId<1)return $result;

    $stmt=$pdo->prepare('SELECT * FROM tips WHERE id=? LIMIT 1');
    $stmt->execute([$tipId]);
    $fresh=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$fresh)return $result;

    $duplicate=(bool)($result['duplicate']??false);
    $ignored=(bool)($result['ignored']??false);
    return array_merge($result,$fresh,[
        'duplicate'=>$duplicate,
        'ignored'=>$ignored,
    ]);
}
