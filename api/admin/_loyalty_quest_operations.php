<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/communications/_communications.php';

function mg_lqo_table_ready(PDO $pdo,string $table,array $columns=[]): bool
{
    try{
        $stmt=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $stmt->execute([$table]);
        $found=array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN)?:[]);
        if($found===[])return false;
        foreach($columns as $column)if(!in_array($column,$found,true))return false;
        return true;
    }catch(Throwable){
        return false;
    }
}

function mg_lqo_mask_email(string $email): string
{
    $email=strtolower(trim($email));
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))return '';
    [$local,$domain]=explode('@',$email,2);
    $visible=mb_substr($local,0,min(2,mb_strlen($local)));
    return $visible.str_repeat('•',max(2,min(8,mb_strlen($local)-mb_strlen($visible)))).'@'.$domain;
}

function mg_lqo_campaign_event(PDO $pdo,int $merchantId,int $campaignId,?int $contactId,string $eventType,array $context): string
{
    $publicId=mg_public_uuid();
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())')
        ->execute([$publicId,$merchantId,$campaignId,null,$contactId,$eventType,json_encode($context,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    return $publicId;
}

function mg_lqo_campaigns(PDO $pdo,string $status='all',string $query='',int $limit=100): array
{
    $allowed=['all','draft','scheduled','active','paused','completed','cancelled','archived'];
    if(!in_array($status,$allowed,true))$status='all';
    $query=mb_strtolower(trim($query));
    $limit=max(1,min(200,$limit));
    $deliveryReady=mg_lqo_table_ready($pdo,'message_delivery_jobs',['merchant_user_id','campaign_id','status']);
    $deliveryJoin=$deliveryReady?"LEFT JOIN (
        SELECT j.campaign_id,j.merchant_user_id,
               SUM(j.status IN ('failed','dead_letter')) delivery_failed,
               SUM(j.status='processing' AND j.updated_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE)) delivery_stuck
        FROM message_delivery_jobs j
        INNER JOIN message_events me ON me.id=j.message_event_id AND me.event_type LIKE 'loyalty_quest.%'
        GROUP BY j.campaign_id,j.merchant_user_id
    ) d ON d.campaign_id=c.id AND d.merchant_user_id=c.merchant_user_id":"";
    $deliverySelect=$deliveryReady
        ?'COALESCE(d.delivery_failed,0) delivery_failed,COALESCE(d.delivery_stuck,0) delivery_stuck'
        :'0 delivery_failed,0 delivery_stuck';
    $where=["c.campaign_type='loyalty_quest'"];
    $params=[];
    if($status!=='all'){
        $where[]='c.status=?';
        $params[]=$status;
    }
    if($query!==''){
        $like='%'.$query.'%';
        $where[]="(LOWER(c.title) LIKE ? OR LOWER(COALESCE(mw.display_name,pp.display_name,u.display_name,u.full_name,u.email,'')) LIKE ? OR LOWER(c.public_id) LIKE ?)";
        array_push($params,$like,$like,$like);
    }
    $sql="SELECT c.id,c.public_id,c.public_slug,c.title,c.status,c.starts_at,c.ends_at,c.issued_count,c.claimed_count,c.redeemed_count,c.updated_at,c.merchant_user_id,
                 COALESCE(NULLIF(mw.display_name,''),NULLIF(pp.display_name,''),NULLIF(u.display_name,''),NULLIF(u.full_name,''),u.email,'Merchant') merchant_name,
                 COALESCE(p.participants,0) participants,COALESCE(p.pending_review,0) pending_review,COALESCE(p.completed,0) completed,
                 COALESCE(e.submitted_evidence,0) submitted_evidence,e.oldest_submitted_at,
                 COALESCE(w.inbox_delivered,0) inbox_delivered,COALESCE(w.redeemed,0) rewards_redeemed,
                 {$deliverySelect}
          FROM campaigns c
          INNER JOIN users u ON u.id=c.merchant_user_id
          LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=c.merchant_user_id
          LEFT JOIN public_profiles pp ON pp.user_id=c.merchant_user_id
          LEFT JOIN (
              SELECT campaign_id,merchant_user_id,COUNT(*) participants,SUM(status='pending_review') pending_review,SUM(status='completed') completed
              FROM loyalty_quest_participations
              GROUP BY campaign_id,merchant_user_id
          ) p ON p.campaign_id=c.id AND p.merchant_user_id=c.merchant_user_id
          LEFT JOIN (
              SELECT campaign_id,merchant_user_id,SUM(status='submitted') submitted_evidence,MIN(CASE WHEN status='submitted' THEN created_at END) oldest_submitted_at
              FROM loyalty_quest_evidence
              GROUP BY campaign_id,merchant_user_id
          ) e ON e.campaign_id=c.id AND e.merchant_user_id=c.merchant_user_id
          LEFT JOIN (
              SELECT campaign_id,merchant_user_id,COUNT(*) inbox_delivered,SUM(status='redeemed') redeemed
              FROM wallet_items
              WHERE source_type='loyalty_quest' AND status<>'cancelled'
              GROUP BY campaign_id,merchant_user_id
          ) w ON w.campaign_id=c.id AND w.merchant_user_id=c.merchant_user_id
          {$deliveryJoin}
          WHERE ".implode(' AND ',$where)."
          ORDER BY CASE c.status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 WHEN 'scheduled' THEN 2 WHEN 'draft' THEN 3 ELSE 4 END,c.updated_at DESC,c.id DESC
          LIMIT {$limit}";
    $stmt=$pdo->prepare($sql);
    $stmt->execute($params);
    $items=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row){
        $items[]=[
            'id'=>(string)$row['public_id'],
            'slug'=>$row['public_slug']??null,
            'title'=>(string)$row['title'],
            'status'=>(string)$row['status'],
            'starts_at'=>$row['starts_at']??null,
            'ends_at'=>$row['ends_at']??null,
            'updated_at'=>$row['updated_at']??null,
            'merchant'=>['id'=>(int)$row['merchant_user_id'],'name'=>(string)$row['merchant_name']],
            'participants'=>(int)$row['participants'],
            'pending_review'=>(int)$row['pending_review'],
            'completed'=>(int)$row['completed'],
            'submitted_evidence'=>(int)$row['submitted_evidence'],
            'oldest_submitted_at'=>$row['oldest_submitted_at']??null,
            'inbox_delivered'=>(int)$row['inbox_delivered'],
            'claimed'=>(int)$row['claimed_count'],
            'redeemed'=>(int)$row['rewards_redeemed'],
            'delivery_failed'=>(int)$row['delivery_failed'],
            'delivery_stuck'=>(int)$row['delivery_stuck'],
            'actions'=>[
                'pause'=>in_array((string)$row['status'],['active','scheduled'],true),
                'resume'=>(string)$row['status']==='paused',
                'end'=>in_array((string)$row['status'],['active','scheduled','paused'],true),
            ],
        ];
    }
    return ['items'=>$items,'delivery_ready'=>$deliveryReady];
}

function mg_lqo_evidence_queue(PDO $pdo,int $limit=100): array
{
    $limit=max(1,min(200,$limit));
    $sql="SELECT lqe.id,lqe.public_id,lqe.evidence_type,lqe.created_at,lqp.public_id participation_public_id,lqp.contact_id,
                 c.id campaign_db_id,c.public_id campaign_public_id,c.title campaign_title,c.merchant_user_id,
                 COALESCE(NULLIF(mw.display_name,''),NULLIF(pp.display_name,''),NULLIF(u.display_name,''),NULLIF(u.full_name,''),u.email,'Merchant') merchant_name,
                 TIMESTAMPDIFF(HOUR,lqe.created_at,NOW()) age_hours
          FROM loyalty_quest_evidence lqe
          INNER JOIN loyalty_quest_participations lqp ON lqp.id=lqe.participation_id AND lqp.merchant_user_id=lqe.merchant_user_id
          INNER JOIN campaigns c ON c.id=lqe.campaign_id AND c.merchant_user_id=lqe.merchant_user_id AND c.campaign_type='loyalty_quest'
          INNER JOIN users u ON u.id=c.merchant_user_id
          LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=c.merchant_user_id
          LEFT JOIN public_profiles pp ON pp.user_id=c.merchant_user_id
          WHERE lqe.status='submitted'
          ORDER BY lqe.created_at ASC,lqe.id ASC
          LIMIT {$limit}";
    $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC)?:[];
    $items=[];
    foreach($rows as $row){
        $items[]=[
            'id'=>(string)$row['public_id'],
            'type'=>(string)$row['evidence_type'],
            'created_at'=>$row['created_at'],
            'age_hours'=>(int)$row['age_hours'],
            'participation_id'=>(string)$row['participation_public_id'],
            'campaign'=>['id'=>(string)$row['campaign_public_id'],'title'=>(string)$row['campaign_title']],
            'merchant'=>['id'=>(int)$row['merchant_user_id'],'name'=>(string)$row['merchant_name']],
            'can_nudge'=>(int)$row['age_hours']>=12,
        ];
    }
    return $items;
}

function mg_lqo_delivery_queue(PDO $pdo,int $limit=100): array
{
    if(!mg_lqo_table_ready($pdo,'message_delivery_jobs',['merchant_user_id','campaign_id','status','recipient_snapshot_json']))return [];
    $limit=max(1,min(200,$limit));
    $sql="SELECT j.id,j.public_id,j.status,j.attempt_count,j.max_attempts,j.next_attempt_at,j.last_error,j.recipient_snapshot_json,j.created_at,j.updated_at,
                 c.public_id campaign_public_id,c.title campaign_title,c.merchant_user_id,
                 COALESCE(NULLIF(mw.display_name,''),NULLIF(pp.display_name,''),NULLIF(u.display_name,''),NULLIF(u.full_name,''),u.email,'Merchant') merchant_name
          FROM message_delivery_jobs j
          INNER JOIN message_events me ON me.id=j.message_event_id AND me.event_type LIKE 'loyalty_quest.%'
          INNER JOIN campaigns c ON c.id=j.campaign_id AND c.merchant_user_id=j.merchant_user_id AND c.campaign_type='loyalty_quest'
          INNER JOIN users u ON u.id=c.merchant_user_id
          LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=c.merchant_user_id
          LEFT JOIN public_profiles pp ON pp.user_id=c.merchant_user_id
          WHERE j.status IN ('failed','dead_letter') OR (j.status='processing' AND j.updated_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE))
          ORDER BY CASE WHEN j.status='processing' THEN 0 WHEN j.status='dead_letter' THEN 1 ELSE 2 END,j.updated_at ASC,j.id ASC
          LIMIT {$limit}";
    $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC)?:[];
    $items=[];
    foreach($rows as $row){
        $recipient=json_decode((string)($row['recipient_snapshot_json']??''),true);
        if(!is_array($recipient))$recipient=[];
        $items[]=[
            'id'=>(string)$row['public_id'],
            'status'=>(string)$row['status'],
            'attempt_count'=>(int)$row['attempt_count'],
            'max_attempts'=>(int)$row['max_attempts'],
            'next_attempt_at'=>$row['next_attempt_at']??null,
            'last_error'=>$row['last_error']??null,
            'created_at'=>$row['created_at']??null,
            'updated_at'=>$row['updated_at']??null,
            'recipient'=>[
                'name'=>(string)($recipient['name']??''),
                'email_masked'=>mg_lqo_mask_email((string)($recipient['email']??'')),
            ],
            'campaign'=>['id'=>(string)$row['campaign_public_id'],'title'=>(string)$row['campaign_title']],
            'merchant'=>['id'=>(int)$row['merchant_user_id'],'name'=>(string)$row['merchant_name']],
            'stale_processing'=>(string)$row['status']==='processing',
        ];
    }
    return $items;
}

function mg_lqo_summary(array $campaigns,array $evidence,array $deliveries): array
{
    $summary=[
        'campaigns'=>count($campaigns),
        'active'=>0,
        'paused'=>0,
        'pending_review'=>count($evidence),
        'evidence_over_24h'=>0,
        'delivery_failures'=>count($deliveries),
        'stale_processing'=>0,
        'participants'=>0,
        'completed'=>0,
        'inbox_delivered'=>0,
        'redeemed'=>0,
    ];
    foreach($campaigns as $campaign){
        if($campaign['status']==='active')$summary['active']++;
        if($campaign['status']==='paused')$summary['paused']++;
        foreach(['participants','completed','inbox_delivered','redeemed'] as $field)$summary[$field]+=(int)$campaign[$field];
    }
    foreach($evidence as $item)if((int)$item['age_hours']>=24)$summary['evidence_over_24h']++;
    foreach($deliveries as $item)if(!empty($item['stale_processing']))$summary['stale_processing']++;
    return $summary;
}

function mg_lqo_recent_events(PDO $pdo,int $limit=50): array
{
    $limit=max(1,min(100,$limit));
    $stmt=$pdo->query("SELECT ce.public_id,ce.event_type,ce.event_context_json,ce.created_at,c.public_id campaign_public_id,c.title campaign_title,c.merchant_user_id
                      FROM campaign_events ce
                      INNER JOIN campaigns c ON c.id=ce.campaign_id AND c.merchant_user_id=ce.merchant_user_id
                      WHERE c.campaign_type='loyalty_quest' AND ce.event_type LIKE 'quest.admin_%'
                      ORDER BY ce.created_at DESC,ce.id DESC
                      LIMIT {$limit}");
    $items=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row){
        $context=json_decode((string)($row['event_context_json']??''),true);
        if(!is_array($context))$context=[];
        $items[]=[
            'id'=>(string)$row['public_id'],
            'event_type'=>(string)$row['event_type'],
            'created_at'=>$row['created_at'],
            'campaign'=>['id'=>(string)$row['campaign_public_id'],'title'=>(string)$row['campaign_title']],
            'operator_user_id'=>(int)($context['operator_user_id']??0),
            'reason'=>(string)($context['reason']??''),
        ];
    }
    return $items;
}

function mg_lqo_require_reason(array $input): string
{
    $reason=preg_replace('/\s+/',' ',trim((string)($input['reason']??'')))??'';
    if(mb_strlen($reason)<12||mb_strlen($reason)>1000)throw new InvalidArgumentException('Add an operator reason between 12 and 1000 characters.');
    return $reason;
}

function mg_lqo_campaign_action(PDO $pdo,int $adminId,string $campaignRef,string $action,string $reason): array
{
    $actionMap=[
        'pause'=>['event'=>'quest.admin_paused','audit'=>'admin.loyalty_quest_paused'],
        'resume'=>['event'=>'quest.admin_resumed','audit'=>'admin.loyalty_quest_resumed'],
        'end'=>['event'=>'quest.admin_ended','audit'=>'admin.loyalty_quest_ended'],
    ];
    if(!isset($actionMap[$action]))throw new InvalidArgumentException('Invalid campaign action.');
    $stmt=$pdo->prepare("SELECT * FROM campaigns WHERE public_id=? AND campaign_type='loyalty_quest' LIMIT 1 FOR UPDATE");
    $stmt->execute([$campaignRef]);
    $campaign=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$campaign)throw new RuntimeException('Loyalty Quest not found.');
    $old=(string)$campaign['status'];
    $new=$old;
    if($action==='pause'){
        if(!in_array($old,['active','scheduled'],true))throw new DomainException('Only active or scheduled quests can be paused.');
        $new='paused';
        $pdo->prepare("UPDATE campaigns SET status='paused',updated_at=NOW() WHERE id=?")->execute([(int)$campaign['id']]);
    }elseif($action==='resume'){
        if($old!=='paused')throw new DomainException('Only paused quests can be resumed.');
        if(!empty($campaign['ends_at'])&&strtotime((string)$campaign['ends_at'])<=time())throw new DomainException('This quest has already ended.');
        $new=!empty($campaign['starts_at'])&&strtotime((string)$campaign['starts_at'])>time()?'scheduled':'active';
        $pdo->prepare('UPDATE campaigns SET status=?,updated_at=NOW() WHERE id=?')->execute([$new,(int)$campaign['id']]);
    }else{
        if(!in_array($old,['active','scheduled','paused'],true))throw new DomainException('Only running or paused quests can be ended.');
        $new='completed';
        $pdo->prepare("UPDATE campaigns SET status='completed',ends_at=CASE WHEN ends_at IS NULL OR ends_at>NOW() THEN NOW() ELSE ends_at END,updated_at=NOW() WHERE id=?")->execute([(int)$campaign['id']]);
    }
    $eventId=mg_lqo_campaign_event(
        $pdo,
        (int)$campaign['merchant_user_id'],
        (int)$campaign['id'],
        null,
        $actionMap[$action]['event'],
        ['operator_user_id'=>$adminId,'reason'=>$reason,'old_status'=>$old,'new_status'=>$new]
    );
    mg_audit(
        $actionMap[$action]['audit'],
        'campaign',
        ['campaign_id'=>$campaignRef,'merchant_user_id'=>(int)$campaign['merchant_user_id'],'old_status'=>$old,'new_status'=>$new,'reason'=>$reason,'campaign_event_id'=>$eventId],
        $adminId
    );
    return ['campaign_id'=>$campaignRef,'old_status'=>$old,'new_status'=>$new,'event_id'=>$eventId];
}

function mg_lqo_review_nudge(PDO $pdo,int $adminId,string $evidenceRef,string $reason): array
{
    $stmt=$pdo->prepare("SELECT lqe.id,lqe.public_id,lqe.created_at,lqp.contact_id,lqp.public_id participation_public_id,c.id campaign_id,c.public_id campaign_public_id,c.title,c.merchant_user_id
                        FROM loyalty_quest_evidence lqe
                        INNER JOIN loyalty_quest_participations lqp ON lqp.id=lqe.participation_id AND lqp.merchant_user_id=lqe.merchant_user_id
                        INNER JOIN campaigns c ON c.id=lqe.campaign_id AND c.merchant_user_id=lqe.merchant_user_id AND c.campaign_type='loyalty_quest'
                        WHERE lqe.public_id=? AND lqe.status='submitted'
                        LIMIT 1 FOR UPDATE");
    $stmt->execute([$evidenceRef]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('Submitted Loyalty Quest evidence not found.');
    $cooldown=$pdo->prepare("SELECT COUNT(*) FROM campaign_events WHERE campaign_id=? AND merchant_user_id=? AND event_type='quest.admin_review_nudged' AND created_at>=DATE_SUB(NOW(),INTERVAL 12 HOUR)");
    $cooldown->execute([(int)$row['campaign_id'],(int)$row['merchant_user_id']]);
    if((int)$cooldown->fetchColumn()>0)throw new DomainException('This merchant was already nudged within the last 12 hours.');
    $eventId=mg_lqo_campaign_event(
        $pdo,
        (int)$row['merchant_user_id'],
        (int)$row['campaign_id'],
        (int)$row['contact_id'],
        'quest.admin_review_nudged',
        ['operator_user_id'=>$adminId,'reason'=>$reason,'evidence_id'=>$evidenceRef,'participation_id'=>(string)$row['participation_public_id']]
    );
    if(mg_lqo_table_ready($pdo,'operational_alerts',['user_id','alert_type','status'])){
        mg_create_operational_alert(
            $pdo,
            (int)$row['merchant_user_id'],
            'loyalty_quest_review_backlog',
            'warning',
            'Loyalty Quest evidence needs review',
            'An administrator flagged submitted evidence that has remained in the review queue.',
            '/merchant-quest-reviews.php?campaign='.rawurlencode((string)$row['campaign_public_id']),
            ['merchant_user_id'=>(int)$row['merchant_user_id'],'evidence_id'=>$evidenceRef,'campaign_id'=>(string)$row['campaign_public_id']]
        );
    }
    mg_audit(
        'admin.loyalty_quest_review_nudged',
        'loyalty_quest_evidence',
        ['evidence_id'=>$evidenceRef,'campaign_id'=>(string)$row['campaign_public_id'],'merchant_user_id'=>(int)$row['merchant_user_id'],'reason'=>$reason,'campaign_event_id'=>$eventId],
        $adminId
    );
    return ['evidence_id'=>$evidenceRef,'campaign_id'=>(string)$row['campaign_public_id'],'event_id'=>$eventId];
}

function mg_lqo_retry_delivery(PDO $pdo,int $adminId,string $jobRef,string $reason): array
{
    if(!mg_lqo_table_ready($pdo,'message_delivery_jobs',['merchant_user_id','campaign_id','status']))throw new RuntimeException('Loyalty Quest delivery migration is not installed.');
    $stmt=$pdo->prepare("SELECT j.*,c.public_id campaign_public_id,c.merchant_user_id campaign_merchant_id
                        FROM message_delivery_jobs j
                        INNER JOIN message_events me ON me.id=j.message_event_id AND me.event_type LIKE 'loyalty_quest.%'
                        INNER JOIN campaigns c ON c.id=j.campaign_id AND c.merchant_user_id=j.merchant_user_id AND c.campaign_type='loyalty_quest'
                        WHERE j.public_id=?
                        LIMIT 1 FOR UPDATE");
    $stmt->execute([$jobRef]);
    $job=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$job)throw new RuntimeException('Loyalty Quest delivery job not found.');
    $stale=(string)$job['status']==='processing'&&strtotime((string)$job['updated_at'])<time()-900;
    if(!in_array((string)$job['status'],['failed','dead_letter'],true)&&!$stale)throw new DomainException('Only failed, dead-letter, or stale processing jobs can be retried.');
    $maxAttempts=min(10,max((int)$job['max_attempts'],(int)$job['attempt_count']+1));
    if((int)$job['attempt_count']>=$maxAttempts)throw new DomainException('This job has reached the administrative retry limit.');
    $pdo->prepare("UPDATE message_delivery_jobs SET status='queued',max_attempts=?,next_attempt_at=NOW(),last_error=NULL,failed_at=NULL,updated_at=NOW() WHERE id=?")
        ->execute([$maxAttempts,(int)$job['id']]);
    $eventId=mg_lqo_campaign_event(
        $pdo,
        (int)$job['merchant_user_id'],
        (int)$job['campaign_id'],
        null,
        'quest.admin_delivery_retried',
        ['operator_user_id'=>$adminId,'reason'=>$reason,'delivery_job_id'=>$jobRef,'old_status'=>(string)$job['status'],'attempt_count'=>(int)$job['attempt_count'],'max_attempts'=>$maxAttempts]
    );
    mg_audit(
        'admin.loyalty_quest_delivery_retried',
        'message_delivery_job',
        ['job_id'=>$jobRef,'campaign_id'=>(string)$job['campaign_public_id'],'merchant_user_id'=>(int)$job['merchant_user_id'],'reason'=>$reason,'campaign_event_id'=>$eventId],
        $adminId
    );
    return ['job_id'=>$jobRef,'status'=>'queued','max_attempts'=>$maxAttempts,'event_id'=>$eventId];
}
