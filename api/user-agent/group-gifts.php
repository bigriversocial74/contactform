<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
$user=mg_require_permission('agent.personal.workflows.manage');
$method=strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if($method==='GET') mg_user_agent_api_run(static fn():array=>mg_personal_workflows_group_gifts(mg_db(),(int)$user['id']));
mg_require_method('POST');$input=mg_input();mg_require_csrf_for_write($input);
mg_user_agent_api_run(static function()use($user,$input):array{
    $action=mg_personal_agent_text($input['action'] ?? 'create',40);
    $id=mg_personal_agent_text($input['group_gift_id'] ?? '',80);
    if($action==='create') return ['group_gift'=>mg_personal_workflows_create_group_gift(mg_db(),(int)$user['id'],$input)];
    if(in_array($action,['open','lock','fulfill','close','cancel'],true)) return ['group_gift'=>mg_personal_workflows_update_group_gift(mg_db(),(int)$user['id'],$id,$action)];
    $participantId=mg_personal_agent_text($input['participant_id'] ?? '',80);
    if(in_array($action,['join','decline'],true)) return mg_personal_workflows_respond_group_invite(mg_db(),(int)$user['id'],$participantId,$action,$input['pledge'] ?? null,mg_personal_workflows_bool($input['is_anonymous'] ?? false));
    if($action==='record_external_pledge') return mg_personal_workflows_record_external_pledge(mg_db(),(int)$user['id'],$participantId,$input['pledge'] ?? null);
    throw new InvalidArgumentException('Invalid group-gift action.');
});
