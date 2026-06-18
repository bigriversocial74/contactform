<?php
declare(strict_types=1);

require_once __DIR__ . '/_execution.php';
mg_require_method('POST');
$user=mg_require_permission('agent.strategies.manage');
$input=mg_input();mg_require_csrf_for_write($input);
$strategyId=trim((string)($input['strategy_id']??''));$action=trim((string)($input['action']??''));
if($strategyId===''||!in_array($action,['activate','pause','retire','restore'],true))mg_fail('Strategy and valid action are required.',422);
$pdo=mg_db();$pdo->beginTransaction();
try{
    $strategy=mg_agent_strategy_owned($pdo,$strategyId,(int)$user['id'],true);
    $from=(string)$strategy['status'];
    $allowed=[
        'activate'=>['draft','paused'],
        'pause'=>['active'],
        'retire'=>['draft','active','paused'],
        'restore'=>['retired'],
    ];
    if(!in_array($from,$allowed[$action],true))throw new RuntimeException('Strategy cannot perform this transition.');
    $to=match($action){'activate'=>'active','pause'=>'paused','retire'=>'retired','restore'=>'draft'};
    $pdo->prepare('UPDATE agent_strategies SET status=?,version_no=version_no+1,updated_at=NOW() WHERE id=?')->execute([$to,(int)$strategy['id']]);
    $pdo->commit();
    mg_audit('agent.strategy_'.$action,'agent_strategy',['strategy_id'=>$strategyId,'from_status'=>$from,'to_status'=>$to],(int)$user['id']);
    mg_ok(['strategy_id'=>$strategyId,'status'=>$to],'Agent strategy updated.');
}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),409);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to update agent strategy.',500);}
