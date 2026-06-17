<?php
declare(strict_types=1);

require_once __DIR__ . '/_orchestration_attempts.php';

function mg_operations_retry_demand_orchestration_coordinated(PDO $pdo,int $adminUserId,string $publicId,string $idempotencyKey,string $reason,?callable $failureHook=null): array
{
    $result=mg_operations_retry_demand_orchestration_safe($pdo,$adminUserId,$publicId,$idempotencyKey,$reason,$failureHook);
    if(!empty($result['duplicate']))return $result;
    $root=mg_operations_orchestration_load($pdo,$publicId);
    $attempt=$pdo->prepare('SELECT strategy_id,strategy_version,team_id,orchestration_type FROM demand_signal_orchestration_attempts WHERE public_id=? LIMIT 1');
    $attempt->execute([(string)$result['attempt_id']]);
    $active=$attempt->fetch(PDO::FETCH_ASSOC);
    if($active){
        $pdo->prepare('UPDATE demand_signal_orchestrations SET strategy_id=COALESCE(strategy_id,?),strategy_version=COALESCE(strategy_version,?),team_id=?,orchestration_type=?,updated_at=NOW() WHERE id=?')
            ->execute([$active['strategy_id'],$active['strategy_version'],$active['team_id'],$active['orchestration_type'],(int)$root['id']]);
    }
    return $result;
}
