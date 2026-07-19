<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-agent-phase3.php';

const MG_ADMIN_AGENT_PHASE4_MIGRATION = 'database/20260719_main_admin_agent_phase4.sql';

function mg_admin_agent_phase4_tables(): array
{
    return [
        'admin_agent_maintenance_windows','admin_agent_change_risk_assessments',
        'admin_agent_reliability_scorecards','admin_agent_capacity_forecasts',
        'admin_agent_incident_learning','admin_agent_prevention_followups',
    ];
}

function mg_admin_agent_phase4_schema_state(PDO $pdo): array
{
    $missing=[];
    foreach(mg_admin_agent_phase4_tables() as $table){
        if(!mg_admin_schema_has_table($pdo,$table)) $missing[]=$table;
    }
    return ['ready'=>$missing===[],'missing_tables'=>$missing,'migration'=>MG_ADMIN_AGENT_PHASE4_MIGRATION];
}

function mg_admin_agent_phase4_ready(PDO $pdo): bool
{
    return mg_admin_agent_phase4_schema_state($pdo)['ready'];
}

function mg_admin_agent_phase4_sync_maintenance(PDO $pdo): array
{
    $active=$pdo->exec('UPDATE admin_agent_maintenance_windows SET status="active",updated_at=NOW() WHERE status="scheduled" AND starts_at<=NOW() AND ends_at>NOW()');
    $completed=$pdo->exec('UPDATE admin_agent_maintenance_windows SET status="completed",updated_at=NOW() WHERE status IN ("scheduled","active") AND ends_at<=NOW()');
    return ['activated'=>(int)$active,'completed'=>(int)$completed];
}

function mg_admin_agent_phase4_maintenance_windows(PDO $pdo,int $limit=50): array
{
    $limit=max(5,min(100,$limit));
    $rows=mg_admin_agent_safe_rows($pdo,'SELECT w.public_id,w.environment_key,w.title,w.reason,w.status,w.suppression_mode,w.starts_at,w.ends_at,w.created_by_user_id,w.canceled_by_user_id,w.canceled_at,w.metadata_json,w.created_at,w.updated_at,s.public_id service_id,s.service_key,s.label service_label,s.domain FROM admin_agent_maintenance_windows w LEFT JOIN admin_agent_services s ON s.id=w.service_id ORDER BY CASE w.status WHEN "active" THEN 1 WHEN "scheduled" THEN 2 ELSE 3 END,w.starts_at DESC LIMIT '.$limit);
    return array_map(static fn(array $r):array=>[
        'id'=>(string)$r['public_id'],'environment_key'=>(string)$r['environment_key'],'title'=>(string)$r['title'],'reason'=>(string)$r['reason'],
        'status'=>(string)$r['status'],'suppression_mode'=>(string)$r['suppression_mode'],'starts_at'=>(string)$r['starts_at'],'ends_at'=>(string)$r['ends_at'],
        'created_by_user_id'=>$r['created_by_user_id']!==null?(int)$r['created_by_user_id']:null,'canceled_by_user_id'=>$r['canceled_by_user_id']!==null?(int)$r['canceled_by_user_id']:null,
        'canceled_at'=>$r['canceled_at'],'metadata'=>mg_admin_agent_json($r['metadata_json']??null),'created_at'=>(string)$r['created_at'],'updated_at'=>(string)$r['updated_at'],
        'service'=>$r['service_id']?['id'=>(string)$r['service_id'],'service_key'=>(string)$r['service_key'],'label'=>(string)$r['service_label'],'domain'=>(string)$r['domain']]:null,
    ],$rows);
}

function mg_admin_agent_phase4_active_maintenance(PDO $pdo,string $environment='production',?int $serviceId=null): array
{
    $sql='SELECT w.*,s.service_key,s.label service_label,s.domain FROM admin_agent_maintenance_windows w LEFT JOIN admin_agent_services s ON s.id=w.service_id WHERE w.environment_key=? AND w.status="active" AND w.starts_at<=NOW() AND w.ends_at>NOW()';
    $params=[$environment];
    if($serviceId!==null){ $sql.=' AND (w.service_id IS NULL OR w.service_id=?)'; $params[]=$serviceId; }
    $sql.=' ORDER BY w.service_id IS NULL DESC,w.starts_at DESC LIMIT 1';
    return mg_admin_agent_safe_row($pdo,$sql,$params);
}

function mg_admin_agent_phase4_maintenance_action(PDO $pdo,int $actorId,array $input): array
{
    $action=strtolower(trim((string)($input['maintenance_action']??'create')));
    if($action==='create'){
        $title=mb_substr(trim((string)($input['title']??'')),0,240);
        $reason=mb_substr(trim((string)($input['reason']??'')),0,2000);
        if($title===''||$reason==='') throw new InvalidArgumentException('Maintenance title and reason are required.');
        $startRaw=trim((string)($input['starts_at']??'')); $endRaw=trim((string)($input['ends_at']??''));
        $start=strtotime($startRaw); $end=strtotime($endRaw);
        if($start===false||$end===false||$end<=$start) throw new InvalidArgumentException('Provide a valid maintenance start and end time.');
        $environment=preg_replace('/[^a-z0-9_-]/','',strtolower((string)($input['environment_key']??'production')))?:'production';
        $mode=strtolower(trim((string)($input['suppression_mode']??'observe_only')));
        if(!in_array($mode,['observe_only','suppress_expected'],true)) $mode='observe_only';
        $serviceId=null; $servicePublic=trim((string)($input['service_id']??''));
        if($servicePublic!==''){
            $service=mg_admin_agent_safe_row($pdo,'SELECT id,service_key,domain FROM admin_agent_services WHERE public_id=? AND status="active" LIMIT 1',[$servicePublic]);
            if($service===[]) throw new InvalidArgumentException('Maintenance service not found.');
            $serviceId=(int)$service['id'];
            if((string)$service['domain']==='security') $mode='observe_only';
        }
        $key=hash('sha256',$environment.'|'.($serviceId??'all').'|'.gmdate('c',$start).'|'.$title);
        $public=mg_public_id();
        $pdo->prepare('INSERT INTO admin_agent_maintenance_windows (public_id,window_key,service_id,environment_key,title,reason,status,suppression_mode,starts_at,ends_at,created_by_user_id,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,? ,IF(?<=NOW() AND ?>NOW(),"active","scheduled"),?,?,?,?,JSON_OBJECT("security_findings_suppressed",false,"critical_findings_suppressed",false),NOW(),NOW())')->execute([
            $public,$key,$serviceId,$environment,$title,$reason,gmdate('Y-m-d H:i:s',$start),gmdate('Y-m-d H:i:s',$end),$mode,gmdate('Y-m-d H:i:s',$start),gmdate('Y-m-d H:i:s',$end),$actorId,
        ]);
        mg_audit('admin_agent_phase4_maintenance_created','system',['window_id'=>$public,'environment'=>$environment,'service_id'=>$servicePublic?:null,'suppression_mode'=>$mode],$actorId);
        return ['id'=>$public,'action'=>'created','suppression_mode'=>$mode];
    }
    $public=trim((string)($input['window_id']??''));
    $window=mg_admin_agent_safe_row($pdo,'SELECT id,public_id,status FROM admin_agent_maintenance_windows WHERE public_id=? LIMIT 1',[$public]);
    if($window===[]) throw new InvalidArgumentException('Maintenance window not found.');
    if($action==='cancel'){
        $pdo->prepare('UPDATE admin_agent_maintenance_windows SET status="canceled",canceled_by_user_id=?,canceled_at=NOW(),updated_at=NOW() WHERE id=? AND status IN ("scheduled","active")')->execute([$actorId,(int)$window['id']]);
    }elseif($action==='complete'){
        $pdo->prepare('UPDATE admin_agent_maintenance_windows SET status="completed",ends_at=LEAST(ends_at,NOW()),updated_at=NOW() WHERE id=? AND status IN ("scheduled","active")')->execute([(int)$window['id']]);
    }else throw new InvalidArgumentException('Unknown maintenance window action.');
    mg_audit('admin_agent_phase4_maintenance_updated','system',['window_id'=>$public,'action'=>$action],$actorId);
    return ['id'=>$public,'action'=>$action,'updated'=>true];
}

function mg_admin_agent_phase4_change_risk_level(int $score): string
{
    if($score>=85) return 'critical';
    if($score>=65) return 'high';
    if($score>=35) return 'medium';
    return 'low';
}

function mg_admin_agent_phase4_evaluate_change(PDO $pdo,string $environment='production',?int $actorId=null,array $context=[]): array
{
    $environment=preg_replace('/[^a-z0-9_-]/','',strtolower($environment))?:'production';
    $deployment=[];
    $deploymentPublic=trim((string)($context['deployment_id']??''));
    if($deploymentPublic!=='') $deployment=mg_admin_agent_safe_row($pdo,'SELECT * FROM admin_agent_deployments WHERE public_id=? LIMIT 1',[$deploymentPublic]);
    if($deployment===[]) $deployment=mg_admin_agent_safe_row($pdo,'SELECT * FROM admin_agent_deployments WHERE environment_key=? ORDER BY deployed_at DESC LIMIT 1',[$environment]);
    $metadata=mg_admin_agent_json($deployment['metadata_json']??null);
    $serviceKeys=[];
    foreach(array_merge((array)($metadata['services']??[]),(array)($context['impacted_services']??[])) as $key){
        $clean=preg_replace('/[^a-z0-9_]/','',strtolower((string)$key)); if($clean!=='') $serviceKeys[$clean]=true;
    }
    if($serviceKeys===[]){
        foreach(mg_admin_agent_safe_rows($pdo,'SELECT DISTINCT s.service_key FROM admin_agent_findings f JOIN admin_agent_services s ON s.domain=f.domain WHERE f.status IN ("open","acknowledged","under_review") AND f.severity IN ("high","critical") LIMIT 8') as $row) $serviceKeys[(string)$row['service_key']]=true;
    }
    $impacted=[]; $criticalServices=0;
    if($serviceKeys!==[]){
        $marks=implode(',',array_fill(0,count($serviceKeys),'?'));
        $impacted=mg_admin_agent_safe_rows($pdo,'SELECT id,public_id,service_key,label,domain,tier FROM admin_agent_services WHERE service_key IN ('.$marks.')',array_keys($serviceKeys));
        foreach($impacted as $service) if(in_array((string)$service['tier'],['critical','high'],true)) $criticalServices++;
    }
    $changedFiles=max(0,(int)($context['changed_files']??$metadata['changed_files']??$metadata['files_changed']??0));
    $migrationCount=max(0,(int)($context['migration_count']??$metadata['migration_count']??$metadata['migrations']??0));
    $criticalSlo=(int)(mg_admin_agent_safe_row($pdo,'SELECT COUNT(*) total FROM admin_agent_slo_snapshots x WHERE x.id IN (SELECT MAX(x2.id) FROM admin_agent_slo_snapshots x2 GROUP BY x2.policy_id) AND x.severity="critical"')['total']??0);
    $warningSlo=(int)(mg_admin_agent_safe_row($pdo,'SELECT COUNT(*) total FROM admin_agent_slo_snapshots x WHERE x.id IN (SELECT MAX(x2.id) FROM admin_agent_slo_snapshots x2 GROUP BY x2.policy_id) AND x.severity="warning"')['total']??0);
    $activeIncidents=(int)(mg_admin_agent_safe_row($pdo,'SELECT COUNT(*) total FROM admin_agent_incident_workspaces WHERE status NOT IN ("resolved","dismissed")')['total']??0);
    $recentDeployments=(int)(mg_admin_agent_safe_row($pdo,'SELECT COUNT(*) total FROM admin_agent_deployments WHERE environment_key=? AND deployed_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)',[$environment])['total']??0);
    $window=mg_admin_agent_phase4_active_maintenance($pdo,$environment,$impacted!==[]?(int)$impacted[0]['id']:null);
    $score=min(100,min(30,$criticalServices*10)+min(15,$changedFiles>0?(int)ceil($changedFiles/10):0)+min(20,$migrationCount*10)+min(20,$criticalSlo*15)+min(10,$warningSlo*3)+min(20,$activeIncidents*10)+min(10,max(0,$recentDeployments-1)*5));
    if($window!==[]) $score=max(0,$score-10);
    elseif($score>=35) $score=min(100,$score+5);
    $level=mg_admin_agent_phase4_change_risk_level($score);
    $factors=[
        'critical_or_high_services'=>$criticalServices,'changed_files'=>$changedFiles,'migration_count'=>$migrationCount,
        'critical_slo_total'=>$criticalSlo,'warning_slo_total'=>$warningSlo,'active_incident_total'=>$activeIncidents,
        'deployments_last_24h'=>$recentDeployments,'maintenance_window_active'=>$window!==[],
    ];
    $recommendations=[];
    if($criticalSlo>0||$activeIncidents>0) $recommendations[]='Delay non-essential production changes until critical reliability conditions clear.';
    if($migrationCount>0) $recommendations[]='Confirm migration backup, rollback, and post-deploy validation before release.';
    if($criticalServices>0) $recommendations[]='Use a service-specific smoke test for each critical impacted service.';
    if($window===[]&&$score>=65) $recommendations[]='Create a planned maintenance window before proceeding.';
    if($recommendations===[]) $recommendations[]='Proceed with standard deployment verification and monitor the first thirty minutes.';
    $scope=$deployment!==[]?(string)$deployment['public_id']:gmdate('Y-m-d');
    $key=hash('sha256',$environment.'|'.$scope);
    $pdo->prepare('INSERT INTO admin_agent_change_risk_assessments (public_id,assessment_key,deployment_id,environment_key,risk_level,risk_score,impacted_services_json,factors_json,recommendations_json,maintenance_window_id,evaluated_by_user_id,evaluated_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE risk_level=VALUES(risk_level),risk_score=VALUES(risk_score),impacted_services_json=VALUES(impacted_services_json),factors_json=VALUES(factors_json),recommendations_json=VALUES(recommendations_json),maintenance_window_id=VALUES(maintenance_window_id),evaluated_by_user_id=VALUES(evaluated_by_user_id),evaluated_at=NOW(),updated_at=NOW()')->execute([
        mg_public_id(),$key,$deployment!==[]?(int)$deployment['id']:null,$environment,$level,$score,
        json_encode(array_map(static fn(array $s):array=>['id'=>(string)$s['public_id'],'service_key'=>(string)$s['service_key'],'label'=>(string)$s['label'],'domain'=>(string)$s['domain'],'tier'=>(string)$s['tier']],$impacted),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
        json_encode($factors,JSON_UNESCAPED_SLASHES),json_encode($recommendations,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$window!==[]?(int)$window['id']:null,$actorId,
    ]);
    $row=mg_admin_agent_safe_row($pdo,'SELECT public_id,risk_level,risk_score,impacted_services_json,factors_json,recommendations_json,evaluated_at FROM admin_agent_change_risk_assessments WHERE assessment_key=? LIMIT 1',[$key]);
    return ['id'=>(string)$row['public_id'],'environment_key'=>$environment,'risk_level'=>(string)$row['risk_level'],'risk_score'=>(int)$row['risk_score'],'impacted_services'=>mg_admin_agent_json($row['impacted_services_json']??null),'factors'=>mg_admin_agent_json($row['factors_json']??null),'recommendations'=>mg_admin_agent_json($row['recommendations_json']??null),'deployment'=>$deployment!==[]?['id'=>(string)$deployment['public_id'],'commit_sha'=>(string)$deployment['commit_sha'],'branch_name'=>(string)$deployment['branch_name'],'deployed_at'=>(string)$deployment['deployed_at']]:null,'maintenance_window'=>$window!==[]?(string)$window['public_id']:null,'evaluated_at'=>(string)$row['evaluated_at']];
}

function mg_admin_agent_phase4_change_risks(PDO $pdo,int $limit=30): array
{
    $limit=max(5,min(100,$limit));
    $rows=mg_admin_agent_safe_rows($pdo,'SELECT a.public_id,a.environment_key,a.risk_level,a.risk_score,a.impacted_services_json,a.factors_json,a.recommendations_json,a.evaluated_at,d.public_id deployment_id,d.commit_sha,d.branch_name,d.deployed_at,w.public_id window_id,w.title window_title FROM admin_agent_change_risk_assessments a LEFT JOIN admin_agent_deployments d ON d.id=a.deployment_id LEFT JOIN admin_agent_maintenance_windows w ON w.id=a.maintenance_window_id ORDER BY a.evaluated_at DESC LIMIT '.$limit);
    return array_map(static fn(array $r):array=>['id'=>(string)$r['public_id'],'environment_key'=>(string)$r['environment_key'],'risk_level'=>(string)$r['risk_level'],'risk_score'=>(int)$r['risk_score'],'impacted_services'=>mg_admin_agent_json($r['impacted_services_json']??null),'factors'=>mg_admin_agent_json($r['factors_json']??null),'recommendations'=>mg_admin_agent_json($r['recommendations_json']??null),'evaluated_at'=>(string)$r['evaluated_at'],'deployment'=>$r['deployment_id']?['id'=>(string)$r['deployment_id'],'commit_sha'=>(string)$r['commit_sha'],'branch_name'=>(string)$r['branch_name'],'deployed_at'=>(string)$r['deployed_at']]:null,'maintenance_window'=>$r['window_id']?['id'=>(string)$r['window_id'],'title'=>(string)$r['window_title']]:null],$rows);
}

function mg_admin_agent_phase4_generate_scorecards(PDO $pdo): array
{
    $services=mg_admin_agent_safe_rows($pdo,'SELECT id,public_id,service_key,label,domain,tier FROM admin_agent_services WHERE status="active" ORDER BY service_key');
    $generated=0; $attention=0;
    foreach($services as $service){
        $policy=mg_admin_agent_safe_row($pdo,'SELECT id,objective_percent FROM admin_agent_slo_policies WHERE service_id=? AND enabled=1 ORDER BY id LIMIT 1',[(int)$service['id']]);
        if($policy===[]) continue;
        foreach([7,30,90] as $days){
            $periodEnd=gmdate('Y-m-d 00:00:00',strtotime('tomorrow UTC')); $periodStart=gmdate('Y-m-d H:i:s',strtotime('-'.$days.' days',strtotime($periodEnd.' UTC')));
            $stats=mg_admin_agent_safe_row($pdo,'SELECT COUNT(*) total,AVG(availability_percent) availability_avg,AVG(error_budget_remaining_percent) budget_avg,SUM(severity="warning") warning_total,SUM(severity="critical") critical_total FROM admin_agent_slo_snapshots WHERE policy_id=? AND generated_at>=? AND generated_at<?',[(int)$policy['id'],$periodStart,$periodEnd]);
            $incidents=(int)(mg_admin_agent_safe_row($pdo,'SELECT COUNT(*) total FROM admin_agent_incident_workspaces WHERE service_id=? AND started_at>=? AND started_at<?',[(int)$service['id'],$periodStart,$periodEnd])['total']??0);
            $availability=(float)($stats['availability_avg']??100.0); $budget=(float)($stats['budget_avg']??100.0); $warning=(int)($stats['warning_total']??0); $critical=(int)($stats['critical_total']??0);
            $score=max(0,min(100,(int)round(min($availability,$budget)-($warning*1.5)-($critical*6)-($incidents*5))));
            $status=$score>=90?'healthy':($score>=75?'watch':($score>=50?'attention':'critical'));
            if(in_array($status,['attention','critical'],true)) $attention++;
            $key=hash('sha256',(string)$service['service_key'].'|'.$days.'|'.$periodEnd);
            $pdo->prepare('INSERT INTO admin_agent_reliability_scorecards (public_id,scorecard_key,service_id,period_days,period_start,period_end,objective_percent,availability_percent,error_budget_remaining_percent,warning_snapshot_total,critical_snapshot_total,incident_total,reliability_score,status,evidence_json,generated_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE availability_percent=VALUES(availability_percent),error_budget_remaining_percent=VALUES(error_budget_remaining_percent),warning_snapshot_total=VALUES(warning_snapshot_total),critical_snapshot_total=VALUES(critical_snapshot_total),incident_total=VALUES(incident_total),reliability_score=VALUES(reliability_score),status=VALUES(status),evidence_json=VALUES(evidence_json),generated_at=NOW(),updated_at=NOW()')->execute([
                mg_public_id(),$key,(int)$service['id'],$days,$periodStart,$periodEnd,(float)$policy['objective_percent'],$availability,$budget,$warning,$critical,$incidents,$score,$status,json_encode(['snapshot_total'=>(int)($stats['total']??0),'service_key'=>$service['service_key'],'domain'=>$service['domain']],JSON_UNESCAPED_SLASHES),
            ]);
            $generated++;
        }
    }
    return ['generated'=>$generated,'attention'=>$attention];
}

function mg_admin_agent_phase4_scorecards(PDO $pdo,int $limit=100): array
{
    $limit=max(10,min(200,$limit));
    $rows=mg_admin_agent_safe_rows($pdo,'SELECT c.public_id,c.period_days,c.period_start,c.period_end,c.objective_percent,c.availability_percent,c.error_budget_remaining_percent,c.warning_snapshot_total,c.critical_snapshot_total,c.incident_total,c.reliability_score,c.status,c.evidence_json,c.generated_at,s.public_id service_id,s.service_key,s.label service_label,s.domain,s.tier FROM admin_agent_reliability_scorecards c JOIN admin_agent_services s ON s.id=c.service_id WHERE c.id IN (SELECT MAX(c2.id) FROM admin_agent_reliability_scorecards c2 GROUP BY c2.service_id,c2.period_days) ORDER BY CASE c.status WHEN "critical" THEN 1 WHEN "attention" THEN 2 WHEN "watch" THEN 3 ELSE 4 END,c.period_days,s.label LIMIT '.$limit);
    return array_map(static fn(array $r):array=>['id'=>(string)$r['public_id'],'period_days'=>(int)$r['period_days'],'period_start'=>(string)$r['period_start'],'period_end'=>(string)$r['period_end'],'objective_percent'=>(float)$r['objective_percent'],'availability_percent'=>(float)$r['availability_percent'],'error_budget_remaining_percent'=>(float)$r['error_budget_remaining_percent'],'warning_snapshot_total'=>(int)$r['warning_snapshot_total'],'critical_snapshot_total'=>(int)$r['critical_snapshot_total'],'incident_total'=>(int)$r['incident_total'],'reliability_score'=>(int)$r['reliability_score'],'status'=>(string)$r['status'],'evidence'=>mg_admin_agent_json($r['evidence_json']??null),'generated_at'=>(string)$r['generated_at'],'service'=>['id'=>(string)$r['service_id'],'service_key'=>(string)$r['service_key'],'label'=>(string)$r['service_label'],'domain'=>(string)$r['domain'],'tier'=>(string)$r['tier']]],$rows);
}

function mg_admin_agent_phase4_generate_capacity_forecasts(PDO $pdo): array
{
    $series=mg_admin_agent_safe_rows($pdo,'SELECT monitor_key,domain,metric_key,COUNT(*) sample_total,MIN(occurred_at) first_at,MAX(occurred_at) last_at FROM admin_agent_metric_samples WHERE occurred_at>=DATE_SUB(NOW(),INTERVAL 14 DAY) GROUP BY monitor_key,domain,metric_key HAVING sample_total>=2 ORDER BY sample_total DESC LIMIT 60');
    $generated=0; $risk=0;
    foreach($series as $item){
        $params=[(string)$item['monitor_key'],(string)$item['domain'],(string)$item['metric_key']];
        $first=mg_admin_agent_safe_row($pdo,'SELECT metric_value,occurred_at FROM admin_agent_metric_samples WHERE monitor_key=? AND domain=? AND metric_key=? AND occurred_at>=DATE_SUB(NOW(),INTERVAL 14 DAY) ORDER BY occurred_at,id LIMIT 1',$params);
        $latest=mg_admin_agent_safe_row($pdo,'SELECT metric_value,occurred_at FROM admin_agent_metric_samples WHERE monitor_key=? AND domain=? AND metric_key=? ORDER BY occurred_at DESC,id DESC LIMIT 1',$params);
        if($first===[]||$latest===[]) continue;
        $days=max(1.0,(strtotime((string)$latest['occurred_at'].' UTC')-strtotime((string)$first['occurred_at'].' UTC'))/86400.0);
        $current=(float)$latest['metric_value']; $trend=($current-(float)$first['metric_value'])/$days;
        $pred7=max(0.0,$current+($trend*7)); $pred30=max(0.0,$current+($trend*30));
        $baseline=mg_admin_agent_safe_row($pdo,'SELECT mean_value,stddev_value,max_value FROM admin_agent_metric_baselines WHERE monitor_key=? AND domain=? AND metric_key=? ORDER BY id DESC LIMIT 1',$params);
        $capacity=max(1.0,(float)($baseline['max_value']??$current)*1.5,(float)($baseline['mean_value']??$current)+(3*(float)($baseline['stddev_value']??0)));
        $utilization=($pred30/$capacity)*100.0;
        $activeAnomaly=(int)(mg_admin_agent_safe_row($pdo,'SELECT COUNT(*) total FROM admin_agent_anomalies WHERE monitor_key=? AND domain=? AND metric_key=? AND status IN ("open","acknowledged","under_review") AND severity IN ("high","critical")',$params)['total']??0);
        $level=$activeAnomaly>0||$utilization>=100?'critical':($utilization>=85?'high':($utilization>=70?'medium':'low'));
        if(in_array($level,['high','critical'],true)) $risk++;
        $key=hash('sha256',implode('|',$params).'|'.gmdate('Y-m-d'));
        $pdo->prepare('INSERT INTO admin_agent_capacity_forecasts (public_id,forecast_key,domain,metric_key,current_value,trend_per_day,predicted_7d,predicted_30d,capacity_limit,utilization_percent,risk_level,evidence_json,generated_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE current_value=VALUES(current_value),trend_per_day=VALUES(trend_per_day),predicted_7d=VALUES(predicted_7d),predicted_30d=VALUES(predicted_30d),capacity_limit=VALUES(capacity_limit),utilization_percent=VALUES(utilization_percent),risk_level=VALUES(risk_level),evidence_json=VALUES(evidence_json),generated_at=NOW(),updated_at=NOW()')->execute([
            mg_public_id(),$key,(string)$item['domain'],(string)$item['monitor_key'].'.'.(string)$item['metric_key'],$current,$trend,$pred7,$pred30,$capacity,$utilization,$level,json_encode(['monitor_key'=>$item['monitor_key'],'metric_key'=>$item['metric_key'],'sample_total'=>(int)$item['sample_total'],'first_value'=>(float)$first['metric_value'],'first_at'=>$first['occurred_at'],'latest_at'=>$latest['occurred_at'],'active_high_anomaly'=>$activeAnomaly>0],JSON_UNESCAPED_SLASHES),
        ]);
        $generated++;
    }
    return ['generated'=>$generated,'high_or_critical'=>$risk];
}

function mg_admin_agent_phase4_capacity_forecasts(PDO $pdo,int $limit=80): array
{
    $limit=max(10,min(200,$limit));
    $rows=mg_admin_agent_safe_rows($pdo,'SELECT public_id,domain,metric_key,current_value,trend_per_day,predicted_7d,predicted_30d,capacity_limit,utilization_percent,risk_level,evidence_json,generated_at FROM admin_agent_capacity_forecasts WHERE id IN (SELECT MAX(f2.id) FROM admin_agent_capacity_forecasts f2 GROUP BY f2.domain,f2.metric_key) ORDER BY CASE risk_level WHEN "critical" THEN 1 WHEN "high" THEN 2 WHEN "medium" THEN 3 ELSE 4 END,utilization_percent DESC,generated_at DESC LIMIT '.$limit);
    return array_map(static fn(array $r):array=>['id'=>(string)$r['public_id'],'domain'=>(string)$r['domain'],'metric_key'=>(string)$r['metric_key'],'current_value'=>(float)$r['current_value'],'trend_per_day'=>(float)$r['trend_per_day'],'predicted_7d'=>(float)$r['predicted_7d'],'predicted_30d'=>(float)$r['predicted_30d'],'capacity_limit'=>$r['capacity_limit']!==null?(float)$r['capacity_limit']:null,'utilization_percent'=>$r['utilization_percent']!==null?(float)$r['utilization_percent']:null,'risk_level'=>(string)$r['risk_level'],'evidence'=>mg_admin_agent_json($r['evidence_json']??null),'generated_at'=>(string)$r['generated_at']],$rows);
}

function mg_admin_agent_phase4_generate_learning(PDO $pdo): array
{
    $workspaces=mg_admin_agent_safe_rows($pdo,'SELECT w.id,w.public_id,w.title,w.summary,w.severity,w.status,w.started_at,w.resolved_at,w.runbook_key,w.recommended_action_key,w.ops_incident_id,o.public_id ops_public,o.status ops_status,o.resolved_at ops_resolved_at FROM admin_agent_incident_workspaces w LEFT JOIN admin_ops_incidents o ON o.id=w.ops_incident_id WHERE w.status IN ("resolved","dismissed") OR o.status="resolved" ORDER BY COALESCE(w.resolved_at,o.resolved_at,w.updated_at) DESC LIMIT 100');
    $generated=0; $followups=0;
    foreach($workspaces as $workspace){
        $timeline=mg_admin_agent_safe_rows($pdo,'SELECT event_type,title,message,occurred_at,evidence_json FROM admin_agent_incident_timeline WHERE workspace_id=? ORDER BY occurred_at,id',[(int)$workspace['id']]);
        $causes=mg_admin_agent_safe_rows($pdo,'SELECT cause_type,title,explanation,confidence_percent,evidence_json FROM admin_agent_cause_candidates WHERE workspace_id=? ORDER BY rank_order,confidence_percent DESC LIMIT 8',[(int)$workspace['id']]);
        $top=$causes[0]??null;
        $durationEnd=(string)($workspace['resolved_at']??$workspace['ops_resolved_at']??gmdate('Y-m-d H:i:s'));
        $duration=max(0,(int)round((strtotime($durationEnd.' UTC')-strtotime((string)$workspace['started_at'].' UTC'))/60));
        $summary='Incident workspace "'.(string)$workspace['title'].'" recorded '.count($timeline).' timeline event(s) across approximately '.$duration.' minute(s).';
        $impact=(string)$workspace['summary'];
        $root=$top!==null?((string)$top['title'].'. '.(string)$top['explanation'].' This remains an evidence-ranked hypothesis until administrator review.'):'No single cause candidate reached the evidence threshold. Administrator review is required.';
        $factors=array_map(static fn(array $c):array=>['type'=>(string)$c['cause_type'],'title'=>(string)$c['title'],'confidence_percent'=>(float)$c['confidence_percent'],'evidence'=>mg_admin_agent_json($c['evidence_json']??null)],$causes);
        $actions=[];
        if((string)($workspace['runbook_key']??'')!=='') $actions[]='Review and update runbook '.(string)$workspace['runbook_key'].'.';
        if((string)($workspace['recommended_action_key']??'')!=='') $actions[]='Validate preventive control '.str_replace('_',' ',(string)$workspace['recommended_action_key']).'.';
        if($top!==null) $actions[]='Add a regression check for the highest-ranked cause candidate: '.(string)$top['title'].'.';
        if($actions===[]) $actions[]='Document the verified cause and assign one measurable prevention action.';
        $review=[];
        if($workspace['ops_incident_id']!==null) $review=mg_admin_agent_safe_row($pdo,'SELECT id,public_id,status FROM admin_ops_incident_reviews WHERE incident_id=? LIMIT 1',[(int)$workspace['ops_incident_id']]);
        $status=$review!==[]&&in_array((string)$review['status'],['completed','followup_complete'],true)?'completed':($workspace['ops_incident_id']!==null?'review_ready':'draft');
        $key=hash('sha256','workspace|'.(string)$workspace['public_id']);
        $pdo->prepare('INSERT INTO admin_agent_incident_learning (public_id,learning_key,workspace_id,ops_incident_id,review_id,status,summary_text,impact_text,root_cause_hypothesis,contributing_factors_json,prevention_actions_json,evidence_json,completed_at,generated_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,IF(?="completed",NOW(),NULL),NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE ops_incident_id=VALUES(ops_incident_id),review_id=VALUES(review_id),status=IF(status IN ("completed","dismissed"),status,VALUES(status)),summary_text=VALUES(summary_text),impact_text=VALUES(impact_text),root_cause_hypothesis=VALUES(root_cause_hypothesis),contributing_factors_json=VALUES(contributing_factors_json),prevention_actions_json=VALUES(prevention_actions_json),evidence_json=VALUES(evidence_json),completed_at=IF(VALUES(status)="completed",COALESCE(completed_at,NOW()),completed_at),generated_at=NOW(),updated_at=NOW()')->execute([
            mg_public_id(),$key,(int)$workspace['id'],$workspace['ops_incident_id']!==null?(int)$workspace['ops_incident_id']:null,$review!==[]?(int)$review['id']:null,$status,$summary,$impact,$root,json_encode($factors,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),json_encode($actions,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),json_encode(['workspace_status'=>$workspace['status'],'ops_incident_status'=>$workspace['ops_status'],'timeline_total'=>count($timeline),'duration_minutes'=>$duration],JSON_UNESCAPED_SLASHES),$status,
        ]);
        $learning=mg_admin_agent_safe_row($pdo,'SELECT id,public_id FROM admin_agent_incident_learning WHERE learning_key=? LIMIT 1',[$key]);
        foreach($actions as $index=>$action){
            $followupKey=hash('sha256',(string)$learning['public_id'].'|'.$index.'|'.$action);
            $pdo->prepare('INSERT INTO admin_agent_prevention_followups (public_id,followup_key,learning_id,review_id,title,description,priority,status,evidence_json,created_at,updated_at) VALUES (?,?,?,?,?,?,? ,"proposed",?,NOW(),NOW()) ON DUPLICATE KEY UPDATE review_id=VALUES(review_id),title=VALUES(title),description=VALUES(description),evidence_json=VALUES(evidence_json),updated_at=NOW()')->execute([
                mg_public_id(),$followupKey,(int)$learning['id'],$review!==[]?(int)$review['id']:null,mb_substr($action,0,240),$action,(string)$workspace['severity']==='critical'?'high':'medium',json_encode(['workspace_id'=>$workspace['public_id'],'source'=>'deterministic_incident_learning'],JSON_UNESCAPED_SLASHES),
            ]);
            $followups++;
        }
        $generated++;
    }
    return ['learning_records'=>$generated,'followup_proposals'=>$followups];
}

function mg_admin_agent_phase4_learning(PDO $pdo,int $limit=50): array
{
    $limit=max(5,min(100,$limit));
    $rows=mg_admin_agent_safe_rows($pdo,'SELECT l.public_id,l.status,l.summary_text,l.impact_text,l.root_cause_hypothesis,l.contributing_factors_json,l.prevention_actions_json,l.evidence_json,l.generated_at,l.completed_at,w.public_id workspace_id,w.title workspace_title,w.severity,w.status workspace_status,o.public_id ops_incident_id,r.public_id review_id,r.status review_status FROM admin_agent_incident_learning l JOIN admin_agent_incident_workspaces w ON w.id=l.workspace_id LEFT JOIN admin_ops_incidents o ON o.id=l.ops_incident_id LEFT JOIN admin_ops_incident_reviews r ON r.id=l.review_id ORDER BY CASE l.status WHEN "review_ready" THEN 1 WHEN "draft" THEN 2 ELSE 3 END,l.generated_at DESC LIMIT '.$limit);
    $result=[];
    foreach($rows as $row){
        $followups=mg_admin_agent_safe_rows($pdo,'SELECT public_id,title,description,priority,status,owner_user_id,due_at,approved_at,completed_at,evidence_json FROM admin_agent_prevention_followups WHERE learning_id=(SELECT id FROM admin_agent_incident_learning WHERE public_id=? LIMIT 1) ORDER BY CASE status WHEN "proposed" THEN 1 WHEN "approved" THEN 2 WHEN "in_progress" THEN 3 ELSE 4 END,priority DESC,due_at',[(string)$row['public_id']]);
        $result[]=['id'=>(string)$row['public_id'],'status'=>(string)$row['status'],'summary_text'=>(string)$row['summary_text'],'impact_text'=>(string)$row['impact_text'],'root_cause_hypothesis'=>(string)$row['root_cause_hypothesis'],'contributing_factors'=>mg_admin_agent_json($row['contributing_factors_json']??null),'prevention_actions'=>mg_admin_agent_json($row['prevention_actions_json']??null),'evidence'=>mg_admin_agent_json($row['evidence_json']??null),'generated_at'=>(string)$row['generated_at'],'completed_at'=>$row['completed_at'],'workspace'=>['id'=>(string)$row['workspace_id'],'title'=>(string)$row['workspace_title'],'severity'=>(string)$row['severity'],'status'=>(string)$row['workspace_status']],'ops_incident_id'=>$row['ops_incident_id'],'review'=>$row['review_id']?['id'=>(string)$row['review_id'],'status'=>(string)$row['review_status']]:null,'followups'=>array_map(static fn(array $f):array=>['id'=>(string)$f['public_id'],'title'=>(string)$f['title'],'description'=>(string)$f['description'],'priority'=>(string)$f['priority'],'status'=>(string)$f['status'],'owner_user_id'=>$f['owner_user_id']!==null?(int)$f['owner_user_id']:null,'due_at'=>$f['due_at'],'approved_at'=>$f['approved_at'],'completed_at'=>$f['completed_at'],'evidence'=>mg_admin_agent_json($f['evidence_json']??null)],$followups)];
    }
    return $result;
}

function mg_admin_agent_phase4_learning_action(PDO $pdo,int $actorId,array $input): array
{
    $learningPublic=trim((string)($input['learning_id']??'')); $action=strtolower(trim((string)($input['learning_action']??''))); $note=mb_substr(trim((string)($input['note']??'')),0,1000);
    $learning=mg_admin_agent_safe_row($pdo,'SELECT id,public_id,status FROM admin_agent_incident_learning WHERE public_id=? LIMIT 1',[$learningPublic]);
    if($learning===[]) throw new InvalidArgumentException('Incident learning record not found.');
    if($action==='mark_review_ready'){
        $pdo->prepare('UPDATE admin_agent_incident_learning SET status="review_ready",updated_at=NOW() WHERE id=? AND status="draft"')->execute([(int)$learning['id']]);
    }elseif($action==='complete'){
        if($note==='') throw new InvalidArgumentException('A completion note is required.');
        $pdo->prepare('UPDATE admin_agent_incident_learning SET status="completed",completed_by_user_id=?,completed_at=NOW(),evidence_json=JSON_SET(COALESCE(evidence_json,JSON_OBJECT()),"$.completion_note",?),updated_at=NOW() WHERE id=?')->execute([$actorId,$note,(int)$learning['id']]);
    }elseif($action==='dismiss'){
        if($note==='') throw new InvalidArgumentException('A dismissal note is required.');
        $pdo->prepare('UPDATE admin_agent_incident_learning SET status="dismissed",evidence_json=JSON_SET(COALESCE(evidence_json,JSON_OBJECT()),"$.dismissal_note",?),updated_at=NOW() WHERE id=?')->execute([$note,(int)$learning['id']]);
    }else throw new InvalidArgumentException('Unknown incident learning action.');
    mg_audit('admin_agent_phase4_learning_action','system',['learning_id'=>$learningPublic,'action'=>$action],$actorId);
    return ['learning_id'=>$learningPublic,'action'=>$action,'updated'=>true];
}

function mg_admin_agent_phase4_run(PDO $pdo,array $options=[]): array
{
    if(!mg_admin_agent_phase4_ready($pdo)) throw new RuntimeException('Main Admin Agent Phase 4 SQL migration is required.');
    $actorId=isset($options['initiated_by_user_id'])&&(int)$options['initiated_by_user_id']>0?(int)$options['initiated_by_user_id']:null;
    $phase3=mg_admin_agent_phase3_run($pdo,$options);
    $maintenance=mg_admin_agent_phase4_sync_maintenance($pdo);
    $changeRisk=mg_admin_agent_phase4_evaluate_change($pdo,(string)($options['environment_key']??'production'),$actorId,(array)($options['change_context']??[]));
    $scorecards=mg_admin_agent_phase4_generate_scorecards($pdo);
    $capacity=mg_admin_agent_phase4_generate_capacity_forecasts($pdo);
    $learning=mg_admin_agent_phase4_generate_learning($pdo);
    mg_audit('admin_agent_phase4_completed','system',['scan_id'=>$phase3['phase2']['scan']['id']??null,'maintenance'=>$maintenance,'change_risk'=>$changeRisk,'scorecards'=>$scorecards,'capacity'=>$capacity,'learning'=>$learning],$actorId);
    return ['phase3'=>$phase3,'maintenance'=>$maintenance,'change_risk'=>$changeRisk,'reliability_scorecards'=>$scorecards,'capacity_forecasts'=>$capacity,'incident_learning'=>$learning,'database_only'=>true,'used_ai'=>false,'credits_used'=>0];
}

function mg_admin_agent_phase4_chat_mode(string $message): ?string
{
    $text=strtolower(trim($message));
    if(str_contains($text,'maintenance window')||str_contains($text,'planned maintenance')) return 'maintenance';
    if(str_contains($text,'change risk')||str_contains($text,'deployment risk')||str_contains($text,'release risk')) return 'change_risk';
    if(str_contains($text,'reliability score')||str_contains($text,'historical slo')||str_contains($text,'reliability trend')) return 'reliability';
    if(str_contains($text,'capacity forecast')||str_contains($text,'capacity risk')||str_contains($text,'operating forecast')) return 'capacity';
    if(str_contains($text,'postmortem')||str_contains($text,'incident learning')||str_contains($text,'lessons learned')) return 'learning';
    if(str_contains($text,'prevention follow')||str_contains($text,'prevention action')) return 'prevention';
    return null;
}

function mg_admin_agent_phase4_report(PDO $pdo,string $mode): array
{
    $maintenance=mg_admin_agent_phase4_maintenance_windows($pdo); $risks=mg_admin_agent_phase4_change_risks($pdo); $scorecards=mg_admin_agent_phase4_scorecards($pdo); $capacity=mg_admin_agent_phase4_capacity_forecasts($pdo); $learning=mg_admin_agent_phase4_learning($pdo);
    $title='Main Admin Agent Phase 4 reliability governance'; $lines=[]; $blocks=[];
    if($mode==='maintenance'){ $active=array_values(array_filter($maintenance,static fn(array $w):bool=>in_array($w['status'],['active','scheduled'],true))); $title='Maintenance-window governance'; $lines[]=count($active).' active or scheduled maintenance window(s). Security and critical findings remain visible during every window.'; $blocks[]=['type'=>'maintenance_windows','items'=>$maintenance]; }
    elseif($mode==='change_risk'){ $title='Deployment change-risk assessment'; if($risks===[]) $risks[] = mg_admin_agent_phase4_evaluate_change($pdo); $lines[]='Current deployment risk: '.strtoupper($risks[0]['risk_level']).' at '.$risks[0]['risk_score'].'/100.'; $blocks[]=['type'=>'change_risks','items'=>$risks]; }
    elseif($mode==='reliability'){ $title='Historical reliability scorecards'; $attention=array_values(array_filter($scorecards,static fn(array $s):bool=>in_array($s['status'],['attention','critical'],true))); $lines[]=count($scorecards).' 7/30/90-day scorecards are available; '.count($attention).' need attention.'; $blocks[]=['type'=>'reliability_scorecards','items'=>$scorecards]; }
    elseif($mode==='capacity'){ $title='Deterministic capacity-risk forecast'; $high=array_values(array_filter($capacity,static fn(array $f):bool=>in_array($f['risk_level'],['high','critical'],true))); $lines[]=count($high).' metric forecast(s) show high or critical thirty-day capacity risk.'; $blocks[]=['type'=>'capacity_forecasts','items'=>$capacity]; }
    elseif($mode==='learning'){ $title='Incident learning and postmortem drafts'; $ready=array_values(array_filter($learning,static fn(array $l):bool=>$l['status']==='review_ready')); $lines[]=count($ready).' incident-learning draft(s) are ready for administrator review. Root-cause statements remain hypotheses until verified.'; $blocks[]=['type'=>'incident_learning','items'=>$learning]; }
    else { $title='Prevention follow-up queue'; $items=[]; foreach($learning as $record) foreach($record['followups'] as $followup) $items[]=$followup+['learning_id'=>$record['id'],'workspace'=>$record['workspace']]; $proposed=array_values(array_filter($items,static fn(array $f):bool=>$f['status']==='proposed')); $lines[]=count($proposed).' prevention follow-up proposal(s) require review before creation in the incident-review workflow.'; $blocks[]=['type'=>'prevention_followups','items'=>$items]; }
    return ['title'=>$title,'content'=>implode("\n",$lines),'blocks'=>$blocks,'metadata'=>['mode'=>$mode,'database_only'=>true,'used_ai'=>false,'credits_used'=>0,'root_causes_are_hypotheses'=>true,'generated_at'=>gmdate('Y-m-d H:i:s')]];
}

function mg_admin_agent_phase4_send(PDO $pdo,int $adminId,array $input): array
{
    $message=mb_substr(trim((string)($input['message']??'')),0,4000); if($message==='') throw new InvalidArgumentException('Enter a message for the Main Admin Agent.');
    $mode=mg_admin_agent_phase4_chat_mode($message); if($mode===null) return mg_admin_agent_phase3_send($pdo,$adminId,$input);
    $thread=mg_admin_agent_thread($pdo,$adminId,isset($input['thread_id'])?(string)$input['thread_id']:null);
    $userMessage=mg_admin_agent_record_message($pdo,(int)$thread['id'],$adminId,'user',$message,'chat',[],['database_only'=>true]);
    $report=mg_admin_agent_phase4_report($pdo,$mode);
    $assistant=mg_admin_agent_record_message($pdo,(int)$thread['id'],$adminId,'assistant',$report['content'],'system_report',$report['blocks'],$report['metadata']+['title'=>$report['title']]);
    mg_audit('admin_agent_phase4_chat_report','system',['thread_id'=>$thread['public_id'],'mode'=>$mode,'database_only'=>true],$adminId);
    return ['thread'=>['id'=>(string)$thread['public_id'],'title'=>(string)$thread['title']],'user_message'=>$userMessage,'assistant_message'=>$assistant,'report'=>$report];
}

function mg_admin_agent_phase4_state(PDO $pdo,int $adminId,array $options=[]): array
{
    $state=mg_admin_agent_phase3_state($pdo,$adminId,$options); $schema=mg_admin_agent_phase4_schema_state($pdo); $state['phase4_schema']=$schema; $state['phase4_ready']=$schema['ready'];
    if(!$schema['ready']) return $state;
    $state['maintenance_windows']=mg_admin_agent_phase4_maintenance_windows($pdo);
    $state['change_risks']=mg_admin_agent_phase4_change_risks($pdo);
    $state['reliability_scorecards']=mg_admin_agent_phase4_scorecards($pdo);
    $state['capacity_forecasts']=mg_admin_agent_phase4_capacity_forecasts($pdo);
    $state['incident_learning']=mg_admin_agent_phase4_learning($pdo);
    $state['phase4_systematic']=['database_only'=>true,'used_ai'=>false,'credits_used'=>0,'maintenance_security_suppression'=>false,'root_causes_are_hypotheses'=>true,'prevention_followups'=>'review_approved_typed_confirmation'];
    return $state;
}
