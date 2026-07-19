<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__).'/includes/app.php';
require_once dirname(__DIR__).'/includes/admin-agent-phase2-remediation.php';

$options=getopt('',['trigger::']);
$trigger=strtolower(trim((string)($options['trigger']??'scheduled')));
if(!in_array($trigger,['scheduled','manual','api'],true)) $trigger='scheduled';

try{
    $pdo=mg_db();
    if(!mg_admin_agent_phase2_ready($pdo)){
        fwrite(STDERR,"Main Admin Agent Phase 2 SQL migration is required: database/20260718_main_admin_agent_phase2.sql\n");
        exit(2);
    }
    $result=mg_admin_agent_phase2_run($pdo,['trigger_source'=>$trigger]);
    echo json_encode(['ok'=>true,'result'=>$result],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit(0);
}catch(Throwable $error){
    fwrite(STDERR,json_encode(['ok'=>false,'error'=>'Main Admin Agent Phase 2 failed.','exception_class'=>$error::class],JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(1);
}
