<?php
declare(strict_types=1);

require_once __DIR__ . '/_loyalty_quest_operations.php';
require_once dirname(__DIR__) . '/public/loyalty-quest/_integrity.php';

function mg_lqi_admin_safe_context(array $context): array
{
    $allowed=['matched_evidence_id','distinct_participants','window_hours','window_minutes','elapsed_seconds','minimum_seconds','rejected_evidence','window_days','completed_quests','distance_km','speed_kph'];
    $safe=[];
    foreach($allowed as $key){
        if(array_key_exists($key,$context)&&is_scalar($context[$key]))$safe[$key]=$context[$key];
    }
    return $safe;
}

function mg_lqi_admin_signals(PDO $pdo,string $status='open',string $severity='all',string $campaignRef='',string $query='',int $limit=200): array
{
    mg_lqi_require_schema($pdo);
    $statuses=['all','open','acknowledged','cleared','confirmed'];
    $severities=['all','low','medium','high','critical'];
    if(!in_array($status,$statuses,true))$status='open';
    if(!in_array($severity,$severities,true))$severity='all';
    if($campaignRef!==''&&(strlen($campaignRef)!==36||preg_match('/^[a-f0-9-]{36}$/',$campaignRef)!==1))throw new InvalidArgumentException('Invalid Loyalty Quest.');
    $query=mb_strtolower(trim($query));
    if(mb_strlen($query)>180)throw new InvalidArgumentException('Search is too long.');
    $limit=max(1,min(300,$limit));
    $where=["c.campaign_type='loyalty_quest'"];$params=[];
    if($status!=='all'){$where[]='s.status=?';$params[]=$status;}
    if($severity!=='all'){$where[]='s.severity=?';$params[]=$severity;}
    if($campaignRef!==''){$where[]='c.public_id=?';$params[]=$campaignRef;}
    if($query!==''){
        $like='%'.$query.'%';
        $where[]="(LOWER(c.title) LIKE ? OR LOWER(COALESCE(mw.display_name,pp.display_name,mu.display_name,mu.full_name,mu.email,'')) LIKE ? OR LOWER(s.signal_type) LIKE ? OR LOWER(s.public_id) LIKE ?)";
        array_push($params,$like,$like,$like,$like);
    }
    $sql="SELECT s.id,s.public_id,s.signal_type,s.severity,s.score,s.status,s.context_json,s.created_at,s.updated_at,s.resolved_at,s.resolution_note,
                 c.public_id campaign_public_id,c.title campaign_title,c.merchant_user_id,
                 COALESCE(NULLIF(mw.display_name,''),NULLIF(pp.display_name,''),NULLIF(mu.display_name,''),NULLIF(mu.full_name,''),mu.email,'Merchant') merchant_name,
                 lqe.public_id evidence_public_id,lqe.status evidence_status,lqe.integrity_score,lqe.integrity_status,lqp.public_id participation_public_id,
                 pu.id participant_user_id,pu.email participant_email
          FROM loyalty_quest_integrity_signals s
          INNER JOIN campaigns c ON c.id=s.campaign_id AND c.merchant_user_id=s.merchant_user_id
          INNER JOIN users mu ON mu.id=c.merchant_user_id
          INNER JOIN users pu ON pu.id=s.participant_user_id
          LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=c.merchant_user_id
          LEFT JOIN public_profiles pp ON pp.user_id=c.merchant_user_id
          LEFT JOIN loyalty_quest_evidence lqe ON lqe.id=s.evidence_id AND lqe.merchant_user_id=s.merchant_user_id
          LEFT JOIN loyalty_quest_participations lqp ON lqp.id=s.participation_id AND lqp.merchant_user_id=s.merchant_user_id
          WHERE ".implode(' AND ',$where)."
          ORDER BY FIELD(s.status,'open','confirmed','acknowledged','cleared'),FIELD(s.severity,'critical','high','medium','low'),s.score DESC,s.created_at ASC
          LIMIT {$limit}";
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$items=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row){
        $context=json_decode((string)($row['context_json']??''),true);if(!is_array($context))$context=[];
        $items[]=[
            'id'=>(string)$row['public_id'],'type'=>(string)$row['signal_type'],'severity'=>(string)$row['severity'],'score'=>(int)$row['score'],'status'=>(string)$row['status'],'context'=>mg_lqi_admin_safe_context($context),'created_at'=>$row['created_at']??null,'updated_at'=>$row['updated_at']??null,'resolved_at'=>$row['resolved_at']??null,'resolution_note'=>$row['resolution_note']??null,
            'campaign'=>['id'=>(string)$row['campaign_public_id'],'title'=>(string)$row['campaign_title']],
            'merchant'=>['id'=>(int)$row['merchant_user_id'],'name'=>(string)$row['merchant_name']],
            'participant'=>['user_id'=>(int)$row['participant_user_id'],'email_masked'=>mg_lqo_mask_email((string)$row['participant_email'])],
            'participation_id'=>$row['participation_public_id']??null,
            'evidence'=>['id'=>$row['evidence_public_id']??null,'status'=>$row['evidence_status']??null,'integrity_score'=>(int)($row['integrity_score']??0),'integrity_status'=>$row['integrity_status']??null],
        ];
    }
    return $items;
}

function mg_lqi_admin_summary(PDO $pdo): array
{
    mg_lqi_require_schema($pdo);
    $rows=$pdo->query("SELECT status,severity,COUNT(*) total FROM loyalty_quest_integrity_signals GROUP BY status,severity")->fetchAll(PDO::FETCH_ASSOC)?:[];
    $summary=['all'=>0,'open'=>0,'critical_open'=>0,'high_open'=>0,'acknowledged'=>0,'cleared'=>0,'confirmed'=>0,'blocked_evidence'=>0,'participants_flagged'=>0];
    foreach($rows as $row){$total=(int)$row['total'];$summary['all']+=$total;$status=(string)$row['status'];if(isset($summary[$status]))$summary[$status]+=$total;if($status==='open'&&$row['severity']==='critical')$summary['critical_open']+=$total;if($status==='open'&&$row['severity']==='high')$summary['high_open']+=$total;}
    $summary['blocked_evidence']=(int)$pdo->query("SELECT COUNT(*) FROM loyalty_quest_evidence WHERE integrity_status='blocked'")->fetchColumn();
    $summary['participants_flagged']=(int)$pdo->query("SELECT COUNT(DISTINCT participant_user_id) FROM loyalty_quest_integrity_signals WHERE status IN ('open','confirmed')")->fetchColumn();
    return $summary;
}

function mg_lqi_admin_campaigns(PDO $pdo): array
{
    $stmt=$pdo->query("SELECT c.public_id,c.title,c.status,COUNT(s.id) open_signals,COALESCE(SUM(s.severity='critical'),0) critical_signals,COALESCE(SUM(s.severity='high'),0) high_signals FROM campaigns c LEFT JOIN loyalty_quest_integrity_signals s ON s.campaign_id=c.id AND s.merchant_user_id=c.merchant_user_id AND s.status='open' WHERE c.campaign_type='loyalty_quest' GROUP BY c.id ORDER BY open_signals DESC,c.updated_at DESC LIMIT 100");
    return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
}

function mg_lqi_admin_resolve(PDO $pdo,int $adminId,string $signalRef,string $resolution,string $reason): array
{
    if(!in_array($resolution,['acknowledged','cleared','confirmed'],true))throw new InvalidArgumentException('Invalid integrity resolution.');
    $stmt=$pdo->prepare("SELECT s.*,c.public_id campaign_public_id,lqe.public_id evidence_public_id,lqe.integrity_status evidence_integrity_status
                        FROM loyalty_quest_integrity_signals s
                        INNER JOIN campaigns c ON c.id=s.campaign_id AND c.merchant_user_id=s.merchant_user_id AND c.campaign_type='loyalty_quest'
                        LEFT JOIN loyalty_quest_evidence lqe ON lqe.id=s.evidence_id AND lqe.merchant_user_id=s.merchant_user_id
                        WHERE s.public_id=? LIMIT 1 FOR UPDATE");
    $stmt->execute([$signalRef]);$signal=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$signal)throw new RuntimeException('Loyalty Quest integrity signal not found.');
    $current=(string)$signal['status'];
    if($current==='cleared'&&$resolution!=='cleared')throw new DomainException('A cleared integrity signal cannot be reopened from this console.');
    if($current==='confirmed'&&!in_array($resolution,['confirmed','cleared'],true))throw new DomainException('A confirmed integrity signal may only remain confirmed or be cleared after re-review.');
    $pdo->prepare('UPDATE loyalty_quest_integrity_signals SET status=?,resolved_by_user_id=?,resolution_note=?,resolved_at=NOW(),updated_at=NOW() WHERE id=?')
        ->execute([$resolution,$adminId,$reason,(int)$signal['id']]);
    if(!empty($signal['evidence_id'])){
        if($resolution==='confirmed'){
            $pdo->prepare("UPDATE loyalty_quest_evidence SET integrity_status='blocked',updated_at=NOW() WHERE id=? AND status='submitted'")->execute([(int)$signal['evidence_id']]);
        }elseif($resolution==='cleared'){
            $remaining=$pdo->prepare("SELECT COALESCE(SUM(status='confirmed'),0) confirmed_count,COALESCE(SUM(status='open'),0) open_count FROM loyalty_quest_integrity_signals WHERE evidence_id=? AND id<>?");
            $remaining->execute([(int)$signal['evidence_id'],(int)$signal['id']]);
            $counts=$remaining->fetch(PDO::FETCH_ASSOC)?:['confirmed_count'=>0,'open_count'=>0];
            if((int)$counts['confirmed_count']>0){
                $next='blocked';
            }elseif((int)$counts['open_count']>0){
                $next='review';
            }else{
                $next=null;
            }
            if($next!==null){
                $pdo->prepare('UPDATE loyalty_quest_evidence SET integrity_status=?,updated_at=NOW() WHERE id=?')->execute([$next,(int)$signal['evidence_id']]);
            }else{
                $pdo->prepare("UPDATE loyalty_quest_evidence SET integrity_status=IF(status='submitted','clear','resolved'),updated_at=NOW() WHERE id=?")->execute([(int)$signal['evidence_id']]);
            }
        }
    }
    $eventId=mg_lqo_campaign_event($pdo,(int)$signal['merchant_user_id'],(int)$signal['campaign_id'],null,'quest.admin_integrity_'.$resolution,['operator_user_id'=>$adminId,'reason'=>$reason,'signal_id'=>$signalRef,'signal_type'=>(string)$signal['signal_type'],'severity'=>(string)$signal['severity'],'old_status'=>$current,'new_status'=>$resolution,'evidence_id'=>$signal['evidence_public_id']??null]);
    mg_audit('admin.loyalty_quest_integrity_'.$resolution,'loyalty_quest_integrity_signal',['signal_id'=>$signalRef,'campaign_id'=>(string)$signal['campaign_public_id'],'merchant_user_id'=>(int)$signal['merchant_user_id'],'participant_user_id'=>(int)$signal['participant_user_id'],'old_status'=>$current,'new_status'=>$resolution,'reason'=>$reason,'campaign_event_id'=>$eventId],$adminId);
    return ['signal_id'=>$signalRef,'old_status'=>$current,'status'=>$resolution,'campaign_id'=>(string)$signal['campaign_public_id'],'evidence_id'=>$signal['evidence_public_id']??null,'event_id'=>$eventId];
}
