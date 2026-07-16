<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__).'/api/db.php';
require_once dirname(__DIR__).'/includes/personal-gifting-agent.php';
$limit=max(1,min(500,(int)($argv[1]??100)));
try{$pdo=mg_db();$scan=mg_personal_agent_recovery_scan($pdo,$limit);$delivery=mg_personal_agent_recovery_process_due($pdo,$limit);echo json_encode(['ok'=>true,'scan'=>$scan,'delivery'=>$delivery],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;exit(($delivery['failed']??0)>0?2:0);}catch(Throwable $e){fwrite(STDERR,json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL);exit(1);}
