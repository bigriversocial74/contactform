<?php
declare(strict_types=1);

function mg_investment_pipeline_sync_profiles(PDO $pdo, ?int $actorUserId = null): int
{
    $stmt = $pdo->prepare("INSERT INTO investor_pipeline_records
      (public_id,investor_user_id,investor_profile_id,stage,priority,qualification_score,capacity_range,created_by_user_id,updated_by_user_id,created_at,updated_at)
      SELECT UUID(),ip.user_id,ip.id,'approved','normal',0,ip.expected_investment_range,?,?,NOW(),NOW()
      FROM investor_profiles ip
      LEFT JOIN investor_pipeline_records pr ON pr.investor_user_id=ip.user_id
      WHERE ip.status='active' AND pr.id IS NULL");
    $stmt->execute([$actorUserId, $actorUserId]);
    return $stmt->rowCount();
}

function mg_investment_pipeline_record(PDO $pdo, string $publicId, bool $lock = false): array
{
    $sql = "SELECT pr.*,ip.firm_name,ip.job_title,ip.website_url,ip.primary_social_url,ip.investor_type,
                   ip.expected_investment_range,ip.status AS profile_status,u.email,u.full_name,u.display_name,
                   au.full_name AS assigned_name
            FROM investor_pipeline_records pr
            INNER JOIN investor_profiles ip ON ip.id=pr.investor_profile_id
            INNER JOIN users u ON u.id=pr.investor_user_id
            LEFT JOIN users au ON au.id=pr.assigned_user_id
            WHERE pr.public_id=? LIMIT 1";
    if ($lock) $sql .= ' FOR UPDATE';
    $stmt=$pdo->prepare($sql);$stmt->execute([$publicId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new MgInvestmentException('Investor pipeline record not found.',404);
    $row['tags']=mg_investment_json($row['tags_json']);unset($row['tags_json']);
    return $row;
}

function mg_investment_pipeline_round(PDO $pdo,string $publicId): array
{
    $stmt=$pdo->prepare('SELECT * FROM investment_rounds WHERE public_id=? LIMIT 1');$stmt->execute([$publicId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new MgInvestmentException('Investment round not found.',404);
    return $row;
}

function mg_investment_pipeline_activity(PDO $pdo,int $investorUserId,?int $roundId,string $type,string $subject,string $details,?int $actorUserId,array $metadata=[]): void
{
    $allowed=['note','call','email','meeting','access_granted','access_revoked','document_view','portal_view','status_change','commitment_update','task_completed','ai_draft'];
    if(!in_array($type,$allowed,true))throw new MgInvestmentException('Invalid pipeline activity type.');
    $stmt=$pdo->prepare('INSERT INTO investor_pipeline_activities (public_id,investor_user_id,round_id,activity_type,subject,details,metadata_json,occurred_at,created_by_user_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())');
    $stmt->execute([mg_investment_uuid(),$investorUserId,$roundId,$type,mg_investment_text($subject,220,2,'Activity subject'),$details!==''?mg_investment_long_text($details,12000):null,$metadata?mg_investment_json_encode($metadata):null,date('Y-m-d H:i:s'),$actorUserId]);
}

function mg_investment_pipeline_dashboard(PDO $pdo,array $filters=[]): array
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
    $summary=$pdo->query('SELECT COUNT(*) AS total,
      SUM(stage NOT IN ("passed","declined","archived")) AS active,
      SUM(stage="meeting_scheduled") AS meetings,
      SUM(stage="due_diligence") AS due_diligence,
      SUM(stage="soft_committed") AS soft_committed,
      SUM(stage="signed") AS signed,
      SUM(stage="funded") AS funded,
      SUM(next_follow_up_at IS NOT NULL AND next_follow_up_at<NOW() AND stage NOT IN ("passed","declined","archived")) AS overdue
      FROM investor_pipeline_records')->fetch(PDO::FETCH_ASSOC) ?: [];
    $money=$pdo->query('SELECT COALESCE(SUM(soft_commitment_cents),0) AS soft_commitment_cents,COALESCE(SUM(signed_cents),0) AS signed_cents,COALESCE(SUM(funded_cents),0) AS funded_cents FROM investor_round_interests')->fetch(PDO::FETCH_ASSOC) ?: [];
    $rounds=$pdo->query('SELECT r.public_id,r.public_name,r.status,r.visibility,r.target_raise_cents,r.funded_cents,COALESCE(p.publication_status,"draft") AS publication_status FROM investment_rounds r LEFT JOIN investment_round_publication p ON p.round_id=r.id ORDER BY r.updated_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $admins=$pdo->query('SELECT DISTINCT u.id,u.full_name,u.email FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id WHERE r.slug IN ("admin","super_admin") ORDER BY u.full_name')->fetchAll(PDO::FETCH_ASSOC);
    return ['items'=>$items,'summary'=>array_merge($summary,$money),'rounds'=>$rounds,'admins'=>$admins];
}

function mg_investment_pipeline_detail(PDO $pdo,string $publicId): array
{
    $record=mg_investment_pipeline_record($pdo,$publicId);$userId=(int)$record['investor_user_id'];
    $activities=$pdo->prepare('SELECT a.*,u.full_name AS actor_name,r.public_name AS round_name FROM investor_pipeline_activities a LEFT JOIN users u ON u.id=a.created_by_user_id LEFT JOIN investment_rounds r ON r.id=a.round_id WHERE a.investor_user_id=? ORDER BY a.occurred_at DESC,a.id DESC LIMIT 250');$activities->execute([$userId]);
    $tasks=$pdo->prepare('SELECT t.*,r.public_name AS round_name,au.full_name AS assigned_name,cu.full_name AS completed_by_name FROM investor_follow_up_tasks t LEFT JOIN investment_rounds r ON r.id=t.round_id LEFT JOIN users au ON au.id=t.assigned_user_id LEFT JOIN users cu ON cu.id=t.completed_by_user_id WHERE t.investor_user_id=? ORDER BY FIELD(t.status,"open","in_progress","completed","cancelled"),COALESCE(t.due_at,"2999-12-31"),t.id DESC');$tasks->execute([$userId]);
    $interests=$pdo->prepare('SELECT ri.*,r.public_id AS round_public_id,r.public_name,r.status AS round_status,r.target_raise_cents,ra.status AS access_status,ra.expires_at AS access_expires_at FROM investor_round_interests ri INNER JOIN investment_rounds r ON r.id=ri.round_id LEFT JOIN investment_round_access ra ON ra.round_id=ri.round_id AND ra.investor_user_id=ri.investor_user_id WHERE ri.investor_user_id=? ORDER BY r.updated_at DESC');$interests->execute([$userId]);
    $available=$pdo->prepare('SELECT r.public_id,r.public_name,r.status,r.target_raise_cents FROM investment_rounds r WHERE NOT EXISTS(SELECT 1 FROM investor_round_interests ri WHERE ri.round_id=r.id AND ri.investor_user_id=?) ORDER BY r.updated_at DESC');$available->execute([$userId]);
    return ['record'=>$record,'activities'=>$activities->fetchAll(PDO::FETCH_ASSOC),'tasks'=>$tasks->fetchAll(PDO::FETCH_ASSOC),'interests'=>$interests->fetchAll(PDO::FETCH_ASSOC),'available_rounds'=>$available->fetchAll(PDO::FETCH_ASSOC)];
}

function mg_investment_pipeline_save_record(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investor_pipeline.manage');$publicId=mg_investment_text($input['investor_id']??'',36,36,'Investor identifier');$actorId=(int)$actor['id'];
    $stages=['approved','qualified','contacted','meeting_scheduled','due_diligence','interested','soft_committed','signed','funded','passed','declined','archived'];$priorities=['low','normal','high','critical'];
    $stage=(string)($input['stage']??'approved');$priority=(string)($input['priority']??'normal');if(!in_array($stage,$stages,true)||!in_array($priority,$priorities,true))throw new MgInvestmentException('Invalid pipeline stage or priority.');
    $score=max(0,min(100,(int)($input['qualification_score']??0)));$assigned=(int)($input['assigned_user_id']??0);$assigned=$assigned>0?$assigned:null;$tags=is_array($input['tags']??null)?array_values(array_unique(array_filter(array_map(static fn($v)=>mg_investment_text($v,60),$input['tags'])))):[];
    $pdo->beginTransaction();try{$record=mg_investment_pipeline_record($pdo,$publicId,true);$oldStage=(string)$record['stage'];$stmt=$pdo->prepare('UPDATE investor_pipeline_records SET stage=?,priority=?,qualification_score=?,source=?,capacity_range=?,assigned_user_id=?,tags_json=?,internal_notes=?,last_contact_at=?,next_follow_up_at=?,archived_at=IF(?="archived",COALESCE(archived_at,NOW()),NULL),updated_by_user_id=?,updated_at=NOW() WHERE id=?');$stmt->execute([$stage,$priority,$score,mg_investment_text($input['source']??'',180)?:null,mg_investment_text($input['capacity_range']??'',80)?:null,$assigned,mg_investment_json_encode($tags),mg_investment_long_text($input['internal_notes']??'',12000)?:null,mg_investment_date($input['last_contact_at']??null),mg_investment_date($input['next_follow_up_at']??null),$stage,$actorId,(int)$record['id']]);if($oldStage!==$stage)mg_investment_pipeline_activity($pdo,(int)$record['investor_user_id'],null,'status_change','Pipeline stage changed',mg_investment_readable_stage($oldStage).' → '.mg_investment_readable_stage($stage),$actorId,['from'=>$oldStage,'to'=>$stage]);$pdo->commit();mg_audit('investor_pipeline_saved','investor_pipeline',['investor_id'=>$publicId,'stage'=>$stage],$actorId);return mg_investment_pipeline_detail($pdo,$publicId);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_investment_pipeline_add_activity(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investor_pipeline.manage');$record=mg_investment_pipeline_record($pdo,mg_investment_text($input['investor_id']??'',36,36,'Investor identifier'));$roundId=null;if(!empty($input['round_id']))$roundId=(int)mg_investment_pipeline_round($pdo,mg_investment_text($input['round_id'],36,36,'Round identifier'))['id'];
    $type=(string)($input['activity_type']??'note');$subject=mg_investment_text($input['subject']??'',220,2,'Activity subject');$details=mg_investment_long_text($input['details']??'',12000);mg_investment_pipeline_activity($pdo,(int)$record['investor_user_id'],$roundId,$type,$subject,$details,(int)$actor['id']);
    if(in_array($type,['call','email','meeting'],true))$pdo->prepare('UPDATE investor_pipeline_records SET last_contact_at=NOW(),updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([(int)$actor['id'],(int)$record['id']]);
    mg_audit('investor_pipeline_activity_added','investor_pipeline',['investor_id'=>$record['public_id'],'type'=>$type],(int)$actor['id']);return mg_investment_pipeline_detail($pdo,(string)$record['public_id']);
}

function mg_investment_pipeline_save_task(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investor_pipeline.manage');$record=mg_investment_pipeline_record($pdo,mg_investment_text($input['investor_id']??'',36,36,'Investor identifier'));$roundId=null;if(!empty($input['round_id']))$roundId=(int)mg_investment_pipeline_round($pdo,mg_investment_text($input['round_id'],36,36,'Round identifier'))['id'];$priority=in_array($input['priority']??'normal',['low','normal','high','critical'],true)?$input['priority']:'normal';$assigned=(int)($input['assigned_user_id']??0);$assigned=$assigned>0?$assigned:null;
    $stmt=$pdo->prepare('INSERT INTO investor_follow_up_tasks (public_id,investor_user_id,round_id,title,details,priority,status,assigned_user_id,due_at,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,"open",?,?,?,NOW(),NOW())');$stmt->execute([mg_investment_uuid(),(int)$record['investor_user_id'],$roundId,mg_investment_text($input['title']??'',220,2,'Task title'),mg_investment_long_text($input['details']??'',6000)?:null,$priority,$assigned,mg_investment_date($input['due_at']??null),(int)$actor['id']]);
    mg_audit('investor_follow_up_created','investor_pipeline',['investor_id'=>$record['public_id']],(int)$actor['id']);return mg_investment_pipeline_detail($pdo,(string)$record['public_id']);
}

function mg_investment_pipeline_complete_task(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investor_pipeline.manage');$taskId=mg_investment_text($input['task_id']??'',36,36,'Task identifier');$stmt=$pdo->prepare('SELECT t.*,pr.public_id AS pipeline_public_id FROM investor_follow_up_tasks t INNER JOIN investor_pipeline_records pr ON pr.investor_user_id=t.investor_user_id WHERE t.public_id=? LIMIT 1');$stmt->execute([$taskId]);$task=$stmt->fetch(PDO::FETCH_ASSOC);if(!$task)throw new MgInvestmentException('Follow-up task not found.',404);
    $pdo->prepare('UPDATE investor_follow_up_tasks SET status="completed",completed_at=NOW(),completed_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([(int)$actor['id'],(int)$task['id']]);mg_investment_pipeline_activity($pdo,(int)$task['investor_user_id'],$task['round_id']?(int)$task['round_id']:null,'task_completed','Follow-up completed',(string)$task['title'],(int)$actor['id']);
    return mg_investment_pipeline_detail($pdo,(string)$task['pipeline_public_id']);
}

function mg_investment_pipeline_save_interest(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investor_pipeline.manage');$record=mg_investment_pipeline_record($pdo,mg_investment_text($input['investor_id']??'',36,36,'Investor identifier'));$round=mg_investment_pipeline_round($pdo,mg_investment_text($input['round_id']??'',36,36,'Round identifier'));$status=(string)($input['status']??'invited');if(!in_array($status,['invited','reviewing','interested','soft_committed','signed','funded','passed','declined','archived'],true))throw new MgInvestmentException('Invalid round-interest status.');
    $indicated=mg_investment_money($input['indicated_interest']??0);$soft=mg_investment_money($input['soft_commitment']??0);$signed=mg_investment_money($input['signed']??0);$funded=mg_investment_money($input['funded']??0);if($funded>$signed)throw new MgInvestmentException('Funded amount cannot exceed signed amount.');if($signed>$soft&&$soft>0)throw new MgInvestmentException('Signed amount cannot exceed soft commitment.');
    $actorId=(int)$actor['id'];$stmt=$pdo->prepare('INSERT INTO investor_round_interests (public_id,round_id,investor_user_id,status,indicated_interest_cents,soft_commitment_cents,signed_cents,funded_cents,probability_bps,next_step,notes,last_activity_at,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status),indicated_interest_cents=VALUES(indicated_interest_cents),soft_commitment_cents=VALUES(soft_commitment_cents),signed_cents=VALUES(signed_cents),funded_cents=VALUES(funded_cents),probability_bps=VALUES(probability_bps),next_step=VALUES(next_step),notes=VALUES(notes),last_activity_at=NOW(),updated_by_user_id=VALUES(updated_by_user_id),updated_at=NOW()');$stmt->execute([mg_investment_uuid(),(int)$round['id'],(int)$record['investor_user_id'],$status,$indicated,$soft,$signed,$funded,mg_investment_bps($input['probability_percent']??0),mg_investment_text($input['next_step']??'',500)?:null,mg_investment_long_text($input['notes']??'',6000)?:null,$actorId,$actorId]);
    $sum=$pdo->prepare('SELECT COALESCE(SUM(soft_commitment_cents),0),COALESCE(SUM(signed_cents),0),COALESCE(SUM(funded_cents),0) FROM investor_round_interests WHERE round_id=? AND status NOT IN ("passed","declined","archived")');$sum->execute([(int)$round['id']]);[$totalSoft,$totalSigned,$totalFunded]=$sum->fetch(PDO::FETCH_NUM);$pdo->prepare('UPDATE investment_rounds SET soft_commitment_cents=?,signed_cents=?,funded_cents=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([(int)$totalSoft,(int)$totalSigned,(int)$totalFunded,$actorId,(int)$round['id']]);
    $pipelineStage=['soft_committed'=>'soft_committed','signed'=>'signed','funded'=>'funded','passed'=>'passed','declined'=>'declined'][$status]??null;if($pipelineStage)$pdo->prepare('UPDATE investor_pipeline_records SET stage=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$pipelineStage,$actorId,(int)$record['id']]);mg_investment_pipeline_activity($pdo,(int)$record['investor_user_id'],(int)$round['id'],'commitment_update','Round interest updated',mg_investment_text($input['next_step']??'',500),$actorId,['status'=>$status,'soft_commitment_cents'=>$soft,'signed_cents'=>$signed,'funded_cents'=>$funded]);
    mg_audit('investor_round_interest_saved','investment_round',['round_id'=>$round['public_id'],'investor_id'=>$record['public_id'],'status'=>$status],$actorId);return mg_investment_pipeline_detail($pdo,(string)$record['public_id']);
}

function mg_investment_pipeline_set_access(PDO $pdo,array $actor,array $input): array
{
    mg_investment_require_permission($actor,'admin.investor_pipeline.manage');$record=mg_investment_pipeline_record($pdo,mg_investment_text($input['investor_id']??'',36,36,'Investor identifier'));$round=mg_investment_pipeline_round($pdo,mg_investment_text($input['round_id']??'',36,36,'Round identifier'));$grant=mg_investment_bool($input['grant']??false);$actorId=(int)$actor['id'];
    if($grant){$pdo->prepare('INSERT INTO investment_round_access (round_id,investor_user_id,status,granted_by_user_id,granted_at,expires_at,revoked_at) VALUES (?,? ,"granted",?,NOW(),?,NULL) ON DUPLICATE KEY UPDATE status="granted",granted_by_user_id=VALUES(granted_by_user_id),granted_at=NOW(),expires_at=VALUES(expires_at),revoked_at=NULL')->execute([(int)$round['id'],(int)$record['investor_user_id'],$actorId,mg_investment_date($input['expires_at']??null)]);mg_investment_pipeline_activity($pdo,(int)$record['investor_user_id'],(int)$round['id'],'access_granted','Selected-round portal access granted','',(int)$actor['id']);}else{$pdo->prepare('UPDATE investment_round_access SET status="revoked",revoked_at=NOW() WHERE round_id=? AND investor_user_id=?')->execute([(int)$round['id'],(int)$record['investor_user_id']]);mg_investment_pipeline_activity($pdo,(int)$record['investor_user_id'],(int)$round['id'],'access_revoked','Selected-round portal access revoked','',$actorId);}
    mg_audit($grant?'investment_round_access_granted':'investment_round_access_revoked','investment_round',['round_id'=>$round['public_id'],'investor_id'=>$record['public_id']],$actorId);return mg_investment_pipeline_detail($pdo,(string)$record['public_id']);
}
