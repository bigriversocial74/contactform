<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/operations/_operations.php';

$allowed=[
    'security_logs'=>['created_at'],
    'delivery_events'=>['created_at'],
    'payment_webhook_events'=>['received_at'],
    'agent_execution_events'=>['created_at'],
    'agent_swarm_events'=>['created_at'],
];
$pdo=mg_db();
$policies=$pdo->query("SELECT * FROM retention_policies WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$summary=['policies'=>0,'completed'=>0,'failed'=>0,'affected'=>0];
foreach($policies as $policy){
    $summary['policies']++;
    $table=(string)$policy['table_name'];$column=(string)$policy['timestamp_column'];
    if(!isset($allowed[$table])||!in_array($column,$allowed[$table],true)||$policy['action_type']!=='delete'){$summary['failed']++;continue;}
    $cutoff=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-'.(int)$policy['retention_days'].' day')->format('Y-m-d H:i:s');
    $runPublic=mg_public_uuid();$pdo->prepare("INSERT INTO retention_runs (public_id,policy_id,status,cutoff_at,started_at,created_at) VALUES (?,?,'running',?,NOW(),NOW())")->execute([$runPublic,(int)$policy['id'],$cutoff]);$runId=(int)$pdo->lastInsertId();
    try{
        $batch=max(1,min((int)$policy['batch_size'],10000));$total=0;
        do{
            $pdo->beginTransaction();
            $sql="DELETE FROM `{$table}` WHERE `{$column}`<? ORDER BY `{$column}` ASC LIMIT {$batch}";
            $stmt=$pdo->prepare($sql);$stmt->execute([$cutoff]);$affected=$stmt->rowCount();$total+=$affected;$pdo->commit();
        }while($affected===$batch);
        $pdo->prepare("UPDATE retention_runs SET status='completed',scanned_count=?,affected_count=?,completed_at=NOW() WHERE id=?")->execute([$total,$total,$runId]);
        $pdo->prepare('UPDATE retention_policies SET last_executed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int)$policy['id']]);
        $summary['completed']++;$summary['affected']+=$total;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$pdo->prepare("UPDATE retention_runs SET status='failed',failure_message=?,completed_at=NOW() WHERE id=?")->execute([mb_substr($e->getMessage(),0,1000),$runId]);$summary['failed']++;}
}
fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
