<?php
declare(strict_types=1);

require_once __DIR__ . '/_swarm.php';
require_once __DIR__ . '/_workflow.php';

final class MgSwarmWorkflowException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus=409)
    {
        parent::__construct($message);
    }
}

function mg_swarm_release_dependencies(PDO $pdo,int $runId): int
{
    $stmt=$pdo->prepare("UPDATE agent_swarm_tasks child
        INNER JOIN (
            SELECT eligible.id
            FROM (
                SELECT candidate.id
                FROM agent_swarm_tasks candidate
                WHERE candidate.swarm_run_id=?
                  AND candidate.status='pending'
                  AND NOT EXISTS (
                    SELECT 1
                    FROM agent_swarm_task_dependencies d
                    INNER JOIN agent_swarm_tasks parent ON parent.id=d.depends_on_task_id
                    WHERE d.task_id=candidate.id
                      AND ((d.dependency_type='success' AND parent.status<>'completed')
                        OR (d.dependency_type='completion' AND parent.status NOT IN ('completed','failed','canceled'))
                        OR (d.dependency_type='review' AND parent.status<>'completed'))
                  )
            ) eligible
        ) ready ON ready.id=child.id
        SET child.status='ready',child.updated_at=NOW()");
    $stmt->execute([$runId]);
    return $stmt->rowCount();
}

function mg_swarm_reconcile_run(PDO $pdo,int $runId,?int $actorUserId=null): string
{
    $stmt=$pdo->prepare("SELECT status,COUNT(*) total FROM agent_swarm_tasks WHERE swarm_run_id=? GROUP BY status");
    $stmt->execute([$runId]);$counts=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$counts[(string)$row['status']]=(int)$row['total'];
    $open=0;foreach(['pending','ready','routed','running','review_pending','blocked'] as $status)$open+=$counts[$status]??0;
    if($open>0)return 'running';
    $completed=$counts['completed']??0;$failed=($counts['failed']??0)+($counts['canceled']??0);
    $status=$failed===0?'completed':($completed>0?'partially_completed':'failed');
    $pdo->prepare('UPDATE agent_swarm_runs SET status=?,completed_at=COALESCE(completed_at,NOW()),result_json=?,failure_message=?,updated_at=NOW() WHERE id=?')
        ->execute([$status,json_encode(['status'=>$status,'counts'=>$counts],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$failed>0?'One or more swarm tasks did not complete.':null,$runId]);
    mg_swarm_event($pdo,$runId,null,'swarm_'.$status,$actorUserId,['counts'=>$counts]);
    return $status;
}

function mg_swarm_route_next_task(PDO $pdo): array
{
    $stmt=$pdo->query("SELECT st.*,sr.public_id swarm_public_id,sr.owner_user_id,sr.budget_units run_budget_units,sr.consumed_units run_consumed_units FROM agent_swarm_tasks st INNER JOIN agent_swarm_runs sr ON sr.id=st.swarm_run_id WHERE st.status='ready' AND sr.status IN ('queued','running','blocked') ORDER BY st.priority ASC,st.id ASC LIMIT 1 FOR UPDATE SKIP LOCKED");
    $task=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$task)return ['processed'=>false];
    if($task['strategy_id']===null)throw new MgSwarmWorkflowException('A Stage 16 strategy is required for execution.');
    if((int)$task['run_consumed_units']+(int)$task['estimated_units']>(int)$task['run_budget_units'])throw new MgSwarmWorkflowException('Swarm budget is exhausted.');
    $input=json_decode((string)$task['input_json'],true,512,JSON_THROW_ON_ERROR);$actions=(array)($input['actions']??[]);
    if($actions===[])throw new MgSwarmWorkflowException('Executable swarm tasks require Stage 16 actions.');
    $route=mg_swarm_route_task($pdo,$task);$member=$route['member'];$provider=$route['provider_route'];
    $strategy=$pdo->prepare("SELECT * FROM agent_strategies WHERE id=? AND owner_user_id=? AND status='active' LIMIT 1 FOR UPDATE");
    $strategy->execute([(int)$task['strategy_id'],(int)$task['owner_user_id']]);$strategyRow=$strategy->fetch(PDO::FETCH_ASSOC);
    if(!$strategyRow)throw new MgSwarmWorkflowException('Assigned Stage 16 strategy is not active.');
    if((int)$strategyRow['agent_id']!==(int)$member['agent_id'])throw new MgSwarmWorkflowException('Strategy agent does not match routed team member.');
    $workflow=mg_agent_create_run($pdo,(int)$task['owner_user_id'],[
        'strategy_id'=>$strategyRow['public_id'],'idempotency_key'=>'swarm:'.$task['swarm_public_id'].':'.$task['task_key'],
        'trigger_type'=>'event','trigger_reference'=>$task['public_id'],
        'input'=>['swarm_run_id'=>$task['swarm_public_id'],'swarm_task_id'=>$task['public_id'],'objective'=>$task['objective'],'input'=>$input],
    ]);
    if((string)$workflow['status']==='queued'){
        mg_agent_execution_event($pdo,(int)$workflow['id'],null,'run_delegated_to_stage16',null,['source'=>'stage17_swarm','swarm_task_id'=>$task['public_id']]);
        mg_agent_plan_run($pdo,$workflow,$actions,(int)$task['owner_user_id']);
    }
    $pdo->prepare("UPDATE agent_swarm_tasks SET team_member_id=?,workflow_run_id=?,status='routed',route_json=?,reserved_units=estimated_units,started_at=COALESCE(started_at,NOW()),failure_message=NULL,updated_at=NOW() WHERE id=? AND status='ready'")
        ->execute([(int)$member['id'],(int)$workflow['id'],json_encode(['agent_id'=>$member['agent_public_id'],'role_key'=>$member['role_key'],'provider_route'=>$provider],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),(int)$task['id']]);
    $pdo->prepare("UPDATE agent_swarm_runs SET status='running',started_at=COALESCE(started_at,NOW()),updated_at=NOW() WHERE id=?")->execute([(int)$task['swarm_run_id']]);
    mg_swarm_event($pdo,(int)$task['swarm_run_id'],(int)$task['id'],'task_routed',null,['agent_id'=>$member['agent_public_id'],'workflow_run_id'=>$workflow['public_id']]);
    return ['processed'=>true,'task_id'=>$task['public_id'],'workflow_run_id'=>$workflow['public_id'],'duplicate'=>(bool)($workflow['duplicate']??false)];
}

function mg_swarm_sync_workflow(PDO $pdo,string $taskPublicId): array
{
    $stmt=$pdo->prepare("SELECT st.*,wr.status workflow_status,wr.result_json workflow_result,wr.failure_message workflow_failure FROM agent_swarm_tasks st INNER JOIN agent_workflow_runs wr ON wr.id=st.workflow_run_id WHERE st.public_id=? LIMIT 1 FOR UPDATE");
    $stmt->execute([$taskPublicId]);$task=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$task)throw new MgSwarmWorkflowException('Swarm task workflow not found.',404);
    if(!in_array((string)$task['status'],['routed','running'],true))return ['task_id'=>$taskPublicId,'status'=>$task['status'],'duplicate'=>true];
    if(!in_array((string)$task['workflow_status'],['completed','partially_completed','failed','canceled'],true))throw new MgSwarmWorkflowException('Stage 16 workflow is not terminal.');
    $success=in_array((string)$task['workflow_status'],['completed','partially_completed'],true);
    $to=$success?((bool)$task['requires_review']?'review_pending':'completed'):'failed';
    $pdo->prepare("UPDATE agent_swarm_tasks SET status=?,output_json=?,failure_message=?,consumed_units=reserved_units,completed_at=IF(? IN ('completed','failed'),NOW(),completed_at),updated_at=NOW() WHERE id=?")
        ->execute([$to,$task['workflow_result'],$task['workflow_failure'],$to,(int)$task['id']]);
    $pdo->prepare('UPDATE agent_swarm_runs SET consumed_units=LEAST(budget_units,consumed_units+?),updated_at=NOW() WHERE id=?')
        ->execute([(int)$task['reserved_units'],(int)$task['swarm_run_id']]);
    mg_swarm_event($pdo,(int)$task['swarm_run_id'],(int)$task['id'],'task_'.$to,null,['workflow_status'=>$task['workflow_status']]);
    if($to==='completed')mg_swarm_release_dependencies($pdo,(int)$task['swarm_run_id']);
    $runStatus=mg_swarm_reconcile_run($pdo,(int)$task['swarm_run_id']);
    return ['task_id'=>$taskPublicId,'status'=>$to,'run_status'=>$runStatus,'duplicate'=>false];
}

function mg_swarm_review_task(PDO $pdo,int $ownerUserId,array $input): array
{
    $taskPublicId=trim((string)($input['task_id']??''));$decision=trim((string)($input['decision']??''));$reason=mb_substr(trim((string)($input['reason']??'')),0,1000);
    if($taskPublicId===''||!in_array($decision,['approve','reject'],true))throw new MgSwarmWorkflowException('Review task and valid decision are required.',422);
    $stmt=$pdo->prepare('SELECT st.*,sr.owner_user_id,sr.public_id swarm_public_id FROM agent_swarm_tasks st INNER JOIN agent_swarm_runs sr ON sr.id=st.swarm_run_id WHERE st.public_id=? AND sr.owner_user_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$taskPublicId,$ownerUserId]);$task=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$task)throw new MgSwarmWorkflowException('Swarm review task not found.',404);
    $target=$decision==='approve'?'completed':'failed';
    if((string)$task['status']!=='review_pending'){
        if((string)$task['status']===$target)return ['task_id'=>$taskPublicId,'status'=>$target,'run_status'=>(string)mg_swarm_reconcile_run($pdo,(int)$task['swarm_run_id'],$ownerUserId),'duplicate'=>true];
        throw new MgSwarmWorkflowException('Review decision conflicts with the recorded task outcome.');
    }
    $pdo->prepare("UPDATE agent_swarm_tasks SET status=?,failure_message=IF(?='failed',?,NULL),completed_at=NOW(),updated_at=NOW() WHERE id=? AND status='review_pending'")
        ->execute([$target,$target,$reason!==''?$reason:'Task rejected during review.',(int)$task['id']]);
    mg_swarm_event($pdo,(int)$task['swarm_run_id'],(int)$task['id'],'task_review_'.$decision,$ownerUserId,['reason'=>$reason]);
    if($target==='completed')mg_swarm_release_dependencies($pdo,(int)$task['swarm_run_id']);
    $runStatus=mg_swarm_reconcile_run($pdo,(int)$task['swarm_run_id'],$ownerUserId);
    return ['task_id'=>$taskPublicId,'status'=>$target,'run_status'=>$runStatus,'duplicate'=>false];
}
