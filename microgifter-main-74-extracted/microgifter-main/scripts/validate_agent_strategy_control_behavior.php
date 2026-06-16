<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__).'/api/agents/_execution.php';
require_once dirname(__DIR__).'/tests/integration/MicrogiftBehaviorFixture.php';

function asc_assert(bool $condition,string $name):void{if(!$condition)throw new RuntimeException('Agent strategy validation failed: '.$name);}
function asc_throws(callable $callback,string $message):bool{try{$callback();}catch(Throwable $error){return str_contains($error->getMessage(),$message);}return false;}
function asc_agent(PDO $pdo,int $userId,string $name):array{
    $now=gmdate('Y-m-d H:i:s');$public=mg_public_uuid();
    $id=mg_it_insert($pdo,'agents',['public_id'=>$public,'user_id'=>$userId,'name'=>$name,'category'=>'community','config_json'=>json_encode(['region'=>'Phoenix'],JSON_THROW_ON_ERROR),'runtime_status'=>'paused','lifecycle_status'=>'active','version_no'=>1,'started_at'=>null,'paused_at'=>$now,'archived_at'=>null,'restored_at'=>null,'deleted_at'=>null,'created_at'=>$now,'updated_at'=>$now]);
    return['id'=>$id,'public_id'=>$public];
}
function asc_input(string $agentId,string $name='Demand Review'):array{return['agent_id'=>$agentId,'name'=>$name,'objective'=>'Review demand signals and create an operational alert before acknowledgement.','trigger_type'=>'demand_signal','trigger_config'=>['minimum_level'=>'warning','minimum_confidence'=>0.7,'orchestration_mode'=>'workflow'],'policy'=>['review_dashboard'=>true],'action_catalog'=>['create_operational_alert','acknowledge_demand_signal'],'max_actions_per_run'=>4,'requires_approval'=>true];}

$pdo=mg_db();$run='asc'.bin2hex(random_bytes(5));
$result=array_fill_keys(['draft_create','safe_projection','update_version','stale_update_rejected','activate','duplicate_transition','active_edit_rejected','pause','owner_isolation','retire','retired_reactivation_rejected','archived_agent_activation_rejected','rollback_clean'],false);
$pdo->beginTransaction();
try{
    $owner=mg_it_user($pdo,$run.'-owner@example.test','Strategy Owner');
    $other=mg_it_user($pdo,$run.'-other@example.test','Other Owner');
    $agent=asc_agent($pdo,$owner,'Operations Agent');
    $archivedAgent=asc_agent($pdo,$owner,'Archived Agent');

    $created=mg_agent_create_strategy($pdo,$owner,asc_input($agent['public_id']));
    $result['draft_create']=$created['status']==='draft'&&(int)$created['version_no']===1&&(string)$created['agent_public_id']===$agent['public_id'];
    $projection=mg_agent_strategy_projection($created);$keys=[];$walk=function($value)use(&$walk,&$keys){if(!is_array($value))return;foreach($value as $key=>$child){$keys[]=strtolower((string)$key);$walk($child);}};$walk($projection);
    $result['safe_projection']=array_intersect(['owner_user_id','created_by_user_id','agent_id','policy_json','trigger_config_json','action_catalog_json'],$keys)===[]&&$projection['permissions']['can_edit']===true;

    $updated=mg_agent_update_strategy($pdo,$owner,asc_input($agent['public_id'],'Updated Demand Review')+['strategy_id'=>$created['public_id'],'version'=>1]);
    $result['update_version']=$updated['name']==='Updated Demand Review'&&(int)$updated['version_no']===2;
    $result['stale_update_rejected']=asc_throws(fn()=>mg_agent_update_strategy($pdo,$owner,asc_input($agent['public_id'],'Stale')+['strategy_id'=>$created['public_id'],'version'=>1]),'changed since it was loaded');

    $active=mg_agent_transition_strategy($pdo,$owner,['strategy_id'=>$created['public_id'],'version'=>2],'active');
    $result['activate']=$active['status']==='active'&&(int)$active['version_no']===3;
    $duplicate=mg_agent_transition_strategy($pdo,$owner,['strategy_id'=>$created['public_id'],'version'=>3],'active');
    $result['duplicate_transition']=($duplicate['duplicate']??false)===true&&(int)$duplicate['version_no']===3;
    $result['active_edit_rejected']=asc_throws(fn()=>mg_agent_update_strategy($pdo,$owner,asc_input($agent['public_id'],'Blocked')+['strategy_id'=>$created['public_id'],'version'=>3]),'cannot be edited');

    $paused=mg_agent_transition_strategy($pdo,$owner,['strategy_id'=>$created['public_id'],'version'=>3],'paused');
    $result['pause']=$paused['status']==='paused'&&(int)$paused['version_no']===4;
    $result['owner_isolation']=asc_throws(fn()=>mg_agent_strategy_owned($pdo,$created['public_id'],$other,true),'not found');
    $retired=mg_agent_transition_strategy($pdo,$owner,['strategy_id'=>$created['public_id'],'version'=>4],'retired');
    $result['retire']=$retired['status']==='retired'&&(int)$retired['version_no']===5;
    $result['retired_reactivation_rejected']=asc_throws(fn()=>mg_agent_transition_strategy($pdo,$owner,['strategy_id'=>$created['public_id'],'version'=>5],'active'),'cannot move');

    $blocked=mg_agent_create_strategy($pdo,$owner,asc_input($archivedAgent['public_id'],'Archived Agent Strategy'));
    $pdo->prepare("UPDATE agents SET lifecycle_status='archived',archived_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$archivedAgent['id']]);
    $result['archived_agent_activation_rejected']=asc_throws(fn()=>mg_agent_transition_strategy($pdo,$owner,['strategy_id'=>$blocked['public_id'],'version'=>1],'active'),'Archived agents');

    foreach($result as $name=>$passed)if($name!=='rollback_clean')asc_assert($passed,$name);
    $pdo->rollBack();$result['rollback_clean']=true;
    echo json_encode($result+['suite'=>'agent_strategy_control_center_section_1'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,$error->getMessage().PHP_EOL);exit(1);}
