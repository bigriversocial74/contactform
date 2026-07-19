<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once dirname(__DIR__, 2) . '/includes/admin-agent-phase2-remediation.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();

function mg_admin_agent_phase2_api_has(array $actor, string $permission): bool
{
    if (mg_admin_account_actor_has($actor, $permission)) return true;
    $fallbacks = match ($permission) {
        'admin.admin_agent.view' => ['admin.operations_command.view','admin.health.view','admin.audit.view','security.logs.view','admin.users.manage'],
        'admin.admin_agent.chat' => ['admin.operations_command.view','admin.users.manage'],
        'admin.admin_agent.manage' => ['admin.operations_command.manage','admin.settings.manage','admin.users.manage'],
        'admin.admin_agent.actions' => ['admin.operations_command.manage','admin.settings.manage','admin.users.manage'],
        'admin.admin_agent.escalations' => ['admin.operations_command.manage','admin.settings.manage','admin.users.manage'],
        'admin.admin_agent.deployments' => ['admin.settings.manage','admin.users.manage'],
        'admin.admin_agent.execute' => ['admin.settings.manage','admin.users.manage'],
        default => [],
    };
    foreach ($fallbacks as $fallback) if (mg_admin_account_actor_has($actor, $fallback)) return true;
    return false;
}

function mg_admin_agent_phase2_api_require(array $actor, string $permission): void
{
    if (mg_admin_agent_phase2_api_has($actor, $permission)) return;
    mg_audit('permission_denied','security',['permission'=>$permission,'area'=>'main_admin_agent_phase2'],(int)$actor['id']);
    mg_security_log('warning','admin_agent.phase2_permission_denied','Main Admin Agent Phase 2 permission denied.',['permission'=>$permission],(int)$actor['id']);
    mg_fail('Permission denied.',403);
}

function mg_admin_agent_phase2_api_options(array $source): array
{
    return [
        'after'=>max(0,(int)($source['after']??0)),
        'event_limit'=>max(10,min(200,(int)($source['event_limit']??100))),
        'domain'=>preg_replace('/[^a-z0-9_]/','',strtolower((string)($source['domain']??''))),
        'finding_status'=>(string)($source['finding_status']??'active'),
        'thread_id'=>trim((string)($source['thread_id']??'')),
    ];
}

function mg_admin_agent_phase2_api_permissions(array $actor): array
{
    return [
        'chat'=>mg_admin_agent_phase2_api_has($actor,'admin.admin_agent.chat'),
        'manage'=>mg_admin_agent_phase2_api_has($actor,'admin.admin_agent.manage'),
        'actions'=>mg_admin_agent_phase2_api_has($actor,'admin.admin_agent.actions'),
        'escalations'=>mg_admin_agent_phase2_api_has($actor,'admin.admin_agent.escalations'),
        'deployments'=>mg_admin_agent_phase2_api_has($actor,'admin.admin_agent.deployments'),
        'execute'=>mg_admin_agent_phase2_api_has($actor,'admin.admin_agent.execute'),
    ];
}

function mg_admin_agent_phase2_api_state(PDO $pdo,int $actorId,array $options,array $actor): array
{
    $payload=mg_admin_agent_phase2_ready($pdo)?mg_admin_agent_phase2_state($pdo,$actorId,$options):mg_admin_agent_state_runtime($pdo,$actorId,$options);
    $payload['phase2_schema']=mg_admin_agent_phase2_schema_state($pdo);
    $payload['phase2_ready']=mg_admin_agent_phase2_ready($pdo);
    $payload['permissions']=mg_admin_agent_phase2_api_permissions($actor);
    return $payload;
}

try {
    if ($method==='GET') {
        mg_rate_limit('admin.agent.phase2.read','user:'.$actorId,240,60);
        mg_admin_agent_phase2_api_require($actor,'admin.admin_agent.view');
        if (mg_admin_agent_schema_ready($pdo)) {
            $last=mg_admin_agent_last_scan($pdo);
            $completed=(string)($last['completed_at']??'');
            $stale=$last===null||$completed===''||strtotime($completed.' UTC')<time()-300;
            if ($stale&&(string)($_GET['skip_scan']??'')!=='1') {
                if (mg_admin_agent_phase2_ready($pdo)) mg_admin_agent_phase2_run($pdo,['trigger_source'=>'workspace_load','initiated_by_user_id'=>$actorId]);
                else mg_admin_agent_scan_runtime($pdo,['trigger_source'=>'workspace_load','initiated_by_user_id'=>$actorId]);
            }
        }
        $payload=mg_admin_agent_phase2_api_state($pdo,$actorId,mg_admin_agent_phase2_api_options($_GET),$actor);
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok($payload,'Main Admin Agent Phase 2 loaded.');
    }

    if ($method==='POST') {
        mg_rate_limit('admin.agent.phase2.write','user:'.$actorId,90,60);
        $input=mg_input();
        mg_require_csrf_for_write($input);
        mg_admin_agent_phase2_api_require($actor,'admin.admin_agent.view');
        if (!mg_admin_agent_schema_ready($pdo)) mg_fail('Main Admin Agent Phase 1 SQL migration is required.',409,['schema'=>mg_admin_agent_schema_state($pdo)]);
        $action=strtolower(trim((string)($input['action']??'send_message')));
        $result=null;
        if ($action==='send_message') {
            mg_admin_agent_phase2_api_require($actor,'admin.admin_agent.chat');
            $result=mg_admin_agent_phase2_ready($pdo)?mg_admin_agent_phase2_send($pdo,$actorId,$input):mg_admin_agent_send_runtime($pdo,$actorId,$input);
        } elseif ($action==='run_scan') {
            mg_admin_agent_phase2_api_require($actor,'admin.admin_agent.manage');
            $result=mg_admin_agent_phase2_ready($pdo)?mg_admin_agent_phase2_run($pdo,['trigger_source'=>'manual','initiated_by_user_id'=>$actorId]):mg_admin_agent_scan_runtime($pdo,['trigger_source'=>'manual','initiated_by_user_id'=>$actorId]);
        } elseif ($action==='new_thread') {
            mg_admin_agent_phase2_api_require($actor,'admin.admin_agent.chat');
            $result=mg_admin_agent_new_thread($pdo,$actorId);
        } elseif ($action==='finding_action') {
            mg_admin_agent_phase2_api_require($actor,'admin.admin_agent.manage');
            $result=mg_admin_agent_apply_finding_action_runtime($pdo,$actorId,$input);
        } elseif ($action==='request_action') {
            mg_admin_agent_phase2_api_require($actor,'admin.admin_agent.actions');
            $result=mg_admin_agent_phase2_ready($pdo)?mg_admin_agent_phase2_request_action($pdo,$actorId,$input):mg_admin_agent_request_action($pdo,$actorId,$input);
        } elseif ($action==='record_deployment') {
            mg_admin_agent_phase2_api_require($actor,'admin.admin_agent.deployments');
            if (!mg_admin_agent_phase2_ready($pdo)) mg_fail('Main Admin Agent Phase 2 SQL migration is required.',409,['schema'=>mg_admin_agent_phase2_schema_state($pdo)]);
            $result=mg_admin_agent_phase2_record_deployment($pdo,$actorId,$input);
            mg_admin_agent_phase2_correlate($pdo);
        } elseif ($action==='generate_summary') {
            mg_admin_agent_phase2_api_require($actor,'admin.admin_agent.manage');
            if (!mg_admin_agent_phase2_ready($pdo)) mg_fail('Main Admin Agent Phase 2 SQL migration is required.',409,['schema'=>mg_admin_agent_phase2_schema_state($pdo)]);
            $result=mg_admin_agent_phase2_generate_summary($pdo,(string)($input['period_type']??'manual'),$actorId);
        } elseif ($action==='intelligence_action') {
            mg_admin_agent_phase2_api_require($actor,'admin.admin_agent.manage');
            if (!mg_admin_agent_phase2_ready($pdo)) mg_fail('Main Admin Agent Phase 2 SQL migration is required.',409,['schema'=>mg_admin_agent_phase2_schema_state($pdo)]);
            $result=mg_admin_agent_phase2_apply_intelligence_action($pdo,$actorId,$input);
        } elseif ($action==='acknowledge_escalation') {
            mg_admin_agent_phase2_api_require($actor,'admin.admin_agent.escalations');
            if (!mg_admin_agent_phase2_ready($pdo)) mg_fail('Main Admin Agent Phase 2 SQL migration is required.',409,['schema'=>mg_admin_agent_phase2_schema_state($pdo)]);
            $result=mg_admin_agent_phase2_acknowledge_escalation($pdo,$actorId,$input);
        } elseif ($action==='review_action') {
            mg_admin_agent_phase2_api_require($actor,'admin.admin_agent.execute');
            if (!mg_admin_agent_phase2_ready($pdo)) mg_fail('Main Admin Agent Phase 2 SQL migration is required.',409,['schema'=>mg_admin_agent_phase2_schema_state($pdo)]);
            $result=mg_admin_agent_phase2_review_action($pdo,$actorId,$input);
        } elseif ($action==='execute_action') {
            mg_admin_agent_phase2_api_require($actor,'admin.admin_agent.execute');
            if (!mg_admin_agent_phase2_ready($pdo)) mg_fail('Main Admin Agent Phase 2 SQL migration is required.',409,['schema'=>mg_admin_agent_phase2_schema_state($pdo)]);
            $result=mg_admin_agent_phase2_execute_action($pdo,$actorId,$input);
        } else throw new InvalidArgumentException('Unknown Main Admin Agent Phase 2 action.');
        $options=mg_admin_agent_phase2_api_options($input);
        if (is_array($result['thread']??null)&&!empty($result['thread']['id'])) $options['thread_id']=(string)$result['thread']['id'];
        if ($action==='new_thread'&&!empty($result['id'])) $options['thread_id']=(string)$result['id'];
        $state=mg_admin_agent_phase2_api_state($pdo,$actorId,$options,$actor);
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok(['result'=>$result,'state'=>$state],'Main Admin Agent Phase 2 action completed.');
    }
    mg_fail('Method not allowed.',405);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(),422);
} catch (Throwable $error) {
    mg_security_log('error','admin_agent.phase2_request_failed','Main Admin Agent Phase 2 request failed.',['exception_class'=>$error::class],$actorId);
    mg_fail('Unable to process the Main Admin Agent Phase 2 request.',500);
}
