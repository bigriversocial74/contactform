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
    $stmt=$pdo->prepare("SELECT e.public_id,e.event_type,e.source_type,e.source_public_id,e.amount_minor,e.currency,e.reason,e.created_at,cc.public_id campaign_public_id,cc.title campaign_title,r.title rule_title
      FROM creator_campaign_earning_events e INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id LEFT JOIN creator_campaign_compensation_rules r ON r.id=e.rule_id WHERE e.creator_user_id=? ORDER BY e.created_at DESC,e.id DESC LIMIT 300");
    $stmt->execute([(int)$context['creator_user_id']]);$items=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    $totals=[];foreach($items as $item){$c=(string)$item['currency'];$totals[$c]=($totals[$c]??0)+(int)$item['amount_minor'];}
    return ['items'=>$items,'totals'=>$totals];
}
