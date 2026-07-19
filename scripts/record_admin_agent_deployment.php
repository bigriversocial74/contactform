<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__).'/includes/app.php';
require_once dirname(__DIR__).'/includes/admin-agent-phase2-remediation.php';

$options=getopt('',['commit:','branch::','environment::','source::','label::','deployed-at::']);

try{
    $pdo=mg_db();
    if(!mg_admin_agent_phase2_ready($pdo)){
        fwrite(STDERR,"Main Admin Agent Phase 2 SQL migration is required: database/20260718_main_admin_agent_phase2.sql\n");
        exit(2);
    }
    $result=mg_admin_agent_phase2_record_deployment($pdo,null,[
        'commit_sha'=>(string)($options['commit']??''),
        'branch_name'=>(string)($options['branch']??'integration-from-repair-20260628'),
        'environment_key'=>(string)($options['environment']??'production'),
        'source_type'=>(string)($options['source']??'cli'),
        'release_label'=>(string)($options['label']??''),
        'deployed_at'=>(string)($options['deployed-at']??''),
    ]);
    mg_admin_agent_phase2_correlate($pdo);
    echo json_encode(['ok'=>true,'deployment'=>$result],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit(0);
}catch(InvalidArgumentException $error){
    fwrite(STDERR,json_encode(['ok'=>false,'error'=>$error->getMessage()],JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(2);
}catch(Throwable $error){
    fwrite(STDERR,json_encode(['ok'=>false,'error'=>'Unable to record deployment.','exception_class'=>$error::class],JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(1);
}
