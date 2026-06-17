<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/finance/_money.php';

function mg_behavior_assert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}

function mg_behavior_expect_throw(callable $callback,string $contains): void
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

$pdo=mg_db();
$runId='behavior_'.bin2hex(random_bytes(8));
$summary=[
    'suite'=>'stage7_money_behavior',
    'run_id'=>$runId,
    'balanced_post'=>false,
    'exact_replay'=>false,
    'conflicting_replay_rejected'=>false,
    'unbalanced_post_rejected'=>false,
    'reversal_integrity'=>false,
    'rollback_clean'=>false,
];

$pdo->beginTransaction();
try{
    $currency='USD';
    $debitAccount=mg_ledger_platform_account($pdo,'behavior_debit_'.$runId,'asset','debit',$currency);
    $creditAccount=mg_ledger_platform_account($pdo,'behavior_credit_'.$runId,'liability','credit',$currency);
    $idempotency='behavior:ledger:'.$runId;
    $entries=[
        ['ledger_account_id'=>$debitAccount,'entry_type'=>'debit','amount_cents'=>1250,'description'=>'Behavioral debit'],
        ['ledger_account_id'=>$creditAccount,'entry_type'=>'credit','amount_cents'=>1250,'description'=>'Behavioral credit'],
    ];
    $groupRequest=[
        'transaction_type'=>'behavior_test',
        'source_type'=>'integration_validation',
        'source_reference'=>$runId,
        'idempotency_key'=>$idempotency,
        'currency'=>$currency,
        'description'=>'Production integration behavioral validation',
        'metadata'=>['run_id'=>$runId],
    ];

    $group=mg_ledger_post($pdo,$groupRequest,$entries,null);
    $groupId=(int)($group['id']??0);
    mg_behavior_assert($groupId>0,'Ledger group was not created.');

    $stmt=$pdo->prepare('SELECT entry_type,SUM(amount_cents) amount_cents FROM ledger_entries WHERE transaction_group_id=? GROUP BY entry_type');
    $stmt->execute([$groupId]);
    $sides=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$sides[(string)$row['entry_type']]=(int)$row['amount_cents'];
    mg_behavior_assert(($sides['debit']??0)===1250,'Debit total is incorrect.');
    mg_behavior_assert(($sides['credit']??0)===1250,'Credit total is incorrect.');
    $summary['balanced_post']=true;

    $replay=mg_ledger_post($pdo,$groupRequest,$entries,null);
    mg_behavior_assert((int)$replay['id']===$groupId,'Exact replay created a different ledger group.');
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM ledger_entries WHERE transaction_group_id=?');
    $stmt->execute([$groupId]);
    mg_behavior_assert((int)$stmt->fetchColumn()===2,'Exact replay duplicated ledger entries.');
    $summary['exact_replay']=true;

    $conflictingEntries=$entries;
    $conflictingEntries[0]['amount_cents']=1300;
    $conflictingEntries[1]['amount_cents']=1300;
    mg_behavior_expect_throw(
        static fn()=>mg_ledger_post($pdo,$groupRequest,$conflictingEntries,null),
        'already bound to different ledger entries'
    );
    $summary['conflicting_replay_rejected']=true;

    mg_behavior_expect_throw(
        static fn()=>mg_ledger_post($pdo,[
            'transaction_type'=>'behavior_test',
            'source_type'=>'integration_validation',
            'source_reference'=>$runId.':unbalanced',
            'idempotency_key'=>$idempotency.':unbalanced',
            'currency'=>$currency,
        ],[
            ['ledger_account_id'=>$debitAccount,'entry_type'=>'debit','amount_cents'=>1000],
            ['ledger_account_id'=>$creditAccount,'entry_type'=>'credit','amount_cents'=>999],
        ],null),
        'not balanced'
    );
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM ledger_transaction_groups WHERE idempotency_key=?');
    $stmt->execute([$idempotency.':unbalanced']);
    mg_behavior_assert((int)$stmt->fetchColumn()===0,'Unbalanced request persisted a ledger group.');
    $summary['unbalanced_post_rejected']=true;

    $reversal=mg_ledger_reverse($pdo,(string)$group['public_id'],$idempotency.':reversal','Behavioral reversal',null);
    $reversalId=(int)($reversal['id']??0);
    mg_behavior_assert($reversalId>0&&$reversalId!==$groupId,'Reversal group was not created.');
    $stmt=$pdo->prepare('SELECT status FROM ledger_transaction_groups WHERE id=?');
    $stmt->execute([$groupId]);
    mg_behavior_assert((string)$stmt->fetchColumn()==='reversed','Original ledger group was not marked reversed.');
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM ledger_reversal_links WHERE original_group_id=? AND reversal_group_id=?');
    $stmt->execute([$groupId,$reversalId]);
    mg_behavior_assert((int)$stmt->fetchColumn()===1,'Reversal link was not persisted.');
    $stmt=$pdo->prepare('SELECT entry_type,SUM(amount_cents) amount_cents FROM ledger_entries WHERE transaction_group_id=? GROUP BY entry_type');
    $stmt->execute([$reversalId]);
    $reversalSides=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$reversalSides[(string)$row['entry_type']]=(int)$row['amount_cents'];
    mg_behavior_assert(($reversalSides['debit']??0)===1250&&($reversalSides['credit']??0)===1250,'Reversal is not balanced.');
    $reversalReplay=mg_ledger_reverse($pdo,(string)$group['public_id'],$idempotency.':reversal','Behavioral reversal',null);
    mg_behavior_assert((int)$reversalReplay['id']===$reversalId,'Reversal replay created a second reversal.');
    $summary['reversal_integrity']=true;

    $pdo->rollBack();

    $stmt=$pdo->prepare('SELECT COUNT(*) FROM ledger_transaction_groups WHERE idempotency_key LIKE ?');
    $stmt->execute(['behavior:ledger:'.$runId.'%']);
    mg_behavior_assert((int)$stmt->fetchColumn()===0,'Behavioral ledger fixtures were not rolled back.');
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM ledger_accounts WHERE account_code IN (?,?)');
    $stmt->execute(['behavior_debit_'.$runId,'behavior_credit_'.$runId]);
    mg_behavior_assert((int)$stmt->fetchColumn()===0,'Behavioral account fixtures were not rolled back.');
    $summary['rollback_clean']=true;

    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    throw $error;
}
