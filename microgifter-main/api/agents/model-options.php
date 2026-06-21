<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/ai/_ai.php';
$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$pdo=mg_db();
if($method==='GET'){
  $user=mg_require_api_user();
  $agentPublicId=trim((string)($_GET['agent']??''));
  $setting=null;
  if($agentPublicId!==''){
    $agent=mg_agent_require_owned((int)$user['id'],$agentPublicId);
    $setting=mg_ai_public_agent_setting(mg_ai_agent_setting($pdo,(int)$agent['id']));
  }
  mg_ok(['models'=>mg_ai_available_models($pdo),'agent_setting'=>$setting]);
}
if($method==='POST'){
  $user=mg_require_permission('agent.ai.configure');
  $input=mg_input();
  mg_require_csrf_for_write($input);
  $agentPublicId=mg_agent_request_id(['id'=>$input['agent_id']??null]);
  $modelPublicId=trim((string)($input['model_id']??''));
  if($modelPublicId==='') mg_fail('Choose a model.',422);
  $pdo->beginTransaction();
  $agent=mg_agent_require_owned((int)$user['id'],$agentPublicId,true);
  $setting=mg_ai_set_agent_model($pdo,$agent,$modelPublicId,(int)$user['id']);
  mg_agent_history($pdo,$agent,'updated',['model_id'=>$modelPublicId]);
  $pdo->commit();
  mg_ok(['agent_setting'=>mg_ai_public_agent_setting($setting)],'Agent model saved.');
}
mg_fail('Method not allowed.',405);
