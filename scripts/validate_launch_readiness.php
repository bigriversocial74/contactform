<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
$_SERVER['REQUEST_METHOD']='CLI';
require_once dirname(__DIR__) . '/api/operations/_public_id.php';
require_once dirname(__DIR__) . '/api/operations/_operations.php';

$pdo=mg_db();
$checks=[];
$record=function(string $key,string $status,string $summary,array $details=[])use(&$checks,$pdo):void{
    if(!in_array($status,['pass','warn','fail'],true))throw new InvalidArgumentException('Invalid readiness status.');
    $checks[]=['key'=>$key,'status'=>$status,'summary'=>$summary,'details'=>$details];
    mg_operations_record_check($pdo,$key,$status,$summary,$details);
};

$record('php_runtime',version_compare(PHP_VERSION,'8.1.0','>=')?'pass':'fail','Supported PHP runtime is active.',['version'=>PHP_VERSION]);
$record('pdo_mysql',in_array('mysql',PDO::getAvailableDrivers(),true)?'pass':'fail','PDO MySQL driver is available.');
try{$pdo->query('SELECT 1');$record('database','pass','Database connectivity is available.');}catch(Throwable $e){$record('database','fail','Database connectivity failed.',['message'=>$e->getMessage()]);}

$requiredMigrations=[
    'stage_12_universal_tips','stage_13_subscriptions_monetization','stage_14_posts_feed_social',
    'stage_15_psr_demand_intelligence','stage_16_agent_execution_orchestration','stage_17_multi_agent_swarms',
    'stage_17b_demand_signal_agent_orchestration','stage_18_production_hardening_launch_readiness',
    'stage_18b_demand_orchestration_operations','stage_18c_demand_orchestration_recovery',
    'stage_18c2_demand_orchestration_retention',
];
$missing=[];$stmt=$pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration_key=?');
foreach($requiredMigrations as $migration){$stmt->execute([$migration]);if((int)$stmt->fetchColumn()!==1)$missing[]=$migration;}
$record('stage_migrations',$missing===[]?'pass':'fail','Required Stage 12-18C migrations are recorded.',['missing'=>$missing]);

$tables=['operational_incidents','deployment_releases','release_gate_results','retention_policies','operational_check_results','demand_signal_orchestrations','demand_signal_orchestration_events','demand_signal_orchestration_attempts','demand_signal_orchestration_incidents'];
$missing=[];$stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
foreach($tables as $table){$stmt->execute([$table]);if((int)$stmt->fetchColumn()!==1)$missing[]=$table;}
$record('operations_tables',$missing===[]?'pass':'fail','Stage 18 orchestration recovery tables are present.',['missing'=>$missing]);

$critical=(int)$pdo->query("SELECT COUNT(*) FROM operational_incidents WHERE severity IN ('sev1','sev2') AND status NOT IN ('resolved','closed')")->fetchColumn();
$record('critical_incidents',$critical===0?'pass':'fail','No unresolved SEV1 or SEV2 incidents.',['count'=>$critical]);
$failedWebhooks=(int)$pdo->query("SELECT COUNT(*) FROM payment_webhook_events WHERE status='failed' AND received_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)")->fetchColumn();
$record('payment_webhooks',$failedWebhooks===0?'pass':'warn',$failedWebhooks===0?'No failed payment webhooks in the last 24 hours.':'Failed payment webhooks require review.',['count'=>$failedWebhooks]);
foreach(mg_operations_demand_orchestration_health($pdo) as $check)$record($check['key'],$check['status'],$check['summary'],$check['details']);

$failed=array_values(array_filter($checks,fn(array $check):bool=>$check['status']==='fail'));
$warnings=array_values(array_filter($checks,fn(array $check):bool=>$check['status']==='warn'));
$result=['ready'=>$failed===[],'status'=>$failed!==[]?'fail':($warnings!==[]?'warn':'pass'),'checks'=>$checks];
fwrite(STDOUT,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
if($failed!==[])fwrite(STDERR,'FAILED: '.implode(', ',array_column($failed,'key')).PHP_EOL);
exit($failed===[]?0:1);
