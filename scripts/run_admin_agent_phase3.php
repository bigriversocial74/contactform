<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-agent-phase3-remediation.php';

$options=getopt('', ['trigger::','environment::']);
$trigger=preg_replace('/[^a-z0-9_-]/','',strtolower((string)($options['trigger']??'scheduled')))?:'scheduled';
$environment=preg_replace('/[^a-z0-9_-]/','',strtolower((string)($options['environment']??getenv('MG_DEPLOY_ENV')?:'production')))?:'production';

try{
    $result=mg_admin_agent_phase3_run_hardened(mg_db(),[
        'trigger_source'=>$trigger,
        'environment_key'=>$environment,
    ]);
    fwrite(STDOUT,json_encode(['ok'=>true,'data'=>$result,'generated_at'=>gmdate('c')],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL);
    exit(0);
}catch(Throwable $error){
    if(function_exists('mg_security_log')) mg_security_log('error','admin_agent.phase3_runner_failed','Main Admin Agent Phase 3 scheduled runner failed.',['exception_class'=>$error::class],null);
    fwrite(STDERR,json_encode(['ok'=>false,'message'=>'Main Admin Agent Phase 3 run failed.','exception_class'=>$error::class,'generated_at'=>gmdate('c')],JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT).PHP_EOL);
    exit(1);
}
