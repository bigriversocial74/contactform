<?php
declare(strict_types=1);

require_once __DIR__ . '/_operations.php';

function mg_retention_allowlist(): array
{
    return [
        'security_logs'=>['created_at'],
        'delivery_events'=>['created_at'],
        'payment_webhook_events'=>['received_at'],
        'agent_execution_events'=>['created_at'],
        'agent_swarm_events'=>['created_at'],
        'demand_signal_orchestration_events'=>['created_at'],
    ];
}

function mg_retention_delete_orchestration_events(PDO $pdo,string $cutoff,int $batch): int
{
    $stmt=$pdo->prepare(
        "SELECT e.id
         FROM demand_signal_orchestration_events e
         INNER JOIN demand_signal_orchestrations o ON o.id=e.orchestration_id
         INNER JOIN (
           SELECT orchestration_id,MAX(id) keep_id
           FROM demand_signal_orchestration_events
           GROUP BY orchestration_id
         ) latest ON latest.orchestration_id=e.orchestration_id
         WHERE e.created_at<? AND o.status='completed' AND e.id<>latest.keep_id
         ORDER BY e.created_at ASC,e.id ASC
         LIMIT ".$batch
    );
    $stmt->execute([$cutoff]);
    $ids=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    if($ids===[])return 0;
    $placeholders=implode(',',array_fill(0,count($ids),'?'));
    $delete=$pdo->prepare('DELETE FROM demand_signal_orchestration_events WHERE id IN ('.$placeholders.')');
    $delete->execute($ids);
    return $delete->rowCount();
}

function mg_retention_run_policy(PDO $pdo,array $policy): array
{
    $allowed=mg_retention_allowlist();
    $table=(string)$policy['table_name'];
    $column=(string)$policy['timestamp_column'];
    if(!isset($allowed[$table])||!in_array($column,$allowed[$table],true)||(string)$policy['action_type']!=='delete'){
        throw new RuntimeException('Retention policy is not allowlisted for deletion.');
    }
    $cutoff=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-'.(int)$policy['retention_days'].' day')->format('Y-m-d H:i:s');
    $runPublic=mg_public_uuid();
    $pdo->prepare("INSERT INTO retention_runs (public_id,policy_id,status,cutoff_at,started_at,created_at) VALUES (?,?,'running',?,NOW(),NOW())")
        ->execute([$runPublic,(int)$policy['id'],$cutoff]);
    $runId=(int)$pdo->lastInsertId();
    $outerTransaction=$pdo->inTransaction();
    try{
        $batch=max(1,min((int)$policy['batch_size'],10000));
        $total=0;$batchNo=0;
        do{
            $batchNo++;
            if($outerTransaction)$pdo->exec('SAVEPOINT retention_batch_'.$batchNo);
            else $pdo->beginTransaction();
            if($table==='demand_signal_orchestration_events'){
                $affected=mg_retention_delete_orchestration_events($pdo,$cutoff,$batch);
            }else{
                $sql="DELETE FROM `{$table}` WHERE `{$column}`<? ORDER BY `{$column}` ASC LIMIT {$batch}";
                $stmt=$pdo->prepare($sql);$stmt->execute([$cutoff]);$affected=$stmt->rowCount();
            }
            $total+=$affected;
            if($outerTransaction)$pdo->exec('RELEASE SAVEPOINT retention_batch_'.$batchNo);
            else $pdo->commit();
        }while($affected===$batch);
        $pdo->prepare("UPDATE retention_runs SET status='completed',scanned_count=?,affected_count=?,completed_at=NOW() WHERE id=?")
            ->execute([$total,$total,$runId]);
        $pdo->prepare('UPDATE retention_policies SET last_executed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int)$policy['id']]);
        return ['run_id'=>$runPublic,'status'=>'completed','affected'=>$total,'cutoff'=>$cutoff];
    }catch(Throwable $error){
        if($outerTransaction){
            try{$pdo->exec('ROLLBACK TO SAVEPOINT retention_batch_'.$batchNo);}catch(Throwable){}
        }elseif($pdo->inTransaction())$pdo->rollBack();
        $pdo->prepare("UPDATE retention_runs SET status='failed',failure_message=?,completed_at=NOW() WHERE id=?")
            ->execute([mb_substr($error->getMessage(),0,1000),$runId]);
        throw $error;
    }
}
