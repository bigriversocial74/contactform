<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-agent-phase2-remediation.php';
require_once dirname(__DIR__) . '/api/admin/_ops_incidents.php';

const MG_ADMIN_AGENT_PHASE3_MIGRATION = 'database/20260719_main_admin_agent_phase3.sql';

function mg_admin_agent_phase3_tables(): array
{
    return [
        'admin_agent_services','admin_agent_service_dependencies','admin_agent_slo_policies',
        'admin_agent_slo_snapshots','admin_agent_incident_workspaces','admin_agent_incident_timeline',
        'admin_agent_cause_candidates','admin_agent_release_gates','admin_agent_brief_subscriptions',
        'admin_agent_brief_deliveries',
    ];
}

function mg_admin_agent_phase3_schema_state(PDO $pdo): array
{
    $missing=[];
    foreach(mg_admin_agent_phase3_tables() as $table){
        if(!mg_admin_schema_has_table($pdo,$table)) $missing[]=$table;
    }
    return ['ready'=>$missing===[],'missing_tables'=>$missing,'migration'=>MG_ADMIN_AGENT_PHASE3_MIGRATION];
}

function mg_admin_agent_phase3_ready(PDO $pdo): bool
{
    return mg_admin_agent_phase3_schema_state($pdo)['ready'];
}

function mg_admin_agent_phase3_services(PDO $pdo): array
{
    $services=mg_admin_agent_safe_rows($pdo,'SELECT id,public_id,service_key,label,domain,tier,owner_label,description,status,metadata_json FROM admin_agent_services WHERE status<>"retired" ORDER BY CASE tier WHEN "critical" THEN 1 WHEN "high" THEN 2 ELSE 3 END,label');
    $dependencies=mg_admin_agent_safe_rows($pdo,'SELECT d.public_id,d.service_id,d.depends_on_service_id,d.dependency_type,d.criticality,d.description,s.service_key,up.service_key depends_on_key,up.label depends_on_label FROM admin_agent_service_dependencies d JOIN admin_agent_services s ON s.id=d.service_id JOIN admin_agent_services up ON up.id=d.depends_on_service_id ORDER BY s.service_key,up.service_key');
    $byService=[];
    foreach($dependencies as $dependency){
        $byService[(int)$dependency['service_id']][]=[
            'id'=>(string)$dependency['public_id'],'service_key'=>(string)$dependency['service_key'],
            'depends_on_key'=>(string)$dependency['depends_on_key'],'depends_on_label'=>(string)$dependency['depends_on_label'],
            'dependency_type'=>(string)$dependency['dependency_type'],'criticality'=>(string)$dependency['criticality'],
            'description'=>$dependency['description'],
        ];
    }
    $domainHealth=[];
    foreach(mg_admin_agent_domain_health($pdo) as $health) $domainHealth[(string)$health['domain']]=$health;
    $latestSlo=[];
    foreach(mg_admin_agent_safe_rows($pdo,'SELECT p.service_id,x.severity,x.availability_percent,x.error_budget_remaining_percent,x.burn_rate,x.generated_at FROM admin_agent_slo_policies p JOIN admin_agent_slo_snapshots x ON x.policy_id=p.id JOIN (SELECT policy_id,MAX(id) max_id FROM admin_agent_slo_snapshots GROUP BY policy_id) latest ON latest.max_id=x.id WHERE p.enabled=1') as $row){
        $latestSlo[(int)$row['service_id']]=$row;
    }
    $result=[];
    foreach($services as $service){
        $health=$domainHealth[(string)$service['domain']]??['score'=>100,'status'=>'healthy','active_total'=>0,'critical_total'=>0,'high_total'=>0];
        $slo=$latestSlo[(int)$service['id']]??null;
        $score=(int)$health['score'];
        if($slo && (string)$slo['severity']==='warning') $score=min($score,79);
        if($slo && (string)$slo['severity']==='critical') $score=min($score,49);
        $result[]=[
            'id'=>(string)$service['public_id'],'service_key'=>(string)$service['service_key'],'label'=>(string)$service['label'],
            'domain'=>(string)$service['domain'],'tier'=>(string)$service['tier'],'owner_label'=>$service['owner_label'],
            'description'=>(string)$service['description'],'status'=>(string)$service['status'],'metadata'=>mg_admin_agent_json($service['metadata_json']??null),
            'health'=>['score'=>$score,'status'=>$score>=90?'healthy':($score>=70?'watch':'attention'),'active_total'=>(int)$health['active_total'],'critical_total'=>(int)$health['critical_total'],'high_total'=>(int)$health['high_total']],
            'slo'=>$slo?['severity'=>(string)$slo['severity'],'availability_percent'=>(float)$slo['availability_percent'],'error_budget_remaining_percent'=>(float)$slo['error_budget_remaining_percent'],'burn_rate'=>(float)$slo['burn_rate'],'generated_at'=>(string)$slo['generated_at']]:null,
            'dependencies'=>$byService[(int)$service['id']]??[],
        ];
    }
    return $result;
}

function mg_admin_agent_phase3_evaluate_slos(PDO $pdo): array
{
    $policies=mg_admin_agent_safe_rows($pdo,'SELECT p.*,s.service_key,s.label service_label,s.domain,s.tier FROM admin_agent_slo_policies p JOIN admin_agent_services s ON s.id=p.service_id WHERE p.enabled=1 AND s.status<>"retired" ORDER BY s.service_key');
    $end=(int)(floor(time()/300)*300);
    $created=0; $warning=0; $critical=0;
    foreach($policies as $policy){
        $start=$end-((int)$policy['window_minutes']*60);
        $startSql=gmdate('Y-m-d H:i:s',$start); $endSql=gmdate('Y-m-d H:i:s',$end);
        $eventRow=mg_admin_agent_safe_row($pdo,'SELECT COUNT(*) total,SUM(severity IN ("error","critical")) bad FROM admin_agent_events WHERE domain=? AND occurred_at>=? AND occurred_at<?',[(string)$policy['domain'],$startSql,$endSql]);
        $findingRow=mg_admin_agent_safe_row($pdo,'SELECT SUM(status IN ("open","acknowledged","under_review") AND severity="critical") critical_total,SUM(status IN ("open","acknowledged","under_review") AND severity="high") high_total,SUM(status IN ("open","acknowledged","under_review") AND severity="medium") medium_total FROM admin_agent_findings WHERE domain=?',[(string)$policy['domain']]);
        $eventTotal=(int)($eventRow['total']??0); $eventBad=(int)($eventRow['bad']??0);
        $criticalFindings=(int)($findingRow['critical_total']??0); $highFindings=(int)($findingRow['high_total']??0); $mediumFindings=(int)($findingRow['medium_total']??0);
        $syntheticBad=($criticalFindings*5)+($highFindings*2)+$mediumFindings;
        $bad=$eventBad+$syntheticBad;
        $total=max(100,$eventTotal+$syntheticBad);
        $good=max(0,$total-$bad);
        $availability=($good/$total)*100.0;
        $allowedError=max(0.0001,100.0-(float)$policy['objective_percent']);
        $errorRate=max(0.0,100.0-$availability);
        $burn=$errorRate/$allowedError;
        $severity=$burn>=(float)$policy['critical_burn_rate']?'critical':($burn>=(float)$policy['warning_burn_rate']?'warning':'healthy');
        $budget=max(0.0,100.0-(($errorRate/$allowedError)*100.0));
        if($severity==='critical') $critical++; elseif($severity==='warning') $warning++;
        $public=mg_public_id();
        $pdo->prepare('INSERT INTO admin_agent_slo_snapshots (public_id,policy_id,window_start,window_end,good_events,bad_events,total_events,availability_percent,error_budget_remaining_percent,burn_rate,severity,evidence_json,generated_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE good_events=VALUES(good_events),bad_events=VALUES(bad_events),total_events=VALUES(total_events),availability_percent=VALUES(availability_percent),error_budget_remaining_percent=VALUES(error_budget_remaining_percent),burn_rate=VALUES(burn_rate),severity=VALUES(severity),evidence_json=VALUES(evidence_json),generated_at=NOW()')->execute([
            $public,(int)$policy['id'],$startSql,$endSql,$good,$bad,$total,$availability,$budget,$burn,$severity,
            json_encode(['service_key'=>$policy['service_key'],'domain'=>$policy['domain'],'event_total'=>$eventTotal,'event_bad'=>$eventBad,'critical_findings'=>$criticalFindings,'high_findings'=>$highFindings,'medium_findings'=>$mediumFindings],JSON_UNESCAPED_SLASHES),
        ]);
        $created++;
    }
    return ['snapshots'=>$created,'warning'=>$warning,'critical'=>$critical,'window_end'=>gmdate('Y-m-d H:i:s',$end)];
}

function mg_admin_agent_phase3_slos(PDO $pdo,int $limit=100): array
{
    $limit=max(10,min(200,$limit));
    $rows=mg_admin_agent_safe_rows($pdo,'SELECT p.public_id policy_id,p.policy_key,p.label,p.objective_percent,p.window_minutes,s.public_id service_id,s.service_key,s.label service_label,s.domain,s.tier,x.public_id snapshot_id,x.availability_percent,x.error_budget_remaining_percent,x.burn_rate,x.severity,x.evidence_json,x.window_start,x.window_end,x.generated_at FROM admin_agent_slo_policies p JOIN admin_agent_services s ON s.id=p.service_id LEFT JOIN admin_agent_slo_snapshots x ON x.id=(SELECT MAX(x2.id) FROM admin_agent_slo_snapshots x2 WHERE x2.policy_id=p.id) WHERE p.enabled=1 ORDER BY CASE COALESCE(x.severity,"healthy") WHEN "critical" THEN 1 WHEN "warning" THEN 2 ELSE 3 END,s.tier,s.label LIMIT '.$limit);
    return array_map(static fn(array $row):array=>[
        'policy_id'=>(string)$row['policy_id'],'policy_key'=>(string)$row['policy_key'],'label'=>(string)$row['label'],'objective_percent'=>(float)$row['objective_percent'],'window_minutes'=>(int)$row['window_minutes'],
        'service_id'=>(string)$row['service_id'],'service_key'=>(string)$row['service_key'],'service_label'=>(string)$row['service_label'],'domain'=>(string)$row['domain'],'tier'=>(string)$row['tier'],
        'snapshot_id'=>$row['snapshot_id']!==null?(string)$row['snapshot_id']:null,'availability_percent'=>$row['availability_percent']!==null?(float)$row['availability_percent']:null,
        'error_budget_remaining_percent'=>$row['error_budget_remaining_percent']!==null?(float)$row['error_budget_remaining_percent']:null,'burn_rate'=>$row['burn_rate']!==null?(float)$row['burn_rate']:null,
        'severity'=>(string)($row['severity']??'unknown'),'evidence'=>mg_admin_agent_json($row['evidence_json']??null),'window_start'=>$row['window_start'],'window_end'=>$row['window_end'],'generated_at'=>$row['generated_at'],
    ],$rows);
}

function mg_admin_agent_phase3_timeline(PDO $pdo,int $workspaceId,string $type,string $title,?string $message=null,?string $sourceTable=null,?string $sourceId=null,?int $actorId=null,array $evidence=[]): void
{
    $pdo->prepare('INSERT INTO admin_agent_incident_timeline (public_id,workspace_id,event_type,title,message,source_table,source_id,actor_user_id,evidence_json,occurred_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())')->execute([
        mg_public_id(),$workspaceId,$type,mb_substr($title,0,240),$message!==null?mb_substr($message,0,2000):null,$sourceTable,$sourceId,$actorId,json_encode($evidence,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
    ]);
}

function mg_admin_agent_phase3_sync_incidents(PDO $pdo): array
{
    $correlations=mg_admin_agent_safe_rows($pdo,'SELECT id,public_id,correlation_type,severity,status,title,summary,domains_json,runbook_key,recommended_action_key,first_detected_at,last_detected_at FROM admin_agent_correlations WHERE status IN ("open","acknowledged","under_review") AND severity IN ("high","critical") ORDER BY CASE severity WHEN "critical" THEN 1 ELSE 2 END,last_detected_at DESC');
    $activeKeys=[]; $created=0; $updated=0; $resolved=0;
    foreach($correlations as $correlation){
        $key=hash('sha256','correlation|'.(string)$correlation['public_id']); $activeKeys[]=$key;
        $existing=mg_admin_agent_safe_row($pdo,'SELECT id,public_id,severity,status FROM admin_agent_incident_workspaces WHERE workspace_key=? LIMIT 1',[$key]);
        $domains=mg_admin_agent_json($correlation['domains_json']??null); $domain=(string)($domains[0]??'operations');
        $service=mg_admin_agent_safe_row($pdo,'SELECT id FROM admin_agent_services WHERE domain=? AND status="active" ORDER BY CASE tier WHEN "critical" THEN 1 WHEN "high" THEN 2 ELSE 3 END LIMIT 1',[$domain]);
        if($existing===[]){
            $public=mg_public_id();
            $pdo->prepare('INSERT INTO admin_agent_incident_workspaces (public_id,workspace_key,correlation_id,service_id,title,severity,status,summary,runbook_key,recommended_action_key,started_at,created_at,updated_at) VALUES (?,?,?,?,?,? ,"watching",?,?,?,?,NOW(),NOW())')->execute([
                $public,$key,(int)$correlation['id'],$service!==[]?(int)$service['id']:null,(string)$correlation['title'],(string)$correlation['severity'],(string)$correlation['summary'],$correlation['runbook_key'],$correlation['recommended_action_key'],(string)$correlation['first_detected_at'],
            ]);
            $workspaceId=(int)$pdo->lastInsertId();
            mg_admin_agent_phase3_timeline($pdo,$workspaceId,'workspace_created','Incident workspace created',(string)$correlation['summary'],'admin_agent_correlations',(string)$correlation['public_id'],null,['correlation_type'=>$correlation['correlation_type'],'domains'=>$domains]);
            $created++;
        }else{
            $pdo->prepare('UPDATE admin_agent_incident_workspaces SET title=?,severity=?,summary=?,runbook_key=?,recommended_action_key=?,status=IF(status IN ("resolved","dismissed"),"watching",status),resolved_at=IF(status IN ("resolved","dismissed"),NULL,resolved_at),updated_at=NOW() WHERE id=?')->execute([(string)$correlation['title'],(string)$correlation['severity'],(string)$correlation['summary'],$correlation['runbook_key'],$correlation['recommended_action_key'],(int)$existing['id']]);
            $updated++;
        }
    }
    $rows=mg_admin_agent_safe_rows($pdo,'SELECT id,workspace_key FROM admin_agent_incident_workspaces WHERE status NOT IN ("resolved","dismissed")');
    foreach($rows as $row){
        if(!in_array((string)$row['workspace_key'],$activeKeys,true)){
            $pdo->prepare('UPDATE admin_agent_incident_workspaces SET status="resolved",resolved_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int)$row['id']]);
            mg_admin_agent_phase3_timeline($pdo,(int)$row['id'],'auto_resolved','Source correlation cleared','The correlated condition is no longer active.');
            $resolved++;
        }
    }
    return ['created'=>$created,'updated'=>$updated,'resolved'=>$resolved];
}

function mg_admin_agent_phase3_upsert_cause(PDO $pdo,int $workspaceId,array $data): string
{
    $key=hash('sha256',(string)$data['cause_type'].'|'.(string)($data['source_public_id']??$data['title']));
    $pdo->prepare('INSERT INTO admin_agent_cause_candidates (public_id,workspace_id,candidate_key,cause_type,title,explanation,confidence_percent,rank_order,source_table,source_public_id,evidence_json,first_detected_at,last_detected_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE title=VALUES(title),explanation=VALUES(explanation),confidence_percent=VALUES(confidence_percent),rank_order=VALUES(rank_order),source_table=VALUES(source_table),source_public_id=VALUES(source_public_id),evidence_json=VALUES(evidence_json),last_detected_at=NOW(),updated_at=NOW()')->execute([
        mg_public_id(),$workspaceId,$key,$data['cause_type'],$data['title'],$data['explanation'],$data['confidence_percent'],$data['rank_order']??1,$data['source_table']??null,$data['source_public_id']??null,json_encode($data['evidence']??[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
    ]);
    return $key;
}

function mg_admin_agent_phase3_analyze_causes(PDO $pdo): array
{
    $workspaces=mg_admin_agent_safe_rows($pdo,'SELECT w.id,w.public_id,w.started_at,w.service_id,c.public_id correlation_public,c.domains_json,c.first_detected_at FROM admin_agent_incident_workspaces w LEFT JOIN admin_agent_correlations c ON c.id=w.correlation_id WHERE w.status NOT IN ("resolved","dismissed")');
    $candidates=0;
    foreach($workspaces as $workspace){
        $domains=mg_admin_agent_json($workspace['domains_json']??null); if($domains===[]) $domains=['operations'];
        $detected=[]; $items=[]; $started=(string)($workspace['first_detected_at']??$workspace['started_at']);
        $deployment=mg_admin_agent_safe_row($pdo,'SELECT public_id,commit_sha,branch_name,deployed_at FROM admin_agent_deployments WHERE deployed_at<=? AND deployed_at>=DATE_SUB(?,INTERVAL 6 HOUR) ORDER BY deployed_at DESC LIMIT 1',[$started,$started]);
        if($deployment!==[]) $items[]=['cause_type'=>'deployment','title'=>'Recent deployment '.substr((string)$deployment['commit_sha'],0,12),'explanation'=>'A deployment occurred within six hours before the correlated condition began.','confidence_percent'=>85.0,'source_table'=>'admin_agent_deployments','source_public_id'=>(string)$deployment['public_id'],'evidence'=>$deployment];
        $placeholders=implode(',',array_fill(0,count($domains),'?'));
        $anomalies=mg_admin_agent_safe_rows($pdo,'SELECT public_id,domain,metric_key,severity,observed_value,baseline_mean,last_detected_at FROM admin_agent_anomalies WHERE domain IN ('.$placeholders.') AND status IN ("open","acknowledged","under_review") AND last_detected_at>=DATE_SUB(?,INTERVAL 2 HOUR) ORDER BY CASE severity WHEN "critical" THEN 1 WHEN "high" THEN 2 ELSE 3 END,last_detected_at DESC LIMIT 4',array_merge($domains,[$started]));
        foreach($anomalies as $anomaly) $items[]=['cause_type'=>'anomaly','title'=>'Abnormal metric: '.str_replace('.',' / ',(string)$anomaly['metric_key']),'explanation'=>'A learned metric anomaly overlaps the incident window in an affected domain.','confidence_percent'=>72.0,'source_table'=>'admin_agent_anomalies','source_public_id'=>(string)$anomaly['public_id'],'evidence'=>$anomaly];
        $findings=mg_admin_agent_safe_rows($pdo,'SELECT public_id,domain,title,summary,severity,last_detected_at FROM admin_agent_findings WHERE domain IN ('.$placeholders.') AND status IN ("open","acknowledged","under_review") AND last_detected_at>=DATE_SUB(?,INTERVAL 4 HOUR) ORDER BY CASE severity WHEN "critical" THEN 1 WHEN "high" THEN 2 ELSE 3 END,last_detected_at DESC LIMIT 4',array_merge($domains,[$started]));
        foreach($findings as $finding) $items[]=['cause_type'=>'finding','title'=>(string)$finding['title'],'explanation'=>'A durable system finding overlaps the incident window and affected domain.','confidence_percent'=>64.0,'source_table'=>'admin_agent_findings','source_public_id'=>(string)$finding['public_id'],'evidence'=>$finding];
        if($workspace['service_id']!==null){
            $dependencies=mg_admin_agent_safe_rows($pdo,'SELECT up.public_id,up.service_key,up.label,up.domain,d.criticality FROM admin_agent_service_dependencies d JOIN admin_agent_services up ON up.id=d.depends_on_service_id WHERE d.service_id=?',[(int)$workspace['service_id']]);
            foreach($dependencies as $dependency){
                $risk=mg_admin_agent_safe_row($pdo,'SELECT COUNT(*) total FROM admin_agent_findings WHERE domain=? AND status IN ("open","acknowledged","under_review") AND severity IN ("high","critical")',[(string)$dependency['domain']]);
                if((int)($risk['total']??0)>0) $items[]=['cause_type'=>'dependency','title'=>'Degraded upstream dependency: '.$dependency['label'],'explanation'=>'An upstream service dependency has active high-severity findings.','confidence_percent'=>(string)$dependency['criticality']==='critical'?78.0:68.0,'source_table'=>'admin_agent_services','source_public_id'=>(string)$dependency['public_id'],'evidence'=>['service_key'=>$dependency['service_key'],'domain'=>$dependency['domain'],'active_risk'=>(int)$risk['total']]];
            }
        }
        $changeCount=(int)(mg_admin_agent_safe_row($pdo,'SELECT COUNT(*) total FROM admin_agent_events WHERE domain="governance" AND occurred_at BETWEEN DATE_SUB(?,INTERVAL 30 MINUTE) AND DATE_ADD(?,INTERVAL 30 MINUTE)',[$started,$started])['total']??0);
        if($changeCount>=5) $items[]=['cause_type'=>'change_activity','title'=>'Concentrated administrative change activity','explanation'=>'Five or more governance events occurred around the incident start window.','confidence_percent'=>55.0,'source_table'=>'admin_agent_events','source_public_id'=>'governance-window-'.$workspace['public_id'],'evidence'=>['governance_events'=>$changeCount,'window_center'=>$started]];
        usort($items,static fn(array $a,array $b):int=>$b['confidence_percent']<=>$a['confidence_percent']);
        foreach($items as $index=>$item){ $item['rank_order']=$index+1; $detected[]=mg_admin_agent_phase3_upsert_cause($pdo,(int)$workspace['id'],$item); $candidates++; }
        if($detected!==[]){
            $marks=implode(',',array_fill(0,count($detected),'?'));
            $pdo->prepare('DELETE FROM admin_agent_cause_candidates WHERE workspace_id=? AND candidate_key NOT IN ('.$marks.')')->execute(array_merge([(int)$workspace['id']],$detected));
        }else $pdo->prepare('DELETE FROM admin_agent_cause_candidates WHERE workspace_id=?')->execute([(int)$workspace['id']]);
    }
    return ['workspaces'=>count($workspaces),'candidates'=>$candidates];
}

function mg_admin_agent_phase3_incidents(PDO $pdo,int $limit=50): array
{
    $limit=max(10,min(100,$limit));
    $rows=mg_admin_agent_safe_rows($pdo,'SELECT w.public_id,w.title,w.severity,w.status,w.summary,w.runbook_key,w.recommended_action_key,w.started_at,w.resolved_at,w.updated_at,w.incident_commander_user_id,c.public_id correlation_id,c.correlation_type,o.public_id ops_incident_id,s.service_key,s.label service_label,s.domain FROM admin_agent_incident_workspaces w LEFT JOIN admin_agent_correlations c ON c.id=w.correlation_id LEFT JOIN admin_ops_incidents o ON o.id=w.ops_incident_id LEFT JOIN admin_agent_services s ON s.id=w.service_id ORDER BY CASE w.status WHEN "declared" THEN 1 WHEN "investigating" THEN 2 WHEN "mitigating" THEN 3 WHEN "watching" THEN 4 ELSE 5 END,CASE w.severity WHEN "critical" THEN 1 WHEN "high" THEN 2 ELSE 3 END,w.updated_at DESC LIMIT '.$limit);
    $result=[];
    foreach($rows as $row){
        $timeline=mg_admin_agent_safe_rows($pdo,'SELECT public_id,event_type,title,message,source_table,source_id,actor_user_id,evidence_json,occurred_at FROM admin_agent_incident_timeline WHERE workspace_id=(SELECT id FROM admin_agent_incident_workspaces WHERE public_id=? LIMIT 1) ORDER BY occurred_at DESC,id DESC LIMIT 20',[(string)$row['public_id']]);
        $causes=mg_admin_agent_safe_rows($pdo,'SELECT public_id,cause_type,title,explanation,confidence_percent,rank_order,source_table,source_public_id,evidence_json,last_detected_at FROM admin_agent_cause_candidates WHERE workspace_id=(SELECT id FROM admin_agent_incident_workspaces WHERE public_id=? LIMIT 1) ORDER BY rank_order,confidence_percent DESC LIMIT 12',[(string)$row['public_id']]);
        $result[]=['id'=>(string)$row['public_id'],'title'=>(string)$row['title'],'severity'=>(string)$row['severity'],'status'=>(string)$row['status'],'summary'=>(string)$row['summary'],'runbook_key'=>$row['runbook_key'],'recommended_action_key'=>$row['recommended_action_key'],'started_at'=>(string)$row['started_at'],'resolved_at'=>$row['resolved_at'],'updated_at'=>(string)$row['updated_at'],'incident_commander_user_id'=>$row['incident_commander_user_id']!==null?(int)$row['incident_commander_user_id']:null,'correlation_id'=>$row['correlation_id'],'correlation_type'=>$row['correlation_type'],'ops_incident_id'=>$row['ops_incident_id'],'service'=>['service_key'=>$row['service_key'],'label'=>$row['service_label'],'domain'=>$row['domain']],
            'timeline'=>array_map(static fn(array $x):array=>['id'=>(string)$x['public_id'],'event_type'=>(string)$x['event_type'],'title'=>(string)$x['title'],'message'=>$x['message'],'source_table'=>$x['source_table'],'source_id'=>$x['source_id'],'actor_user_id'=>$x['actor_user_id']!==null?(int)$x['actor_user_id']:null,'evidence'=>mg_admin_agent_json($x['evidence_json']??null),'occurred_at'=>(string)$x['occurred_at']],$timeline),
            'causes'=>array_map(static fn(array $x):array=>['id'=>(string)$x['public_id'],'cause_type'=>(string)$x['cause_type'],'title'=>(string)$x['title'],'explanation'=>(string)$x['explanation'],'confidence_percent'=>(float)$x['confidence_percent'],'rank_order'=>(int)$x['rank_order'],'source_table'=>$x['source_table'],'source_public_id'=>$x['source_public_id'],'evidence'=>mg_admin_agent_json($x['evidence_json']??null),'last_detected_at'=>(string)$x['last_detected_at']],$causes),
        ];
    }
    return $result;
}

function mg_admin_agent_phase3_incident_action(PDO $pdo,int $actorId,array $input): array
{
    $public=trim((string)($input['workspace_id']??'')); $action=strtolower(trim((string)($input['workspace_action']??''))); $note=mb_substr(trim((string)($input['note']??'')),0,2000);
    $workspace=mg_admin_agent_safe_row($pdo,'SELECT id,public_id,status FROM admin_agent_incident_workspaces WHERE public_id=? LIMIT 1',[$public]);
    if($workspace===[]) throw new InvalidArgumentException('Incident workspace not found.');
    if($action==='assign_self'){
        $pdo->prepare('UPDATE admin_agent_incident_workspaces SET incident_commander_user_id=?,status=IF(status="watching","investigating",status),updated_at=NOW() WHERE id=?')->execute([$actorId,(int)$workspace['id']]);
        mg_admin_agent_phase3_timeline($pdo,(int)$workspace['id'],'commander_assigned','Incident commander assigned','The current administrator assumed incident command.',null,null,$actorId);
    }elseif($action==='add_note'){
        if($note==='') throw new InvalidArgumentException('Enter an incident note.');
        mg_admin_agent_phase3_timeline($pdo,(int)$workspace['id'],'operator_note','Operator note',$note,null,null,$actorId);
    }elseif($action==='update_status'){
        $status=strtolower(trim((string)($input['status']??'')));
        if(!in_array($status,['watching','declared','investigating','mitigating','monitoring','resolved','dismissed'],true)) throw new InvalidArgumentException('Invalid incident workspace status.');
        if(in_array($status,['resolved','dismissed'],true)&&$note==='') throw new InvalidArgumentException('A resolution or dismissal note is required.');
        $pdo->prepare('UPDATE admin_agent_incident_workspaces SET status=?,resolved_at=IF(? IN ("resolved","dismissed"),NOW(),NULL),updated_at=NOW() WHERE id=?')->execute([$status,$status,(int)$workspace['id']]);
        mg_admin_agent_phase3_timeline($pdo,(int)$workspace['id'],'status_update','Workspace status: '.$status,$note?:'Incident workspace status updated.',null,null,$actorId,['status'=>$status]);
    }else throw new InvalidArgumentException('Unknown incident workspace action.');
    mg_audit('admin_agent_phase3_incident_action','system',['workspace_id'=>$public,'action'=>$action],$actorId);
    return ['workspace_id'=>$public,'action'=>$action,'updated'=>true];
}

function mg_admin_agent_phase3_evaluate_release(PDO $pdo,string $environment='production'): array
{
    $environment=preg_replace('/[^a-z0-9_-]/','',strtolower($environment))?:'production';
    $deployment=mg_admin_agent_safe_row($pdo,'SELECT id,public_id,commit_sha,branch_name,deployed_at FROM admin_agent_deployments WHERE environment_key=? ORDER BY deployed_at DESC LIMIT 1',[$environment]);
    $health=mg_admin_agent_health($pdo);
    $criticalSlo=(int)(mg_admin_agent_safe_row($pdo,'SELECT COUNT(*) total FROM admin_agent_slo_snapshots x WHERE x.id IN (SELECT MAX(x2.id) FROM admin_agent_slo_snapshots x2 GROUP BY x2.policy_id) AND x.severity="critical"')['total']??0);
    $warningSlo=(int)(mg_admin_agent_safe_row($pdo,'SELECT COUNT(*) total FROM admin_agent_slo_snapshots x WHERE x.id IN (SELECT MAX(x2.id) FROM admin_agent_slo_snapshots x2 GROUP BY x2.policy_id) AND x.severity="warning"')['total']??0);
    $activeIncidents=(int)(mg_admin_agent_safe_row($pdo,'SELECT COUNT(*) total FROM admin_agent_incident_workspaces WHERE status NOT IN ("resolved","dismissed")')['total']??0);
    $criticalIncidents=(int)(mg_admin_agent_safe_row($pdo,'SELECT COUNT(*) total FROM admin_agent_incident_workspaces WHERE status NOT IN ("resolved","dismissed") AND severity="critical"')['total']??0);
    $postDeployCritical=0;
    if($deployment!==[]) $postDeployCritical=(int)(mg_admin_agent_safe_row($pdo,'SELECT COUNT(*) total FROM admin_agent_findings WHERE status IN ("open","acknowledged","under_review") AND severity="critical" AND first_detected_at>=?',[(string)$deployment['deployed_at']])['total']??0);
    $reasons=[];
    if($criticalSlo>0) $reasons[]=$criticalSlo.' critical SLO burn condition(s).';
    if($criticalIncidents>0) $reasons[]=$criticalIncidents.' active critical incident workspace(s).';
    if($postDeployCritical>0) $reasons[]=$postDeployCritical.' critical finding(s) first detected after the latest deployment.';
    if((int)$health['score']<70) $reasons[]='Overall system health is below 70.';
    $status=$reasons!==[]?'block':(((int)$health['score']<90||$warningSlo>0||$activeIncidents>0)?'warn':'pass');
    $score=max(0,min(100,(int)$health['score']-($criticalSlo*15)-($criticalIncidents*20)-($warningSlo*5)));
    $scope=$deployment!==[]?(string)$deployment['public_id']:gmdate('Y-m-d'); $key=hash('sha256',$environment.'|'.$scope);
    $pdo->prepare('INSERT INTO admin_agent_release_gates (public_id,gate_key,deployment_id,environment_key,status,score,health_score,critical_slo_total,active_incident_total,blocking_reasons_json,evidence_json,evaluated_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status),score=VALUES(score),health_score=VALUES(health_score),critical_slo_total=VALUES(critical_slo_total),active_incident_total=VALUES(active_incident_total),blocking_reasons_json=VALUES(blocking_reasons_json),evidence_json=VALUES(evidence_json),evaluated_at=NOW(),updated_at=NOW()')->execute([
        mg_public_id(),$key,$deployment!==[]?(int)$deployment['id']:null,$environment,$status,$score,(int)$health['score'],$criticalSlo,$activeIncidents,json_encode($reasons,JSON_UNESCAPED_SLASHES),json_encode(['warning_slo_total'=>$warningSlo,'critical_incident_total'=>$criticalIncidents,'post_deploy_critical_total'=>$postDeployCritical,'deployment'=>$deployment],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
    ]);
    $row=mg_admin_agent_safe_row($pdo,'SELECT public_id,status,score,health_score,critical_slo_total,active_incident_total,blocking_reasons_json,evidence_json,evaluated_at FROM admin_agent_release_gates WHERE gate_key=? LIMIT 1',[$key]);
    return ['id'=>(string)$row['public_id'],'environment_key'=>$environment,'status'=>(string)$row['status'],'score'=>(int)$row['score'],'health_score'=>(int)$row['health_score'],'critical_slo_total'=>(int)$row['critical_slo_total'],'active_incident_total'=>(int)$row['active_incident_total'],'blocking_reasons'=>mg_admin_agent_json($row['blocking_reasons_json']??null),'evidence'=>mg_admin_agent_json($row['evidence_json']??null),'evaluated_at'=>(string)$row['evaluated_at']];
}

function mg_admin_agent_phase3_release_gates(PDO $pdo,int $limit=20): array
{
    $limit=max(5,min(100,$limit));
    $rows=mg_admin_agent_safe_rows($pdo,'SELECT g.public_id,g.environment_key,g.status,g.score,g.health_score,g.critical_slo_total,g.active_incident_total,g.blocking_reasons_json,g.evidence_json,g.evaluated_at,d.public_id deployment_id,d.commit_sha,d.branch_name,d.deployed_at FROM admin_agent_release_gates g LEFT JOIN admin_agent_deployments d ON d.id=g.deployment_id ORDER BY g.evaluated_at DESC LIMIT '.$limit);
    return array_map(static fn(array $r):array=>['id'=>(string)$r['public_id'],'environment_key'=>(string)$r['environment_key'],'status'=>(string)$r['status'],'score'=>(int)$r['score'],'health_score'=>$r['health_score']!==null?(int)$r['health_score']:null,'critical_slo_total'=>(int)$r['critical_slo_total'],'active_incident_total'=>(int)$r['active_incident_total'],'blocking_reasons'=>mg_admin_agent_json($r['blocking_reasons_json']??null),'evidence'=>mg_admin_agent_json($r['evidence_json']??null),'evaluated_at'=>(string)$r['evaluated_at'],'deployment'=>$r['deployment_id']?['id'=>(string)$r['deployment_id'],'commit_sha'=>(string)$r['commit_sha'],'branch_name'=>(string)$r['branch_name'],'deployed_at'=>(string)$r['deployed_at']]:null],$rows);
}

function mg_admin_agent_phase3_brief_subscriptions(PDO $pdo,int $adminId): array
{
    $rows=mg_admin_agent_safe_rows($pdo,'SELECT public_id,cadence,channel,hour_utc,weekday_utc,enabled,last_delivered_at,created_at,updated_at FROM admin_agent_brief_subscriptions WHERE admin_user_id=? ORDER BY cadence',[$adminId]);
    return array_map(static fn(array $r):array=>['id'=>(string)$r['public_id'],'cadence'=>(string)$r['cadence'],'channel'=>(string)$r['channel'],'hour_utc'=>(int)$r['hour_utc'],'weekday_utc'=>(int)$r['weekday_utc'],'enabled'=>(bool)$r['enabled'],'last_delivered_at'=>$r['last_delivered_at'],'created_at'=>(string)$r['created_at'],'updated_at'=>(string)$r['updated_at']],$rows);
}

function mg_admin_agent_phase3_update_brief(PDO $pdo,int $adminId,array $input): array
{
    $cadence=strtolower(trim((string)($input['cadence']??'daily'))); if(!in_array($cadence,['daily','weekly'],true)) throw new InvalidArgumentException('Invalid brief cadence.');
    $hour=max(0,min(23,(int)($input['hour_utc']??13))); $weekday=max(1,min(7,(int)($input['weekday_utc']??1))); $enabled=!empty($input['enabled']);
    $pdo->prepare('INSERT INTO admin_agent_brief_subscriptions (public_id,admin_user_id,cadence,channel,hour_utc,weekday_utc,enabled,created_at,updated_at) VALUES (?, ?, ?,"notification_center",?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE hour_utc=VALUES(hour_utc),weekday_utc=VALUES(weekday_utc),enabled=VALUES(enabled),updated_at=NOW()')->execute([mg_public_id(),$adminId,$cadence,$hour,$weekday,$enabled?1:0]);
    mg_audit('admin_agent_phase3_brief_updated','system',['cadence'=>$cadence,'hour_utc'=>$hour,'weekday_utc'=>$weekday,'enabled'=>$enabled],$adminId);
    return ['cadence'=>$cadence,'hour_utc'=>$hour,'weekday_utc'=>$weekday,'enabled'=>$enabled];
}

function mg_admin_agent_phase3_process_briefs(PDO $pdo,?int $actorId=null): array
{
    $hour=(int)gmdate('G'); $weekday=(int)gmdate('N'); $today=gmdate('Y-m-d'); $week=gmdate('o-W'); $sent=0; $skipped=0;
    $subscriptions=mg_admin_agent_safe_rows($pdo,'SELECT * FROM admin_agent_brief_subscriptions WHERE enabled=1 AND hour_utc<=? ORDER BY id',[$hour]);
    foreach($subscriptions as $subscription){
        $cadence=(string)$subscription['cadence'];
        if($cadence==='weekly' && $weekday!==(int)$subscription['weekday_utc']){ $skipped++; continue; }
        $period=$cadence==='daily'?$today:$week; $key=hash('sha256',(int)$subscription['id'].'|'.$cadence.'|'.$period);
        if(mg_admin_agent_safe_row($pdo,'SELECT id FROM admin_agent_brief_deliveries WHERE delivery_key=? LIMIT 1',[$key])!==[]){ $skipped++; continue; }
        $summary=mg_admin_agent_phase2_generate_summary($pdo,$cadence,$actorId);
        $summaryRow=mg_admin_agent_safe_row($pdo,'SELECT id FROM admin_agent_executive_summaries WHERE public_id=? LIMIT 1',[(string)$summary['id']]);
        $deliveryPublic=mg_public_id();
        try{
            mg_queue_notice_create($pdo,['note_id'=>null,'target_user_id'=>(int)$subscription['admin_user_id'],'assigned_admin_user_id'=>(int)$subscription['admin_user_id'],'actor_user_id'=>$actorId,'notification_type'=>'digest','severity'=>(int)($summary['health_score']??100)<70?'critical':'info','title'=>(string)$summary['title'],'message'=>(string)$summary['summary_text'],'metadata'=>['admin_agent_brief'=>true,'cadence'=>$cadence,'summary_id'=>$summary['id']]]);
            $pdo->prepare('INSERT INTO admin_agent_brief_deliveries (public_id,delivery_key,subscription_id,summary_id,status,delivered_at,created_at,updated_at) VALUES (?,?,?,?,"sent",NOW(),NOW(),NOW())')->execute([$deliveryPublic,$key,(int)$subscription['id'],$summaryRow!==[]?(int)$summaryRow['id']:null]);
            $pdo->prepare('UPDATE admin_agent_brief_subscriptions SET last_delivered_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int)$subscription['id']]);
            $sent++;
        }catch(Throwable $error){
            $pdo->prepare('INSERT INTO admin_agent_brief_deliveries (public_id,delivery_key,subscription_id,summary_id,status,failure_message,created_at,updated_at) VALUES (?,?,?,?,"failed",?,NOW(),NOW()) ON DUPLICATE KEY UPDATE status="failed",failure_message=VALUES(failure_message),updated_at=NOW()')->execute([$deliveryPublic,$key,(int)$subscription['id'],$summaryRow!==[]?(int)$summaryRow['id']:null,mb_substr($error->getMessage(),0,1000)]);
        }
    }
    return ['sent'=>$sent,'skipped'=>$skipped];
}

function mg_admin_agent_phase3_run(PDO $pdo,array $options=[]): array
{
    if(!mg_admin_agent_phase3_ready($pdo)) throw new RuntimeException('Main Admin Agent Phase 3 SQL migration is required.');
    $actorId=isset($options['initiated_by_user_id'])&&(int)$options['initiated_by_user_id']>0?(int)$options['initiated_by_user_id']:null;
    $phase2=mg_admin_agent_phase2_run($pdo,$options);
    $slos=mg_admin_agent_phase3_evaluate_slos($pdo);
    $incidents=mg_admin_agent_phase3_sync_incidents($pdo);
    $causes=mg_admin_agent_phase3_analyze_causes($pdo);
    $release=mg_admin_agent_phase3_evaluate_release($pdo,(string)($options['environment_key']??'production'));
    $briefs=mg_admin_agent_phase3_process_briefs($pdo,$actorId);
    mg_audit('admin_agent_phase3_completed','system',['scan_id'=>$phase2['scan']['id']??null,'slos'=>$slos,'incidents'=>$incidents,'causes'=>$causes,'release'=>$release,'briefs'=>$briefs],$actorId);
    return ['phase2'=>$phase2,'slos'=>$slos,'incidents'=>$incidents,'causes'=>$causes,'release_gate'=>$release,'briefs'=>$briefs,'database_only'=>true,'used_ai'=>false,'credits_used'=>0];
}

function mg_admin_agent_phase3_chat_mode(string $message): ?string
{
    $text=strtolower(trim($message));
    if(str_contains($text,'topolog')||str_contains($text,'service map')||str_contains($text,'dependenc')) return 'topology';
    if(str_contains($text,'slo')||str_contains($text,'error budget')||str_contains($text,'burn rate')) return 'slos';
    if(str_contains($text,'war room')||str_contains($text,'incident workspace')) return 'incidents';
    if(str_contains($text,'root cause')||str_contains($text,'cause timeline')||str_contains($text,'causal')) return 'causes';
    if(str_contains($text,'release gate')||str_contains($text,'release readiness')||str_contains($text,'safe to deploy')) return 'release';
    if(str_contains($text,'brief delivery')||str_contains($text,'brief subscription')||str_contains($text,'scheduled brief')) return 'briefs';
    return null;
}

function mg_admin_agent_phase3_report(PDO $pdo,string $mode,int $adminId): array
{
    $services=mg_admin_agent_phase3_services($pdo); $slos=mg_admin_agent_phase3_slos($pdo); $incidents=mg_admin_agent_phase3_incidents($pdo); $gates=mg_admin_agent_phase3_release_gates($pdo); $subscriptions=mg_admin_agent_phase3_brief_subscriptions($pdo,$adminId);
    $title='Main Admin Agent Phase 3 operations'; $lines=[]; $blocks=[];
    if($mode==='topology'){ $title='Service topology and dependency report'; $attention=array_values(array_filter($services,static fn(array $s):bool=>$s['health']['status']!=='healthy')); $lines[]=count($services).' services are registered; '.count($attention).' currently need attention.'; $blocks[]=['type'=>'services','items'=>$services]; }
    elseif($mode==='slos'){ $title='SLO and error-budget report'; $burning=array_values(array_filter($slos,static fn(array $s):bool=>in_array($s['severity'],['warning','critical'],true))); $lines[]=count($burning).' SLO policy/policies are consuming error budget above the healthy rate.'; $blocks[]=['type'=>'slos','items'=>$slos]; }
    elseif($mode==='incidents'){ $title='Incident workspace report'; $active=array_values(array_filter($incidents,static fn(array $i):bool=>!in_array($i['status'],['resolved','dismissed'],true))); $lines[]=count($active).' active incident workspace(s) organize correlated risk, ownership, timelines, and remediation.'; $blocks[]=['type'=>'incidents','items'=>$active]; }
    elseif($mode==='causes'){ $title='Deterministic cause analysis'; $total=array_sum(array_map(static fn(array $i):int=>count($i['causes']),$incidents)); $lines[]=$total.' ranked cause candidate(s) are attached to current incident workspaces. Candidates are evidence-based hypotheses, not proof.'; $blocks[]=['type'=>'incidents','items'=>$incidents]; }
    elseif($mode==='release'){ $title='Release-readiness gate'; if($gates===[]) $gates[] = mg_admin_agent_phase3_evaluate_release($pdo); $lines[]='Current production release gate: '.strtoupper($gates[0]['status']).' with score '.$gates[0]['score'].'/100.'; $blocks[]=['type'=>'release_gates','items'=>$gates]; }
    else { $title='Scheduled executive brief delivery'; $lines[]=count($subscriptions).' brief subscription(s) are configured for this administrator.'; $blocks[]=['type'=>'briefs','items'=>$subscriptions]; }
    return ['title'=>$title,'content'=>implode("\n",$lines),'blocks'=>$blocks,'metadata'=>['mode'=>$mode,'database_only'=>true,'used_ai'=>false,'credits_used'=>0,'generated_at'=>gmdate('Y-m-d H:i:s')]];
}

function mg_admin_agent_phase3_send(PDO $pdo,int $adminId,array $input): array
{
    $message=mb_substr(trim((string)($input['message']??'')),0,4000); if($message==='') throw new InvalidArgumentException('Enter a message for the Main Admin Agent.');
    $mode=mg_admin_agent_phase3_chat_mode($message); if($mode===null) return mg_admin_agent_phase2_send($pdo,$adminId,$input);
    $thread=mg_admin_agent_thread($pdo,$adminId,isset($input['thread_id'])?(string)$input['thread_id']:null);
    $userMessage=mg_admin_agent_record_message($pdo,(int)$thread['id'],$adminId,'user',$message,'chat',[],['database_only'=>true]);
    $report=mg_admin_agent_phase3_report($pdo,$mode,$adminId);
    $assistant=mg_admin_agent_record_message($pdo,(int)$thread['id'],$adminId,'assistant',$report['content'],'system_report',$report['blocks'],$report['metadata']+['title'=>$report['title']]);
    mg_audit('admin_agent_phase3_chat_report','system',['thread_id'=>$thread['public_id'],'mode'=>$mode,'database_only'=>true],$adminId);
    return ['thread'=>['id'=>(string)$thread['public_id'],'title'=>(string)$thread['title']],'user_message'=>$userMessage,'assistant_message'=>$assistant,'report'=>$report];
}

function mg_admin_agent_phase3_state(PDO $pdo,int $adminId,array $options=[]): array
{
    $state=mg_admin_agent_phase2_state($pdo,$adminId,$options); $schema=mg_admin_agent_phase3_schema_state($pdo); $state['phase3_schema']=$schema; $state['phase3_ready']=$schema['ready'];
    if(!$schema['ready']) return $state;
    $state['services']=mg_admin_agent_phase3_services($pdo); $state['slos']=mg_admin_agent_phase3_slos($pdo); $state['incident_workspaces']=mg_admin_agent_phase3_incidents($pdo); $state['release_gates']=mg_admin_agent_phase3_release_gates($pdo); $state['brief_subscriptions']=mg_admin_agent_phase3_brief_subscriptions($pdo,$adminId);
    $state['phase3_systematic']=['database_only'=>true,'used_ai'=>false,'credits_used'=>0,'cause_candidates_are_hypotheses'=>true,'incident_declaration'=>'review_approved_typed_confirmation'];
    return $state;
}
