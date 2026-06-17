<?php
declare(strict_types=1);
require_once __DIR__.'/_alerts.php';

final class MgOpsQueueApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus=409){parent::__construct($message);}
}

function mg_ops_queue_can_read(PDO $pdo,int $actorUserId): bool
{
    return mg_ops_has($pdo,$actorUserId,'ops.alerts.assign')||mg_ops_has($pdo,$actorUserId,'ops.alerts.resolve');
}
function mg_ops_queue_require_read(PDO $pdo,int $actorUserId): void
{
    if(!mg_ops_queue_can_read($pdo,$actorUserId))throw new MgOpsQueueApiException('Ops queue read denied.',403);
}
function mg_ops_queue_request_key(array $input): string
{
    $key=trim((string)($input['request_key']??''));
    if($key==='')throw new MgOpsQueueApiException('Missing request key.',422);
    if(mb_strlen($key)>190)throw new MgOpsQueueApiException('Request key is too long.',422);
    return $key;
}
function mg_ops_queue_alert_array(array $r): array
{
    return ['alert_id'=>(string)$r['public_id'],'alert_key'=>(string)$r['alert_key'],'source_type'=>(string)$r['source_type'],'source_id'=>(string)$r['source_id'],'severity'=>(string)$r['severity'],'status'=>(string)$r['status'],'title'=>(string)$r['title'],'body'=>(string)$r['body'],'assigned_to_user_id'=>$r['assigned_to_user_id']===null?null:(int)$r['assigned_to_user_id'],'resolution_reason'=>$r['resolution_reason'],'created_at'=>(string)$r['created_at'],'updated_at'=>(string)$r['updated_at'],'resolved_at'=>$r['resolved_at']];
}
function mg_ops_queue_event_array(array $r): array
{
    return ['event_id'=>(string)$r['public_id'],'actor_user_id'=>$r['actor_user_id']===null?null:(int)$r['actor_user_id'],'event_type'=>(string)$r['event_type'],'before'=>$r['before_json']?json_decode((string)$r['before_json'],true):null,'after'=>$r['after_json']?json_decode((string)$r['after_json'],true):null,'created_at'=>(string)$r['created_at']];
}
function mg_ops_queue_list(PDO $pdo,array $input): array
{
    mg_ops_alert_install($pdo);$actor=(int)($input['actor_user_id']??0);mg_ops_queue_require_read($pdo,$actor);$where=[];$params=[];
    foreach(['status','severity','source_type'] as $f){if(isset($input[$f])&&trim((string)$input[$f])!==''){$where[]=$f.'=?';$params[]=(string)$input[$f];}}
    $limit=max(1,min(100,(int)($input['limit']??50)));$sql='SELECT * FROM ops_alerts'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY created_at ASC LIMIT '.$limit;$s=$pdo->prepare($sql);$s->execute($params);$items=[];while($row=$s->fetch(PDO::FETCH_ASSOC))$items[]=mg_ops_queue_alert_array($row);return ['items'=>$items,'count'=>count($items)];
}
function mg_ops_queue_detail(PDO $pdo,array $input): array
{
    mg_ops_alert_install($pdo);$actor=(int)($input['actor_user_id']??0);mg_ops_queue_require_read($pdo,$actor);$id=trim((string)($input['alert_id']??''));if($id==='')throw new MgOpsQueueApiException('Missing alert id.',422);$s=$pdo->prepare('SELECT * FROM ops_alerts WHERE public_id=? LIMIT 1');$s->execute([$id]);$alert=$s->fetch(PDO::FETCH_ASSOC);if(!$alert)throw new MgOpsQueueApiException('Ops alert not found.',404);$e=$pdo->prepare('SELECT * FROM ops_alert_events WHERE alert_id=? ORDER BY id ASC');$e->execute([(int)$alert['id']]);$events=[];while($row=$e->fetch(PDO::FETCH_ASSOC))$events[]=mg_ops_queue_event_array($row);return ['alert'=>mg_ops_queue_alert_array($alert),'events'=>$events];
}
function mg_ops_queue_assign(PDO $pdo,array $input): array
{
    return mg_ops_assign_alert($pdo,['actor_user_id'=>(int)($input['actor_user_id']??0),'alert_public_id'=>(string)($input['alert_id']??''),'assigned_to_user_id'=>(int)($input['assigned_to_user_id']??0),'event_key'=>mg_ops_queue_request_key($input)]);
}
function mg_ops_queue_resolve(PDO $pdo,array $input): array
{
    return mg_ops_resolve_alert($pdo,['actor_user_id'=>(int)($input['actor_user_id']??0),'alert_public_id'=>(string)($input['alert_id']??''),'resolution_reason'=>(string)($input['resolution_reason']??''),'event_key'=>mg_ops_queue_request_key($input)]);
}
function mg_ops_queue_route(PDO $pdo,string $action,array $input): array
{
    return match($action){'list'=>mg_ops_queue_list($pdo,$input),'detail'=>mg_ops_queue_detail($pdo,$input),'assign'=>mg_ops_queue_assign($pdo,$input),'resolve'=>mg_ops_queue_resolve($pdo,$input),default=>throw new MgOpsQueueApiException('Unknown action.',404)};
}
