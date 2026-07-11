<?php
declare(strict_types=1);

function mg_lqa_table_ready(PDO $pdo,string $table,array $columns=[]): bool
{
    try{
        $stmt=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $stmt->execute([$table]);
        $found=array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN)?:[]);
        if($found===[])return false;
        foreach($columns as $column)if(!in_array($column,$found,true))return false;
        return true;
    }catch(Throwable){return false;}
}

function mg_lqa_days(mixed $value): int
{
    $days=(int)$value;
    return in_array($days,[7,30,90,180,365],true)?$days:30;
}

function mg_lqa_campaign_ref(mixed $value): string
{
    $ref=strtolower(trim((string)$value));
    if($ref==='')return '';
    if(strlen($ref)!==36||preg_match('/^[a-f0-9-]{36}$/',$ref)!==1)throw new InvalidArgumentException('Invalid Loyalty Quest.');
    return $ref;
}

function mg_lqa_percent(int|float $numerator,int|float $denominator): float
{
    return $denominator>0?round(((float)$numerator/(float)$denominator)*100,1):0.0;
}

function mg_lqa_group(PDO $pdo,string $sql,array $params,string $key='campaign_id'): array
{
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$out=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row)$out[(int)$row[$key]]=$row;
    return $out;
}

function mg_lqa_merge_trend(array &$trend,array $rows,array $map): void
{
    foreach($rows as $row){
        $day=(string)($row['day']??'');if($day==='')continue;
        $trend[$day]??=['date'=>$day,'started'=>0,'completed'=>0,'evidence_submitted'=>0,'evidence_verified'=>0,'inbox_delivered'=>0,'claimed'=>0,'redeemed'=>0];
        foreach($map as $source=>$target)$trend[$day][$target]+=(int)($row[$source]??0);
    }
}

function mg_lqa_report(PDO $pdo,int $merchantId,int $days=30,string $campaignRef=''): array
{
    if($merchantId<1)throw new InvalidArgumentException('Merchant account is required.');
    $days=mg_lqa_days($days);$campaignRef=mg_lqa_campaign_ref($campaignRef);
    $cutoff="DATE_SUB(NOW(),INTERVAL {$days} DAY)";
    $campaignSql="SELECT id,public_id,public_slug,title,status,starts_at,ends_at,created_at,updated_at,rules_json FROM campaigns WHERE merchant_user_id=? AND campaign_type='loyalty_quest'";
    $campaignParams=[$merchantId];
    if($campaignRef!==''){$campaignSql.=' AND public_id=?';$campaignParams[]=$campaignRef;}
    $campaignSql.=' ORDER BY updated_at DESC,id DESC LIMIT 100';
    $campaignStmt=$pdo->prepare($campaignSql);$campaignStmt->execute($campaignParams);$campaignRows=$campaignStmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    $campaignIds=array_map(static fn(array $row):int=>(int)$row['id'],$campaignRows);
    if($campaignRef!==''&&$campaignRows===[])throw new RuntimeException('Loyalty Quest not found.');
    if($campaignIds===[]){
        return ['days'=>$days,'date_from'=>gmdate('Y-m-d',time()-$days*86400),'date_to'=>gmdate('Y-m-d'),'campaign_filter'=>$campaignRef?:null,'summary'=>['campaigns'=>0,'active_campaigns'=>0,'contacts'=>0,'participants'=>0,'completed'=>0,'completion_rate'=>0,'pending_review'=>0,'inbox_delivered'=>0,'claimed'=>0,'redeemed'=>0,'redemption_rate'=>0,'redeemed_value_cents'=>0],'funnel'=>[],'campaigns'=>[],'trend'=>[],'verification'=>[],'sources'=>[],'delivery'=>['ready'=>false,'queued'=>0,'delivered'=>0,'failed'=>0,'suppressed'=>0],'privacy'=>['contains_personal_data'=>false,'minimum_geo_group_size'=>5]];
    }
    $in=implode(',',array_fill(0,count($campaignIds),'?'));
    $baseParams=array_merge([$merchantId],$campaignIds);

    $contacts=mg_lqa_group($pdo,"SELECT campaign_id,COUNT(*) contacts,SUM(opt_in_status='opted_in') opted_in FROM campaign_contacts WHERE merchant_user_id=? AND campaign_id IN ({$in}) GROUP BY campaign_id",$baseParams);
    $parts=mg_lqa_group($pdo,"SELECT campaign_id,COUNT(*) participants,SUM(status='in_progress') in_progress,SUM(status='pending_review') pending_review,SUM(status='completed') completed,SUM(status='rejected') rejected,SUM(status='cancelled') cancelled,ROUND(AVG(completion_percent),1) avg_completion_percent,ROUND(AVG(CASE WHEN completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,joined_at,completed_at) END),1) avg_completion_minutes FROM loyalty_quest_participations WHERE merchant_user_id=? AND campaign_id IN ({$in}) AND joined_at>={$cutoff} GROUP BY campaign_id",$baseParams);
    $evidence=mg_lqa_group($pdo,"SELECT campaign_id,COUNT(*) evidence_total,SUM(status='submitted') evidence_submitted,SUM(status='verified') evidence_verified,SUM(status='rejected') evidence_rejected,COUNT(DISTINCT CASE WHEN status='verified' THEN participant_user_id END) participants_verified,ROUND(AVG(CASE WHEN status='verified' AND verified_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,created_at,verified_at) END),1) avg_review_minutes FROM loyalty_quest_evidence WHERE merchant_user_id=? AND campaign_id IN ({$in}) AND created_at>={$cutoff} GROUP BY campaign_id",$baseParams);
    $rewards=mg_lqa_group($pdo,"SELECT campaign_id,COUNT(*) inbox_delivered,SUM(status='claimed') claimed,SUM(status='redeemed') redeemed,SUM(status='expired') expired,COALESCE(SUM(value_cents_snapshot),0) issued_value_cents,COALESCE(SUM(CASE WHEN status='redeemed' THEN value_cents_snapshot ELSE 0 END),0) redeemed_value_cents,ROUND(AVG(CASE WHEN redeemed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,issued_at,redeemed_at) END),1) avg_redemption_minutes FROM wallet_items WHERE merchant_user_id=? AND campaign_id IN ({$in}) AND source_type='loyalty_quest' AND status<>'cancelled' AND issued_at>={$cutoff} GROUP BY campaign_id",$baseParams);

    $deliveryReady=mg_lqa_table_ready($pdo,'message_delivery_jobs',['merchant_user_id','campaign_id','status','created_at'])&&mg_lqa_table_ready($pdo,'message_events',['event_type']);
    $deliveries=[];
    if($deliveryReady)$deliveries=mg_lqa_group($pdo,"SELECT j.campaign_id,COUNT(*) delivery_total,SUM(j.status IN ('queued','processing','retrying')) delivery_queued,SUM(j.status='delivered') delivery_delivered,SUM(j.status IN ('failed','dead_letter')) delivery_failed,SUM(j.status='suppressed') delivery_suppressed FROM message_delivery_jobs j INNER JOIN message_events e ON e.id=j.message_event_id WHERE j.merchant_user_id=? AND j.campaign_id IN ({$in}) AND e.event_type LIKE 'loyalty_quest.%' AND j.created_at>={$cutoff} GROUP BY j.campaign_id",$baseParams);

    $campaigns=[];$summary=['campaigns'=>count($campaignRows),'active_campaigns'=>0,'contacts'=>0,'participants'=>0,'completed'=>0,'completion_rate'=>0.0,'pending_review'=>0,'evidence_verified'=>0,'inbox_delivered'=>0,'claimed'=>0,'redeemed'=>0,'claim_rate'=>0.0,'redemption_rate'=>0.0,'issued_value_cents'=>0,'redeemed_value_cents'=>0,'avg_completion_minutes'=>0.0,'avg_review_minutes'=>0.0,'avg_redemption_minutes'=>0.0];
    $weighted=['completion'=>0.0,'review'=>0.0,'redemption'=>0.0,'completion_n'=>0,'review_n'=>0,'redemption_n'=>0];
    foreach($campaignRows as $campaign){
        $id=(int)$campaign['id'];$p=$parts[$id]??[];$e=$evidence[$id]??[];$r=$rewards[$id]??[];$d=$deliveries[$id]??[];$c=$contacts[$id]??[];
        $participants=(int)($p['participants']??0);$completed=(int)($p['completed']??0);$inbox=(int)($r['inbox_delivered']??0);$claimed=(int)($r['claimed']??0);$redeemed=(int)($r['redeemed']??0);
        $row=['id'=>(string)$campaign['public_id'],'slug'=>$campaign['public_slug']??null,'title'=>(string)$campaign['title'],'status'=>(string)$campaign['status'],'starts_at'=>$campaign['starts_at']??null,'ends_at'=>$campaign['ends_at']??null,'contacts'=>(int)($c['contacts']??0),'opted_in'=>(int)($c['opted_in']??0),'participants'=>$participants,'in_progress'=>(int)($p['in_progress']??0),'pending_review'=>(int)($p['pending_review']??0),'completed'=>$completed,'rejected'=>(int)($p['rejected']??0),'cancelled'=>(int)($p['cancelled']??0),'avg_completion_percent'=>(float)($p['avg_completion_percent']??0),'avg_completion_minutes'=>(float)($p['avg_completion_minutes']??0),'evidence_total'=>(int)($e['evidence_total']??0),'evidence_submitted'=>(int)($e['evidence_submitted']??0),'evidence_verified'=>(int)($e['evidence_verified']??0),'evidence_rejected'=>(int)($e['evidence_rejected']??0),'avg_review_minutes'=>(float)($e['avg_review_minutes']??0),'inbox_delivered'=>$inbox,'claimed'=>$claimed,'redeemed'=>$redeemed,'expired'=>(int)($r['expired']??0),'issued_value_cents'=>(int)($r['issued_value_cents']??0),'redeemed_value_cents'=>(int)($r['redeemed_value_cents']??0),'avg_redemption_minutes'=>(float)($r['avg_redemption_minutes']??0),'delivery_total'=>(int)($d['delivery_total']??0),'delivery_queued'=>(int)($d['delivery_queued']??0),'delivery_delivered'=>(int)($d['delivery_delivered']??0),'delivery_failed'=>(int)($d['delivery_failed']??0),'delivery_suppressed'=>(int)($d['delivery_suppressed']??0),'start_rate'=>mg_lqa_percent($participants,(int)($c['contacts']??0)),'completion_rate'=>mg_lqa_percent($completed,$participants),'evidence_approval_rate'=>mg_lqa_percent((int)($e['evidence_verified']??0),(int)($e['evidence_total']??0)),'claim_rate'=>mg_lqa_percent($claimed,$inbox),'redemption_rate'=>mg_lqa_percent($redeemed,$inbox),'delivery_success_rate'=>mg_lqa_percent((int)($d['delivery_delivered']??0),(int)($d['delivery_total']??0))];
        $campaigns[]=$row;
        if($row['status']==='active')$summary['active_campaigns']++;
        foreach(['contacts','participants','completed','pending_review','evidence_verified','inbox_delivered','claimed','redeemed','issued_value_cents','redeemed_value_cents'] as $field)$summary[$field]+=$row[$field];
        if($row['avg_completion_minutes']>0&&$completed>0){$weighted['completion']+=$row['avg_completion_minutes']*$completed;$weighted['completion_n']+=$completed;}
        if($row['avg_review_minutes']>0&&(int)$row['evidence_verified']>0){$weighted['review']+=$row['avg_review_minutes']*(int)$row['evidence_verified'];$weighted['review_n']+=(int)$row['evidence_verified'];}
        if($row['avg_redemption_minutes']>0&&$redeemed>0){$weighted['redemption']+=$row['avg_redemption_minutes']*$redeemed;$weighted['redemption_n']+=$redeemed;}
    }
    usort($campaigns,static fn(array $a,array $b):int=>($b['completed']<=>$a['completed'])?:($b['redeemed']<=>$a['redeemed'])?:($b['participants']<=>$a['participants']));
    $summary['completion_rate']=mg_lqa_percent($summary['completed'],$summary['participants']);$summary['claim_rate']=mg_lqa_percent($summary['claimed'],$summary['inbox_delivered']);$summary['redemption_rate']=mg_lqa_percent($summary['redeemed'],$summary['inbox_delivered']);
    $summary['avg_completion_minutes']=$weighted['completion_n']>0?round($weighted['completion']/$weighted['completion_n'],1):0.0;$summary['avg_review_minutes']=$weighted['review_n']>0?round($weighted['review']/$weighted['review_n'],1):0.0;$summary['avg_redemption_minutes']=$weighted['redemption_n']>0?round($weighted['redemption']/$weighted['redemption_n'],1):0.0;

    $trend=[];
    $stmt=$pdo->prepare("SELECT DATE(joined_at) day,COUNT(*) started,SUM(status='completed') completed FROM loyalty_quest_participations WHERE merchant_user_id=? AND campaign_id IN ({$in}) AND joined_at>={$cutoff} GROUP BY DATE(joined_at)");$stmt->execute($baseParams);mg_lqa_merge_trend($trend,$stmt->fetchAll(PDO::FETCH_ASSOC)?:[],['started'=>'started','completed'=>'completed']);
    $stmt=$pdo->prepare("SELECT DATE(created_at) day,SUM(status='submitted') evidence_submitted,SUM(status='verified') evidence_verified FROM loyalty_quest_evidence WHERE merchant_user_id=? AND campaign_id IN ({$in}) AND created_at>={$cutoff} GROUP BY DATE(created_at)");$stmt->execute($baseParams);mg_lqa_merge_trend($trend,$stmt->fetchAll(PDO::FETCH_ASSOC)?:[],['evidence_submitted'=>'evidence_submitted','evidence_verified'=>'evidence_verified']);
    $stmt=$pdo->prepare("SELECT DATE(issued_at) day,COUNT(*) inbox_delivered,SUM(status='claimed') claimed,SUM(status='redeemed') redeemed FROM wallet_items WHERE merchant_user_id=? AND campaign_id IN ({$in}) AND source_type='loyalty_quest' AND status<>'cancelled' AND issued_at>={$cutoff} GROUP BY DATE(issued_at)");$stmt->execute($baseParams);mg_lqa_merge_trend($trend,$stmt->fetchAll(PDO::FETCH_ASSOC)?:[],['inbox_delivered'=>'inbox_delivered','claimed'=>'claimed','redeemed'=>'redeemed']);
    ksort($trend);$trend=array_values($trend);

    $verifyStmt=$pdo->prepare("SELECT evidence_type,COUNT(*) total,SUM(status='verified') verified,SUM(status='rejected') rejected,ROUND(AVG(CASE WHEN status='verified' THEN distance_meters END),1) avg_distance_meters FROM loyalty_quest_evidence WHERE merchant_user_id=? AND campaign_id IN ({$in}) AND created_at>={$cutoff} GROUP BY evidence_type ORDER BY total DESC,evidence_type ASC");$verifyStmt->execute($baseParams);$verification=[];
    foreach($verifyStmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row){$total=(int)$row['total'];$verification[]=['type'=>(string)$row['evidence_type'],'total'=>$total,'verified'=>(int)$row['verified'],'rejected'=>(int)$row['rejected'],'approval_rate'=>mg_lqa_percent((int)$row['verified'],$total),'avg_distance_meters'=>$total>=5&&$row['avg_distance_meters']!==null?(float)$row['avg_distance_meters']:null];}

    $sourceStmt=$pdo->prepare("SELECT COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.joined_from')),''),'unknown') source,COUNT(*) participants,SUM(status='completed') completed FROM loyalty_quest_participations WHERE merchant_user_id=? AND campaign_id IN ({$in}) AND joined_at>={$cutoff} GROUP BY source ORDER BY participants DESC");$sourceStmt->execute($baseParams);$sources=[];
    foreach($sourceStmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row)$sources[]=['source'=>(string)$row['source'],'participants'=>(int)$row['participants'],'completed'=>(int)$row['completed'],'completion_rate'=>mg_lqa_percent((int)$row['completed'],(int)$row['participants'])];

    $delivery=['ready'=>$deliveryReady,'queued'=>0,'delivered'=>0,'failed'=>0,'suppressed'=>0];
    foreach($campaigns as $row){$delivery['queued']+=$row['delivery_queued'];$delivery['delivered']+=$row['delivery_delivered'];$delivery['failed']+=$row['delivery_failed'];$delivery['suppressed']+=$row['delivery_suppressed'];}
    $delivery['success_rate']=mg_lqa_percent($delivery['delivered'],$delivery['queued']+$delivery['delivered']+$delivery['failed']+$delivery['suppressed']);
    $funnel=[['stage'=>'Contacts','count'=>$summary['contacts']],['stage'=>'Participants','count'=>$summary['participants']],['stage'=>'Completed','count'=>$summary['completed']],['stage'=>'Inbox delivered','count'=>$summary['inbox_delivered']],['stage'=>'Claimed','count'=>$summary['claimed']],['stage'=>'Redeemed','count'=>$summary['redeemed']]];
    return ['days'=>$days,'date_from'=>gmdate('Y-m-d',time()-$days*86400),'date_to'=>gmdate('Y-m-d'),'campaign_filter'=>$campaignRef?:null,'summary'=>$summary,'funnel'=>$funnel,'campaigns'=>$campaigns,'trend'=>$trend,'verification'=>$verification,'sources'=>$sources,'delivery'=>$delivery,'privacy'=>['contains_personal_data'=>false,'minimum_geo_group_size'=>5,'precise_coordinates_exported'=>false]];
}
