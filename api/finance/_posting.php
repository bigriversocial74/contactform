<?php
declare(strict_types=1);

require_once __DIR__ . '/_money.php';

function mg_stage7_post_paid_order(PDO $pdo,array $order,?int $actorUserId=null): array
{
    $wallet=mg_wallet_resolve($pdo,'merchant',(int)$order['merchant_user_id'],(string)$order['currency']);
    $processor=mg_ledger_platform_account($pdo,'processor_clearing','asset','debit',(string)$order['currency']);
    $available=mg_wallet_account_id($pdo,(int)$wallet['id'],'available',(string)$wallet['currency']);
    $platformRevenue=mg_ledger_platform_account($pdo,'platform_fee_revenue','revenue','credit',(string)$order['currency']);
    $total=(int)$order['total_cents'];
    $fee=max(0,min($total,(int)($order['platform_fee_cents']??0)));
    $merchantNet=$total-$fee;
    $entries=[
        ['ledger_account_id'=>$processor,'entry_type'=>'debit','amount_cents'=>$total,'description'=>'Processor clearing receivable'],
    ];
    if($merchantNet>0)$entries[]=['ledger_account_id'=>$available,'entry_type'=>'credit','amount_cents'=>$merchantNet,'description'=>'Merchant net proceeds'];
    if($fee>0)$entries[]=['ledger_account_id'=>$platformRevenue,'entry_type'=>'credit','amount_cents'=>$fee,'description'=>'Microgifter platform fee'];

    return mg_ledger_post($pdo,[
        'transaction_type'=>'order_payment',
        'source_type'=>'commerce_order',
        'source_reference'=>(string)$order['public_id'],
        'idempotency_key'=>'order:paid:'.(string)$order['public_id'],
        'currency'=>(string)$order['currency'],
        'description'=>'Captured customer order funds',
        'metadata'=>[
            'order_id'=>$order['public_id'],
            'gross_cents'=>$total,
            'platform_fee_cents'=>$fee,
            'merchant_net_cents'=>$merchantNet,
        ],
    ],$entries,$actorUserId);
}

function mg_stage7_post_refund(PDO $pdo,array $order,string $refundPublicId,int $amountCents,?int $actorUserId=null): array
{
    $wallet=mg_wallet_resolve($pdo,'merchant',(int)$order['merchant_user_id'],(string)$order['currency']);
    $processor=mg_ledger_platform_account($pdo,'processor_clearing','asset','debit',(string)$order['currency']);
    $available=mg_wallet_account_id($pdo,(int)$wallet['id'],'available',(string)$wallet['currency']);
    $platformRevenue=mg_ledger_platform_account($pdo,'platform_fee_revenue','revenue','credit',(string)$order['currency']);
    $total=max(1,(int)$order['total_cents']);
    $originalFee=max(0,min($total,(int)($order['platform_fee_cents']??0)));
    $feeReversal=min($originalFee,(int)round($amountCents*($originalFee/$total)));
    $merchantReversal=$amountCents-$feeReversal;
    $entries=[];
    if($merchantReversal>0)$entries[]=['ledger_account_id'=>$available,'entry_type'=>'debit','amount_cents'=>$merchantReversal,'description'=>'Reverse merchant proceeds'];
    if($feeReversal>0)$entries[]=['ledger_account_id'=>$platformRevenue,'entry_type'=>'debit','amount_cents'=>$feeReversal,'description'=>'Reverse platform fee'];
    $entries[]=['ledger_account_id'=>$processor,'entry_type'=>'credit','amount_cents'=>$amountCents,'description'=>'Reduce processor clearing receivable'];

    return mg_ledger_post($pdo,[
        'transaction_type'=>'refund',
        'source_type'=>'payment_refund',
        'source_reference'=>$refundPublicId,
        'idempotency_key'=>'refund:'.(string)$refundPublicId,
        'currency'=>(string)$order['currency'],
        'description'=>'Refund customer order funds',
        'metadata'=>[
            'order_id'=>$order['public_id'],
            'refund_id'=>$refundPublicId,
            'platform_fee_reversal_cents'=>$feeReversal,
            'merchant_reversal_cents'=>$merchantReversal,
        ],
    ],$entries,$actorUserId);
}
