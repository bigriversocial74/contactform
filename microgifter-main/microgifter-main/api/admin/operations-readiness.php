<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/operations/_operations.php';
mg_require_method('GET');
$user=mg_require_permission('operations.readiness.view');
$pdo=mg_db();
$checks=[];
$add=function(string $key,string $status,string $summary,array $details=[])use(&$checks,$pdo):void{
    $checks[]=['key'=>$key,'status'=>$status,'summary'=>$summary,'details'=>$details];
    mg_operations_record_check($pdo,$key,$status,$summary,$details);
};
try{$pdo->query('SELECT 1');$add('database','pass','Database connection is available.');}
catch(Throwable $e){$add('database','fail','Database connection failed.',['message'=>$e->getMessage()]);}
$requiredMigrations=['stage_12_universal_tips','stage_13_subscriptions_monetization','stage_14_posts_feed_social','stage_15_psr_demand_intelligence','stage_16_agent_execution_orchestration','stage_17_multi_agent_swarms','stage_17b_demand_signal_agent_orchestration','stage_18_production_hardening_launch_readiness','stage_18b_demand_orchestration_operations','stage_18c_demand_orchestration_recovery','stage_18c2_demand_orchestration_retention'];
$missing=[];$stmt=$pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration_key=?');
foreach($requiredMigrations as $migration){$stmt->execute([$migration]);if((int)$stmt->fetchColumn()!==1)$missing[]=$migration;}
$add('stage_migrations',$missing===[]?'pass':'fail',$missing===[]?'Required Stage 12-18C migrations are recorded.':'Required stage migrations are missing.',['missing'=>$missing]);
$tables=['operational_incidents','deployment_releases','release_gate_results','retention_policies','operational_check_results','demand_signal_orchestrations','demand_signal_orchestration_events','demand_signal_orchestration_attempts','demand_signal_orchestration_incidents'];
$missing=[];$stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
foreach($tables as $table){$stmt->execute([$table]);if((int)$stmt->fetchColumn()!==1)$missing[]=$table;}
$add('operations_tables',$missing===[]?'pass':'fail',$missing===[]?'Stage 18 orchestration operations tables are present.':'Required operational tables are missing.',['missing'=>$missing]);
$count=(int)$pdo->query("SELECT COUNT(*) FROM operational_incidents WHERE status IN ('open','investigating') AND severity IN ('sev1','sev2')")->fetchColumn();
$add('critical_incidents',$count===0?'pass':'fail',$count===0?'No open SEV1 or SEV2 incidents.':'Critical incidents are open.',['count'=>$count]);
$count=(int)$pdo->query("SELECT COUNT(*) FROM payment_webhook_events WHERE status='failed' AND received_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)")->fetchColumn();
$add('payment_webhooks',$count===0?'pass':'warn',$count===0?'No failed payment webhooks in the last 24 hours.':'Failed payment webhooks require review.',['count'=>$count]);
$count=(int)$pdo->query("SELECT (SELECT COUNT(*) FROM agent_workflow_runs WHERE status IN ('queued','planning','executing') AND updated_at<DATE_SUB(NOW(),INTERVAL 1 HOUR))+(SELECT COUNT(*) FROM agent_swarm_runs WHERE status IN ('queued','planning','running') AND updated_at<DATE_SUB(NOW(),INTERVAL 1 HOUR))")->fetchColumn();
$add('agent_queues',$count===0?'pass':'warn',$count===0?'No stale agent workflow or swarm runs.':'Stale agent runs require review.',['count'=>$count]);
foreach(mg_operations_demand_orchestration_health($pdo) as $check)$add($check['key'],$check['status'],$check['summary'],$check['details']);
$failed=array_values(array_filter($checks,fn(array $check):bool=>$check['status']==='fail'));
$warnings=array_values(array_filter($checks,fn(array $check):bool=>$check['status']==='warn'));
mg_ok(['ready'=>$failed===[],'status'=>$failed!==[]?'fail':($warnings!==[]?'warn':'pass'),'checks'=>$checks,'checked_by_user_id'=>(int)$user['id']]);
