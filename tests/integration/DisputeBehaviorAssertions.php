<?php
declare(strict_types=1);

function mg_dispute_behavior_assert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}

function mg_dispute_behavior_scalar(PDO $pdo,string $sql,array $params=[]): mixed
{
    $stmt=$pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function mg_dispute_behavior_balanced(PDO $pdo,int $groupId): bool
{
    $stmt=$pdo->prepare('SELECT entry_type,SUM(amount_cents) total FROM ledger_entries WHERE transaction_group_id=? GROUP BY entry_type');
    $stmt->execute([$groupId]);
    $sides=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$sides[(string)$row['entry_type']]=(int)$row['total'];
    return ($sides['debit']??0)>0&&($sides['debit']??0)===($sides['credit']??-1);
}

function mg_dispute_behavior_process(PDO $pdo,array $event,?callable $hook=null): array
{
    $payload=json_encode($event,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    return mg_dispute_process_webhook($pdo,'sandbox',$event,$payload,$hook);
}
