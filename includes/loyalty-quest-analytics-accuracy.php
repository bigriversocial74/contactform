<?php
declare(strict_types=1);

function mg_lqa_accuracy_scope(PDO $pdo,int $merchantId,int $days,string $campaignRef=''): array
{
    $days=mg_lqa_days($days);$campaignRef=mg_lqa_campaign_ref($campaignRef);
    $sql="SELECT id FROM campaigns WHERE merchant_user_id=? AND campaign_type='loyalty_quest'";$params=[$merchantId];
    if($campaignRef!==''){$sql.=' AND public_id=?';$params[]=$campaignRef;}
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$ids=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN)?:[]);
    return ['days'=>$days,'cutoff'=>"DATE_SUB(NOW(),INTERVAL {$days} DAY)",'ids'=>$ids,'in'=>$ids?implode(',',array_fill(0,count($ids),'?')):'','params'=>array_merge([$merchantId],$ids)];
}

function mg_lqa_accuracy_trend(PDO $pdo,array $scope): array
{
    if($scope['ids']===[])return [];
    $in=$scope['in'];$cutoff=$scope['cutoff'];$params=$scope['params'];$trend=[];
    $queries=[
        ["SELECT DATE(joined_at) day,COUNT(*) total FROM loyalty_quest_participations WHERE merchant_user_id=? AND campaign_id IN ({$in}) AND joined_at>={$cutoff} GROUP BY DATE(joined_at)",'started'],
        ["SELECT DATE(completed_at) day,COUNT(*) total FROM loyalty_quest_participations WHERE merchant_user_id=? AND campaign_id IN ({$in}) AND completed_at IS NOT NULL AND completed_at>={$cutoff} GROUP BY DATE(completed_at)",'completed'],
        ["SELECT DATE(created_at) day,COUNT(*) total FROM loyalty_quest_evidence WHERE merchant_user_id=? AND campaign_id IN ({$in}) AND created_at>={$cutoff} GROUP BY DATE(created_at)",'evidence_submitted'],
        ["SELECT DATE(verified_at) day,COUNT(*) total FROM loyalty_quest_evidence WHERE merchant_user_id=? AND campaign_id IN ({$in}) AND verified_at IS NOT NULL AND verified_at>={$cutoff} GROUP BY DATE(verified_at)",'evidence_verified'],
        ["SELECT DATE(issued_at) day,COUNT(*) total FROM wallet_items WHERE merchant_user_id=? AND campaign_id IN ({$in}) AND source_type='loyalty_quest' AND status<>'cancelled' AND issued_at>={$cutoff} GROUP BY DATE(issued_at)",'inbox_delivered'],
        ["SELECT DATE(claimed_at) day,COUNT(*) total FROM wallet_items WHERE merchant_user_id=? AND campaign_id IN ({$in}) AND source_type='loyalty_quest' AND claimed_at IS NOT NULL AND claimed_at>={$cutoff} GROUP BY DATE(claimed_at)",'claimed'],
        ["SELECT DATE(redeemed_at) day,COUNT(*) total FROM wallet_items WHERE merchant_user_id=? AND campaign_id IN ({$in}) AND source_type='loyalty_quest' AND redeemed_at IS NOT NULL AND redeemed_at>={$cutoff} GROUP BY DATE(redeemed_at)",'redeemed'],
    ];
    foreach($queries as [$sql,$field]){
        $stmt=$pdo->prepare($sql);$stmt->execute($params);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row){$day=(string)$row['day'];$trend[$day]??=['date'=>$day,'started'=>0,'completed'=>0,'evidence_submitted'=>0,'evidence_verified'=>0,'inbox_delivered'=>0,'claimed'=>0,'redeemed'=>0];$trend[$day][$field]+=(int)$row['total'];}
    }
    ksort($trend);return array_values($trend);
}

function mg_lqa_accuracy_sources(PDO $pdo,array $scope): array
{
    if($scope['ids']===[])return [];
    $sql="SELECT COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(lqp.metadata_json,'$.joined_from')),''),NULLIF(cc.source,''),'unknown') source,COUNT(*) participants,SUM(lqp.status='completed') completed FROM loyalty_quest_participations lqp LEFT JOIN campaign_contacts cc ON cc.id=lqp.contact_id AND cc.merchant_user_id=lqp.merchant_user_id WHERE lqp.merchant_user_id=? AND lqp.campaign_id IN ({$scope['in']}) AND lqp.joined_at>={$scope['cutoff']} GROUP BY source ORDER BY participants DESC";
    $stmt=$pdo->prepare($sql);$stmt->execute($scope['params']);$out=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row)$out[]=['source'=>(string)$row['source'],'participants'=>(int)$row['participants'],'completed'=>(int)$row['completed'],'completion_rate'=>mg_lqa_percent((int)$row['completed'],(int)$row['participants'])];
    return $out;
}

function mg_lqa_accuracy_currency(PDO $pdo,array $scope): array
{
    if($scope['ids']===[])return ['totals'=>[],'campaigns'=>[]];
    $cutoff=$scope['cutoff'];$in=$scope['in'];$params=$scope['params'];
    $stmt=$pdo->prepare("SELECT currency_snapshot currency,SUM(issued_at>={$cutoff}) inbox_delivered,SUM(redeemed_at IS NOT NULL AND redeemed_at>={$cutoff}) redeemed,COALESCE(SUM(CASE WHEN issued_at>={$cutoff} THEN value_cents_snapshot ELSE 0 END),0) issued_value_cents,COALESCE(SUM(CASE WHEN redeemed_at IS NOT NULL AND redeemed_at>={$cutoff} THEN value_cents_snapshot ELSE 0 END),0) redeemed_value_cents FROM wallet_items WHERE merchant_user_id=? AND campaign_id IN ({$in}) AND source_type='loyalty_quest' AND status<>'cancelled' GROUP BY currency_snapshot ORDER BY currency_snapshot");
    $stmt->execute($params);$totals=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row)$totals[]=['currency'=>(string)$row['currency'],'inbox_delivered'=>(int)$row['inbox_delivered'],'redeemed'=>(int)$row['redeemed'],'issued_value_cents'=>(int)$row['issued_value_cents'],'redeemed_value_cents'=>(int)$row['redeemed_value_cents']];
    $stmt=$pdo->prepare("SELECT c.public_id,currency_snapshot currency,SUM(wi.issued_at>={$cutoff}) inbox_delivered,SUM(wi.redeemed_at IS NOT NULL AND wi.redeemed_at>={$cutoff}) redeemed,COALESCE(SUM(CASE WHEN wi.issued_at>={$cutoff} THEN wi.value_cents_snapshot ELSE 0 END),0) issued_value_cents,COALESCE(SUM(CASE WHEN wi.redeemed_at IS NOT NULL AND wi.redeemed_at>={$cutoff} THEN wi.value_cents_snapshot ELSE 0 END),0) redeemed_value_cents FROM wallet_items wi INNER JOIN campaigns c ON c.id=wi.campaign_id AND c.merchant_user_id=wi.merchant_user_id WHERE wi.merchant_user_id=? AND wi.campaign_id IN ({$in}) AND wi.source_type='loyalty_quest' AND wi.status<>'cancelled' GROUP BY c.public_id,currency_snapshot ORDER BY c.public_id,currency_snapshot");
    $stmt->execute($params);$campaigns=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row)$campaigns[(string)$row['public_id']][]=['currency'=>(string)$row['currency'],'inbox_delivered'=>(int)$row['inbox_delivered'],'redeemed'=>(int)$row['redeemed'],'issued_value_cents'=>(int)$row['issued_value_cents'],'redeemed_value_cents'=>(int)$row['redeemed_value_cents']];
    return ['totals'=>$totals,'campaigns'=>$campaigns];
}

function mg_lqa_apply_accuracy(PDO $pdo,int $merchantId,array $report): array
{
    $scope=mg_lqa_accuracy_scope($pdo,$merchantId,(int)($report['days']??30),(string)($report['campaign_filter']??''));
    $report['trend']=mg_lqa_accuracy_trend($pdo,$scope);
    $report['sources']=mg_lqa_accuracy_sources($pdo,$scope);
    $currency=mg_lqa_accuracy_currency($pdo,$scope);$report['value_by_currency']=$currency['totals'];
    unset($report['summary']['issued_value_cents'],$report['summary']['redeemed_value_cents']);
    foreach($report['campaigns'] as &$campaign){
        $values=$currency['campaigns'][(string)$campaign['id']]??[];
        $campaign['value_by_currency']=$values;
        $campaign['currency']=count($values)===1?(string)$values[0]['currency']:(count($values)>1?'MIXED':'USD');
        $campaign['mixed_currency']=count($values)>1;
        $campaign['issued_value_cents']=count($values)===1?(int)$values[0]['issued_value_cents']:null;
        $campaign['redeemed_value_cents']=count($values)===1?(int)$values[0]['redeemed_value_cents']:null;
        $campaign['delivery_success_rate']=mg_lqa_percent((int)($campaign['delivery_delivered']??0),(int)($campaign['delivery_delivered']??0)+(int)($campaign['delivery_failed']??0));
    }
    unset($campaign);
    $report['delivery']['success_rate']=mg_lqa_percent((int)($report['delivery']['delivered']??0),(int)($report['delivery']['delivered']??0)+(int)($report['delivery']['failed']??0));
    $report['privacy']['currency_values_combined']=false;
    $report['accuracy']=['trend_uses_event_timestamps'=>true,'currency_grouped'=>true,'delivery_rate_excludes_pending'=>true,'source_fallback_uses_contact'=>true];
    return $report;
}
