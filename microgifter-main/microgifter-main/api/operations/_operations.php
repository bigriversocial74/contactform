<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function mg_operations_incident_event(PDO $pdo,int $incidentId,string $eventType,?string $from,?string $to,?int $actor,string $note='',array $payload=[]): void
{
    $pdo->prepare('INSERT INTO operational_incident_events (public_id,incident_id,event_type,from_status,to_status,actor_user_id,note,payload_json,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())')
        ->execute([mg_public_uuid(),$incidentId,$eventType,$from,$to,$actor,$note!==''?$note:null,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
}

function mg_operations_create_incident(PDO $pdo,int $userId,array $input): array
{
    $key=mb_substr(trim((string)($input['incident_key']??'')),0,120);
    $title=mb_substr(trim((string)($input['title']??'')),0,240);
    $severity=trim((string)($input['severity']??''));
    $summary=trim((string)($input['summary']??''));
    if($key===''||$title===''||$summary===''||!in_array($severity,['sev1','sev2','sev3','sev4'],true))throw new InvalidArgumentException('Incident key, title, severity, and summary are required.');
    $public=mg_public_uuid();
    $pdo->prepare("INSERT INTO operational_incidents (public_id,incident_key,title,severity,status,service_key,summary,impact_summary,opened_by_user_id,commander_user_id,opened_at,metadata_json,created_at,updated_at) VALUES (?,?,?,?, 'open',?,?,?,?,?,NOW(),?,NOW(),NOW())")
        ->execute([$public,$key,$title,$severity,mb_substr(trim((string)($input['service_key']??'microgifter')),0,120),$summary,trim((string)($input['impact_summary']??''))?:null,$userId,isset($input['commander_user_id'])?(int)$input['commander_user_id']:$userId,json_encode($input['metadata']??[],JSON_THROW_ON_ERROR)]);
    $id=(int)$pdo->lastInsertId();
    mg_operations_incident_event($pdo,$id,'incident_opened',null,'open',$userId,$summary,[]);
    $stmt=$pdo->prepare('SELECT * FROM operational_incidents WHERE id=?');$stmt->execute([$id]);return $stmt->fetch(PDO::FETCH_ASSOC);
}

function mg_operations_transition_incident(PDO $pdo,array $incident,string $action,int $userId,array $input=[]): array
{
    $from=(string)$incident['status'];
    $allowed=['investigate'=>['open'],'mitigate'=>['open','investigating'],'resolve'=>['open','investigating','mitigated'],'close'=>['resolved'],'reopen'=>['resolved','closed']];
    if(!isset($allowed[$action])||!in_array($from,$allowed[$action],true))throw new RuntimeException('Incident cannot perform this transition.');
    $to=match($action){'investigate'=>'investigating','mitigate'=>'mitigated','resolve'=>'resolved','close'=>'closed','reopen'=>'open'};
    $note=trim((string)($input['note']??''));
    $pdo->prepare("UPDATE operational_incidents SET status=?,mitigation_summary=IF(?='mitigated',?,mitigation_summary),root_cause_summary=IF(?='resolved',?,root_cause_summary),mitigated_at=IF(?='mitigated',NOW(),mitigated_at),resolved_at=IF(?='resolved',NOW(),IF(?='open',NULL,resolved_at)),closed_at=IF(?='closed',NOW(),IF(?='open',NULL,closed_at)),updated_at=NOW() WHERE id=?")
        ->execute([$to,$to,$note,$to,$note,$to,$to,$to,$to,$to,(int)$incident['id']]);
    mg_operations_incident_event($pdo,(int)$incident['id'],'incident_'.$action,$from,$to,$userId,$note,$input['payload']??[]);
    return $incident+['status'=>$to];
}

function mg_operations_release_required_gates(): array
{
    return ['composer_validate','php_syntax','ordered_migrations','clean_install','security_suite','phpunit','browser_smoke','backup_verified','rollback_verified','readiness_checks'];
}

function mg_operations_create_release(PDO $pdo,int $userId,array $input): array
{
    $version=mb_substr(trim((string)($input['release_version']??'')),0,100);
    $sha=strtolower(trim((string)($input['git_commit_sha']??'')));
    $environment=trim((string)($input['environment']??'staging'));
    if($version===''||!preg_match('/^[a-f0-9]{40}$/',$sha)||!in_array($environment,['staging','production'],true))throw new InvalidArgumentException('Release version, 40-character commit SHA, and environment are required.');
    $rollback=(array)($input['rollback_plan']??[]);
    if($rollback===[])throw new InvalidArgumentException('A rollback plan is required.');
    $public=mg_public_uuid();
    $pdo->prepare("INSERT INTO deployment_releases (public_id,release_version,git_commit_sha,environment,status,rollback_plan_json,created_at,updated_at) VALUES (?,?,?,?, 'planned',?,NOW(),NOW())")
        ->execute([$public,$version,$sha,$environment,json_encode($rollback,JSON_THROW_ON_ERROR)]);
    $releaseId=(int)$pdo->lastInsertId();
    $insert=$pdo->prepare("INSERT INTO release_gate_results (public_id,deployment_release_id,gate_key,status,required_flag,created_at,updated_at) VALUES (?,?,?,'pending',1,NOW(),NOW())");
    foreach(mg_operations_release_required_gates() as $gate)$insert->execute([mg_public_uuid(),$releaseId,$gate]);
    $stmt=$pdo->prepare('SELECT * FROM deployment_releases WHERE id=?');$stmt->execute([$releaseId]);return $stmt->fetch(PDO::FETCH_ASSOC);
}

function mg_operations_release_gate(PDO $pdo,array $release,string $gate,string $status,int $userId,array $evidence=[],string $message=''): void
{
    if(!in_array($gate,mg_operations_release_required_gates(),true))throw new InvalidArgumentException('Unknown release gate.');
    if(!in_array($status,['passed','failed','waived'],true))throw new InvalidArgumentException('Invalid release gate status.');
    if($status==='waived'&&(string)$release['environment']==='production')throw new RuntimeException('Production release gates cannot be waived.');
    $pdo->prepare('UPDATE release_gate_results SET status=?,evidence_json=?,failure_message=?,evaluated_at=NOW(),waived_by_user_id=IF(?=\'waived\',?,NULL),waived_at=IF(?=\'waived\',NOW(),NULL),updated_at=NOW() WHERE deployment_release_id=? AND gate_key=?')
        ->execute([$status,json_encode($evidence,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$message!==''?mb_substr($message,0,1000):null,$status,$userId,$status,(int)$release['id'],$gate]);
}

function mg_operations_release_can_approve(PDO $pdo,int $releaseId): bool
{
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM release_gate_results WHERE deployment_release_id=? AND required_flag=1 AND status<>'passed'");
    $stmt->execute([$releaseId]);return (int)$stmt->fetchColumn()===0;
}

function mg_operations_record_check(PDO $pdo,string $key,string $status,string $summary,array $details=[]): string
{
    if(!in_array($status,['pass','warn','fail'],true))throw new InvalidArgumentException('Invalid operational check status.');
    $public=mg_public_uuid();
    $pdo->prepare('INSERT INTO operational_check_results (public_id,check_key,check_scope,status,summary,details_json,checked_at,expires_at,created_at) VALUES (?,?,\'platform\',?,?,?,NOW(),DATE_ADD(NOW(),INTERVAL 1 DAY),NOW())')
        ->execute([$public,mb_substr($key,0,120),$status,mb_substr($summary,0,500),json_encode($details,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
    return $public;
}

function mg_operations_demand_orchestration_health(PDO $pdo): array
{
    $metrics=$pdo->query(
        "SELECT
            COALESCE(SUM(CASE WHEN o.status='claimed' AND o.updated_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE) THEN 1 ELSE 0 END),0) stale_claimed,
            COALESCE(SUM(CASE WHEN o.status='running' AND o.updated_at<DATE_SUB(NOW(),INTERVAL 1 HOUR) THEN 1 ELSE 0 END),0) stale_running,
            COALESCE(SUM(CASE WHEN o.status='awaiting_approval' AND o.updated_at<DATE_SUB(NOW(),INTERVAL 24 HOUR) THEN 1 ELSE 0 END),0) stale_approval,
            COALESCE(SUM(CASE WHEN o.status='review_required' AND o.updated_at<DATE_SUB(NOW(),INTERVAL 24 HOUR) THEN 1 ELSE 0 END),0) stale_review,
            COALESCE(SUM(CASE WHEN o.status='failed' AND o.updated_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR) THEN 1 ELSE 0 END),0) failed_recent
         FROM demand_signal_orchestrations o"
    )->fetch(PDO::FETCH_ASSOC)?:[];
    foreach(['stale_claimed','stale_running','stale_approval','stale_review','failed_recent'] as $key)$metrics[$key]=(int)($metrics[$key]??0);
    $metrics['critical_overdue']=(int)$pdo->query(
        "SELECT COUNT(*)
         FROM demand_agent_signals s
         LEFT JOIN demand_signal_orchestrations o ON o.demand_signal_id=s.id
         WHERE s.signal_level='critical' AND s.status='open'
           AND s.triggered_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE)
           AND (o.id IS NULL OR o.status<>'completed')"
    )->fetchColumn();
    $staleActive=$metrics['stale_claimed']+$metrics['stale_running']+$metrics['stale_approval'];
    return [
        ['key'=>'demand_orchestration_queue','status'=>$metrics['critical_overdue']>0?'fail':($staleActive>0?'warn':'pass'),'summary'=>$metrics['critical_overdue']>0?'Critical demand signals are overdue.':($staleActive>0?'Stale demand orchestrations require review.':'Demand orchestration queues are current.'),'details'=>$metrics],
        ['key'=>'demand_orchestration_failures','status'=>$metrics['failed_recent']>0?'warn':'pass','summary'=>$metrics['failed_recent']>0?'Recent demand orchestration failures require review.':'No recent demand orchestration failures.','details'=>['failed_recent'=>$metrics['failed_recent']]],
        ['key'=>'demand_orchestration_reviews','status'=>$metrics['stale_review']>0?'warn':'pass','summary'=>$metrics['stale_review']>0?'Review-required demand orchestrations are overdue.':'No overdue review-required demand orchestrations.','details'=>['stale_review'=>$metrics['stale_review']]],
    ];
}

function mg_operations_record_demand_orchestration_health(PDO $pdo): array
{
    $checks=mg_operations_demand_orchestration_health($pdo);
    foreach($checks as $check)mg_operations_record_check($pdo,$check['key'],$check['status'],$check['summary'],$check['details']);
    return $checks;
}

function mg_operations_list_demand_orchestrations(PDO $pdo,array $filters=[]): array
{
    $statuses=['claimed','awaiting_approval','running','completed','failed','review_required'];
    $levels=['info','opportunity','warning','critical'];
    $types=['workflow','swarm','alert_only'];
    $where=[];$params=[];
    $status=trim((string)($filters['status']??''));
    if($status!==''&&$status!=='all'){if(!in_array($status,$statuses,true))throw new InvalidArgumentException('Invalid orchestration status.');$where[]='o.status=?';$params[]=$status;}
    $level=trim((string)($filters['signal_level']??''));
    if($level!==''&&$level!=='all'){if(!in_array($level,$levels,true))throw new InvalidArgumentException('Invalid signal level.');$where[]='s.signal_level=?';$params[]=$level;}
    $type=trim((string)($filters['orchestration_type']??''));
    if($type!==''&&$type!=='all'){if(!in_array($type,$types,true))throw new InvalidArgumentException('Invalid orchestration type.');$where[]='o.orchestration_type=?';$params[]=$type;}
    $before=max(0,(int)($filters['before_id']??0));
    if($before>0){$where[]='o.id<?';$params[]=$before;}
    $limit=max(1,min((int)($filters['limit']??50),100));
    $sql="SELECT
            o.id cursor_id,o.public_id,o.orchestration_type,o.status,o.recommendation_action,o.attempt_count,o.last_error,
            o.claimed_at,o.started_at,o.completed_at,o.created_at,o.updated_at,
            s.public_id demand_signal_id,s.signal_key,s.signal_level,s.status demand_signal_status,s.summary signal_summary,s.triggered_at signal_triggered_at,s.expires_at signal_expires_at,
            st.public_id strategy_id,st.name strategy_name,o.strategy_version,
            t.public_id team_id,t.name team_name,
            w.public_id workflow_run_id,w.status workflow_status,w.failure_message workflow_failure,
            sw.public_id swarm_run_id,sw.status swarm_status,sw.failure_message swarm_failure
         FROM demand_signal_orchestrations o
         INNER JOIN demand_agent_signals s ON s.id=o.demand_signal_id
         LEFT JOIN agent_strategies st ON st.id=o.strategy_id
         LEFT JOIN agent_teams t ON t.id=o.team_id
         LEFT JOIN agent_workflow_runs w ON w.id=o.workflow_run_id
         LEFT JOIN agent_swarm_runs sw ON sw.id=o.swarm_run_id";
    if($where!==[])$sql.=' WHERE '.implode(' AND ',$where);
    $sql.=' ORDER BY o.id DESC LIMIT '.$limit;
    $stmt=$pdo->prepare($sql);$stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mg_operations_get_demand_orchestration(PDO $pdo,string $publicId): ?array
{
    $publicId=trim($publicId);
    if($publicId==='')throw new InvalidArgumentException('Orchestration ID is required.');
    $stmt=$pdo->prepare("SELECT
            o.id cursor_id,o.public_id,o.orchestration_type,o.status,o.recommendation_action,o.attempt_count,o.last_error,
            o.claimed_at,o.started_at,o.completed_at,o.created_at,o.updated_at,
            s.public_id demand_signal_id,s.signal_key,s.signal_level,s.status demand_signal_status,s.summary signal_summary,s.triggered_at signal_triggered_at,s.expires_at signal_expires_at,
            st.public_id strategy_id,st.name strategy_name,o.strategy_version,
            t.public_id team_id,t.name team_name,
            w.public_id workflow_run_id,w.status workflow_status,w.failure_message workflow_failure,
            sw.public_id swarm_run_id,sw.status swarm_status,sw.failure_message swarm_failure
         FROM demand_signal_orchestrations o
         INNER JOIN demand_agent_signals s ON s.id=o.demand_signal_id
         LEFT JOIN agent_strategies st ON st.id=o.strategy_id
         LEFT JOIN agent_teams t ON t.id=o.team_id
         LEFT JOIN agent_workflow_runs w ON w.id=o.workflow_run_id
         LEFT JOIN agent_swarm_runs sw ON sw.id=o.swarm_run_id
         WHERE o.public_id=? LIMIT 1");
    $stmt->execute([$publicId]);$record=$stmt->fetch(PDO::FETCH_ASSOC)?:null;
    if($record===null)return null;
    $events=$pdo->prepare('SELECT public_id,event_key,event_type,created_at FROM demand_signal_orchestration_events WHERE orchestration_id=? ORDER BY id ASC LIMIT 250');
    $events->execute([(int)$record['cursor_id']]);
    $record['events']=$events->fetchAll(PDO::FETCH_ASSOC);
    return $record;
}
