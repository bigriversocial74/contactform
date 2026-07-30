<?php
declare(strict_types=1);

function mg_creator_campaign_compensation_dashboard_merchant(PDO $pdo,array $user,array $filters=[]): array
{
    $context=mg_creator_campaign_compensation_merchant_context($pdo,$user,'merchant.creator_compensation.view');
    $workspaceId=(int)$context['workspace_id'];
    $stmt=$pdo->prepare("SELECT COUNT(*) rules_total,SUM(r.status='active') active_rules FROM creator_campaign_compensation_rules r INNER JOIN creator_campaigns cc ON cc.id=r.campaign_id WHERE cc.workspace_id=?");
    $stmt->execute([$workspaceId]);$rules=$stmt->fetch(PDO::FETCH_ASSOC)?:[];
    $stmt=$pdo->prepare("SELECT COALESCE(SUM(e.amount_minor),0) net_earnings_minor,COUNT(*) event_count,COUNT(DISTINCT e.creator_user_id) creators FROM creator_campaign_earning_events e INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id WHERE cc.workspace_id=?");
    $stmt->execute([$workspaceId]);$earnings=$stmt->fetch(PDO::FETCH_ASSOC)?:[];
    return ['metrics'=>array_merge($rules,$earnings),'rules'=>mg_creator_campaign_compensation_rules_merchant($pdo,$user,$filters)['items'],'earnings'=>mg_creator_campaign_earnings_merchant($pdo,$user,$filters)['items']];
}

function mg_creator_campaign_compensation_rules_merchant(PDO $pdo,array $user,array $filters=[]): array
{
    $context=mg_creator_campaign_compensation_merchant_context($pdo,$user,'merchant.creator_compensation.view');
    $sql="SELECT r.public_id,r.rule_code,r.title,r.compensation_type,r.trigger_type,r.status,r.lock_version,cc.public_id campaign_public_id,cc.title campaign_title,v.public_id version_public_id,v.version_number,v.currency,v.flat_amount_minor,v.rate_bps,v.minimum_source_amount_minor,v.maximum_earning_minor,v.terms_text
      FROM creator_campaign_compensation_rules r INNER JOIN creator_campaigns cc ON cc.id=r.campaign_id LEFT JOIN creator_campaign_compensation_rule_versions v ON v.id=r.current_version_id WHERE cc.workspace_id=?";
    $params=[(int)$context['workspace_id']];
    if(trim((string)($filters['campaign_id']??''))!==''){$sql.=' AND cc.public_id=?';$params[]=trim((string)$filters['campaign_id']);}
    $sql.=' ORDER BY r.updated_at DESC,r.id DESC LIMIT 200';
    $stmt=$pdo->prepare($sql);$stmt->execute($params);
    return ['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)?:[]];
}

function mg_creator_campaign_earnings_merchant(PDO $pdo,array $user,array $filters=[]): array
{
    $context=mg_creator_campaign_compensation_merchant_context($pdo,$user,'merchant.creator_earnings.view');
    $sql="SELECT e.public_id,e.event_type,e.source_type,e.source_public_id,e.amount_minor,e.currency,e.reason,e.created_at,cc.public_id campaign_public_id,cc.title campaign_title,p.public_id participant_public_id,cp.display_name creator_name,r.title rule_title
      FROM creator_campaign_earning_events e INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id INNER JOIN creator_campaign_participants p ON p.id=e.participant_id INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id LEFT JOIN creator_campaign_compensation_rules r ON r.id=e.rule_id WHERE cc.workspace_id=?";
    $params=[(int)$context['workspace_id']];
    if(trim((string)($filters['campaign_id']??''))!==''){$sql.=' AND cc.public_id=?';$params[]=trim((string)$filters['campaign_id']);}
    $sql.=' ORDER BY e.created_at DESC,e.id DESC LIMIT 300';
    $stmt=$pdo->prepare($sql);$stmt->execute($params);
    return ['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)?:[]];
}

function mg_creator_campaign_earnings_creator(PDO $pdo,array $user,array $filters=[]): array
{
    $context=mg_creator_campaign_compensation_creator_context($pdo,$user,'creator.campaign_earnings.view_own');
    $creatorUserId=(int)$context['creator_user_id'];
    $stmt=$pdo->prepare("SELECT e.id,e.public_id,e.event_type,e.source_type,e.source_public_id,e.amount_minor,e.currency,e.reason,e.created_at,cc.public_id campaign_public_id,cc.title campaign_title,r.title rule_title
      FROM creator_campaign_earning_events e
      INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id
      LEFT JOIN creator_campaign_compensation_rules r ON r.id=e.rule_id
      WHERE e.creator_user_id=? ORDER BY e.created_at DESC,e.id DESC LIMIT 300");
    $stmt->execute([$creatorUserId]);
    $items=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    $eventIds=array_values(array_filter(array_map(static fn(array $item):int=>(int)$item['id'],$items)));
    $reservations=[];$payouts=[];
    if($eventIds!==[]){
        $marks=implode(',',array_fill(0,count($eventIds),'?'));
        $stmt=$pdo->prepare("SELECT earning_event_id,public_id,status,amount_minor,currency,reserved_at,committed_at,released_at FROM creator_campaign_budget_reservations WHERE earning_event_id IN ({$marks})");
        $stmt->execute($eventIds);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row)$reservations[(int)$row['earning_event_id']]=$row;
        $stmt=$pdo->prepare("SELECT i.earning_event_id,i.public_id payout_item_public_id,i.status payout_item_status,i.amount_minor payout_item_amount_minor,p.public_id payout_public_id,p.status payout_status,p.provider_reference,p.created_at payout_created_at,p.approved_at,p.processing_at,p.paid_at,p.failed_at,p.cancelled_at,p.reversed_at
          FROM creator_campaign_payout_items i INNER JOIN creator_campaign_payouts p ON p.id=i.payout_id
          WHERE i.earning_event_id IN ({$marks}) ORDER BY p.updated_at DESC,p.id DESC,i.id DESC");
        $stmt->execute($eventIds);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row){$eventId=(int)$row['earning_event_id'];if(!isset($payouts[$eventId]))$payouts[$eventId]=$row;}
    }
    $totals=[];
    foreach($items as &$item){
        $eventId=(int)$item['id'];$currency=(string)$item['currency'];
        if(!isset($totals[$currency]))$totals[$currency]=['net_minor'=>0,'earned_minor'=>0,'reserved_minor'=>0,'committed_minor'=>0,'scheduled_minor'=>0,'processing_minor'=>0,'paid_minor'=>0,'adjusted_minor'=>0];
        $amount=(int)$item['amount_minor'];$totals[$currency]['net_minor']+=$amount;
        if($amount>0&&$item['event_type']==='earning')$totals[$currency]['earned_minor']+=$amount;
        if($amount<0)$totals[$currency]['adjusted_minor']+=abs($amount);
        $reservation=$reservations[$eventId]??null;$payout=$payouts[$eventId]??null;
        $item['reservation_id']=$reservation['public_id']??null;$item['reservation_status']=$reservation['status']??null;$item['reservation_amount_minor']=isset($reservation['amount_minor'])?(int)$reservation['amount_minor']:null;$item['committed_at']=$reservation['committed_at']??null;
        $item['payout_id']=$payout['payout_public_id']??null;$item['payout_status']=$payout['payout_status']??null;$item['payout_item_status']=$payout['payout_item_status']??null;$item['provider_reference']=$payout['provider_reference']??null;$item['paid_at']=$payout['paid_at']??null;
        $lifecycle='earned';
        if($item['event_type']==='adjustment')$lifecycle=$amount<0?'adjusted':'adjustment';
        elseif($item['event_type']==='reversal')$lifecycle='reversed';
        elseif($payout){$lifecycle=match((string)$payout['payout_status']){'draft'=>'scheduled','approved'=>'approved','processing'=>'processing','paid'=>'paid','failed'=>'payment_failed','cancelled'=>'cancelled','reversed'=>'reversed',default=>'scheduled'};}
        elseif($reservation){$lifecycle=match((string)$reservation['status']){'reserved'=>'reserved','committed'=>'committed','released'=>'released','cancelled'=>'cancelled',default=>'earned'};}
        $item['lifecycle_status']=$lifecycle;
        if($amount>0&&$reservation&&!$payout){if($reservation['status']==='reserved')$totals[$currency]['reserved_minor']+=(int)$reservation['amount_minor'];if($reservation['status']==='committed')$totals[$currency]['committed_minor']+=(int)$reservation['amount_minor'];}
        if($amount>0&&$payout){$payoutAmount=(int)($payout['payout_item_amount_minor']??0);if(in_array($payout['payout_status'],['draft','approved'],true))$totals[$currency]['scheduled_minor']+=$payoutAmount;if($payout['payout_status']==='processing')$totals[$currency]['processing_minor']+=$payoutAmount;if($payout['payout_status']==='paid')$totals[$currency]['paid_minor']+=$payoutAmount;}
        unset($item['id']);
    }
    unset($item);
    $policies=function_exists('mg_creator_campaign_operations_creator_policies')?mg_creator_campaign_operations_creator_policies($pdo,$creatorUserId):[];
    return ['items'=>$items,'totals'=>$totals,'policies'=>$policies,'status_guide'=>[
        'earned'=>'Recorded but not yet reserved against a merchant budget.',
        'reserved'=>'Budget funds are reserved for this earning.',
        'committed'=>'Merchant obligation is committed and waiting for payout eligibility.',
        'scheduled'=>'Included in a draft or approved payout record.',
        'processing'=>'Merchant recorded that external payment processing has started.',
        'paid'=>'Merchant confirmed external payment with a provider reference.',
        'adjusted'=>'A refund or approved correction reduced or changed the earning.',
    ]];
}
