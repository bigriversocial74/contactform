<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
$user=mg_require_permission('agent.personal.workflows.manage');
$method=strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if($method==='GET') mg_user_agent_api_run(static fn():array=>['eligible_gifts'=>mg_personal_workflows_eligible_gifts(mg_db(),(int)$user['id']),'reminders'=>mg_personal_workflows_lifecycle_reminders(mg_db(),(int)$user['id'])]);
mg_require_method('POST');$input=mg_input();mg_require_csrf_for_write($input);
mg_user_agent_api_run(static function()use($user,$input):array{
    $action=mg_personal_agent_text($input['action'] ?? 'create',40);
    if($action==='create') return ['reminder'=>mg_personal_workflows_create_lifecycle_reminder(mg_db(),(int)$user['id'],$input)];
    return ['reminder'=>mg_personal_workflows_update_lifecycle_reminder(mg_db(),(int)$user['id'],mg_personal_agent_text($input['reminder_id'] ?? '',80),$action)];
});
