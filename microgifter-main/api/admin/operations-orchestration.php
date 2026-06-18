<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/operations/_operations.php';
mg_require_method('GET');
$user=mg_require_permission('operations.orchestrations.view');
$publicId=trim((string)($_GET['orchestration_id']??''));

try{
    $record=mg_operations_get_demand_orchestration(mg_db(),$publicId);
    if($record===null)mg_fail('Demand orchestration not found.',404);
    mg_ok(['orchestration'=>$record,'viewed_by_user_id'=>(int)$user['id']]);
}catch(InvalidArgumentException $e){
    mg_fail($e->getMessage(),422);
}catch(Throwable $e){
    mg_fail('Unable to load demand orchestration.',500);
}
