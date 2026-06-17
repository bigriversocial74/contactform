<?php
declare(strict_types=1);

require_once __DIR__ . '/_workflow.php';

$user=mg_require_permission('agent.approvals.decide');
$userId=(int)$user['id'];
$pdo=mg_db();

function mg_agent_approval_cursor_encode(array $row):string
{
    return rtrim(strtr(base64_encode(json_encode(['time'=>(string)$row['requested_at'],'id'=>(string)$row['approval_public_id']],JSON_THROW_ON_ERROR)),'+/','-_'),'=');
}

function mg_agent_approval_cursor_decode(?string $cursor):?array
{
    $cursor=trim((string)$cursor);if($cursor==='')return null;
    if(strlen($cursor)>500||preg_match('/^[A-Za-z0-9_-]+$/',$cursor)!==1)throw new InvalidArgumentException('Invalid approval cursor.');
    $padding=(4-strlen($cursor)%4)%4;$raw=base64_decode(strtr($cursor.str_repeat('=',$padding),'-_','+/'),true);$value=is_string($raw)?json_decode($raw,true):null;
    if(!is_array($value)||!isset($value['time'],$value['id'])||strtotime((string)$value['time'])===false||preg_match('/^[a-f0-9-]{36}$/i',(string)$value['id'])!==1)throw new InvalidArgumentException('Invalid approval cursor.');
    return $value;
}

function mg_agent_approval_row(PDO $pdo,string $approvalId,int $ownerUserId):array
{
    $stmt=$pdo->prepare('SELECT ar.public_id approval_public_id,ar.status approval_status,ar.owner_user_id,ar.requested_at,wr.public_id run_public_id FROM agent_approval_requests ar INNER JOIN agent_workflow_runs wr ON wr.id=ar.run_id WHERE ar.public_id=? AND ar.owner_user_id=? LIMIT 1');
    $stmt->execute([$approvalId,$ownerUserId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)throw new MgAgentWorkflowException('Approval request not found.',404);return $row;
}

if($_SERVER['REQUEST_METHOD']==='GET'){
    mg_rate_limit('agent.approvals.read','user:'.$userId,180,60);
    try{
        $status=strtolower(trim((string)($_GET['status']??'pending')));$allowed=['all','pending','approved','rejected','expired','canceled'];
        if(!in_array($status,$allowed,true))throw new InvalidArgumentException('Invalid approval status.');
        $limit=max(1,min((int)($_GET['limit']??20),50));$cursor=mg_agent_approval_cursor_decode(isset($_GET['cursor'])?(string)$_GET['cursor']:null);
        $pdo->beginTransaction();$expired=mg_agent_expire_approvals($pdo,$userId);$pdo->commit();
        $where=['ar.owner_user_id=?'];$params=[$userId];
        if($status!=='all'){$where[]='ar.status=?';$params[]=$status;}
        if($cursor!==null){$where[]='(ar.requested_at<? OR (ar.requested_at=? AND ar.public_id<?))';array_push($params,$cursor['time'],$cursor['time'],$cursor['id']);}
        $sql='SELECT ar.public_id approval_public_id,ar.status approval_status,ar.owner_user_id,ar.requested_at,wr.public_id run_public_id FROM agent_approval_requests ar INNER JOIN agent_workflow_runs wr ON wr.id=ar.run_id WHERE '.implode(' AND ',$where).' ORDER BY ar.requested_at DESC,ar.public_id DESC LIMIT '.($limit+1);
        $stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);$more=count($rows)>$limit;if($more)array_pop($rows);
        $items=[];foreach($rows as $row)$items[]=mg_agent_approval_projection($pdo,$row);
        $summaryStmt=$pdo->prepare("SELECT status,COUNT(*) total FROM agent_approval_requests WHERE owner_user_id=? GROUP BY status");$summaryStmt->execute([$userId]);$summary=array_fill_keys(['pending','approved','rejected','expired','canceled'],0);foreach($summaryStmt->fetchAll(PDO::FETCH_ASSOC) as $row)$summary[(string)$row['status']]=(int)$row['total'];
        $next=$more&&$rows!==[]?mg_agent_approval_cursor_encode($rows[array_key_last($rows)]):null;
        header('Cache-Control: private, no-store, max-age=0');
        mg_event('agent.approvals_read',['status'=>$status,'result_count'=>count($items),'expired_reconciled'=>$expired],$userId);
        mg_ok(['approvals'=>['items'=>$items,'next_cursor'=>$next,'has_more'=>$more,'limit'=>$limit,'status'=>$status],'summary'=>$summary,'policy'=>['bulk_approval_enabled'=>false,'high_risk_reason_required'=>true]]);
    }catch(InvalidArgumentException $error){if($pdo->inTransaction())$pdo->rollBack();mg_fail($error->getMessage(),422);}
    catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();mg_security_log('error','agent.approvals_read_failed','Agent approval queue read failed.',['exception_class'=>$error::class],$userId);mg_fail('Unable to load approval requests.',500);}
}

mg_require_method('POST');
$input=mg_input();mg_require_csrf_for_write($input);mg_rate_limit('agent.approvals.write','user:'.$userId,60,60);
$pdo->beginTransaction();
try{
    mg_agent_expire_approvals($pdo,$userId);
    $result=mg_agent_decide_approval($pdo,$userId,$input);
    $row=mg_agent_approval_row($pdo,(string)$result['approval_id'],$userId);
    $approval=mg_agent_approval_projection($pdo,$row);
    $pdo->commit();
    mg_audit('agent.approval_'.$result['status'],'agent_approval',['approval_id'=>$result['approval_id'],'action_id'=>$approval['action']['id'],'run_id'=>$approval['plan']['id'],'duplicate'=>$result['duplicate']],$userId);
    mg_event('agent.approval_'.$result['status'],['approval_id'=>$result['approval_id'],'run_id'=>$approval['plan']['id'],'risk'=>$approval['action']['risk']],$userId);
    mg_ok(['approval'=>$approval,'duplicate'=>$result['duplicate'],'run_status'=>$result['run_status']??$approval['plan']['status']],'Approval decision recorded.');
}catch(MgAgentWorkflowException $error){if($pdo->inTransaction())$pdo->rollBack();mg_fail($error->getMessage(),$error->httpStatus);}
catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();mg_security_log('error','agent.approval_mutation_failed','Agent approval decision failed.',['exception_class'=>$error::class],$userId);mg_fail('Unable to decide approval.',500);}
