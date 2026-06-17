<?php
declare(strict_types=1);

require_once __DIR__ . '/_execution.php';

$user=mg_require_permission('agent.strategies.manage');
$userId=(int)$user['id'];
$pdo=mg_db();

function mg_agent_strategy_cursor_encode(array $row): string
{
    return rtrim(strtr(base64_encode(json_encode(['time'=>(string)$row['updated_at'],'id'=>(string)$row['public_id']],JSON_THROW_ON_ERROR)),'+/','-_'),'=');
}

function mg_agent_strategy_cursor_decode(?string $cursor): ?array
{
    $cursor=trim((string)$cursor);
    if($cursor==='')return null;
    if(strlen($cursor)>500||preg_match('/^[A-Za-z0-9_-]+$/',$cursor)!==1)throw new InvalidArgumentException('Invalid strategy cursor.');
    $padding=(4-strlen($cursor)%4)%4;
    $raw=base64_decode(strtr($cursor.str_repeat('=',$padding),'-_','+/'),true);
    $value=is_string($raw)?json_decode($raw,true):null;
    if(!is_array($value)||!isset($value['time'],$value['id'])||strtotime((string)$value['time'])===false||preg_match('/^[a-f0-9-]{36}$/i',(string)$value['id'])!==1)throw new InvalidArgumentException('Invalid strategy cursor.');
    return $value;
}

if($_SERVER['REQUEST_METHOD']==='GET'){
    mg_rate_limit('agent.strategies.read','user:'.$userId,180,60);
    try{
        $status=strtolower(trim((string)($_GET['status']??'all')));
        if(!in_array($status,array_merge(['all'],MG_AGENT_STRATEGY_STATUSES),true))throw new InvalidArgumentException('Invalid strategy status.');
        $agentRef=trim((string)($_GET['agent_id']??''));
        $limit=max(1,min((int)($_GET['limit']??24),50));
        $cursor=mg_agent_strategy_cursor_decode(isset($_GET['cursor'])?(string)$_GET['cursor']:null);
        $where=['s.owner_user_id=?','a.user_id=s.owner_user_id'];$params=[$userId];
        if($status!=='all'){$where[]='s.status=?';$params[]=$status;}
        if($agentRef!==''){$where[]='a.public_id=?';$params[]=$agentRef;}
        if($cursor!==null){$where[]='(s.updated_at<? OR (s.updated_at=? AND s.public_id<?))';array_push($params,$cursor['time'],$cursor['time'],$cursor['id']);}
        $sql='SELECT s.*,a.public_id agent_public_id,a.name agent_name,a.runtime_status agent_runtime_status,a.lifecycle_status agent_lifecycle_status FROM agent_strategies s INNER JOIN agents a ON a.id=s.agent_id WHERE '.implode(' AND ',$where).' ORDER BY s.updated_at DESC,s.public_id DESC LIMIT '.($limit+1);
        $stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasMore=count($rows)>$limit;if($hasMore)array_pop($rows);
        $next=$hasMore&&$rows!==[]?mg_agent_strategy_cursor_encode($rows[array_key_last($rows)]):null;
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok(['strategies'=>['items'=>array_map('mg_agent_strategy_projection',$rows),'next_cursor'=>$next,'has_more'=>$hasMore,'limit'=>$limit,'status'=>$status]]);
    }catch(InvalidArgumentException $error){mg_fail($error->getMessage(),422);}
}

mg_require_method('POST');
$input=mg_input();
mg_require_csrf_for_write($input);
mg_rate_limit('agent.strategies.write','user:'.$userId,60,60);
$action=strtolower(trim((string)($input['action']??'create')));
if(!in_array($action,['create','update','activate','pause','retire'],true))mg_fail('Invalid strategy action.',422);

$pdo->beginTransaction();
try{
    $duplicate=false;
    if($action==='create')$strategy=mg_agent_create_strategy($pdo,$userId,$input);
    elseif($action==='update')$strategy=mg_agent_update_strategy($pdo,$userId,$input);
    else{
        $target=match($action){'activate'=>'active','pause'=>'paused','retire'=>'retired'};
        $strategy=mg_agent_transition_strategy($pdo,$userId,$input,$target);
        $duplicate=(bool)($strategy['duplicate']??false);
    }
    $projected=mg_agent_strategy_projection($strategy);
    $pdo->commit();
    mg_audit('agent.strategy_'.$action,'agent_strategy',['strategy_id'=>$projected['id'],'agent_id'=>$projected['agent']['id'],'status'=>$projected['status'],'version'=>$projected['version'],'duplicate'=>$duplicate],$userId);
    mg_event('agent.strategy_'.$action,['strategy_id'=>$projected['id'],'status'=>$projected['status'],'version'=>$projected['version']],$userId);
    mg_ok(['strategy'=>$projected,'duplicate'=>$duplicate],$action==='create'?'Agent strategy created.':'Agent strategy updated.',$action==='create'?201:200);
}catch(InvalidArgumentException $error){if($pdo->inTransaction())$pdo->rollBack();mg_fail($error->getMessage(),422);}
catch(RuntimeException $error){if($pdo->inTransaction())$pdo->rollBack();mg_fail($error->getMessage(),409);}
catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','agent.strategy_mutation_failed','Agent strategy mutation failed.',['action'=>$action,'exception_class'=>$error::class],$userId);
    mg_fail('Unable to update agent strategy.',500);
}
