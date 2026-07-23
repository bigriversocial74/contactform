<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__,2) . '/includes/privacy/account-erasure.php';

$actor=mg_require_api_user();
$actorId=(int)$actor['id'];
$permissions=is_array($actor['permissions']??null)?$actor['permissions']:[];
$roles=is_array($actor['roles']??null)?$actor['roles']:[];
$isSuper=in_array('super_admin',$roles,true);
$canView=$isSuper||in_array('admin.privacy_requests.view',$permissions,true)||in_array('admin.privacy_requests.manage',$permissions,true);
$canManage=$isSuper||in_array('admin.privacy_requests.manage',$permissions,true);
if(!$canView)mg_fail('Permission denied.',403);
$pdo=mg_db();

function mg_admin_privacy_detail(PDO $pdo,int $requestId): ?array
{
    $request=mg_privacy_request_by_id($pdo,$requestId);
    if(!$request)return null;
    if(!empty($request['user_id'])){
        $user=$pdo->prepare('SELECT id,email,display_name,full_name,status,privacy_state,deletion_requested_at,deletion_due_at,anonymized_at FROM users WHERE id=? LIMIT 1');
        $user->execute([(int)$request['user_id']]);
        $request['user']=$user->fetch(PDO::FETCH_ASSOC)?:null;
    }else{$request['user']=null;}
    $collections=[
        'events'=>['SELECT e.*,u.display_name AS actor_name FROM privacy_request_events e LEFT JOIN users u ON u.id=e.actor_user_id WHERE e.request_id=? ORDER BY e.id DESC LIMIT 100','details_json'],
        'holds'=>['SELECT h.*,p.display_name AS placed_by_name,r.display_name AS released_by_name FROM privacy_legal_holds h LEFT JOIN users p ON p.id=h.placed_by_user_id LEFT JOIN users r ON r.id=h.released_by_user_id WHERE h.request_id=? ORDER BY h.id DESC','scope_json'],
        'handoffs'=>['SELECT h.*,u.display_name AS merchant_name,u.email AS merchant_email FROM privacy_merchant_handoffs h JOIN users u ON u.id=h.merchant_user_id WHERE h.request_id=? ORDER BY h.id',''],
        'actions'=>['SELECT * FROM privacy_data_actions WHERE request_id=? ORDER BY id','details_json'],
    ];
    foreach($collections as $key=>[$sql,$jsonColumn]){
        $stmt=$pdo->prepare($sql);$stmt->execute([$requestId]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
        if($jsonColumn!=='')foreach($rows as &$row){$row['details']=json_decode((string)($row[$jsonColumn]??''),true)?:[];unset($row[$jsonColumn]);}unset($row);
        $request[$key]=$rows;
    }
    return $request;
}

if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
    $requestId=(int)($_GET['request_id']??0);
    if($requestId>0){
        $item=mg_admin_privacy_detail($pdo,$requestId);
        if(!$item)mg_fail('Privacy request not found.',404);
        mg_ok(['item'=>$item,'can_manage'=>$canManage]);
    }
    $items=mg_privacy_list_requests($pdo,[
        'status'=>trim((string)($_GET['status']??'')),
        'jurisdiction'=>trim((string)($_GET['jurisdiction']??'')),
        'q'=>trim((string)($_GET['q']??'')),
    ]);
    mg_ok(['items'=>$items,'can_manage'=>$canManage,'statuses'=>['submitted','identity_verified','acknowledged','under_review','approved','restricted','blocked_by_hold','processing','completed','partially_completed','denied','cancelled']]);
}

mg_require_method('POST');
if(!$canManage)mg_fail('Permission denied.',403);
$input=mg_input();
mg_require_csrf_for_write($input);
mg_rate_limit('admin.privacy_requests.write','user:'.$actorId,120,60);
$action=strtolower(trim((string)($input['action']??'')));
$requestId=(int)($input['request_id']??0);
if($requestId<1)mg_fail('Valid request ID required.',422);
$reason=trim((string)($input['reason']??''));
if(in_array($action,['deny','extend','add_hold','release_hold'],true)&&(mb_strlen($reason)<8||mb_strlen($reason)>500))mg_fail('Provide a reason between 8 and 500 characters.',422);

try{
    $pdo->beginTransaction();
    $request=mg_privacy_request_by_id($pdo,$requestId,true);
    if(!$request)throw new RuntimeException('Privacy request not found.');
    $userId=(int)($request['user_id']??0);
    $result=[];
    switch($action){
        case 'acknowledge':
            $pdo->prepare('UPDATE privacy_requests SET status=IF(status="submitted","acknowledged",status),acknowledged_at=COALESCE(acknowledged_at,NOW()),assigned_to_user_id=COALESCE(assigned_to_user_id,?),updated_at=NOW() WHERE id=?')->execute([$actorId,$requestId]);
            mg_privacy_event($pdo,$requestId,'request_acknowledged',[],$actorId);
            $result=['status'=>'acknowledged'];
            break;
        case 'approve':
            $pdo->prepare('UPDATE privacy_requests SET decision="approve",status=IF(status IN ("submitted","identity_verified","acknowledged","under_review"),"approved",status),decision_reason=?,assigned_to_user_id=?,updated_at=NOW() WHERE id=?')->execute([$reason!==''?$reason:'Approved after privacy review.',$actorId,$requestId]);
            if($userId>0 && (string)$request['status']!=='restricted')$result=mg_privacy_restrict_account($pdo,$requestId,$userId);
            $result['status']='approved';
            mg_privacy_event($pdo,$requestId,'request_approved',['reason'=>$reason],$actorId);
            break;
        case 'deny':
            if(in_array((string)$request['status'],['processing','completed'],true))throw new RuntimeException('A processing or completed request cannot be denied.');
            $pdo->prepare('UPDATE privacy_requests SET status="denied",decision="deny",decision_reason=?,completed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$reason,$requestId]);
            if($userId>0 && mg_privacy_column_exists($pdo,'users','privacy_state')){
                $pdo->prepare('UPDATE users SET status="active",privacy_state="active",deletion_requested_at=NULL,deletion_due_at=NULL,privacy_restricted_at=NULL,updated_at=NOW() WHERE id=? AND privacy_state<>"anonymized"')->execute([$userId]);
            }
            mg_privacy_event($pdo,$requestId,'request_denied',['reason'=>$reason],$actorId);
            $result=['status'=>'denied'];
            break;
        case 'extend':
            $newDue=trim((string)($input['new_due_at']??''));
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',$newDue,new DateTimeZone('UTC'));
            if(!$date)throw new RuntimeException('Provide a valid extension date.');
            $max=(new DateTimeImmutable((string)$request['response_due_at'],new DateTimeZone('UTC')))->modify('+60 days');
            if($date>$max)throw new RuntimeException('The extension exceeds the supported two-month maximum.');
            $pdo->prepare('UPDATE privacy_requests SET extended_due_at=?,extension_reason=?,updated_at=NOW() WHERE id=?')->execute([$date->format('Y-m-d 23:59:59'),$reason,$requestId]);
            mg_privacy_event($pdo,$requestId,'deadline_extended',['new_due_at'=>$date->format('Y-m-d'),'reason'=>$reason],$actorId);
            $result=['extended_due_at'=>$date->format('Y-m-d 23:59:59')];
            break;
        case 'add_hold':
            $scope=trim((string)($input['scope']??'all'));
            $stmt=$pdo->prepare('INSERT INTO privacy_legal_holds (request_id,user_id,status,reason,scope_json,placed_by_user_id,placed_at) VALUES (?,? ,"active",?,?,?,NOW())');
            $stmt->execute([$requestId,$userId?:null,$reason,json_encode(['scope'=>$scope],JSON_UNESCAPED_SLASHES),$actorId]);
            $holdId=(int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE privacy_requests SET status="blocked_by_hold",updated_at=NOW() WHERE id=?')->execute([$requestId]);
            mg_privacy_event($pdo,$requestId,'legal_hold_added',['hold_id'=>$holdId,'reason'=>$reason,'scope'=>$scope],$actorId);
            $result=['hold_id'=>$holdId,'status'=>'blocked_by_hold'];
            break;
        case 'release_hold':
            $holdId=(int)($input['hold_id']??0);
            $stmt=$pdo->prepare('UPDATE privacy_legal_holds SET status="released",released_by_user_id=?,released_at=NOW(),release_reason=? WHERE id=? AND request_id=? AND status="active"');
            $stmt->execute([$actorId,$reason,$holdId,$requestId]);
            if($stmt->rowCount()<1)throw new RuntimeException('Active legal hold not found.');
            $remaining=mg_privacy_active_hold($pdo,$requestId,$userId?:null);
            if(!$remaining)$pdo->prepare('UPDATE privacy_requests SET status=IF(restricted_at IS NULL,"under_review","restricted"),updated_at=NOW() WHERE id=?')->execute([$requestId]);
            mg_privacy_event($pdo,$requestId,'legal_hold_released',['hold_id'=>$holdId,'reason'=>$reason],$actorId);
            $result=['hold_id'=>$holdId,'released'=>true];
            break;
        case 'handoff_complete':
            $handoffId=(int)($input['handoff_id']??0);
            $stmt=$pdo->prepare('UPDATE privacy_merchant_handoffs SET status="completed",completed_at=NOW(),notes=?,updated_at=NOW() WHERE id=? AND request_id=?');
            $stmt->execute([$reason!==''?$reason:null,$handoffId,$requestId]);
            if($stmt->rowCount()<1)throw new RuntimeException('Merchant handoff not found.');
            mg_privacy_event($pdo,$requestId,'merchant_handoff_completed',['handoff_id'=>$handoffId],$actorId);
            $result=['handoff_id'=>$handoffId,'completed'=>true];
            break;
        case 'finalize':
            $result=mg_privacy_finalize_request($pdo,$requestId,$actorId,!empty($input['force']));
            break;
        default:
            throw new RuntimeException('Unsupported privacy action.');
    }
    $pdo->commit();
    mg_audit('admin.privacy.'.$action,'privacy_request',['request_id'=>$requestId,'reason'=>$reason,'result'=>$result],$actorId);
    mg_security_log('info','admin.privacy.completed','Privacy administration action completed.',['request_id'=>$requestId,'action'=>$action],$actorId);
    mg_ok(['result'=>$result,'request'=>mg_admin_privacy_detail($pdo,$requestId)],'Privacy action completed.');
}catch(RuntimeException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('warning','admin.privacy.rejected','Privacy administration action rejected.',['request_id'=>$requestId,'action'=>$action,'reason'=>$error->getMessage()],$actorId);
    mg_fail($error->getMessage(),422);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail_unexpected($error,'admin.privacy.failed','Unable to complete the privacy action.',500,['request_id'=>$requestId,'action'=>$action],$actorId);
}
