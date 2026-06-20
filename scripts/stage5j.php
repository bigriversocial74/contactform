<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__).'/api/db.php';

$root=dirname(__DIR__);
$paths=[
    $root.'/database/stage_v1c_checkout_session_intent_authority.sql',
    $root.'/database/stage_3_commerce_microgift_fulfillment.sql',
    $root.'/database/stage_5j_foundation_reconciliation.sql',
];

try{
    $pdo=mg_db();
    foreach($paths as $path){
        $sql=file_get_contents($path);
        if(!is_string($sql)||trim($sql)===''){
            throw new RuntimeException('Stage 5J dependency schema not found: '.basename($path));
        }
        $pdo->exec($sql);
    }
    echo "Stage 5J checkout authority, fulfillment, and foundation schema applied.\n";
}catch(Throwable $e){
    fwrite(STDERR,'FAILED: '.$e->getMessage()."\n");
    exit(1);
}
