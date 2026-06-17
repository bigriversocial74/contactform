<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/demand/_snapshot.php';

$horizon=max(1,min((int)($argv[1]??30),365));
$asOfInput=trim((string)($argv[2]??'now'));
$asOf=new DateTimeImmutable($asOfInput,new DateTimeZone('UTC'));
[$windowStart,$windowEnd]=mg_demand_snapshot_window($asOf,$horizon);
$pdo=mg_db();
$scopes=mg_demand_snapshot_scopes($pdo,$horizon,$asOf);
$summary=['scopes'=>0,'snapshots'=>0,'signals'=>0,'horizon_days'=>$horizon,'snapshot_date'=>$windowStart->format('Y-m-d'),'window_end'=>$windowEnd->format('Y-m-d')];
foreach($scopes as $scope){
    $pdo->beginTransaction();
    try{
        $snapshot=mg_demand_build_windowed_snapshot($pdo,(int)$scope['merchant_user_id'],$scope['location_id']!==null?(int)$scope['location_id']:null,$scope['product_id']!==null?(int)$scope['product_id']:null,$horizon,$asOf);
        $signals=mg_demand_emit_agent_signals($pdo,$snapshot);
        $pdo->commit();
        $summary['scopes']++;$summary['snapshots']++;$summary['signals']+=count($signals);
    }catch(Throwable $error){
        if($pdo->inTransaction())$pdo->rollBack();
        fwrite(STDERR,'Demand scope failed: '.$error->getMessage().PHP_EOL);
    }
}
fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
