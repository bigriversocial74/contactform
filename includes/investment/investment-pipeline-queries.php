<?php
declare(strict_types=1);

function mg_investment_pipeline_dashboard_v2(PDO $pdo,array $filters=[]): array
{
    mg_investment_pipeline_sync_profiles($pdo);
    $stage=trim((string)($filters['stage']??''));$priority=trim((string)($filters['priority']??''));$search=trim((string)($filters['q']??''));
    $params=[];$where=['ip.status="active"'];
    if($stage!==''){$where[]='pr.stage=?';$params[]=$stage;}
    if($priority!==''){$where[]='pr.priority=?';$params[]=$priority;}
    if($search!==''){$where[]='(u.email LIKE ? OR u.full_name LIKE ? OR u.display_name LIKE ? OR ip.firm_name LIKE ?)';$like='%'.$search.'%';array_push($params,$like,$like,$like,$like);}
    $sql='SELECT pr.public_id,pr.stage,pr.priority,pr.qualification_score,pr.source,pr.capacity_range,pr.tags_json,pr.last_contact_at,pr.next_follow_up_at,pr.updated_at,pr.investor_user_id,
                 ip.firm_name,ip.job_title,ip.investor_type,ip.expected_investment_range,u.email,u.full_name,u.display_name,au.full_name AS assigned_name,
                 (SELECT COUNT(*) FROM investor_follow_up_tasks t WHERE t.investor_user_id=pr.investor_user_id AND t.status IN ("open","in_progress")) AS open_tasks,
                 (SELECT COUNT(*) FROM investor_follow_up_tasks t WHERE t.investor_user_id=pr.investor_user_id AND t.status IN ("open","in_progress") AND t.due_at<NOW()) AS overdue_tasks,
                 COALESCE((SELECT SUM(ri.soft_commitment_cents) FROM investor_round_interests ri WHERE ri.investor_user_id=pr.investor_user_id),0) AS soft_commitment_cents,
                 COALESCE((SELECT SUM(ri.signed_cents) FROM investor_round_interests ri WHERE ri.investor_user_id=pr.investor_user_id),0) AS signed_cents,
                 COALESCE((SELECT SUM(ri.funded_cents) FROM investor_round_interests ri WHERE ri.investor_user_id=pr.investor_user_id),0) AS funded_cents
          FROM investor_pipeline_records pr INNER JOIN investor_profiles ip ON ip.id=pr.investor_profile_id INNER JOIN users u ON u.id=pr.investor_user_id LEFT JOIN users au ON au.id=pr.assigned_user_id
          WHERE '.implode(' AND ',$where).' ORDER BY FIELD(pr.priority,"critical","high","normal","low"),COALESCE(pr.next_follow_up_at,"2999-12-31"),pr.updated_at DESC LIMIT 500';
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$items=$stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($items as &$item){$item['tags']=mg_investment_json($item['tags_json']);unset($item['tags_json']);}
    $summary=$pdo->query('SELECT COUNT(*) AS total,SUM(stage NOT IN ("passed","declined","archived")) AS active,SUM(stage="meeting_scheduled") AS meetings,SUM(stage="due_diligence") AS due_diligence,SUM(stage="soft_committed") AS soft_committed,SUM(stage="signed") AS signed,SUM(stage="funded") AS funded,SUM(next_follow_up_at IS NOT NULL AND next_follow_up_at<NOW() AND stage NOT IN ("passed","declined","archived")) AS overdue FROM investor_pipeline_records')->fetch(PDO::FETCH_ASSOC)?:[];
    $money=$pdo->query('SELECT COALESCE(SUM(soft_commitment_cents),0) AS soft_commitment_cents,COALESCE(SUM(signed_cents),0) AS signed_cents,COALESCE(SUM(funded_cents),0) AS funded_cents FROM investor_round_interests')->fetch(PDO::FETCH_ASSOC)?:[];
    $rounds=$pdo->query('SELECT r.public_id,r.public_name,r.status,r.visibility,r.target_raise_cents,r.funded_cents,w.public_id AS workspace_public_id,COALESCE(p.publication_status,"draft") AS publication_status FROM investment_rounds r INNER JOIN investment_workspaces w ON w.id=r.workspace_id LEFT JOIN investment_round_publication p ON p.round_id=r.id ORDER BY r.updated_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $admins=$pdo->query('SELECT DISTINCT u.id,u.full_name,u.email FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id WHERE r.slug IN ("admin","super_admin") ORDER BY u.full_name')->fetchAll(PDO::FETCH_ASSOC);
    return ['items'=>$items,'summary'=>array_merge($summary,$money),'rounds'=>$rounds,'admins'=>$admins];
}

function mg_investment_pipeline_save_record_v2(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investor_pipeline.manage');
    $publicId=mg_investment_text($input['investor_id']??'',36,36,'Investor identifier');$actorId=(int)$actor['id'];
    $stages=['approved','qualified','contacted','meeting_scheduled','due_diligence','interested','soft_committed','signed','funded','passed','declined','archived'];$priorities=['low','normal','high','critical'];
    $stage=(string)($input['stage']??'approved');$priority=(string)($input['priority']??'normal');if(!in_array($stage,$stages,true)||!in_array($priority,$priorities,true))throw new MgInvestmentException('Invalid pipeline stage or priority.');
    $score=max(0,min(100,(int)($input['qualification_score']??0)));$assigned=(int)($input['assigned_user_id']??0);$assigned=$assigned>0?$assigned:null;
    $tags=is_array($input['tags']??null)?array_values(array_unique(array_filter(array_map(static fn($v)=>mg_investment_text($v,60),$input['tags'])))):[];
    $pdo->beginTransaction();
    try{
        $record=mg_investment_pipeline_record($pdo,$publicId,true);$oldStage=(string)$record['stage'];
        $stmt=$pdo->prepare('UPDATE investor_pipeline_records SET stage=?,priority=?,qualification_score=?,source=?,capacity_range=?,assigned_user_id=?,tags_json=?,internal_notes=?,last_contact_at=?,next_follow_up_at=?,archived_at=IF(?="archived",COALESCE(archived_at,NOW()),NULL),updated_by_user_id=?,updated_at=NOW() WHERE id=?');
        $stmt->execute([$stage,$priority,$score,mg_investment_text($input['source']??'',180)?:null,mg_investment_text($input['capacity_range']??'',80)?:null,$assigned,mg_investment_json_encode($tags),mg_investment_long_text($input['internal_notes']??'',12000)?:null,mg_investment_date($input['last_contact_at']??null),mg_investment_date($input['next_follow_up_at']??null),$stage,$actorId,(int)$record['id']]);
        if($oldStage!==$stage)mg_investment_pipeline_activity($pdo,(int)$record['investor_user_id'],null,'status_change','Pipeline stage changed',mg_investment_readable_stage($oldStage).' → '.mg_investment_readable_stage($stage),$actorId,['from'=>$oldStage,'to'=>$stage]);
        $pdo->commit();mg_audit('investor_pipeline_saved','investor_pipeline',['investor_id'=>$publicId,'stage'=>$stage],$actorId);return mg_investment_pipeline_detail($pdo,$publicId);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_investment_readable_stage(string $value): string
{
    return ucwords(str_replace('_',' ',$value));
}

function mg_investment_metric_history_v2(PDO $pdo,string $workspacePublicId): array
{
    $workspace=mg_investment_workspace_by_public_id($pdo,$workspacePublicId);
    $stmt=$pdo->prepare('SELECT m.public_id,m.metric_key,m.name,m.unit,m.current_value,m.confidence,s.snapshot_type,s.value,s.snapshot_at,r.public_name AS round_name,g.baseline_value,g.target_value,g.target_date,CASE WHEN g.target_value IS NULL OR s.value IS NULL THEN NULL ELSE s.value-g.target_value END AS variance_to_target FROM investment_metrics m LEFT JOIN investment_metric_snapshots s ON s.metric_id=m.id LEFT JOIN investment_rounds r ON r.id=s.round_id LEFT JOIN investment_scenario_goals g ON g.scenario_id=r.adopted_scenario_id AND g.metric_key=m.metric_key WHERE m.workspace_id=? ORDER BY m.name,s.snapshot_at DESC');
    $stmt->execute([(int)$workspace['id']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
}
