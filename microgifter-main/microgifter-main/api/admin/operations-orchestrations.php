<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/operations/_operations.php';
mg_require_method('GET');
$user=mg_require_permission('operations.orchestrations.view');
$pdo=mg_db();

try{
    $items=mg_operations_list_demand_orchestrations($pdo,[
        'status'=>$_GET['status']??'all',
        'signal_level'=>$_GET['signal_level']??'all',
        'orchestration_type'=>$_GET['orchestration_type']??'all',
        'before_id'=>$_GET['before_id']??0,
        'limit'=>$_GET['limit']??50,
    ]);
    mg_ok([
        'orchestrations'=>$items,
        'health'=>mg_operations_demand_orchestration_health($pdo),
        'next_before_id'=>$items!==[]?(int)end($items)['cursor_id']:null,
        'viewed_by_user_id'=>(int)$user['id'],
    ]);
}catch(InvalidArgumentException $e){
    mg_fail($e->getMessage(),422);
}catch(Throwable $e){
    mg_fail('Unable to load demand orchestration operations.',500);
}
