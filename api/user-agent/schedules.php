<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
$user=mg_require_permission('agent.personal.workflows.manage');
$method=strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if($method==='GET') {
    $status=mg_personal_agent_text($_GET['status'] ?? 'all',30);
    mg_user_agent_api_run(static fn():array=>['schedules'=>mg_personal_workflows_schedules(mg_db(),(int)$user['id'],$status)]);
}
mg_require_method('POST');$input=mg_input();mg_require_csrf_for_write($input);
mg_user_agent_api_run(static function()use($user,$input):array{
    $action=mg_personal_agent_text($input['action'] ?? 'create',30);
    if($action==='create') return ['schedule'=>mg_personal_workflows_create_schedule(mg_db(),(int)$user['id'],$input)];
    return ['schedule'=>mg_personal_workflows_update_schedule(mg_db(),(int)$user['id'],mg_personal_agent_text($input['schedule_id'] ?? '',80),$action)];
});
