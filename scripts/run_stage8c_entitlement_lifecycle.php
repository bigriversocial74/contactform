<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){exit(1);}
require_once dirname(__DIR__) . '/api/db.php';
$path=dirname(__DIR__) . '/database/stage_8c_entitlement_lifecycle.sql';
$sql=file_get_contents($path);
if($sql===false){fwrite(STDERR,"Stage 8C schema file not found.\n");exit(1);}
try{
    mg_db()->exec($sql);
    echo "Stage 8C entitlement lifecycle schema applied.\n";
}catch(Throwable $e){
    fwrite(STDERR,"Stage 8C schema failed.\n");
    exit(1);
}
