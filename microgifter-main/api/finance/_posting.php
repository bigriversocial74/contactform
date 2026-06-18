<?php
declare(strict_types=1);
require_once __DIR__ . '/_money.php';

function mg_stage7_post_paid_order(PDO $pdo, array $order, ?int $actorUserId = null): array
{
    $wallet=mg_wallet_resolve($pdo,'merchant',(int)$order['merchant_user_id'],(string)$order['currency']);
    $processor=mg_ledger_platform_account($pdo,'processor_clearing','asset','debit',(string)$order['currency']);
    $available=mg_wallet_account_id($pdo,(int)$wallet['id'],'available',(string)$wallet['currency']);
    return mg_ledger_post($pdo,[
        'transaction_type'=>'order_payment',
        'source_type'=>'commerce_order',
        'source_reference'=>(string)$order['public_id'],
        'idempotency_key'=>'order:paid:'.(string)$order['public_id'],
        'currency'=>(string)$order['currency'],
        'description'=>'Captured customer order funds',
        'metadata'=>['order_id'=>$order['public_id']],
    ],[
        ['ledger_account_id'=>$processor,'entry_type'=>'debit','amount_cents'=>(int)$order['total_cents'],'description'=>'Processor clearing receivable'],
        ['ledger_account_id'=>$available,'entry_type'=>'credit','amount_cents'=>(int)$order['total_cents'],'description'=>'Merchant available balance'],
    ],$actorUserId);
}

function mg_stage7_post_refund(PDO $pdo, array $order, string $refundPublicId, int $amountCents, ?int $actorUserId = null): array
{
    $wallet=mg_wallet_resolve($pdo,'merchant',(int)$order['merchant_user_id'],(string)$order['currency']);
    $processor=mg_ledger_platform_account($pdo,'processor_clearing','asset','debit',(string)$order['currency']);
    $available=mg_wallet_account_id($pdo,(int)$wallet['id'],'available',(string)$wallet['currency']);
    return mg_ledger_post($pdo,[
        'transaction_type'=>'refund',
        'source_type'=>'payment_refund',
        'source_reference'=>$refundPublicId,
        'idempotency_key'=>'refund:'.(string)$refundPublicId,
        'currency'=>(string)$order['currency'],
        'description'=>'Refund customer order funds',
        'metadata'=>['order_id'=>$order['public_id'],'refund_id'=>$refundPublicId],
    ],[
        ['ledger_account_id'=>$available,'entry_type'=>'debit','amount_cents'=>$amountCents,'description'=>'Reduce merchant available balance'],
        ['ledger_account_id'=>$processor,'entry_type'=>'credit','amount_cents'=>$amountCents,'description'=>'Reduce processor clearing receivable'],
    ],$actorUserId);
}
