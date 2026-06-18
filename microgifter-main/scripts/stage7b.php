<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__).'/api/db.php';
$sql=file_get_contents(dirname(__DIR__).'/database/stage_7b_money_engine.sql');
if(!is_string($sql)||trim($sql)===''){fwrite(STDERR,"Stage 7B migration not found.\n");exit(1);}
try{mg_db()->exec($sql);echo "Stage 7B money engine schema applied.\n";}catch(Throwable $e){fwrite(STDERR,'FAILED: '.$e->getMessage()."\n");exit(1);}
