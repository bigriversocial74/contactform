<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){
    fwrite(STDERR,"This worker must run from the command line.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/api/communications/_loyalty_quest_worker.php';

$limit=50;
foreach(array_slice($argv,1) as $argument){
    if(preg_match('/^--limit=(\d+)$/',$argument,$match)===1)$limit=max(1,min(200,(int)$match[1]));
}

try{
    $result=mg_lqn_worker_run(mg_db(),$limit);
    echo json_encode(['ok'=>true,'limit'=>$limit,'result'=>$result],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit(0);
}catch(Throwable $error){
    if(function_exists('mg_security_log'))mg_security_log('error','communications.loyalty_quest_cli_failed','Loyalty Quest CLI worker failed.',['exception_class'=>$error::class,'message'=>$error->getMessage()]);
    fwrite(STDERR,json_encode(['ok'=>false,'error'=>'Loyalty Quest notification worker failed.','exception_class'=>$error::class],JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(1);
}
