<?php
declare(strict_types=1);

require_once __DIR__ . '/_loyalty_quest_worker.php';

$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
$user=mg_require_permission('admin.users.view');
$pdo=mg_db();

if($method==='GET'){
    $counts=$pdo->query("SELECT j.status,COUNT(*) total FROM message_delivery_jobs j INNER JOIN message_events e ON e.id=j.message_event_id WHERE e.event_type LIKE 'loyalty_quest.%' GROUP BY j.status ORDER BY j.status")->fetchAll(PDO::FETCH_KEY_PAIR);
    mg_ok(['queue'=>$counts,'schema_ready'=>true]);
}
if($method!=='POST')mg_fail('Method not allowed.',405);
$input=mg_input();mg_require_csrf_for_write($input);
$limit=max(1,min(200,(int)($input['limit']??50)));
$result=mg_lqn_worker_run($pdo,$limit);
mg_audit('communications.loyalty_quest_worker_run','message_delivery_jobs',['limit'=>$limit,'result'=>$result],(int)$user['id']);
mg_ok($result,'Loyalty Quest notification worker completed.');
