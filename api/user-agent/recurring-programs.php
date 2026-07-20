<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
$user=mg_require_permission('agent.personal.workflows.manage');
$method=strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if($method==='GET') {
    $status=mg_personal_agent_text($_GET['status'] ?? 'all',30);
    mg_user_agent_api_run(static fn():array=>['programs'=>mg_personal_workflows_recurring_programs(mg_db(),(int)$user['id'],$status)]);
}
mg_require_method('POST');
$input=mg_input();
mg_require_csrf_for_write($input);
mg_user_agent_api_run(static function()use($user,$input):array{
    $action=mg_personal_agent_text($input['action'] ?? 'create',30);
    $programId=mg_personal_agent_text($input['program_id'] ?? '',80);
    if($action==='create') return ['program'=>mg_personal_workflows_create_recurring_program(mg_db(),(int)$user['id'],$input)];
    if($action==='generate_draft') return mg_personal_workflows_generate_recurring_draft(mg_db(),(int)$user['id'],$programId);
    if($action==='skip_next') return mg_personal_workflows_skip_recurring_run(
        mg_db(),
        (int)$user['id'],
        $programId,
        mg_personal_agent_text($input['expected_next_run_at'] ?? '',40)
    );
    return ['program'=>mg_personal_workflows_update_recurring_program(mg_db(),(int)$user['id'],$programId,$action)];
});
