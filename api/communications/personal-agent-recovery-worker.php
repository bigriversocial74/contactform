<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__,2) . '/includes/personal-gifting-agent.php';
$user=mg_require_permission('admin.users.view');
$pdo=mg_db();
$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
if($method==='GET'){
 try{mg_personal_agent_recovery_require_schema($pdo);$counts=$pdo->query("SELECT status,COUNT(*) count FROM personal_agent_opportunity_followups GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR)?:[];mg_ok(['schema_ready'=>true,'counts'=>$counts]);}
 catch(RuntimeException $e){mg_fail($e->getMessage(),503);}
}
mg_require_method('POST');
$input=mg_input();mg_require_csrf_for_write($input);$limit=max(1,min(200,(int)($input['limit']??50)));
try{
 $scan=mg_personal_agent_recovery_scan($pdo,$limit);
 $delivery=mg_personal_agent_recovery_process_due($pdo,$limit);
 $result=['scan'=>$scan,'delivery'=>$delivery];
 mg_audit('user_agent.recovery_worker_run','personal_agent_opportunity_followups',['scheduled'=>$scan['scheduled']??0,'processed'=>$delivery['processed']??0,'delivered'=>$delivery['delivered']??0,'failed'=>$delivery['failed']??0],(int)$user['id']);
 mg_ok($result,'Personal Agent recovery worker complete.');
}catch(RuntimeException $e){mg_fail($e->getMessage(),503);}catch(Throwable $e){mg_security_log('error','user_agent.recovery_worker_failed','Personal Agent recovery worker failed.',['exception_type'=>$e::class],(int)$user['id']);mg_fail('Unable to run Personal Agent recovery automation.',500);}
