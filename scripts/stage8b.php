<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__).'/api/db.php';
$files=['stage_8b_entitlements_library.sql','stage_8c_entitlement_lifecycle.sql'];
try{
    foreach($files as $file){
        $sql=file_get_contents(dirname(__DIR__).'/database/'.$file);
        if(!is_string($sql)||trim($sql)===''){throw new RuntimeException('Missing Stage 8 migration: '.$file);}
        mg_db()->exec($sql);
    }
    echo "Stage 8 entitlement and lifecycle schema applied.\n";
}catch(Throwable $e){fwrite(STDERR,'FAILED: '.$e->getMessage()."\n");exit(1);}
