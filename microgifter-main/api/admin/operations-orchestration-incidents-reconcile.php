<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/operations/_orchestration_recovery.php';
mg_require_method('POST');
$user=mg_require_permission('operations.incidents.manage');
$input=mg_input();
mg_require_csrf_for_write($input);
$pdo=mg_db();
$pdo->beginTransaction();
try{
    $result=mg_operations_reconcile_demand_incidents($pdo,(int)$user['id']);
    $pdo->commit();
    mg_audit('operations.orchestration_incidents_reconcile','demand_orchestration',$result,(int)$user['id']);
    mg_ok(['incidents'=>$result],'Demand orchestration incidents reconciled.');
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail('Unable to reconcile demand orchestration incidents.',500);
}
