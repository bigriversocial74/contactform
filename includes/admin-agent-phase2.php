<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-agent-runtime.php';
require_once dirname(__DIR__) . '/api/admin/_queue_alerts.php';

const MG_ADMIN_AGENT_PHASE2_MIGRATION = 'database/20260718_main_admin_agent_phase2.sql';

function mg_admin_agent_phase2_tables(): array
{
    return [
        'admin_agent_metric_samples','admin_agent_metric_baselines','admin_agent_anomalies',
        'admin_agent_deployments','admin_agent_runbooks','admin_agent_correlations',
        'admin_agent_escalation_policies','admin_agent_escalations','admin_agent_executive_summaries',
        'admin_agent_remediation_adapters','admin_agent_remediation_executions',
    ];
}

function mg_admin_agent_phase2_schema_state(PDO $pdo): array
{
    $missing = [];
    foreach (mg_admin_agent_phase2_tables() as $table) {
        if (!mg_admin_schema_has_table($pdo, $table)) $missing[] = $table;
    }
    return ['ready'=>$missing === [],'missing_tables'=>$missing,'migration'=>MG_ADMIN_AGENT_PHASE2_MIGRATION];
}

function mg_admin_agent_phase2_ready(PDO $pdo): bool
{
    return mg_admin_agent_phase2_schema_state($pdo)['ready'];
}

function mg_admin_agent_phase2_severity_rank(string $severity): int
{
    return ['low'=>1,'medium'=>2,'high'=>3,'critical'=>4][strtolower($severity)] ?? 0;
}

function mg_admin_agent_phase2_event_severity(string $severity): string
{
    return match (strtolower($severity)) {
        'critical' => 'critical',
        'high' => 'error',
        'medium' => 'warning',
        default => 'info',
    };
}

function mg_admin_agent_phase2_flatten_numeric(mixed $value, string $prefix = '', int $depth = 0): array
{
    if ($depth > 3) return [];
    if (is_bool($value) || $value === null) return [];
    if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
        return $prefix === '' ? [] : [$prefix=>(float)$value];
    }
    if (!is_array($value)) return [];
    $result = [];
    foreach ($value as $key=>$child) {
        $clean = preg_replace('/[^a-zA-Z0-9_]+/', '_', (string)$key) ?: 'metric';
        $path = $prefix === '' ? $clean : $prefix . '.' . $clean;
        foreach (mg_admin_agent_phase2_flatten_numeric($child, $path, $depth + 1) as $metric=>$number) {
            $result[$metric] = $number;
            if (count($result) >= 150) break 2;
        }
    }
    return $result;
}

function mg_admin_agent_phase2_metric_ignored(string $metricKey): bool
{
    $leaf = strtolower((string)substr(strrchr('.' . $metricKey, '.'), 1));
    return in_array($leaf, ['available','supported','failed','score'], true)
        || str_contains($leaf, 'timestamp')
        || str_contains($leaf, '_at');
}

function mg_admin_agent_phase2_anomaly_severity(?float $zScore, ?float $ratio): string
{
    $value = max((float)($zScore ?? 0), (float)($ratio ?? 0));
    if ($value >= 6.0) return 'critical';
    if ($value >= 4.0) return 'high';
    if ($value >= 3.0) return 'medium';
    return 'low';
}

function mg_admin_agent_phase2_upsert_anomaly(PDO $pdo, array $data): array
{
    $key = hash('sha256', (string)$data['monitor_key'] . '|' . (string)$data['metric_key'] . '|' . (string)$data['dimension_key']);
    $public = mg_public_id();
    $existing = mg_admin_agent_safe_row($pdo, 'SELECT id,public_id,status FROM admin_agent_anomalies WHERE anomaly_key=? LIMIT 1', [$key]);
    $wasResolved = in_array((string)($existing['status'] ?? ''), ['resolved','dismissed'], true);
    $stmt = $pdo->prepare('INSERT INTO admin_agent_anomalies
        (public_id,anomaly_key,baseline_id,monitor_key,domain,metric_key,dimension_key,severity,status,observed_value,baseline_mean,baseline_stddev,z_score,deviation_ratio,threshold_value,evidence_json,first_detected_at,last_detected_at,occurrence_count,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,? ,"open",?,?,?,?,?,?,?,NOW(),NOW(),1,NOW(),NOW())
        ON DUPLICATE KEY UPDATE
        severity=VALUES(severity),observed_value=VALUES(observed_value),baseline_mean=VALUES(baseline_mean),baseline_stddev=VALUES(baseline_stddev),
        z_score=VALUES(z_score),deviation_ratio=VALUES(deviation_ratio),threshold_value=VALUES(threshold_value),evidence_json=VALUES(evidence_json),
        recurrence_count=recurrence_count+IF(status IN ("resolved","dismissed"),1,0),
        resolved_by_user_id=IF(status IN ("resolved","dismissed"),NULL,resolved_by_user_id),resolved_at=IF(status IN ("resolved","dismissed"),NULL,resolved_at),
        resolution_note=IF(status IN ("resolved","dismissed"),NULL,resolution_note),status=IF(status IN ("resolved","dismissed"),"open",status),
        last_detected_at=NOW(),occurrence_count=occurrence_count+1,updated_at=NOW()');
    $stmt->execute([
        $public,$key,$data['baseline_id'],$data['monitor_key'],$data['domain'],$data['metric_key'],$data['dimension_key'],$data['severity'],
        $data['observed_value'],$data['baseline_mean'],$data['baseline_stddev'],$data['z_score'],$data['deviation_ratio'],$data['threshold'],
        json_encode($data['evidence'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
    $row = mg_admin_agent_safe_row($pdo, 'SELECT id,public_id,status,severity FROM admin_agent_anomalies WHERE anomaly_key=? LIMIT 1', [$key]);
    return ['id'=>(int)$row['id'],'public_id'=>(string)$row['public_id'],'key'=>$key,'created'=>$existing === [],'reopened'=>$wasResolved,'severity'=>(string)$row['severity']];
}

function mg_admin_agent_phase2_resolve_anomaly(PDO $pdo, string $monitorKey, string $metricKey, string $dimensionKey): bool
{
    $key = hash('sha256', $monitorKey . '|' . $metricKey . '|' . $dimensionKey);
    $stmt = $pdo->prepare('UPDATE admin_agent_anomalies SET status="resolved",resolved_at=NOW(),resolution_note="Metric returned within its learned baseline.",updated_at=NOW() WHERE anomaly_key=? AND status IN ("open","acknowledged","under_review")');
    $stmt->execute([$key]);
    return $stmt->rowCount() > 0;
}

function mg_admin_agent_phase2_capture_metrics(PDO $pdo, string $scanPublicId): array
{
    $scan = mg_admin_agent_safe_row($pdo, 'SELECT id,metrics_json,completed_at FROM admin_agent_scans WHERE public_id=? LIMIT 1', [$scanPublicId]);
    if ($scan === []) return ['samples'=>0,'anomalies_detected'=>0,'anomalies_resolved'=>0];
    $metrics = mg_admin_agent_json($scan['metrics_json'] ?? null);
    $domains = [];
    foreach (mg_admin_agent_safe_rows($pdo, 'SELECT monitor_key,domain FROM admin_agent_monitors') as $monitor) {
        $domains[(string)$monitor['monitor_key']] = (string)$monitor['domain'];
    }
    $samples = $detected = $resolved = 0;
    foreach ($metrics as $monitorKey=>$monitorMetrics) {
        if (!is_array($monitorMetrics)) continue;
        foreach (mg_admin_agent_phase2_flatten_numeric($monitorMetrics) as $metricKey=>$value) {
            if (mg_admin_agent_phase2_metric_ignored($metricKey)) continue;
            $dimensionKey = hash('sha256', 'global');
            $domain = $domains[(string)$monitorKey] ?? 'system';
            $pdo->beginTransaction();
            try {
                $baseline = mg_admin_agent_safe_row($pdo, 'SELECT * FROM admin_agent_metric_baselines WHERE monitor_key=? AND metric_key=? AND dimension_key=? LIMIT 1 FOR UPDATE', [(string)$monitorKey,$metricKey,$dimensionKey]);
                if ($baseline === []) {
                    $public = mg_public_id();
                    $pdo->prepare('INSERT INTO admin_agent_metric_baselines (public_id,monitor_key,domain,metric_key,dimension_key,dimensions_json,sample_count,mean_value,m2_value,variance_value,stddev_value,min_value,max_value,latest_value,last_sample_at,created_at,updated_at) VALUES (?,?,?,?,?,JSON_OBJECT(),1,?,0,0,0,?,?,?,NOW(),NOW(),NOW())')->execute([$public,(string)$monitorKey,$domain,$metricKey,$dimensionKey,$value,$value,$value,$value]);
                    $baselineId = (int)$pdo->lastInsertId();
                    $previousCount = 0;
                    $previousMean = $value;
                    $previousStddev = 0.0;
                } else {
                    $baselineId = (int)$baseline['id'];
                    $previousCount = (int)$baseline['sample_count'];
                    $previousMean = (float)$baseline['mean_value'];
                    $previousM2 = (float)$baseline['m2_value'];
                    $previousStddev = (float)$baseline['stddev_value'];
                    $newCount = $previousCount + 1;
                    $delta = $value - $previousMean;
                    $newMean = $previousMean + ($delta / $newCount);
                    $delta2 = $value - $newMean;
                    $newM2 = $previousM2 + ($delta * $delta2);
                    $variance = $newCount > 1 ? $newM2 / ($newCount - 1) : 0.0;
                    $stddev = sqrt(max(0.0, $variance));
                    $pdo->prepare('UPDATE admin_agent_metric_baselines SET sample_count=?,mean_value=?,m2_value=?,variance_value=?,stddev_value=?,min_value=LEAST(COALESCE(min_value,?),?),max_value=GREATEST(COALESCE(max_value,?),?),latest_value=?,last_sample_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$newCount,$newMean,$newM2,$variance,$stddev,$value,$value,$value,$value,$value,$baselineId]);
                }
                $pdo->prepare('INSERT INTO admin_agent_metric_samples (public_id,scan_id,monitor_key,domain,metric_key,dimension_key,dimensions_json,metric_value,occurred_at,created_at) VALUES (?,?,?,?,?,?,JSON_OBJECT(),?,COALESCE(?,NOW()),NOW())')->execute([mg_public_id(),(int)$scan['id'],(string)$monitorKey,$domain,$metricKey,$dimensionKey,$value,$scan['completed_at']]);
                $samples++;
                $minimum = (int)($baseline['minimum_samples'] ?? 8);
                $threshold = (float)($baseline['anomaly_threshold'] ?? 3.0);
                $zScore = null;
                $ratio = null;
                $isAnomaly = false;
                if ($previousCount >= $minimum) {
                    $difference = abs($value - $previousMean);
                    if ($previousStddev > 0.000001) {
                        $zScore = $difference / $previousStddev;
                        $isAnomaly = $zScore >= $threshold;
                    } else {
                        $ratio = $difference / max(1.0, abs($previousMean));
                        $isAnomaly = $ratio >= 1.5 && $difference >= 2.0;
                    }
                }
                if ($isAnomaly) {
                    $severity = mg_admin_agent_phase2_anomaly_severity($zScore, $ratio !== null ? $ratio * 3.0 : null);
                    $anomaly = mg_admin_agent_phase2_upsert_anomaly($pdo, [
                        'baseline_id'=>$baselineId,'monitor_key'=>(string)$monitorKey,'domain'=>$domain,'metric_key'=>$metricKey,'dimension_key'=>$dimensionKey,
                        'severity'=>$severity,'observed_value'=>$value,'baseline_mean'=>$previousMean,'baseline_stddev'=>$previousStddev,
                        'z_score'=>$zScore,'deviation_ratio'=>$ratio,'threshold'=>$threshold,
                        'evidence'=>['sample_count'=>$previousCount,'scan_id'=>$scanPublicId,'difference'=>abs($value-$previousMean)],
                    ]);
                    $detected++;
                    mg_admin_agent_ingest_event($pdo, [
                        'monitor_key'=>'anomaly_baselines','domain'=>$domain,'severity'=>mg_admin_agent_phase2_event_severity($severity),
                        'event_type'=>'admin_agent.anomaly.detected','title'=>'Metric anomaly: ' . str_replace('.', ' / ', $metricKey),
                        'message'=>'Observed ' . round($value, 3) . ' against baseline ' . round($previousMean, 3) . '.',
                        'source_table'=>'admin_agent_anomalies','source_id'=>$anomaly['public_id'],'entity_type'=>'admin_agent_anomaly','entity_id'=>$anomaly['public_id'],
                        'evidence'=>['z_score'=>$zScore,'deviation_ratio'=>$ratio,'monitor_key'=>$monitorKey],'occurred_at'=>gmdate('Y-m-d H:i:s'),
                    ]);
                } elseif (mg_admin_agent_phase2_resolve_anomaly($pdo, (string)$monitorKey, $metricKey, $dimensionKey)) {
                    $resolved++;
                }
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
        }
    }
    return ['samples'=>$samples,'anomalies_detected'=>$detected,'anomalies_resolved'=>$resolved];
}

function mg_admin_agent_phase2_anomalies(PDO $pdo, string $status = 'active', int $limit = 100): array
{
    $where = $status === 'active' ? 'status IN ("open","acknowledged","under_review")' : 'status=?';
    $params = $status === 'active' ? [] : [$status];
    $limit = max(10, min(200, $limit));
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT public_id,monitor_key,domain,metric_key,severity,status,observed_value,baseline_mean,baseline_stddev,z_score,deviation_ratio,first_detected_at,last_detected_at,occurrence_count,recurrence_count,resolution_note FROM admin_agent_anomalies WHERE ' . $where . ' ORDER BY CASE severity WHEN "critical" THEN 1 WHEN "high" THEN 2 WHEN "medium" THEN 3 ELSE 4 END,last_detected_at DESC LIMIT ' . $limit, $params);
    return array_map(static fn(array $row): array => [
        'id'=>(string)$row['public_id'],'monitor_key'=>(string)$row['monitor_key'],'domain'=>(string)$row['domain'],'metric_key'=>(string)$row['metric_key'],
        'severity'=>(string)$row['severity'],'status'=>(string)$row['status'],'observed_value'=>(float)$row['observed_value'],'baseline_mean'=>(float)$row['baseline_mean'],
        'baseline_stddev'=>(float)$row['baseline_stddev'],'z_score'=>$row['z_score'] !== null ? (float)$row['z_score'] : null,'deviation_ratio'=>$row['deviation_ratio'] !== null ? (float)$row['deviation_ratio'] : null,
        'first_detected_at'=>(string)$row['first_detected_at'],'last_detected_at'=>(string)$row['last_detected_at'],'occurrence_count'=>(int)$row['occurrence_count'],'recurrence_count'=>(int)$row['recurrence_count'],'resolution_note'=>$row['resolution_note'],
    ], $rows);
}

function mg_admin_agent_phase2_record_deployment(PDO $pdo, ?int $actorId, array $input): array
{
    $commit = strtolower(trim((string)($input['commit_sha'] ?? '')));
    if (!preg_match('/^[a-f0-9]{7,64}$/', $commit)) throw new InvalidArgumentException('Enter a valid deployment commit SHA.');
    $branch = trim((string)($input['branch_name'] ?? 'integration-from-repair-20260628'));
    if ($branch === '' || !preg_match('/^[A-Za-z0-9._\/-]{1,190}$/', $branch)) throw new InvalidArgumentException('Enter a valid deployment branch.');
    $environment = preg_replace('/[^a-z0-9_-]/', '', strtolower((string)($input['environment_key'] ?? 'production'))) ?: 'production';
    $source = strtolower(trim((string)($input['source_type'] ?? 'manual')));
    if (!in_array($source, ['manual','cli','environment','github','release'], true)) $source = 'manual';
    $deployedAt = trim((string)($input['deployed_at'] ?? ''));
    if ($deployedAt === '' || strtotime($deployedAt) === false) $deployedAt = gmdate('Y-m-d H:i:s');
    else $deployedAt = gmdate('Y-m-d H:i:s', (int)strtotime($deployedAt));
    $key = hash('sha256', $environment . '|' . $commit);
    $public = mg_public_id();
    $pdo->prepare('INSERT INTO admin_agent_deployments (public_id,deployment_key,environment_key,branch_name,commit_sha,source_type,release_label,metadata_json,recorded_by_user_id,deployed_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE branch_name=VALUES(branch_name),source_type=VALUES(source_type),release_label=VALUES(release_label),metadata_json=VALUES(metadata_json),recorded_by_user_id=COALESCE(VALUES(recorded_by_user_id),recorded_by_user_id),deployed_at=VALUES(deployed_at)')->execute([
        $public,$key,$environment,$branch,$commit,$source,mb_substr(trim((string)($input['release_label'] ?? '')),0,240) ?: null,
        json_encode(is_array($input['metadata'] ?? null) ? $input['metadata'] : [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),$actorId,$deployedAt,
    ]);
    $row = mg_admin_agent_safe_row($pdo, 'SELECT id,public_id,environment_key,branch_name,commit_sha,source_type,release_label,deployed_at FROM admin_agent_deployments WHERE deployment_key=? LIMIT 1', [$key]);
    mg_admin_agent_ingest_event($pdo, [
        'monitor_key'=>'cross_system_correlation','domain'=>'deployment','severity'=>'info','event_type'=>'admin_agent.deployment.recorded',
        'title'=>'Deployment recorded','message'=>(string)$row['branch_name'] . ' at ' . substr((string)$row['commit_sha'],0,12),
        'source_table'=>'admin_agent_deployments','source_id'=>(string)$row['public_id'],'actor_user_id'=>$actorId,'entity_type'=>'deployment','entity_id'=>(string)$row['public_id'],
        'evidence'=>['environment'=>$row['environment_key'],'source'=>$row['source_type']],'occurred_at'=>(string)$row['deployed_at'],
    ]);
    mg_audit('admin_agent_deployment_recorded', 'system', ['deployment_id'=>$row['public_id'],'commit_sha'=>$commit,'branch'=>$branch,'environment'=>$environment], $actorId);
    return ['id'=>(string)$row['public_id'],'environment_key'=>(string)$row['environment_key'],'branch_name'=>(string)$row['branch_name'],'commit_sha'=>(string)$row['commit_sha'],'source_type'=>(string)$row['source_type'],'release_label'=>$row['release_label'],'deployed_at'=>(string)$row['deployed_at']];
}

function mg_admin_agent_phase2_deployments(PDO $pdo, int $limit = 30): array
{
    $limit = max(5, min(100, $limit));
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT public_id,environment_key,branch_name,commit_sha,source_type,release_label,metadata_json,deployed_at,created_at FROM admin_agent_deployments ORDER BY deployed_at DESC LIMIT ' . $limit);
    return array_map(static fn(array $row): array => ['id'=>(string)$row['public_id'],'environment_key'=>(string)$row['environment_key'],'branch_name'=>(string)$row['branch_name'],'commit_sha'=>(string)$row['commit_sha'],'source_type'=>(string)$row['source_type'],'release_label'=>$row['release_label'],'metadata'=>mg_admin_agent_json($row['metadata_json'] ?? null),'deployed_at'=>(string)$row['deployed_at'],'created_at'=>(string)$row['created_at']], $rows);
}

function mg_admin_agent_phase2_correlation_sources(PDO $pdo): array
{
    $findings = mg_admin_agent_safe_rows($pdo, 'SELECT id,public_id,domain,severity,finding_type,title,summary,first_detected_at,last_detected_at FROM admin_agent_findings WHERE status IN ("open","acknowledged","under_review")');
    $anomalies = mg_admin_agent_safe_rows($pdo, 'SELECT id,public_id,domain,severity,metric_key,first_detected_at,last_detected_at FROM admin_agent_anomalies WHERE status IN ("open","acknowledged","under_review")');
    return ['findings'=>$findings,'anomalies'=>$anomalies];
}

function mg_admin_agent_phase2_filter_rank(array $items, array $domains, int $minimumRank = 3): array
{
    return array_values(array_filter($items, static fn(array $item): bool => in_array((string)$item['domain'], $domains, true) && mg_admin_agent_phase2_severity_rank((string)$item['severity']) >= $minimumRank));
}

function mg_admin_agent_phase2_upsert_correlation(PDO $pdo, array $data): array
{
    $key = hash('sha256', (string)$data['correlation_type'] . '|' . (string)($data['scope_key'] ?? 'global'));
    $existing = mg_admin_agent_safe_row($pdo, 'SELECT id,public_id,status FROM admin_agent_correlations WHERE correlation_key=? LIMIT 1', [$key]);
    $wasResolved = in_array((string)($existing['status'] ?? ''), ['resolved','dismissed'], true);
    $public = mg_public_id();
    $pdo->prepare('INSERT INTO admin_agent_correlations (public_id,correlation_key,correlation_type,severity,status,title,summary,domains_json,finding_ids_json,anomaly_ids_json,deployment_id,evidence_json,runbook_key,recommended_action_key,first_detected_at,last_detected_at,occurrence_count,created_at,updated_at) VALUES (?,?,?,? ,"open",?,?,?,?,?,?,?,?,?,NOW(),NOW(),1,NOW(),NOW()) ON DUPLICATE KEY UPDATE severity=VALUES(severity),title=VALUES(title),summary=VALUES(summary),domains_json=VALUES(domains_json),finding_ids_json=VALUES(finding_ids_json),anomaly_ids_json=VALUES(anomaly_ids_json),deployment_id=VALUES(deployment_id),evidence_json=VALUES(evidence_json),runbook_key=VALUES(runbook_key),recommended_action_key=VALUES(recommended_action_key),recurrence_count=recurrence_count+IF(status IN ("resolved","dismissed"),1,0),resolved_by_user_id=IF(status IN ("resolved","dismissed"),NULL,resolved_by_user_id),resolved_at=IF(status IN ("resolved","dismissed"),NULL,resolved_at),resolution_note=IF(status IN ("resolved","dismissed"),NULL,resolution_note),status=IF(status IN ("resolved","dismissed"),"open",status),last_detected_at=NOW(),occurrence_count=occurrence_count+1,updated_at=NOW()')->execute([
        $public,$key,$data['correlation_type'],$data['severity'],$data['title'],$data['summary'],
        json_encode(array_values($data['domains'] ?? []),JSON_UNESCAPED_SLASHES),json_encode(array_values($data['finding_ids'] ?? []),JSON_UNESCAPED_SLASHES),json_encode(array_values($data['anomaly_ids'] ?? []),JSON_UNESCAPED_SLASHES),
        $data['deployment_id'] ?? null,json_encode($data['evidence'] ?? [],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$data['runbook_key'] ?? null,$data['recommended_action_key'] ?? null,
    ]);
    $row = mg_admin_agent_safe_row($pdo, 'SELECT id,public_id,severity,status FROM admin_agent_correlations WHERE correlation_key=? LIMIT 1', [$key]);
    if ($existing === [] || $wasResolved) {
        mg_admin_agent_ingest_event($pdo, [
            'monitor_key'=>'cross_system_correlation','domain'=>'intelligence','severity'=>mg_admin_agent_phase2_event_severity((string)$row['severity']),
            'event_type'=>'admin_agent.correlation.detected','title'=>(string)$data['title'],'message'=>(string)$data['summary'],
            'source_table'=>'admin_agent_correlations','source_id'=>(string)$row['public_id'],'entity_type'=>'admin_agent_correlation','entity_id'=>(string)$row['public_id'],
            'evidence'=>$data['evidence'] ?? [],'occurred_at'=>gmdate('Y-m-d H:i:s'),
        ]);
    }
    return ['id'=>(int)$row['id'],'public_id'=>(string)$row['public_id'],'key'=>$key,'created'=>$existing === [],'reopened'=>$wasResolved];
}

function mg_admin_agent_phase2_correlate(PDO $pdo): array
{
    $sources = mg_admin_agent_phase2_correlation_sources($pdo);
    $findings = $sources['findings'];
    $anomalies = $sources['anomalies'];
    $detectedTypes = [];
    $created = $updated = 0;
    $emit = static function(array $data) use ($pdo, &$detectedTypes, &$created, &$updated): void {
        $result = mg_admin_agent_phase2_upsert_correlation($pdo, $data);
        $detectedTypes[] = $data['correlation_type'] . '|' . ($data['scope_key'] ?? 'global');
        $result['created'] ? $created++ : $updated++;
    };

    $criticalDomains = [];
    foreach (array_merge($findings,$anomalies) as $item) {
        if (mg_admin_agent_phase2_severity_rank((string)$item['severity']) >= 4) $criticalDomains[(string)$item['domain']] = true;
    }
    if (count($criticalDomains) >= 2) {
        $criticalItems = array_values(array_filter(array_merge($findings,$anomalies), static fn(array $item): bool => mg_admin_agent_phase2_severity_rank((string)$item['severity']) >= 4));
        $emit(['correlation_type'=>'multi_domain_critical','severity'=>'critical','title'=>'Critical conditions span multiple systems','summary'=>count($criticalItems) . ' critical conditions are active across ' . count($criticalDomains) . ' platform domains.','domains'=>array_keys($criticalDomains),'finding_ids'=>array_values(array_map(static fn(array $i): string => isset($i['finding_type']) ? (string)$i['public_id'] : '', $criticalItems)),'anomaly_ids'=>array_values(array_map(static fn(array $i): string => isset($i['metric_key']) ? (string)$i['public_id'] : '', $criticalItems)),'evidence'=>['critical_domains'=>array_keys($criticalDomains),'critical_total'=>count($criticalItems)],'runbook_key'=>'multi_domain_critical','recommended_action_key'=>'declare_operations_incident']);
    }

    $notificationRisk = mg_admin_agent_phase2_filter_rank(array_merge($findings,$anomalies), ['notifications']);
    $supportRisk = mg_admin_agent_phase2_filter_rank(array_merge($findings,$anomalies), ['support']);
    if ($notificationRisk !== [] && $supportRisk !== []) {
        $emit(['correlation_type'=>'notification_queue_pressure','severity'=>'high','title'=>'Notification failures coincide with queue pressure','summary'=>'Delivery or notification risk is active while support and SLA pressure is also elevated.','domains'=>['notifications','support'],'finding_ids'=>array_values(array_filter(array_map(static fn(array $i): string => (string)($i['public_id'] ?? ''), array_merge($notificationRisk,$supportRisk)))),'anomaly_ids'=>[],'evidence'=>['notification_conditions'=>count($notificationRisk),'support_conditions'=>count($supportRisk)],'runbook_key'=>'notification_queue_pressure','recommended_action_key'=>'retry_failed_notifications']);
    }

    $automationRisk = mg_admin_agent_phase2_filter_rank(array_merge($findings,$anomalies), ['automation']);
    if ($automationRisk !== [] && $supportRisk !== []) {
        $emit(['correlation_type'=>'automation_sla_degradation','severity'=>'high','title'=>'Automation degradation is increasing SLA risk','summary'=>'Administrative automation is stale or failing while overdue or escalated work is active.','domains'=>['automation','support'],'finding_ids'=>array_values(array_filter(array_map(static fn(array $i): string => (string)($i['public_id'] ?? ''), array_merge($automationRisk,$supportRisk)))),'anomaly_ids'=>[],'evidence'=>['automation_conditions'=>count($automationRisk),'support_conditions'=>count($supportRisk)],'runbook_key'=>'automation_sla_degradation','recommended_action_key'=>'run_queue_automation']);
    }

    $securityRisk = mg_admin_agent_phase2_filter_rank(array_merge($findings,$anomalies), ['security']);
    $governanceEvents = (int)(mg_admin_agent_safe_row($pdo, 'SELECT COUNT(*) total FROM admin_agent_events WHERE domain="governance" AND occurred_at>=DATE_SUB(NOW(),INTERVAL 30 MINUTE)')['total'] ?? 0);
    if ($securityRisk !== [] && $governanceEvents >= 5) {
        $severity = count($securityRisk) >= 3 ? 'critical' : 'high';
        $emit(['correlation_type'=>'security_governance_change','severity'=>$severity,'title'=>'Security activity coincides with administrative change','summary'=>'Elevated security conditions overlap with a concentrated window of administrative audit activity.','domains'=>['security','governance'],'finding_ids'=>array_map(static fn(array $i): string => (string)$i['public_id'],$securityRisk),'anomaly_ids'=>[],'evidence'=>['security_conditions'=>count($securityRisk),'governance_events_30m'=>$governanceEvents],'runbook_key'=>'security_governance_change','recommended_action_key'=>'investigate_security_events']);
    }

    $latestDeploy = mg_admin_agent_safe_row($pdo, 'SELECT id,public_id,branch_name,commit_sha,deployed_at FROM admin_agent_deployments WHERE environment_key="production" ORDER BY deployed_at DESC LIMIT 1');
    if ($latestDeploy !== [] && strtotime((string)$latestDeploy['deployed_at'] . ' UTC') >= time() - 21600) {
        $postDeploy = array_values(array_filter(array_merge($findings,$anomalies), static function(array $item) use ($latestDeploy): bool {
            return mg_admin_agent_phase2_severity_rank((string)$item['severity']) >= 3 && strtotime((string)$item['first_detected_at'] . ' UTC') >= strtotime((string)$latestDeploy['deployed_at'] . ' UTC');
        }));
        if ($postDeploy !== []) {
            $critical = count(array_filter($postDeploy, static fn(array $i): bool => (string)$i['severity'] === 'critical'));
            $emit(['correlation_type'=>'deployment_regression','scope_key'=>(string)$latestDeploy['public_id'],'severity'=>$critical > 0 ? 'critical' : 'high','title'=>'New system risk detected after deployment','summary'=>count($postDeploy) . ' high or critical condition(s) first appeared after deployment ' . substr((string)$latestDeploy['commit_sha'],0,12) . '.','domains'=>array_values(array_unique(array_map(static fn(array $i): string => (string)$i['domain'],$postDeploy))),'finding_ids'=>array_values(array_map(static fn(array $i): string => isset($i['finding_type']) ? (string)$i['public_id'] : '', $postDeploy)),'anomaly_ids'=>array_values(array_map(static fn(array $i): string => isset($i['metric_key']) ? (string)$i['public_id'] : '', $postDeploy)),'deployment_id'=>(int)$latestDeploy['id'],'evidence'=>['branch'=>$latestDeploy['branch_name'],'commit_sha'=>$latestDeploy['commit_sha'],'deployed_at'=>$latestDeploy['deployed_at'],'post_deploy_conditions'=>count($postDeploy)],'runbook_key'=>'deployment_regression','recommended_action_key'=>'generate_migration_plan']);
        }
    }

    $aiRisk = mg_admin_agent_phase2_filter_rank(array_merge($findings,$anomalies), ['ai_accounting']);
    $aiSecurityEvents = (int)(mg_admin_agent_safe_row($pdo, 'SELECT COUNT(*) total FROM admin_agent_events WHERE domain="security" AND occurred_at>=DATE_SUB(NOW(),INTERVAL 60 MINUTE) AND (LOWER(event_type) LIKE "%ai%" OR LOWER(title) LIKE "%ai%" OR LOWER(message) LIKE "%provider%")')['total'] ?? 0);
    if ($aiRisk !== [] && $aiSecurityEvents > 0) {
        $emit(['correlation_type'=>'ai_provider_accounting_risk','severity'=>'high','title'=>'AI accounting risk overlaps provider or security activity','summary'=>'AI credit accounting conditions overlap with recent AI/provider-related security events.','domains'=>['ai_accounting','security'],'finding_ids'=>array_map(static fn(array $i): string => (string)$i['public_id'],$aiRisk),'anomaly_ids'=>[],'evidence'=>['ai_conditions'=>count($aiRisk),'security_events_60m'=>$aiSecurityEvents],'runbook_key'=>'security_governance_change','recommended_action_key'=>'run_ai_credit_reconciliation']);
    }

    $known = ['multi_domain_critical','notification_queue_pressure','automation_sla_degradation','security_governance_change','deployment_regression','ai_provider_accounting_risk'];
    foreach (mg_admin_agent_safe_rows($pdo, 'SELECT id,correlation_type,public_id FROM admin_agent_correlations WHERE status IN ("open","acknowledged","under_review")') as $row) {
        $scope = (string)$row['correlation_type'];
        $stillDetected = false;
        foreach ($detectedTypes as $detected) {
            if (str_starts_with($detected, $scope . '|')) { $stillDetected = true; break; }
        }
        if (in_array($scope,$known,true) && !$stillDetected) {
            $pdo->prepare('UPDATE admin_agent_correlations SET status="resolved",resolved_at=NOW(),resolution_note="Correlation conditions cleared in a later analysis run.",updated_at=NOW() WHERE id=?')->execute([(int)$row['id']]);
        }
    }
    return ['created'=>$created,'updated'=>$updated,'active_detected'=>count($detectedTypes)];
}

function mg_admin_agent_phase2_correlations(PDO $pdo, string $status = 'active', int $limit = 100): array
{
    $where = $status === 'active' ? 'c.status IN ("open","acknowledged","under_review")' : 'c.status=?';
    $params = $status === 'active' ? [] : [$status];
    $limit = max(10,min(200,$limit));
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT c.*,d.public_id deployment_public_id,d.branch_name,d.commit_sha,d.deployed_at FROM admin_agent_correlations c LEFT JOIN admin_agent_deployments d ON d.id=c.deployment_id WHERE ' . $where . ' ORDER BY CASE c.severity WHEN "critical" THEN 1 WHEN "high" THEN 2 WHEN "medium" THEN 3 ELSE 4 END,c.last_detected_at DESC LIMIT ' . $limit, $params);
    return array_map(static fn(array $row): array => [
        'id'=>(string)$row['public_id'],'type'=>(string)$row['correlation_type'],'severity'=>(string)$row['severity'],'status'=>(string)$row['status'],'title'=>(string)$row['title'],'summary'=>(string)$row['summary'],
        'domains'=>mg_admin_agent_json($row['domains_json'] ?? null),'finding_ids'=>array_values(array_filter(mg_admin_agent_json($row['finding_ids_json'] ?? null))),'anomaly_ids'=>array_values(array_filter(mg_admin_agent_json($row['anomaly_ids_json'] ?? null))),
        'evidence'=>mg_admin_agent_json($row['evidence_json'] ?? null),'runbook_key'=>$row['runbook_key'],'recommended_action_key'=>$row['recommended_action_key'],
        'deployment'=>$row['deployment_public_id'] ? ['id'=>(string)$row['deployment_public_id'],'branch'=>(string)$row['branch_name'],'commit_sha'=>(string)$row['commit_sha'],'deployed_at'=>(string)$row['deployed_at']] : null,
        'first_detected_at'=>(string)$row['first_detected_at'],'last_detected_at'=>(string)$row['last_detected_at'],'occurrence_count'=>(int)$row['occurrence_count'],'recurrence_count'=>(int)$row['recurrence_count'],'resolution_note'=>$row['resolution_note'],
    ], $rows);
}

function mg_admin_agent_phase2_source_record(PDO $pdo, string $sourceType, int $sourceId): array
{
    $map = [
        'finding'=>['admin_agent_findings','title','summary'],
        'anomaly'=>['admin_agent_anomalies','metric_key','metric_key'],
        'correlation'=>['admin_agent_correlations','title','summary'],
    ];
    if (!isset($map[$sourceType])) return [];
    [$table,$titleColumn,$messageColumn] = $map[$sourceType];
    return mg_admin_agent_safe_row($pdo, 'SELECT id,public_id,severity,status,' . $titleColumn . ' title,' . $messageColumn . ' message,first_detected_at,last_detected_at FROM ' . $table . ' WHERE id=? LIMIT 1', [$sourceId]);
}

function mg_admin_agent_phase2_notification(PDO $pdo, array $data): ?string
{
    if (!mg_admin_schema_has_table($pdo, 'admin_queue_notifications')) return null;
    $columns = mg_admin_schema_columns($pdo, 'admin_queue_notifications');
    foreach (['public_id','notification_type','title','message'] as $required) if (empty($columns[$required])) return null;
    $public = function_exists('mg_public_uuid') ? mg_public_uuid() : mg_public_id();
    $type = function_exists('mg_queue_notice_type') ? mg_queue_notice_type($pdo, 'admin_agent_escalation') : 'digest';
    $insert = ['public_id'=>$public,'notification_type'=>$type,'title'=>$data['title'],'message'=>$data['message']];
    if (!empty($columns['severity'])) $insert['severity'] = $data['severity'];
    if (!empty($columns['actor_user_id'])) $insert['actor_user_id'] = $data['actor_user_id'] ?? null;
    if (!empty($columns['metadata_json'])) $insert['metadata_json'] = json_encode($data['metadata'] ?? [],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $names = array_keys($insert); $holders = array_fill(0,count($names),'?'); $values = array_values($insert);
    if (!empty($columns['created_at'])) { $names[]='created_at'; $holders[]='NOW()'; }
    $pdo->prepare('INSERT INTO admin_queue_notifications (' . implode(',',$names) . ') VALUES (' . implode(',',$holders) . ')')->execute($values);
    return $public;
}

function mg_admin_agent_phase2_process_escalations(PDO $pdo, ?int $actorId = null): array
{
    $scheduled = $sent = $resolved = 0;
    $policies = mg_admin_agent_safe_rows($pdo, 'SELECT * FROM admin_agent_escalation_policies WHERE enabled=1 ORDER BY source_type,severity');
    foreach ($policies as $policy) {
        $table = ['finding'=>'admin_agent_findings','anomaly'=>'admin_agent_anomalies','correlation'=>'admin_agent_correlations'][(string)$policy['source_type']] ?? null;
        if ($table === null) continue;
        $sources = mg_admin_agent_safe_rows($pdo, 'SELECT id,public_id,severity,first_detected_at FROM ' . $table . ' WHERE status IN ("open","acknowledged","under_review") AND severity=?', [(string)$policy['severity']]);
        foreach ($sources as $source) {
            $ageMinutes = (time() - strtotime((string)$source['first_detected_at'] . ' UTC')) / 60;
            $last = mg_admin_agent_safe_row($pdo, 'SELECT escalation_level,sent_at,status FROM admin_agent_escalations WHERE source_type=? AND source_id=? ORDER BY escalation_level DESC LIMIT 1', [(string)$policy['source_type'],(int)$source['id']]);
            $nextLevel = $last === [] ? 1 : (int)$last['escalation_level'] + 1;
            if ($nextLevel > (int)$policy['maximum_level']) continue;
            $due = false;
            if ($last === []) $due = $ageMinutes >= (int)$policy['initial_delay_minutes'];
            elseif ((string)$last['status'] === 'sent' && $last['sent_at']) $due = (time() - strtotime((string)$last['sent_at'] . ' UTC')) / 60 >= (int)$policy['repeat_interval_minutes'];
            if (!$due) continue;
            $key = hash('sha256', (string)$policy['source_type'] . '|' . (int)$source['id'] . '|' . $nextLevel);
            $pdo->prepare('INSERT IGNORE INTO admin_agent_escalations (public_id,escalation_key,policy_id,source_type,source_id,severity,escalation_level,status,due_at,evidence_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,"scheduled",NOW(),JSON_OBJECT("source_public_id",?),NOW(),NOW())')->execute([mg_public_id(),$key,(int)$policy['id'],(string)$policy['source_type'],(int)$source['id'],(string)$source['severity'],$nextLevel,(string)$source['public_id']]);
            if ($pdo->lastInsertId()) $scheduled++;
        }
    }
    $dueRows = mg_admin_agent_safe_rows($pdo, 'SELECT e.*,p.notify_admin_center FROM admin_agent_escalations e JOIN admin_agent_escalation_policies p ON p.id=e.policy_id WHERE e.status="scheduled" AND e.due_at<=NOW() ORDER BY e.severity="critical" DESC,e.due_at ASC LIMIT 100');
    foreach ($dueRows as $escalation) {
        $source = mg_admin_agent_phase2_source_record($pdo, (string)$escalation['source_type'], (int)$escalation['source_id']);
        if ($source === [] || !in_array((string)$source['status'], ['open','acknowledged','under_review'], true)) {
            $pdo->prepare('UPDATE admin_agent_escalations SET status="resolved",updated_at=NOW() WHERE id=?')->execute([(int)$escalation['id']]);
            $resolved++;
            continue;
        }
        $notification = null;
        if ((int)$escalation['notify_admin_center'] === 1) {
            $notification = mg_admin_agent_phase2_notification($pdo, [
                'title'=>'Admin Agent escalation L' . (int)$escalation['escalation_level'] . ': ' . (string)$source['title'],
                'message'=>(string)$source['message'],'severity'=>(string)$escalation['severity'],'actor_user_id'=>$actorId,
                'metadata'=>['admin_agent_escalation_id'=>$escalation['public_id'],'source_type'=>$escalation['source_type'],'source_id'=>$source['public_id'],'level'=>(int)$escalation['escalation_level']],
            ]);
        }
        $pdo->prepare('UPDATE admin_agent_escalations SET status="sent",sent_at=NOW(),notification_public_id=?,updated_at=NOW() WHERE id=?')->execute([$notification,(int)$escalation['id']]);
        mg_admin_agent_ingest_event($pdo, ['monitor_key'=>'cross_system_correlation','domain'=>'intelligence','severity'=>mg_admin_agent_phase2_event_severity((string)$escalation['severity']),'event_type'=>'admin_agent.escalation.sent','title'=>'Admin Agent escalation sent','message'=>(string)$source['title'],'source_table'=>'admin_agent_escalations','source_id'=>(string)$escalation['public_id'],'actor_user_id'=>$actorId,'entity_type'=>(string)$escalation['source_type'],'entity_id'=>(string)$source['public_id'],'evidence'=>['level'=>(int)$escalation['escalation_level'],'notification_public_id'=>$notification],'occurred_at'=>gmdate('Y-m-d H:i:s')]);
        $sent++;
    }
    foreach (mg_admin_agent_safe_rows($pdo, 'SELECT id,source_type,source_id FROM admin_agent_escalations WHERE status IN ("scheduled","sent")') as $row) {
        $source = mg_admin_agent_phase2_source_record($pdo,(string)$row['source_type'],(int)$row['source_id']);
        if ($source === [] || !in_array((string)$source['status'],['open','acknowledged','under_review'],true)) {
            $pdo->prepare('UPDATE admin_agent_escalations SET status="resolved",updated_at=NOW() WHERE id=?')->execute([(int)$row['id']]);
            $resolved++;
        }
    }
    return ['scheduled'=>$scheduled,'sent'=>$sent,'resolved'=>$resolved];
}

function mg_admin_agent_phase2_escalations(PDO $pdo, int $limit = 100): array
{
    $limit=max(10,min(200,$limit));
    $rows=mg_admin_agent_safe_rows($pdo,'SELECT e.public_id,e.source_type,e.source_id,e.severity,e.escalation_level,e.status,e.due_at,e.sent_at,e.notification_public_id,e.created_at,p.policy_key FROM admin_agent_escalations e JOIN admin_agent_escalation_policies p ON p.id=e.policy_id ORDER BY CASE e.status WHEN "scheduled" THEN 1 WHEN "sent" THEN 2 ELSE 3 END,e.due_at DESC LIMIT '.$limit);
    return array_map(static fn(array $row):array=>['id'=>(string)$row['public_id'],'source_type'=>(string)$row['source_type'],'source_id'=>(int)$row['source_id'],'severity'=>(string)$row['severity'],'level'=>(int)$row['escalation_level'],'status'=>(string)$row['status'],'policy_key'=>(string)$row['policy_key'],'due_at'=>(string)$row['due_at'],'sent_at'=>$row['sent_at'],'notification_public_id'=>$row['notification_public_id'],'created_at'=>(string)$row['created_at']],$rows);
}

function mg_admin_agent_phase2_generate_summary(PDO $pdo, string $periodType = 'daily', ?int $actorId = null): array
{
    if (!in_array($periodType,['daily','weekly','manual'],true)) $periodType='daily';
    $now=time();
    if ($periodType==='weekly') { $start=strtotime('monday this week 00:00:00',$now); $end=strtotime('+7 days',$start)-1; }
    elseif ($periodType==='daily') { $start=strtotime(gmdate('Y-m-d 00:00:00',$now).' UTC'); $end=$start+86399; }
    else { $start=$now-86400; $end=$now; }
    $startSql=gmdate('Y-m-d H:i:s',$start); $endSql=gmdate('Y-m-d H:i:s',$end);
    $key=hash('sha256',$periodType.'|'.$startSql.'|'.$endSql.($periodType==='manual'?'|'.gmdate('YmdHi') : ''));
    $health=mg_admin_agent_health($pdo);
    $correlations=mg_admin_agent_phase2_correlations($pdo,'active',20);
    $anomalies=mg_admin_agent_phase2_anomalies($pdo,'active',20);
    $deployments=mg_admin_agent_phase2_deployments($pdo,10);
    $eventCounts=mg_admin_agent_safe_rows($pdo,'SELECT domain,severity,COUNT(*) total FROM admin_agent_events WHERE occurred_at BETWEEN ? AND ? GROUP BY domain,severity ORDER BY total DESC',[$startSql,$endSql]);
    $title=ucfirst($periodType).' Main Admin Agent executive summary';
    $lines=[
        'System health is '.$health['score'].'/100 ('.$health['status'].').',
        count($correlations).' active cross-system correlation(s) and '.count($anomalies).' active anomaly/anomalies are tracked.',
        count($deployments).' deployment record(s) are available in the recent release timeline.',
    ];
    if ($correlations!==[]) {
        $lines[]='Highest-priority correlated risk: '.$correlations[0]['title'].' — '.$correlations[0]['summary'];
    }
    $blocks=[['type'=>'health','health'=>$health],['type'=>'correlations','items'=>array_slice($correlations,0,10)],['type'=>'anomalies','items'=>array_slice($anomalies,0,10)],['type'=>'deployments','items'=>array_slice($deployments,0,5)],['type'=>'event_counts','items'=>$eventCounts]];
    $public=mg_public_id();
    $pdo->prepare('INSERT INTO admin_agent_executive_summaries (public_id,summary_key,period_type,period_start,period_end,health_score,title,summary_text,blocks_json,generated_by_user_id,generated_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE health_score=VALUES(health_score),title=VALUES(title),summary_text=VALUES(summary_text),blocks_json=VALUES(blocks_json),generated_by_user_id=COALESCE(VALUES(generated_by_user_id),generated_by_user_id),generated_at=NOW()')->execute([$public,$key,$periodType,$startSql,$endSql,$health['score'],$title,implode("\n",$lines),json_encode($blocks,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$actorId]);
    $row=mg_admin_agent_safe_row($pdo,'SELECT public_id,period_type,period_start,period_end,health_score,title,summary_text,blocks_json,generated_at FROM admin_agent_executive_summaries WHERE summary_key=? LIMIT 1',[$key]);
    return ['id'=>(string)$row['public_id'],'period_type'=>(string)$row['period_type'],'period_start'=>(string)$row['period_start'],'period_end'=>(string)$row['period_end'],'health_score'=>$row['health_score']!==null?(int)$row['health_score']:null,'title'=>(string)$row['title'],'summary_text'=>(string)$row['summary_text'],'blocks'=>mg_admin_agent_json($row['blocks_json']??null),'generated_at'=>(string)$row['generated_at']];
}

function mg_admin_agent_phase2_summaries(PDO $pdo, int $limit=20): array
{
    $limit=max(5,min(100,$limit));
    $rows=mg_admin_agent_safe_rows($pdo,'SELECT public_id,period_type,period_start,period_end,health_score,title,summary_text,blocks_json,generated_at FROM admin_agent_executive_summaries ORDER BY generated_at DESC LIMIT '.$limit);
    return array_map(static fn(array $row):array=>['id'=>(string)$row['public_id'],'period_type'=>(string)$row['period_type'],'period_start'=>(string)$row['period_start'],'period_end'=>(string)$row['period_end'],'health_score'=>$row['health_score']!==null?(int)$row['health_score']:null,'title'=>(string)$row['title'],'summary_text'=>(string)$row['summary_text'],'blocks'=>mg_admin_agent_json($row['blocks_json']??null),'generated_at'=>(string)$row['generated_at']],$rows);
}

function mg_admin_agent_phase2_run(PDO $pdo, array $options=[]): array
{
    if (!mg_admin_agent_phase2_ready($pdo)) throw new RuntimeException('Main Admin Agent Phase 2 SQL migration is required.');
    $actorId=isset($options['initiated_by_user_id'])&&(int)$options['initiated_by_user_id']>0?(int)$options['initiated_by_user_id']:null;
    $envCommit=trim((string)(getenv('MG_DEPLOY_COMMIT_SHA')?:getenv('GIT_COMMIT_SHA')?:''));
    if ($envCommit!=='' && preg_match('/^[a-fA-F0-9]{7,64}$/',$envCommit)) {
        mg_admin_agent_phase2_record_deployment($pdo,$actorId,['commit_sha'=>$envCommit,'branch_name'=>(string)(getenv('MG_DEPLOY_BRANCH')?:'integration-from-repair-20260628'),'environment_key'=>(string)(getenv('MG_DEPLOY_ENV')?:'production'),'source_type'=>'environment','release_label'=>(string)(getenv('MG_RELEASE_LABEL')?:'')]);
    }
    $scan=mg_admin_agent_scan_runtime($pdo,['trigger_source'=>$options['trigger_source']??'scheduled','initiated_by_user_id'=>$actorId]);
    $metric=mg_admin_agent_phase2_capture_metrics($pdo,(string)$scan['id']);
    $correlation=mg_admin_agent_phase2_correlate($pdo);
    $escalation=mg_admin_agent_phase2_process_escalations($pdo,$actorId);
    $daily=mg_admin_agent_phase2_generate_summary($pdo,'daily',$actorId);
    $weekly=null;
    if ((int)gmdate('N')===1) $weekly=mg_admin_agent_phase2_generate_summary($pdo,'weekly',$actorId);
    mg_audit('admin_agent_phase2_completed','system',['scan_id'=>$scan['id'],'metric'=>$metric,'correlation'=>$correlation,'escalation'=>$escalation],$actorId);
    return ['scan'=>$scan,'metrics'=>$metric,'correlations'=>$correlation,'escalations'=>$escalation,'daily_summary'=>$daily,'weekly_summary'=>$weekly,'database_only'=>true,'used_ai'=>false,'credits_used'=>0];
}

function mg_admin_agent_phase2_chat_mode(string $message): ?string
{
    $text=strtolower(trim($message));
    if (str_contains($text,'anomal')) return 'anomalies';
    if (str_contains($text,'correlat') || str_contains($text,'cross-system') || str_contains($text,'cross system')) return 'correlations';
    if (str_contains($text,'deploy') || str_contains($text,'release impact')) return 'deployments';
    if (str_contains($text,'executive') || str_contains($text,'daily summary') || str_contains($text,'weekly summary') || str_contains($text,'digest')) return 'executive';
    if (str_contains($text,'escalat')) return 'escalations';
    if (str_contains($text,'remediation') || str_contains($text,'action review') || str_contains($text,'approved action')) return 'remediation';
    return null;
}

function mg_admin_agent_phase2_report(PDO $pdo,string $mode): array
{
    $health=mg_admin_agent_health($pdo);
    $correlations=mg_admin_agent_phase2_correlations($pdo,'active',30);
    $anomalies=mg_admin_agent_phase2_anomalies($pdo,'active',30);
    $deployments=mg_admin_agent_phase2_deployments($pdo,20);
    $escalations=mg_admin_agent_phase2_escalations($pdo,30);
    $summaries=mg_admin_agent_phase2_summaries($pdo,10);
    $title='Main Admin Agent Phase 2 intelligence'; $lines=[]; $blocks=[['type'=>'health','health'=>$health]];
    if ($mode==='anomalies') { $title='Learned anomaly report'; $lines[] = count($anomalies).' active anomaly/anomalies are tracked against learned monitor baselines.'; $blocks[]=['type'=>'anomalies','items'=>$anomalies]; }
    elseif ($mode==='correlations') { $title='Cross-system correlation report'; $lines[] = count($correlations).' active correlation(s) combine findings, anomalies, deployments, and events.'; $blocks[]=['type'=>'correlations','items'=>$correlations]; }
    elseif ($mode==='deployments') { $title='Deployment impact report'; $lines[] = count($deployments).' deployment record(s) are available; recent release-linked correlations are shown below.'; $blocks[]=['type'=>'deployments','items'=>$deployments]; $blocks[]=['type'=>'correlations','items'=>array_values(array_filter($correlations,static fn(array $c):bool=>$c['type']==='deployment_regression'))]; }
    elseif ($mode==='escalations') { $title='Escalation and SLA routing report'; $active=array_values(array_filter($escalations,static fn(array $e):bool=>in_array($e['status'],['scheduled','sent'],true))); $lines[] = count($active).' escalation(s) are scheduled or sent.'; $blocks[]=['type'=>'escalations','items'=>$active]; }
    elseif ($mode==='executive') { $title='Executive system summary'; if ($summaries===[]) $summaries[] = mg_admin_agent_phase2_generate_summary($pdo,'manual',null); $lines[]=$summaries[0]['summary_text']; $blocks[]=['type'=>'summary','item'=>$summaries[0]]; }
    else { $title='Controlled remediation report'; $lines[]='Approved adapters are in-process, allowlisted, idempotent, explicitly confirmed, and fully audited. Financial, destructive, provider-configuration, and customer-facing mutations remain disabled.'; $blocks[]=['type'=>'remediation','items'=>mg_admin_agent_phase2_remediation_state($pdo)]; }
    if ($lines===[]) $lines[]='No Phase 2 report data is available.';
    return ['title'=>$title,'content'=>implode("\n",$lines),'blocks'=>$blocks,'metadata'=>['mode'=>$mode,'database_only'=>true,'used_ai'=>false,'credits_used'=>0,'generated_at'=>gmdate('Y-m-d H:i:s')]];
}

function mg_admin_agent_phase2_send(PDO $pdo,int $adminId,array $input): array
{
    $message=mb_substr(trim((string)($input['message']??'')),0,4000);
    if ($message==='') throw new InvalidArgumentException('Enter a message for the Main Admin Agent.');
    $mode=mg_admin_agent_phase2_chat_mode($message);
    if ($mode===null) return mg_admin_agent_send_runtime($pdo,$adminId,$input);
    $thread=mg_admin_agent_thread($pdo,$adminId,isset($input['thread_id'])?(string)$input['thread_id']:null);
    $userMessage=mg_admin_agent_record_message($pdo,(int)$thread['id'],$adminId,'user',$message,'chat',[],['database_only'=>true]);
    $report=mg_admin_agent_phase2_report($pdo,$mode);
    $assistant=mg_admin_agent_record_message($pdo,(int)$thread['id'],$adminId,'assistant',$report['content'],'system_report',$report['blocks'],$report['metadata']+['title'=>$report['title']]);
    mg_audit('admin_agent_phase2_chat_report','system',['thread_id'=>$thread['public_id'],'mode'=>$mode,'database_only'=>true],$adminId);
    return ['thread'=>['id'=>(string)$thread['public_id'],'title'=>(string)$thread['title']],'user_message'=>$userMessage,'assistant_message'=>$assistant,'report'=>$report];
}

function mg_admin_agent_phase2_state(PDO $pdo,int $adminId,array $options=[]): array
{
    $state=mg_admin_agent_state_runtime($pdo,$adminId,$options);
    $phase2=mg_admin_agent_phase2_schema_state($pdo);
    $state['phase2_schema']=$phase2;
    $state['phase2_ready']=$phase2['ready'];
    if (!$phase2['ready']) return $state;
    $state['anomalies']=mg_admin_agent_phase2_anomalies($pdo,'active',100);
    $state['correlations']=mg_admin_agent_phase2_correlations($pdo,'active',100);
    $state['deployments']=mg_admin_agent_phase2_deployments($pdo,30);
    $state['escalations']=mg_admin_agent_phase2_escalations($pdo,100);
    $state['executive_summaries']=mg_admin_agent_phase2_summaries($pdo,20);
    $state['remediation']=mg_admin_agent_phase2_remediation_state($pdo);
    return $state;
}
