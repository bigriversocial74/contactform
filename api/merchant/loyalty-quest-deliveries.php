<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/communications/_delivery.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_merchant_require_permission($method === 'GET' ? 'merchant.campaigns.view' : 'merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo,$user);

if ($method === 'GET') {
    $campaignRef = strtolower(trim((string)($_GET['campaign_id'] ?? '')));
    $status = strtolower(trim((string)($_GET['status'] ?? 'all')));
    $limit = max(1,min(200,(int)($_GET['limit'] ?? 100)));
    if ($campaignRef !== '' && (strlen($campaignRef)!==36 || preg_match('/^[a-f0-9-]{36}$/',$campaignRef)!==1)) mg_fail('Invalid Loyalty Quest.',422);
    $allowed = ['all','queued','processing','retrying','delivered','failed','dead_letter','suppressed'];
    if (!in_array($status,$allowed,true)) mg_fail('Invalid delivery status.',422);

    $where = ["j.merchant_user_id=?","c.campaign_type='loyalty_quest'","e.event_type LIKE 'loyalty_quest.%'"];
    $params = [$merchantId];
    if ($campaignRef !== '') { $where[]='c.public_id=?'; $params[]=$campaignRef; }
    if ($status !== 'all') { $where[]='j.status=?'; $params[]=$status; }
    $sql = "SELECT j.public_id,j.channel,j.template_key,j.status,j.attempt_count,j.max_attempts,j.next_attempt_at,j.provider_message_id,j.last_error,j.recipient_snapshot_json,j.payload_snapshot_json,j.delivered_at,j.failed_at,j.created_at,j.updated_at,j.source_public_id,e.event_type,c.public_id campaign_public_id,c.title campaign_title,COUNT(a.id) attempt_records,MAX(a.created_at) last_attempt_at FROM message_delivery_jobs j INNER JOIN message_events e ON e.id=j.message_event_id INNER JOIN campaigns c ON c.id=j.campaign_id AND c.merchant_user_id=j.merchant_user_id LEFT JOIN message_delivery_attempts a ON a.job_id=j.id WHERE ".implode(' AND ',$where)." GROUP BY j.id ORDER BY j.created_at DESC,j.id DESC LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);$stmt->execute($params);
    $items=[];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $recipient=json_decode((string)($row['recipient_snapshot_json']??''),true);if(!is_array($recipient))$recipient=[];
        $payload=json_decode((string)($row['payload_snapshot_json']??''),true);if(!is_array($payload))$payload=[];
        $items[]=[
            'id'=>(string)$row['public_id'],'channel'=>(string)$row['channel'],'template_key'=>(string)$row['template_key'],'event_type'=>(string)$row['event_type'],'status'=>(string)$row['status'],'attempt_count'=>(int)$row['attempt_count'],'max_attempts'=>(int)$row['max_attempts'],'attempt_records'=>(int)$row['attempt_records'],'next_attempt_at'=>$row['next_attempt_at']??null,'last_attempt_at'=>$row['last_attempt_at']??null,'delivered_at'=>$row['delivered_at']??null,'failed_at'=>$row['failed_at']??null,'last_error'=>$row['last_error']??null,'provider_message_id'=>$row['provider_message_id']??null,'created_at'=>$row['created_at']??null,'updated_at'=>$row['updated_at']??null,
            'recipient'=>['name'=>(string)($recipient['name']??''),'email'=>(string)($recipient['email']??'')],
            'campaign'=>['id'=>(string)$row['campaign_public_id'],'title'=>(string)$row['campaign_title']],
            'source_public_id'=>$row['source_public_id']??null,
            'subject'=>(string)($payload['subject']??''),
            'retry_allowed'=>in_array((string)$row['status'],['failed','dead_letter'],true)&&(int)$row['attempt_count']<10,
        ];
    }
    $countStmt=$pdo->prepare("SELECT j.status,COUNT(*) total FROM message_delivery_jobs j INNER JOIN message_events e ON e.id=j.message_event_id INNER JOIN campaigns c ON c.id=j.campaign_id AND c.merchant_user_id=j.merchant_user_id WHERE j.merchant_user_id=? AND c.campaign_type='loyalty_quest' AND e.event_type LIKE 'loyalty_quest.%' GROUP BY j.status");
    $countStmt->execute([$merchantId]);
    $counts=['all'=>0,'queued'=>0,'processing'=>0,'retrying'=>0,'delivered'=>0,'failed'=>0,'dead_letter'=>0,'suppressed'=>0];
    foreach($countStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row){$key=(string)$row['status'];$counts[$key]=(int)$row['total'];$counts['all']+=(int)$row['total'];}
    mg_ok(['items'=>$items,'counts'=>$counts]);
}

if ($method !== 'POST') mg_fail('Method not allowed.',405);
$input=mg_input();mg_require_csrf_for_write($input);
$jobRef=strtolower(trim((string)($input['job_id']??'')));
$action=strtolower(trim((string)($input['action']??'retry')));
if(strlen($jobRef)!==36||preg_match('/^[a-f0-9-]{36}$/',$jobRef)!==1||$action!=='retry')mg_fail('Invalid delivery action.',422);
$pdo->beginTransaction();
try{
    $stmt=$pdo->prepare("SELECT j.*,e.event_type,c.public_id campaign_public_id FROM message_delivery_jobs j INNER JOIN message_events e ON e.id=j.message_event_id INNER JOIN campaigns c ON c.id=j.campaign_id AND c.merchant_user_id=j.merchant_user_id WHERE j.public_id=? AND j.merchant_user_id=? AND c.campaign_type='loyalty_quest' AND e.event_type LIKE 'loyalty_quest.%' LIMIT 1 FOR UPDATE");
    $stmt->execute([$jobRef,$merchantId]);$job=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$job)mg_fail('Loyalty Quest delivery job not found.',404);
    if(!in_array((string)$job['status'],['failed','dead_letter'],true))mg_fail('Only failed Loyalty Quest deliveries can be retried.',409);
    $newMax=min(10,max((int)$job['max_attempts'],(int)$job['attempt_count']+1));
    if((int)$job['attempt_count']>=$newMax)mg_fail('This delivery has reached the manual retry limit.',409);
    $pdo->prepare("UPDATE message_delivery_jobs SET status='queued',max_attempts=?,next_attempt_at=NOW(),last_error=NULL,failed_at=NULL,updated_at=NOW() WHERE id=? AND merchant_user_id=?")->execute([$newMax,(int)$job['id'],$merchantId]);
    mg_audit('merchant.loyalty_quest_delivery_retried','message_delivery_job',['job_id'=>$jobRef,'campaign_id'=>(string)$job['campaign_public_id'],'attempt_count'=>(int)$job['attempt_count'],'max_attempts'=>$newMax],$merchantId);
    $pdo->commit();
    mg_ok(['job_id'=>$jobRef,'status'=>'queued','max_attempts'=>$newMax],'Loyalty Quest delivery queued for retry.');
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','merchant.loyalty_quest_delivery_retry_failed','Unable to retry Loyalty Quest delivery.',['exception_class'=>$error::class],$merchantId);
    mg_fail('Unable to retry Loyalty Quest delivery.',500);
}
