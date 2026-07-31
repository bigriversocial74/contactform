<?php
declare(strict_types=1);

function mg_creator_campaign_payout_dashboard_merchant(PDO $pdo,array $user,array $filters=[]): array
{
    $context=mg_creator_campaign_payout_merchant_context($pdo,$user,'merchant.creator_payouts.view');$workspaceId=(int)$context['workspace_id'];
    $sql="SELECT p.public_id,p.status,p.amount_minor,p.currency,p.provider_reference,p.created_at,p.updated_at,cc.public_id campaign_public_id,cc.title campaign_title,participant.public_id participant_public_id,cp.display_name creator_name,pp.status profile_status,COUNT(i.id) item_count
      FROM creator_campaign_payouts p INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id INNER JOIN creator_campaign_participants participant ON participant.id=p.participant_id INNER JOIN creator_profiles cp ON cp.id=participant.creator_profile_id INNER JOIN creator_campaign_payout_profiles pp ON pp.id=p.payout_profile_id LEFT JOIN creator_campaign_payout_items i ON i.payout_id=p.id WHERE cc.workspace_id=?";$params=[$workspaceId];if(trim((string)($filters['status']??''))!==''){$sql.=' AND p.status=?';$params[]=trim((string)$filters['status']);}$sql.=' GROUP BY p.id ORDER BY p.updated_at DESC,p.id DESC LIMIT 300';$stmt=$pdo->prepare($sql);$stmt->execute($params);$payouts=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    $stmt=$pdo->prepare("SELECT d.public_id,d.source_type,d.source_public_id,d.status,d.reason,d.resolution_note,d.opened_at,d.updated_at,cc.title campaign_title,cp.display_name creator_name FROM creator_campaign_disputes d INNER JOIN creator_campaigns cc ON cc.id=d.campaign_id INNER JOIN creator_campaign_participants participant ON participant.id=d.participant_id INNER JOIN creator_profiles cp ON cp.id=participant.creator_profile_id WHERE cc.workspace_id=? ORDER BY d.updated_at DESC,d.id DESC LIMIT 300");$stmt->execute([$workspaceId]);$disputes=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    $stmt=$pdo->prepare("SELECT pp.public_id,pp.status,pp.currency,pp.method_label,pp.minimum_payout_minor,pp.eligibility_note,cp.display_name creator_name,participant.public_id participant_public_id,cc.title campaign_title FROM creator_campaign_payout_profiles pp INNER JOIN creator_campaign_participants participant ON participant.creator_user_id=pp.creator_user_id INNER JOIN creator_profiles cp ON cp.id=participant.creator_profile_id INNER JOIN creator_campaigns cc ON cc.id=participant.campaign_id WHERE cc.workspace_id=? GROUP BY pp.id,participant.id ORDER BY pp.updated_at DESC LIMIT 300");$stmt->execute([$workspaceId]);$profiles=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    $metrics=['payout_count'=>count($payouts),'scheduled_minor'=>0,'paid_minor'=>0,'active_disputes'=>0];foreach($payouts as$p){if(in_array($p['status'],['draft','approved','processing'],true))$metrics['scheduled_minor']+=(int)$p['amount_minor'];if($p['status']==='paid')$metrics['paid_minor']+=(int)$p['amount_minor'];}foreach($disputes as$d)if(in_array($d['status'],['open','under_review'],true))$metrics['active_disputes']++;
    $policies=[];if(function_exists('mg_creator_campaign_operations_installed')&&mg_creator_campaign_operations_installed($pdo)){$stmt=$pdo->prepare('SELECT * FROM creator_campaign_payout_policies WHERE workspace_id=? ORDER BY currency');$stmt->execute([$workspaceId]);$policies=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];foreach($policies as &$policy)$policy['next_payout_date']=mg_creator_campaign_operations_next_payout_date($policy);unset($policy);}
    return['metrics'=>$metrics,'profiles'=>$profiles,'payouts'=>$payouts,'disputes'=>$disputes,'policies'=>$policies,'operations_url'=>'/merchant-creator-affiliate-operations.php'];
}

function mg_creator_campaign_payout_dashboard_creator(PDO $pdo,array $user): array
{
    $context=mg_creator_campaign_payout_creator_context($pdo,$user,'creator.campaign_payouts.view_own');
    $creatorId=(int)$context['creator_user_id'];
    $stmt=$pdo->prepare('SELECT public_id,status,currency,method_label,minimum_payout_minor,eligibility_note,updated_at FROM creator_campaign_payout_profiles WHERE creator_user_id=? ORDER BY currency');$stmt->execute([$creatorId]);$profiles=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    $stmt=$pdo->prepare("SELECT p.public_id,p.status,p.amount_minor,p.currency,p.provider_reference,p.created_at,p.updated_at,p.approved_at,p.processing_at,p.paid_at,p.failed_at,p.cancelled_at,p.reversed_at,cc.title campaign_title,COUNT(i.id) item_count FROM creator_campaign_payouts p INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id LEFT JOIN creator_campaign_payout_items i ON i.payout_id=p.id WHERE p.creator_user_id=? GROUP BY p.id ORDER BY p.updated_at DESC,p.id DESC LIMIT 300");$stmt->execute([$creatorId]);$payouts=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    $stmt=$pdo->prepare("SELECT d.public_id,d.source_type,d.source_public_id,d.status,d.reason,d.resolution_note,d.opened_at,d.updated_at,cc.title campaign_title FROM creator_campaign_disputes d INNER JOIN creator_campaigns cc ON cc.id=d.campaign_id WHERE d.creator_user_id=? ORDER BY d.updated_at DESC,d.id DESC LIMIT 300");$stmt->execute([$creatorId]);$disputes=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    $totals=[];foreach($payouts as$p){$c=(string)$p['currency'];if(!isset($totals[$c]))$totals[$c]=['scheduled_minor'=>0,'processing_minor'=>0,'paid_minor'=>0,'failed_minor'=>0];if(in_array($p['status'],['draft','approved'],true))$totals[$c]['scheduled_minor']+=(int)$p['amount_minor'];if($p['status']==='processing')$totals[$c]['processing_minor']+=(int)$p['amount_minor'];if($p['status']==='paid')$totals[$c]['paid_minor']+=(int)$p['amount_minor'];if($p['status']==='failed')$totals[$c]['failed_minor']+=(int)$p['amount_minor'];}
    $policies=function_exists('mg_creator_campaign_operations_creator_policy_views')?mg_creator_campaign_operations_creator_policy_views($pdo,$creatorId):[];
    return['profiles'=>$profiles,'payouts'=>$payouts,'disputes'=>$disputes,'totals'=>$totals,'policies'=>$policies,'status_guide'=>[
        'draft'=>'The merchant assembled the payout; approval is still required.',
        'approved'=>'The merchant approved the payout for external processing.',
        'processing'=>'The merchant recorded that payment is being processed externally.',
        'paid'=>'The merchant confirmed payment and recorded an external provider reference.',
        'failed'=>'External payment did not complete and requires merchant attention.',
        'cancelled'=>'The payout was cancelled and its scheduled items were released.',
        'reversed'=>'A previously paid payout was reversed and remains in the audit history.',
    ]];
}
