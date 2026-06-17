<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}

require_once dirname(__DIR__).'/api/agents/_swarm_workflow.php';
require_once dirname(__DIR__).'/tests/integration/MicrogiftBehaviorFixture.php';

function mg_swarm_behavior_assert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}

function mg_swarm_behavior_scalar(PDO $pdo,string $sql,array $params=[]): mixed
{
    $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchColumn();
}

$pdo=mg_db();$runId='swarm_'.bin2hex(random_bytes(8));
$summary=[
    'suite'=>'swarm_routing_review_completion_behavior','run_id'=>$runId,
    'run_created'=>false,'first_task_routed'=>false,'stage16_delegated'=>false,'review_pending'=>false,
    'review_replay'=>false,'conflicting_review_rejected'=>false,'dependency_released'=>false,
    'second_task_completed'=>false,'swarm_completed'=>false,'routing_replay_safe'=>false,
    'audit_consistent'=>false,'budget_consistent'=>false,'fixtures_clean'=>false,
];

$pdo->beginTransaction();
try{
    $ownerEmail=$runId.'-owner@example.test';$ownerId=mg_it_user($pdo,$ownerEmail,'Swarm Owner');
    $agentPublic=mg_public_uuid();
    $pdo->prepare("INSERT INTO agents (public_id,user_id,name,category,config_json,runtime_status,lifecycle_status,version_no,created_at,updated_at) VALUES (?,?,?,'operations','{}','running','active',1,NOW(),NOW())")
        ->execute([$agentPublic,$ownerId,'Swarm Agent']);

    $strategy=mg_agent_create_strategy($pdo,$ownerId,[
        'agent_id'=>$agentPublic,'name'=>'Swarm execution strategy','objective'=>'Execute swarm alerts',
        'trigger_type'=>'event','action_catalog'=>['create_operational_alert'],'max_actions_per_run'=>2,'requires_approval'=>false,
    ]);
    $pdo->prepare("UPDATE agent_strategies SET status='active' WHERE id=?")->execute([(int)$strategy['id']]);

    $team=mg_swarm_create_team($pdo,$ownerId,[
        'name'=>'Behavior swarm','objective'=>'Validate routing review and completion','coordination_mode'=>'pipeline',
        'conflict_policy'=>'owner_decides','max_parallel_tasks'=>2,'default_budget_units'=>1000,'metadata'=>['run_id'=>$runId],
    ]);
    $pdo->prepare("UPDATE agent_teams SET status='active' WHERE id=?")->execute([(int)$team['id']]);
    mg_swarm_add_member($pdo,$team,$ownerId,[
        'agent_id'=>$agentPublic,'role_key'=>'executor','role_type'=>'executor','priority'=>10,
        'capabilities'=>['operations'],'routing_profile'=>['run_id'=>$runId],'max_concurrent_tasks'=>2,
    ]);

    $action=function(string $title) use ($ownerId,$runId): array {
        return [
            'action_type'=>'create_operational_alert','target_type'=>'user','target_reference'=>(string)$ownerId,
            'risk_level'=>'low','request'=>['title'=>$title,'message'=>'Swarm behavior '.$runId,'action_url'=>'/account.php'],
        ];
    };
    $run=mg_swarm_create_run($pdo,$ownerId,[
        'team_id'=>$team['public_id'],'idempotency_key'=>'behavior:swarm:'.$runId,'objective'=>'Validate swarm lifecycle','budget_units'=>1000,
        'tasks'=>[
            ['task_key'=>'reviewed','task_type'=>'operations','capability_key'=>'operations','objective'=>'Reviewed task','strategy_id'=>$strategy['public_id'],'requires_review'=>true,'estimated_units'=>100,'input'=>['actions'=>[$action('Reviewed swarm action')]]],
            ['task_key'=>'dependent','task_type'=>'operations','capability_key'=>'operations','objective'=>'Dependent task','strategy_id'=>$strategy['public_id'],'requires_review'=>false,'estimated_units'=>150,'depends_on'=>['reviewed'],'input'=>['actions'=>[$action('Dependent swarm action')]]],
        ],
    ]);
    mg_swarm_behavior_assert((string)$run['status']==='queued','Swarm run did not queue.');
    mg_swarm_behavior_assert((int)mg_swarm_behavior_scalar($pdo,"SELECT COUNT(*) FROM agent_swarm_tasks WHERE swarm_run_id=? AND status='ready'",[(int)$run['id']])===1,'Root task was not released.');
    $summary['run_created']=true;

    $route1=mg_swarm_route_next_task($pdo);
    mg_swarm_behavior_assert($route1['processed']===true,'First task was not routed.');
    $task1=(string)$route1['task_id'];
    mg_swarm_behavior_assert((string)mg_swarm_behavior_scalar($pdo,'SELECT status FROM agent_swarm_tasks WHERE public_id=?',[$task1])==='routed','First task route state is wrong.');
    $summary['first_task_routed']=true;
    mg_swarm_behavior_assert((int)mg_swarm_behavior_scalar($pdo,"SELECT COUNT(*) FROM agent_execution_events ee INNER JOIN agent_workflow_runs wr ON wr.id=ee.run_id WHERE wr.public_id=? AND ee.event_type='run_delegated_to_stage16'",[$route1['workflow_run_id']])===1,'Stage 16 delegation event is missing.');
    $summary['stage16_delegated']=true;

    $processed1=mg_agent_process_next_action($pdo);
    mg_swarm_behavior_assert($processed1['status']==='completed','First Stage 16 action did not complete.');
    $sync1=mg_swarm_sync_workflow($pdo,$task1);
    mg_swarm_behavior_assert($sync1['status']==='review_pending','Reviewed task did not enter review.');
    $summary['review_pending']=true;

    $reviewInput=['task_id'=>$task1,'decision'=>'approve','reason'=>'Validated output.'];
    $review=mg_swarm_review_task($pdo,$ownerId,$reviewInput);
    mg_swarm_behavior_assert($review['status']==='completed'&&$review['duplicate']===false,'Review approval failed.');
    $reviewReplay=mg_swarm_review_task($pdo,$ownerId,$reviewInput);
    mg_swarm_behavior_assert($reviewReplay['duplicate']===true,'Exact review replay was not idempotent.');
    mg_swarm_behavior_assert((int)mg_swarm_behavior_scalar($pdo,"SELECT COUNT(*) FROM agent_swarm_events WHERE swarm_run_id=? AND task_id=(SELECT id FROM agent_swarm_tasks WHERE public_id=?) AND event_type='task_review_approve'",[(int)$run['id'],$task1])===1,'Review replay duplicated events.');
    $summary['review_replay']=true;

    $conflict=false;
    try{mg_swarm_review_task($pdo,$ownerId,['task_id'=>$task1,'decision'=>'reject','reason'=>'Conflict.']);}
    catch(MgSwarmWorkflowException $error){$conflict=$error->httpStatus===409;}
    mg_swarm_behavior_assert($conflict,'Conflicting review replay was accepted.');
    $summary['conflicting_review_rejected']=true;

    mg_swarm_behavior_assert((int)mg_swarm_behavior_scalar($pdo,"SELECT COUNT(*) FROM agent_swarm_tasks WHERE swarm_run_id=? AND task_key='dependent' AND status='ready'",[(int)$run['id']])===1,'Dependent task was not released after approved review.');
    $summary['dependency_released']=true;

    $route2=mg_swarm_route_next_task($pdo);
    mg_swarm_behavior_assert($route2['processed']===true,'Second task was not routed.');
    $task2=(string)$route2['task_id'];
    $processed2=mg_agent_process_next_action($pdo);
    mg_swarm_behavior_assert($processed2['status']==='completed','Second Stage 16 action did not complete.');
    $sync2=mg_swarm_sync_workflow($pdo,$task2);
    mg_swarm_behavior_assert($sync2['status']==='completed','Second swarm task did not complete.');
    $summary['second_task_completed']=true;

    mg_swarm_behavior_assert((string)mg_swarm_behavior_scalar($pdo,'SELECT status FROM agent_swarm_runs WHERE id=?',[(int)$run['id']])==='completed','Swarm run did not reconcile to completed.');
    mg_swarm_behavior_assert((int)mg_swarm_behavior_scalar($pdo,"SELECT COUNT(*) FROM agent_swarm_events WHERE swarm_run_id=? AND event_type='swarm_completed'",[(int)$run['id']])===1,'Swarm completion event count is wrong.');
    $summary['swarm_completed']=true;

    $noRoute=mg_swarm_route_next_task($pdo);
    mg_swarm_behavior_assert($noRoute['processed']===false,'Completed swarm routed another task.');
    $summary['routing_replay_safe']=true;

    mg_swarm_behavior_assert((int)mg_swarm_behavior_scalar($pdo,"SELECT COUNT(*) FROM agent_swarm_events WHERE swarm_run_id=? AND event_type='task_routed'",[(int)$run['id']])===2,'Routing audit history is inconsistent.');
    mg_swarm_behavior_assert((int)mg_swarm_behavior_scalar($pdo,"SELECT COUNT(*) FROM agent_swarm_events WHERE swarm_run_id=? AND event_type IN ('task_review_pending','task_completed')",[(int)$run['id']])===2,'Task completion audit history is inconsistent.');
    $summary['audit_consistent']=true;

    $reserved=(int)mg_swarm_behavior_scalar($pdo,'SELECT reserved_units FROM agent_swarm_runs WHERE id=?',[(int)$run['id']]);
    $consumed=(int)mg_swarm_behavior_scalar($pdo,'SELECT consumed_units FROM agent_swarm_runs WHERE id=?',[(int)$run['id']]);
    mg_swarm_behavior_assert($reserved===250&&$consumed===250,'Swarm budget accounting is inconsistent.');
    $summary['budget_consistent']=true;

    $pdo->rollBack();
    mg_swarm_behavior_assert((int)mg_swarm_behavior_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email=?',[$ownerEmail])===0,'Swarm user fixture remains.');
    mg_swarm_behavior_assert((int)mg_swarm_behavior_scalar($pdo,'SELECT COUNT(*) FROM agent_swarm_runs WHERE idempotency_key=?',['behavior:swarm:'.$runId])===0,'Swarm run fixture remains.');
    mg_swarm_behavior_assert((int)mg_swarm_behavior_scalar($pdo,"SELECT COUNT(*) FROM operational_alerts WHERE body LIKE ?",['%'.$runId.'%'])===0,'Swarm alert fixtures remain.');
    $summary['fixtures_clean']=true;

    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    throw $error;
}
