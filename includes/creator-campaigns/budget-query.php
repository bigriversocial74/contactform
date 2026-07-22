<?php
declare(strict_types=1);

function mg_creator_campaign_budget_dashboard(PDO $pdo,array $user,array $filters=[]): array
{
    $context=mg_creator_campaign_budget_merchant_context($pdo,$user,'merchant.creator_budgets.view');$workspaceId=(int)$context['workspace_id'];
    $sql="SELECT b.*,cc.public_id campaign_public_id,cc.title campaign_title,
      COALESCE(SUM(e.available_delta_minor),0) available_minor,COALESCE(SUM(e.reserved_delta_minor),0) reserved_minor,COALESCE(SUM(e.committed_delta_minor),0) committed_minor
      FROM creator_campaign_budgets b INNER JOIN creator_campaigns cc ON cc.id=b.campaign_id LEFT JOIN creator_campaign_budget_events e ON e.budget_id=b.id WHERE cc.workspace_id=?";
    $params=[$workspaceId];if(trim((string)($filters['campaign_id']??''))!==''){$sql.=' AND cc.public_id=?';$params[]=trim((string)$filters['campaign_id']);}
    $sql.=' GROUP BY b.id ORDER BY b.updated_at DESC,b.id DESC';$stmt=$pdo->prepare($sql);$stmt->execute($params);$budgets=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    $stmt=$pdo->prepare("SELECT r.public_id,r.status,r.amount_minor,r.currency,r.reserved_at,r.committed_at,r.released_at,e.public_id earning_public_id,cc.title campaign_title,cp.display_name creator_name,b.public_id budget_public_id FROM creator_campaign_budget_reservations r INNER JOIN creator_campaign_budgets b ON b.id=r.budget_id INNER JOIN creator_campaigns cc ON cc.id=r.campaign_id INNER JOIN creator_campaign_earning_events e ON e.id=r.earning_event_id INNER JOIN creator_campaign_participants p ON p.id=r.participant_id INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id WHERE cc.workspace_id=? ORDER BY r.updated_at DESC,r.id DESC LIMIT 300");$stmt->execute([$workspaceId]);$reservations=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    $metrics=['budget_count'=>count($budgets),'available_minor'=>0,'reserved_minor'=>0,'committed_minor'=>0];foreach($budgets as $b){foreach(['available_minor','reserved_minor','committed_minor'] as $k)$metrics[$k]+=(int)$b[$k];}
    return ['metrics'=>$metrics,'budgets'=>$budgets,'reservations'=>$reservations];
}
