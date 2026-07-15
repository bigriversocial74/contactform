<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
$user=mg_require_permission('agent.personal.requests.respond');
$method=strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if($method==='GET') mg_user_agent_api_run(static fn():array=>mg_personal_workflows_requests(mg_db(),(int)$user['id']));
mg_require_method('POST');$input=mg_input();mg_require_csrf_for_write($input);
mg_user_agent_api_run(static function()use($user,$input):array{
    $action=mg_personal_agent_text($input['action'] ?? 'create',40);
    $id=mg_personal_agent_text($input['request_id'] ?? '',80);
    if($action==='create') return ['request'=>mg_personal_workflows_create_data_request(mg_db(),(int)$user['id'],$input)];
    if($action==='approve' || $action==='decline') return ['request'=>mg_personal_workflows_respond_data_request(mg_db(),(int)$user['id'],$id,$action,$input)];
    if($action==='cancel') return ['request'=>mg_personal_workflows_cancel_data_request(mg_db(),(int)$user['id'],$id)];
    if($action==='revoke') return ['request'=>mg_personal_workflows_revoke_data_request(mg_db(),(int)$user['id'],$id)];
    throw new InvalidArgumentException('Invalid recipient-data request action.');
});
