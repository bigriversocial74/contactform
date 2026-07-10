<?php
declare(strict_types=1);

require_once __DIR__ . '/_fulfillment.php';
require_once dirname(__DIR__) . '/commerce/_order_issuance_summary.php';

function mg_payment_reconcile_paid_order(PDO $pdo,int $orderDbId,?int $actorUserId=null,string $reason='payment_capture'): array
{
    if(!$pdo->inTransaction())throw new LogicException('Paid order issuance reconciliation requires an active transaction.');

    $stmt=$pdo->prepare('SELECT * FROM commerce_orders WHERE id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$orderDbId]);
    $order=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$order)throw new RuntimeException('Commerce order not found for issuance reconciliation.');
    if((string)$order['payment_status']!=='paid'){
        return ['skipped'=>true,'reason'=>'order_not_paid','order_id'=>(string)$order['public_id']];
    }

    $buyerUserId=(int)$order['buyer_user_id'];
    $eventActorUserId=$actorUserId?:$buyerUserId;
    $before=mg_order_issuance_summary($pdo,$order,$buyerUserId);
    $previousStatus=(string)$order['fulfillment_status'];

    $pppm=mg_payment_issue_order_pppm($pdo,$orderDbId,$eventActorUserId);
    $microgifts=mg_payment_issue_order_microgifts($pdo,$orderDbId,$eventActorUserId);

    $stmt->execute([$orderDbId]);
    $order=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$order)throw new RuntimeException('Commerce order disappeared during issuance reconciliation.');
    $after=mg_order_issuance_summary($pdo,$order,$buyerUserId);
    $finalStatus=!empty($after['complete'])?'issued':(((int)($after['issued_units']??0)>0)?'partial':'pending');

    if((string)$order['fulfillment_status']!==$finalStatus){
        $pdo->prepare('UPDATE commerce_orders SET fulfillment_status=?,updated_at=NOW() WHERE id=?')
            ->execute([$finalStatus,$orderDbId]);
    }
    if($previousStatus!==$finalStatus){
        mg_order_history($pdo,$orderDbId,'fulfillment',$previousStatus,$finalStatus,'system',$eventActorUserId,'issuance_reconciled',[
            'reason'=>$reason,
            'expected_units'=>(int)$after['expected_units'],
            'issued_units'=>(int)$after['issued_units'],
        ]);
    }

    $changed=$before!=$after||$previousStatus!==$finalStatus;
    if($changed){
        mg_order_event($pdo,$orderDbId,'fulfillment.reconciled',$eventActorUserId,[
            'reason'=>$reason,
            'before'=>$before,
            'after'=>$after,
            'pppm_result'=>$pppm,
            'microgift_result'=>[
                'issued_count'=>(int)($microgifts['issued_count']??0),
                'duplicate_count'=>(int)($microgifts['duplicate_count']??0),
                'linked_count'=>(int)($microgifts['linked_count']??0),
            ],
        ]);
    }

    if(empty($before['complete'])&&!empty($after['complete'])){
        $existing=$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND type='microgift_delivery_ready' AND action_url=?");
        $actionUrl='/checkout-success.php?order='.rawurlencode((string)$order['public_id']);
        $existing->execute([$buyerUserId,$actionUrl]);
        if((int)$existing->fetchColumn()===0){
            $pdo->prepare('INSERT INTO notifications (public_id,user_id,type,title,body,action_url,created_at) VALUES (?,?,?,?,?,?,NOW())')
                ->execute([mg_public_uuid(),$buyerUserId,'microgift_delivery_ready','Your Microgifts are ready','Your purchased Microgifts are available in your Action Center.',$actionUrl]);
        }
    }

    return [
        'order_id'=>(string)$order['public_id'],
        'fulfillment_status'=>$finalStatus,
        'complete'=>(bool)$after['complete'],
        'before'=>$before,
        'issuance'=>$after,
        'pppm'=>$pppm,
        'microgifts'=>$microgifts,
        'changed'=>$changed,
    ];
}
