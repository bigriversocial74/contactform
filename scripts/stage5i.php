<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__).'/api/db.php';

$root=dirname(__DIR__);
$paths=[
    $root.'/database/stage_5i_payments_checkout_reconciliation.sql',
    $root.'/database/stage_v1c_checkout_session_intent_authority.sql',
];

try{
    $pdo=mg_db();
    foreach($paths as $path){
        $sql=file_get_contents($path);
        if(!is_string($sql)||trim($sql)===''){
            throw new RuntimeException('Stage 5I migration not found: '.basename($path));
        }
        $pdo->exec($sql);
    }
    echo "Stage 5I financial and checkout-session authority schema applied.\n";
}catch(Throwable $e){
    fwrite(STDERR,'FAILED: '.$e->getMessage()."\n");
    exit(1);
}
