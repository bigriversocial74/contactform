<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(1);}
require_once dirname(__DIR__).'/api/db.php';
$sql=file_get_contents(dirname(__DIR__).'/database/stage_9d_microgift_operations.sql');
if(!is_string($sql)||trim($sql)===''){fwrite(STDERR,"Missing Stage 9D migration.\n");exit(1);}
try{mg_db()->exec($sql);echo "Stage 9D Microgift operations schema applied.\n";}catch(Throwable $e){fwrite(STDERR,"Stage 9D migration failed.\n");exit(1);}
