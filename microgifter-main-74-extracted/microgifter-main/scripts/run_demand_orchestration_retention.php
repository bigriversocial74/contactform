<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/operations/_retention.php';

$pdo=mg_db();
$stmt=$pdo->prepare("SELECT * FROM retention_policies WHERE policy_key='demand_orchestration_events_365d' AND status='active' LIMIT 1");
$stmt->execute();
$policy=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$policy)throw new RuntimeException('Demand orchestration retention policy is not active.');
$result=mg_retention_run_policy($pdo,$policy);
fwrite(STDOUT,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
