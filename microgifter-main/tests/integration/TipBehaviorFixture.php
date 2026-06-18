<?php
declare(strict_types=1);

require_once __DIR__ . '/MicrogiftBehaviorFixture.php';

function mg_tip_it_expect_throw(callable $callback,string $contains): void
{
    try{
        $callback();
    }catch(Throwable $error){
        if($contains!==''&&!str_contains($error->getMessage(),$contains)){
            throw new RuntimeException('Unexpected exception: '.$error->getMessage(),0,$error);
        }
        return;
    }
    throw new RuntimeException('Expected exception was not thrown: '.$contains);
}

function mg_tip_it_wallet(PDO $pdo,string $ownerType,int $ownerUserId,string $currency='USD'): array
{
    $wallet=mg_wallet_resolve($pdo,$ownerType,$ownerUserId,$currency);
    return [
        'id'=>(int)$wallet['id'],
        'public_id'=>(string)$wallet['public_id'],
        'available_account_id'=>mg_wallet_account_id($pdo,(int)$wallet['id'],'available',$currency),
    ];
}

function mg_tip_it_fund_sender(PDO $pdo,int $senderUserId,string $runId,int $amountCents): array
{
    $wallet=mg_tip_it_wallet($pdo,'user',$senderUserId,'USD');
    $fundingAccount=mg_ledger_platform_account($pdo,'tip_behavior_funding_'.$runId,'asset','debit','USD');
    $group=mg_ledger_post($pdo,[
        'transaction_type'=>'tip_behavior_funding',
        'source_type'=>'integration_validation',
        'source_reference'=>$runId,
        'idempotency_key'=>'tip-behavior:funding:'.$runId,
        'currency'=>'USD',
        'description'=>'Wallet tip behavior fixture funding',
        'metadata'=>['run_id'=>$runId,'sender_user_id'=>$senderUserId],
    ],[
        ['ledger_account_id'=>$fundingAccount,'entry_type'=>'debit','amount_cents'=>$amountCents,'description'=>'Behavior fixture asset'],
        ['ledger_account_id'=>$wallet['available_account_id'],'entry_type'=>'credit','amount_cents'=>$amountCents,'description'=>'Behavior fixture wallet funding'],
    ],$senderUserId);
    return $wallet+['funding_group_id'=>(int)$group['id'],'funded_cents'=>$amountCents];
}

function mg_tip_it_account_balance(PDO $pdo,int $accountId): int
{
    $stmt=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN e.entry_type=a.normal_side THEN e.amount_cents ELSE -e.amount_cents END),0) FROM ledger_accounts a LEFT JOIN ledger_entries e ON e.ledger_account_id=a.id WHERE a.id=? GROUP BY a.id");
    $stmt->execute([$accountId]);
    $value=$stmt->fetchColumn();
    return $value===false?0:(int)$value;
}

function mg_tip_it_merchant_workspace(PDO $pdo,int $merchantUserId,string $runId): array
{
    $now=gmdate('Y-m-d H:i:s');
    $publicId=mg_public_uuid();
    $id=mg_it_insert($pdo,'merchant_workspaces',[
        'public_id'=>$publicId,
        'merchant_user_id'=>$merchantUserId,
        'display_name'=>'Tip Behavior Merchant '.$runId,
        'default_currency'=>'USD',
        'timezone'=>'UTC',
        'status'=>'active',
        'eligibility_status'=>'eligible',
        'onboarding_percent'=>100,
        'created_at'=>$now,
        'updated_at'=>$now,
    ]);
    return ['id'=>$id,'public_id'=>$publicId,'merchant_user_id'=>$merchantUserId];
}

function mg_tip_it_alert_count(PDO $pdo,string $tipPublicId,string $alertType): int
{
    return (int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM operational_alerts WHERE alert_type=? AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.tip_id'))=?",[$alertType,$tipPublicId]);
}

function mg_tip_it_event_count(PDO $pdo,string $tipPublicId,string $eventType): int
{
    return (int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM events WHERE event_type=? AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.tip_id'))=?",[$eventType,$tipPublicId]);
}

function mg_tip_it_ledger_entry_amount(PDO $pdo,int $groupId,int $accountId,string $entryType): int
{
    return (int)mg_it_scalar($pdo,'SELECT COALESCE(SUM(amount_cents),0) FROM ledger_entries WHERE transaction_group_id=? AND ledger_account_id=? AND entry_type=?',[$groupId,$accountId,$entryType]);
}

function mg_tip_it_group_sides(PDO $pdo,int $groupId): array
{
    $stmt=$pdo->prepare('SELECT entry_type,COALESCE(SUM(amount_cents),0) amount_cents FROM ledger_entries WHERE transaction_group_id=? GROUP BY entry_type');
    $stmt->execute([$groupId]);
    $sides=['debit'=>0,'credit'=>0];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$sides[(string)$row['entry_type']]=(int)$row['amount_cents'];
    return $sides;
}

function mg_tip_it_state_counts(PDO $pdo): array
{
    return [
        'tips'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM tips'),
        'tip_groups'=>(int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM ledger_transaction_groups WHERE transaction_type='tip'"),
        'tip_entries'=>(int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM ledger_entries e INNER JOIN ledger_transaction_groups g ON g.id=e.transaction_group_id WHERE g.transaction_type='tip'"),
        'tip_events'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM tip_events'),
        'alerts'=>(int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM operational_alerts WHERE alert_type IN ('tip_received','tip_reversed')"),
        'events'=>(int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM events WHERE event_type LIKE 'tip.%'"),
    ];
}
