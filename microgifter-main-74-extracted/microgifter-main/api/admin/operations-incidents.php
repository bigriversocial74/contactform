<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/operations/_operations.php';
$user=mg_require_permission('operations.incidents.manage');
$pdo=mg_db();
if($_SERVER['REQUEST_METHOD']==='GET'){
    $status=trim((string)($_GET['status']??'all'));
    $sql='SELECT public_id,incident_key,title,severity,status,service_key,summary,impact_summary,mitigation_summary,root_cause_summary,opened_at,mitigated_at,resolved_at,closed_at,updated_at FROM operational_incidents';$params=[];
    if($status!=='all'){$sql.=' WHERE status=?';$params[]=$status;}
    $sql.=' ORDER BY FIELD(severity,\'sev1\',\'sev2\',\'sev3\',\'sev4\'),opened_at DESC LIMIT 100';
    $stmt=$pdo->prepare($sql);$stmt->execute($params);mg_ok(['incidents'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}
mg_require_method('POST');
$input=mg_input();mg_require_csrf_for_write($input);$action=trim((string)($input['action']??'create'));
$pdo->beginTransaction();
try{
    if($action==='create'){$incident=mg_operations_create_incident($pdo,(int)$user['id'],$input);$result=['incident'=>$incident];}
    else{$stmt=$pdo->prepare('SELECT * FROM operational_incidents WHERE public_id=? LIMIT 1 FOR UPDATE');$stmt->execute([trim((string)($input['incident_id']??''))]);$incident=$stmt->fetch(PDO::FETCH_ASSOC);if(!$incident)throw new RuntimeException('Incident not found.');$updated=mg_operations_transition_incident($pdo,$incident,$action,(int)$user['id'],$input);$result=['incident_id'=>$incident['public_id'],'status'=>$updated['status']];}
    $pdo->commit();mg_audit('operations.incident_'.$action,'operational_incident',$result,(int)$user['id']);mg_ok($result,'Incident updated.',$action==='create'?201:200);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),422);}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),409);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to update incident.',500);}
