<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once dirname(__DIR__,2) . '/includes/admin-agent-phase3-remediation.php';

$actor=mg_require_api_user();
$actorId=(int)$actor['id'];
$pdo=mg_db();
$allowed=mg_admin_account_actor_has($actor,'admin.admin_agent.view')
    ||mg_admin_account_actor_has($actor,'admin.operations_command.view')
    ||mg_admin_account_actor_has($actor,'admin.health.view')
    ||mg_admin_account_actor_has($actor,'admin.settings.manage')
    ||mg_admin_account_actor_has($actor,'admin.users.manage');
if(!$allowed){
    mg_security_log('warning','admin_agent.phase3_stream_denied','Main Admin Agent Phase 3 stream permission denied.',[],$actorId);
    http_response_code(403); exit;
}

mg_rate_limit('admin.agent.phase3.stream','user:'.$actorId,30,60);
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');
header('Vary: Cookie, Authorization');
if(function_exists('session_write_close')) @session_write_close();
@set_time_limit(20); ignore_user_abort(true);

function mg_admin_agent_phase3_sse(string $event,array $payload,?int $id=null): void
{
    if($id!==null) echo 'id: '.$id."\n";
    echo 'event: '.$event."\n";
    echo 'data: '.json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\n";
    @ob_flush(); flush();
}

$after=max(0,(int)($_GET['after']??0));
$domain=preg_replace('/[^a-z0-9_]/','',strtolower((string)($_GET['domain']??'')));
echo "retry: 5000\n\n";

if(!mg_admin_agent_schema_ready($pdo)){
    mg_admin_agent_phase3_sse('schema',['phase1_ready'=>false,'migration'=>'database/20260718_main_admin_agent_phase1.sql']); exit;
}

$phase2=mg_admin_agent_phase2_schema_state($pdo); $phase3=mg_admin_agent_phase3_schema_state($pdo);
$snapshot=[
    'phase2_ready'=>$phase2['ready'],'phase2_schema'=>$phase2,'phase3_ready'=>$phase3['ready'],'phase3_schema'=>$phase3,
    'health'=>mg_admin_agent_health($pdo),'last_scan'=>mg_admin_agent_last_scan($pdo),
    'findings'=>mg_admin_agent_findings($pdo,['status'=>'active','domain'=>$domain,'limit'=>30]),
    'generated_at'=>gmdate('Y-m-d H:i:s'),
];
if($phase2['ready']){
    $snapshot['anomalies']=mg_admin_agent_phase2_anomalies($pdo,'active',30);
    $snapshot['correlations']=mg_admin_agent_phase2_correlations($pdo,'active',30);
    $snapshot['deployments']=mg_admin_agent_phase2_deployments($pdo,10);
    $snapshot['escalations']=mg_admin_agent_phase2_escalations($pdo,30);
    $snapshot['executive_summaries']=mg_admin_agent_phase2_summaries($pdo,5);
    $snapshot['remediation']=mg_admin_agent_phase2_remediation_state($pdo);
}
if($phase3['ready']){
    $snapshot['services']=mg_admin_agent_phase3_services($pdo);
    $snapshot['slos']=mg_admin_agent_phase3_slos($pdo,40);
    $snapshot['incident_workspaces']=mg_admin_agent_phase3_incidents($pdo,30);
    $snapshot['release_gates']=mg_admin_agent_phase3_release_gates($pdo,10);
    $snapshot['brief_subscriptions']=mg_admin_agent_phase3_brief_subscriptions($pdo,$actorId);
}
mg_admin_agent_phase3_sse('snapshot',$snapshot);

for($iteration=0;$iteration<8;$iteration++){
    if(connection_aborted()) break;
    $events=mg_admin_agent_events_runtime($pdo,$after,100,$domain);
    if($events!==[]){
        $after=(int)end($events)['cursor'];
        mg_admin_agent_phase3_sse('events',['events'=>$events,'cursor'=>$after,'generated_at'=>gmdate('Y-m-d H:i:s')],$after);
    }else mg_admin_agent_phase3_sse('heartbeat',['cursor'=>$after,'generated_at'=>gmdate('Y-m-d H:i:s')]);
    if($iteration<7) sleep(2);
}
