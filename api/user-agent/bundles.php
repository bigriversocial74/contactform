<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
$user=mg_require_permission('agent.personal.workflows.manage');
$method=strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if($method==='GET') mg_user_agent_api_run(static fn():array=>['bundles'=>mg_personal_workflows_bundles(mg_db(),(int)$user['id']),'catalog'=>mg_personal_workflows_catalog(mg_db(),40)]);
mg_require_method('POST');$input=mg_input();mg_require_csrf_for_write($input);
mg_user_agent_api_run(static function()use($user,$input):array{
    $action=mg_personal_agent_text($input['action'] ?? 'create',40);
    $id=mg_personal_agent_text($input['bundle_id'] ?? '',80);
    if($action==='create') return ['bundle'=>mg_personal_workflows_create_bundle(mg_db(),(int)$user['id'],$input)];
    if($action==='add_item') return ['bundle'=>mg_personal_workflows_add_bundle_item(mg_db(),(int)$user['id'],$id,$input)];
    if($action==='remove_item') return ['bundle'=>mg_personal_workflows_remove_bundle_item(mg_db(),(int)$user['id'],$id,mg_personal_agent_text($input['item_id'] ?? '',80))];
    if(in_array($action,['ready','reopen','archive'],true)) return ['bundle'=>mg_personal_workflows_update_bundle(mg_db(),(int)$user['id'],$id,$action)];
    throw new InvalidArgumentException('Invalid bundle action.');
});
