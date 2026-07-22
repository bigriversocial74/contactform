<?php
declare(strict_types=1);

function mg_creator_campaign_tracking_dashboard_merchant(PDO $pdo,array $user):array
{
    $context=mg_creator_campaign_tracking_merchant_context($pdo,$user,'merchant.creator_tracking.view');
    $workspaceId=(int)$context['workspace_id'];
    $metrics=[];
    $queries=[
        'sources'=>"SELECT COUNT(*) FROM creator_campaign_tracking_sources s INNER JOIN creator_campaigns cc ON cc.id=s.campaign_id WHERE cc.workspace_id=? AND s.status<>'retired'",
        'events_30d'=>"SELECT COUNT(*) FROM creator_campaign_tracking_events e INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id WHERE cc.workspace_id=? AND e.occurred_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)",
        'unique_clicks_30d'=>"SELECT COUNT(*) FROM creator_campaign_tracking_events e INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id WHERE cc.workspace_id=? AND e.event_type='click' AND e.is_unique=1 AND e.status='accepted' AND e.occurred_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)",
        'conversions_30d'=>"SELECT COUNT(*) FROM creator_campaign_attributions a INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id WHERE cc.workspace_id=? AND a.status IN ('attributed','overridden') AND a.attributed_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)",
        'suspect_events'=>"SELECT COUNT(*) FROM creator_campaign_tracking_events e INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id WHERE cc.workspace_id=? AND e.status IN ('duplicate','suspect')",
    ];
    foreach($queries as $key=>$sql){$stmt=$pdo->prepare($sql);$stmt->execute([$workspaceId]);$metrics[$key]=(int)$stmt->fetchColumn();}
    $stmt=$pdo->prepare(
        "SELECT cc.public_id campaign_public_id,cc.title campaign_title,cc.status,
                COUNT(DISTINCT s.id) source_count,
                COUNT(DISTINCT e.id) event_count,
                COUNT(DISTINCT a.id) attribution_count
         FROM creator_campaigns cc
         LEFT JOIN creator_campaign_tracking_sources s ON s.campaign_id=cc.id
         LEFT JOIN creator_campaign_tracking_events e ON e.campaign_id=cc.id AND e.occurred_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)
         LEFT JOIN creator_campaign_attributions a ON a.campaign_id=cc.id AND a.attributed_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)
         WHERE cc.workspace_id=?
         GROUP BY cc.id ORDER BY cc.updated_at DESC LIMIT 100"
    );$stmt->execute([$workspaceId]);$campaigns=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    $participants=$pdo->prepare(
        "SELECT p.public_id participant_public_id,cc.public_id campaign_public_id,cc.title campaign_title,
                cp.display_name creator_name,p.status
         FROM creator_campaign_participants p
         INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id
         INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id
         INNER JOIN creator_campaign_agreements a ON a.participant_id=p.id
         WHERE cc.workspace_id=? AND p.status='active' AND a.latest_accepted_version_id IS NOT NULL
         ORDER BY cc.title,cp.display_name"
    );$participants->execute([$workspaceId]);
    return ['metrics'=>$metrics,'campaigns'=>$campaigns,'participants'=>$participants->fetchAll(PDO::FETCH_ASSOC)?:[],'definitions'=>[
        'channels'=>mg_creator_campaign_tracking_channels(),'event_types'=>mg_creator_campaign_tracking_event_types(),
        'source_statuses'=>mg_creator_campaign_tracking_source_statuses(),'attribution_models'=>mg_creator_campaign_attribution_models(),
    ]];
}

function mg_creator_campaign_tracking_sources_merchant(PDO $pdo,array $user,array $filters=[]):array
{
    $context=mg_creator_campaign_tracking_merchant_context($pdo,$user,'merchant.creator_tracking.view');
    $where=['cc.workspace_id=?'];$params=[(int)$context['workspace_id']];
    $campaign=trim((string)($filters['campaign_id']??''));if($campaign!==''){$where[]='cc.public_id=?';$params[]=$campaign;}
    $status=trim((string)($filters['status']??''));if($status!==''){$where[]='s.status=?';$params[]=$status;}
    $stmt=$pdo->prepare(
        "SELECT s.*,p.public_id participant_public_id,cp.display_name creator_name,
                cc.public_id campaign_public_id,cc.title campaign_title,
                (SELECT COUNT(*) FROM creator_campaign_tracking_events e WHERE e.source_id=s.id) event_count,
                (SELECT COUNT(*) FROM creator_campaign_tracking_events e WHERE e.source_id=s.id AND e.event_type='click' AND e.is_unique=1 AND e.status='accepted') unique_clicks,
                (SELECT COUNT(*) FROM creator_campaign_attributions a WHERE a.source_id=s.id AND a.status IN ('attributed','overridden')) conversions
         FROM creator_campaign_tracking_sources s
         INNER JOIN creator_campaign_participants p ON p.id=s.participant_id
         INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id
         INNER JOIN creator_campaigns cc ON cc.id=s.campaign_id
         WHERE ".implode(' AND ',$where)." ORDER BY s.updated_at DESC,s.id DESC LIMIT 250"
    );$stmt->execute($params);$items=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    foreach($items as &$item){$item=mg_creator_campaign_tracking_source_payload($item);}unset($item);
    return ['items'=>$items];
}

function mg_creator_campaign_tracking_events_merchant(PDO $pdo,array $user,array $filters=[]):array
{
    $context=mg_creator_campaign_tracking_merchant_context($pdo,$user,'merchant.creator_tracking.view');
    $where=['cc.workspace_id=?'];$params=[(int)$context['workspace_id']];
    foreach(['campaign_id'=>'cc.public_id','source_id'=>'s.public_id','status'=>'e.status','event_type'=>'e.event_type'] as $key=>$column){
        $value=trim((string)($filters[$key]??''));if($value!==''){$where[]=$column.'=?';$params[]=$value;}
    }
    $stmt=$pdo->prepare(
        "SELECT e.public_id,e.event_type,e.status,e.is_unique,e.risk_score,e.risk_flags_json,e.target_path,
                e.referrer_host,e.occurred_at,s.public_id source_public_id,s.label source_label,
                p.public_id participant_public_id,cp.display_name creator_name,
                cc.public_id campaign_public_id,cc.title campaign_title,
                a.public_id attribution_public_id,a.status attribution_status
         FROM creator_campaign_tracking_events e
         LEFT JOIN creator_campaign_tracking_sources s ON s.id=e.source_id
         LEFT JOIN creator_campaign_participants p ON p.id=e.participant_id
         LEFT JOIN creator_profiles cp ON cp.id=p.creator_profile_id
         INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id
         LEFT JOIN creator_campaign_attributions a ON a.conversion_event_id=e.id
         WHERE ".implode(' AND ',$where)." ORDER BY e.occurred_at DESC,e.id DESC LIMIT 500"
    );$stmt->execute($params);$items=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    foreach($items as &$item){$item['risk_flags']=mg_creator_campaign_participation_decode_json($item['risk_flags_json']??null);unset($item['risk_flags_json']);}unset($item);
    return ['items'=>$items];
}

function mg_creator_campaign_attributions_merchant(PDO $pdo,array $user,array $filters=[]):array
{
    $context=mg_creator_campaign_tracking_merchant_context($pdo,$user,'merchant.creator_attribution.view');
    $where=['cc.workspace_id=?'];$params=[(int)$context['workspace_id']];
    $campaign=trim((string)($filters['campaign_id']??''));if($campaign!==''){$where[]='cc.public_id=?';$params[]=$campaign;}
    $status=trim((string)($filters['status']??''));if($status!==''){$where[]='a.status=?';$params[]=$status;}
    $stmt=$pdo->prepare(
        "SELECT a.*,e.public_id conversion_event_public_id,e.event_type conversion_type,e.occurred_at,
                s.public_id source_public_id,s.label source_label,p.public_id participant_public_id,
                cp.display_name creator_name,cc.public_id campaign_public_id,cc.title campaign_title
         FROM creator_campaign_attributions a
         INNER JOIN creator_campaign_tracking_events e ON e.id=a.conversion_event_id
         LEFT JOIN creator_campaign_tracking_sources s ON s.id=a.source_id
         LEFT JOIN creator_campaign_participants p ON p.id=a.participant_id
         LEFT JOIN creator_profiles cp ON cp.id=p.creator_profile_id
         INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id
         WHERE ".implode(' AND ',$where)." ORDER BY a.attributed_at DESC,a.id DESC LIMIT 500"
    );$stmt->execute($params);return ['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)?:[]];
}

function mg_creator_campaign_tracking_dashboard_creator(PDO $pdo,array $user):array
{
    $context=mg_creator_campaign_tracking_creator_context($pdo,$user,'creator.campaign_tracking.view_own');
    $creatorId=(int)$context['creator_user_id'];
    $stmt=$pdo->prepare(
        "SELECT p.public_id participant_public_id,p.status,cc.public_id campaign_public_id,cc.title campaign_title,
                mw.display_name merchant_name,
                (SELECT COUNT(*) FROM creator_campaign_tracking_sources s WHERE s.participant_id=p.id AND s.status<>'retired') source_count,
                (SELECT COUNT(*) FROM creator_campaign_tracking_events e WHERE e.participant_id=p.id AND e.event_type='click' AND e.is_unique=1 AND e.status='accepted') unique_clicks,
                (SELECT COUNT(*) FROM creator_campaign_attributions a WHERE a.participant_id=p.id AND a.status IN ('attributed','overridden')) conversions
         FROM creator_campaign_participants p
         INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id
         INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
         WHERE p.creator_user_id=? AND p.status='active'
         ORDER BY p.updated_at DESC"
    );$stmt->execute([$creatorId]);
    return ['campaigns'=>$stmt->fetchAll(PDO::FETCH_ASSOC)?:[],'definitions'=>[
        'channels'=>mg_creator_campaign_tracking_channels(),'source_statuses'=>mg_creator_campaign_tracking_source_statuses(),
        'attribution_models'=>['first_touch','last_touch','direct'],
    ]];
}

function mg_creator_campaign_tracking_sources_creator(PDO $pdo,array $user,array $filters=[]):array
{
    $context=mg_creator_campaign_tracking_creator_context($pdo,$user,'creator.campaign_tracking.view_own');
    $where=['s.creator_user_id=?'];$params=[(int)$context['creator_user_id']];
    $campaign=trim((string)($filters['campaign_id']??''));if($campaign!==''){$where[]='cc.public_id=?';$params[]=$campaign;}
    $stmt=$pdo->prepare(
        "SELECT s.*,p.public_id participant_public_id,cc.public_id campaign_public_id,cc.title campaign_title,
                mw.display_name merchant_name,
                (SELECT COUNT(*) FROM creator_campaign_tracking_events e WHERE e.source_id=s.id) event_count,
                (SELECT COUNT(*) FROM creator_campaign_tracking_events e WHERE e.source_id=s.id AND e.event_type='click' AND e.is_unique=1 AND e.status='accepted') unique_clicks,
                (SELECT COUNT(*) FROM creator_campaign_attributions a WHERE a.source_id=s.id AND a.status IN ('attributed','overridden')) conversions
         FROM creator_campaign_tracking_sources s
         INNER JOIN creator_campaign_participants p ON p.id=s.participant_id
         INNER JOIN creator_campaigns cc ON cc.id=s.campaign_id
         INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
         WHERE ".implode(' AND ',$where)." ORDER BY s.updated_at DESC,s.id DESC LIMIT 250"
    );$stmt->execute($params);$items=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    foreach($items as &$item){$item=mg_creator_campaign_tracking_source_payload($item);}unset($item);
    return ['items'=>$items];
}

function mg_creator_campaign_tracking_events_creator(PDO $pdo,array $user,array $filters=[]):array
{
    $context=mg_creator_campaign_tracking_creator_context($pdo,$user,'creator.campaign_tracking.view_own');
    $stmt=$pdo->prepare(
        "SELECT e.public_id,e.event_type,e.status,e.is_unique,e.risk_score,e.target_path,e.referrer_host,e.occurred_at,
                s.public_id source_public_id,s.label source_label,cc.public_id campaign_public_id,cc.title campaign_title,
                a.public_id attribution_public_id,a.status attribution_status
         FROM creator_campaign_tracking_events e
         LEFT JOIN creator_campaign_tracking_sources s ON s.id=e.source_id
         INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id
         LEFT JOIN creator_campaign_attributions a ON a.conversion_event_id=e.id
         WHERE e.creator_user_id=? ORDER BY e.occurred_at DESC,e.id DESC LIMIT 250"
    );$stmt->execute([(int)$context['creator_user_id']]);return ['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)?:[]];
}
