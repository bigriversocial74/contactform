<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once dirname(__DIR__,2) . '/includes/admin-agent-phase4-remediation.php';

$actor=mg_require_api_user();
$actorId=(int)$actor['id'];
$pdo=mg_db();
$allowed=mg_admin_account_actor_has($actor,'admin.admin_agent.view')
    ||mg_admin_account_actor_has($actor,'admin.operations_command.view')
    ||mg_admin_account_actor_has($actor,'admin.health.view')
    ||mg_admin_account_actor_has($actor,'admin.settings.manage')
    ||mg_admin_account_actor_has($actor,'admin.users.manage');
if(!$allowed){
    mg_security_log('warning','admin_agent.phase4_stream_denied','Main Admin Agent Phase 4 stream permission denied.',[],$actorId);
    http_response_code(403);
    exit;
}

mg_rate_limit('admin.agent.phase4.stream','user:'.$actorId,30,60);
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');
header('Vary: Cookie, Authorization');
if(function_exists('session_write_close')) @session_write_close();
@set_time_limit(20);
ignore_user_abort(true);

function mg_admin_agent_phase4_sse(string $event,array $payload,?int $id=null): void
{
    if($id!==null) echo 'id: '.$id."\n";
    echo 'event: '.$event."\n";
    echo 'data: '.json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n\n";
    @ob_flush();
    flush();
}

$after=max(0,(int)($_GET['after']??0));
$domain=preg_replace('/[^a-z0-9_]/','',strtolower((string)($_GET['domain']??'')));
echo "retry: 5000\n\n";

if(!mg_admin_agent_schema_ready($pdo)){
    mg_admin_agent_phase4_sse('schema',['phase1_ready'=>false,'migration'=>'database/20260718_main_admin_agent_phase1.sql']);
    exit;
}

$options=['after'=>$after,'event_limit'=>100,'domain'=>$domain,'finding_status'=>'active'];
if(mg_admin_agent_phase4_ready($pdo)) $snapshot=mg_admin_agent_phase4_state($pdo,$actorId,$options);
elseif(mg_admin_agent_phase3_ready($pdo)) $snapshot=mg_admin_agent_phase3_state($pdo,$actorId,$options);
elseif(mg_admin_agent_phase2_ready($pdo)) $snapshot=mg_admin_agent_phase2_state($pdo,$actorId,$options);
else $snapshot=mg_admin_agent_state_runtime($pdo,$actorId,$options);
$snapshot['phase4_schema']=mg_admin_agent_phase4_schema_state($pdo);
$snapshot['phase4_ready']=mg_admin_agent_phase4_ready($pdo);
$snapshot['generated_at']=gmdate('Y-m-d H:i:s');
mg_admin_agent_phase4_sse('snapshot',$snapshot);

for($iteration=0;$iteration<8;$iteration++){
    if(connection_aborted()) break;
    $events=mg_admin_agent_events_runtime($pdo,$after,100,$domain);
    if($events!==[]){
        $after=(int)end($events)['cursor'];
        mg_admin_agent_phase4_sse('events',['events'=>$events,'cursor'=>$after,'generated_at'=>gmdate('Y-m-d H:i:s')],$after);
    }else{
        mg_admin_agent_phase4_sse('heartbeat',['cursor'=>$after,'generated_at'=>gmdate('Y-m-d H:i:s')]);
    }
    if($iteration<7) sleep(2);
}
