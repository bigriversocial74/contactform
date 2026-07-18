<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/ids.php';
require_once dirname(__DIR__) . '/api/admin/_admin_schema.php';

const MG_ADMIN_AGENT_SCHEMA_TABLES = [
    'admin_agent_monitors',
    'admin_agent_scans',
    'admin_agent_events',
    'admin_agent_findings',
    'admin_agent_finding_actions',
    'admin_agent_threads',
    'admin_agent_messages',
    'admin_agent_action_reviews',
];

function mg_admin_agent_schema_ready(PDO $pdo): bool
{
    foreach (MG_ADMIN_AGENT_SCHEMA_TABLES as $table) {
        if (!mg_admin_schema_has_table($pdo, $table)) return false;
    }
    return true;
}

function mg_admin_agent_schema_state(PDO $pdo): array
{
    $missing = [];
    foreach (MG_ADMIN_AGENT_SCHEMA_TABLES as $table) {
        if (!mg_admin_schema_has_table($pdo, $table)) $missing[] = $table;
    }
    return [
        'ready' => $missing === [],
        'missing_tables' => $missing,
        'migration' => 'database/20260718_main_admin_agent_phase1.sql',
    ];
}

function mg_admin_agent_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_admin_agent_safe_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    } catch (Throwable) {
        return [];
    }
}

function mg_admin_agent_safe_row(PDO $pdo, string $sql, array $params = []): array
{
    $rows = mg_admin_agent_safe_rows($pdo, $sql, $params);
    return $rows[0] ?? [];
}

function mg_admin_agent_event_severity(string $severity): string
{
    $severity = strtolower(trim($severity));
    return in_array($severity, ['debug','info','warning','error','critical'], true) ? $severity : 'info';
}

function mg_admin_agent_finding_severity(string $severity): string
{
    $severity = strtolower(trim($severity));
    if ($severity === 'error') $severity = 'high';
    if ($severity === 'warning') $severity = 'medium';
    return in_array($severity, ['low','medium','high','critical'], true) ? $severity : 'medium';
}

function mg_admin_agent_event_key(array $event): string
{
    return hash('sha256', implode('|', [
        (string)($event['monitor_key'] ?? 'system'),
        (string)($event['source_table'] ?? ''),
        (string)($event['source_id'] ?? ''),
        (string)($event['event_type'] ?? 'event'),
    ]));
}

function mg_admin_agent_finding_key(array $finding): string
{
    return hash('sha256', implode('|', [
        (string)($finding['monitor_key'] ?? 'system'),
        (string)($finding['finding_type'] ?? 'finding'),
        (string)($finding['source_reference'] ?? 'global'),
    ]));
}

function mg_admin_agent_ingest_event(PDO $pdo, array $event): bool
{
    $eventKey = mg_admin_agent_event_key($event);
    $stmt = $pdo->prepare('INSERT IGNORE INTO admin_agent_events
        (public_id,event_key,monitor_key,domain,severity,event_type,title,message,source_table,source_id,actor_user_id,entity_type,entity_id,evidence_json,occurred_at,ingested_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
    $stmt->execute([
        mg_public_id(),
        $eventKey,
        mb_substr((string)($event['monitor_key'] ?? 'system'), 0, 100),
        mb_substr((string)($event['domain'] ?? 'system'), 0, 80),
        mg_admin_agent_event_severity((string)($event['severity'] ?? 'info')),
        mb_substr((string)($event['event_type'] ?? 'system.event'), 0, 160),
        mb_substr((string)($event['title'] ?? 'System event'), 0, 240),
        mb_substr((string)($event['message'] ?? ''), 0, 2000) ?: null,
        mb_substr((string)($event['source_table'] ?? ''), 0, 100) ?: null,
        mb_substr((string)($event['source_id'] ?? ''), 0, 190) ?: null,
        isset($event['actor_user_id']) && (int)$event['actor_user_id'] > 0 ? (int)$event['actor_user_id'] : null,
        mb_substr((string)($event['entity_type'] ?? ''), 0, 100) ?: null,
        mb_substr((string)($event['entity_id'] ?? ''), 0, 190) ?: null,
        json_encode(is_array($event['evidence'] ?? null) ? $event['evidence'] : [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        (string)($event['occurred_at'] ?? gmdate('Y-m-d H:i:s')),
    ]);
    return $stmt->rowCount() > 0;
}

function mg_admin_agent_finding_action(PDO $pdo, int $findingId, ?int $actorId, string $action, ?string $note = null, array $metadata = []): void
{
    $stmt = $pdo->prepare('INSERT INTO admin_agent_finding_actions
        (public_id,finding_id,action_type,admin_user_id,note,metadata_json,created_at)
        VALUES (?,?,?,?,?,?,NOW())');
    $stmt->execute([
        mg_public_id(),
        $findingId,
        mb_substr($action, 0, 80),
        $actorId && $actorId > 0 ? $actorId : null,
        $note !== null ? mb_substr(trim($note), 0, 1000) : null,
        json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
}

function mg_admin_agent_upsert_finding(PDO $pdo, int $scanId, array $finding): array
{
    $key = mg_admin_agent_finding_key($finding);
    $existingStmt = $pdo->prepare('SELECT id,status FROM admin_agent_findings WHERE finding_key=? LIMIT 1');
    $existingStmt->execute([$key]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $reopened = $existing && in_array((string)$existing['status'], ['resolved','dismissed'], true);

    $stmt = $pdo->prepare('INSERT INTO admin_agent_findings
        (public_id,finding_key,monitor_key,domain,finding_type,severity,status,title,summary,source_reference,evidence_json,first_detected_at,last_detected_at,occurrence_count,recurrence_count,last_scan_id,created_at,updated_at)
        VALUES (?,?,?,?,?,?,"open",?,?,?,?,NOW(),NOW(),1,0,?,NOW(),NOW())
        ON DUPLICATE KEY UPDATE
          domain=VALUES(domain),finding_type=VALUES(finding_type),severity=VALUES(severity),title=VALUES(title),summary=VALUES(summary),source_reference=VALUES(source_reference),evidence_json=VALUES(evidence_json),
          last_detected_at=NOW(),occurrence_count=occurrence_count+1,
          recurrence_count=recurrence_count+IF(status IN ("resolved","dismissed"),1,0),
          status=IF(status IN ("resolved","dismissed"),"open",status),
          resolved_by_user_id=IF(status IN ("resolved","dismissed"),NULL,resolved_by_user_id),
          resolved_at=IF(status IN ("resolved","dismissed"),NULL,resolved_at),
          resolution_note=IF(status IN ("resolved","dismissed"),NULL,resolution_note),
          last_scan_id=VALUES(last_scan_id),updated_at=NOW()');
    $stmt->execute([
        mg_public_id(),
        $key,
        mb_substr((string)($finding['monitor_key'] ?? 'system'), 0, 100),
        mb_substr((string)($finding['domain'] ?? 'system'), 0, 80),
        mb_substr((string)($finding['finding_type'] ?? 'system_finding'), 0, 120),
        mg_admin_agent_finding_severity((string)($finding['severity'] ?? 'medium')),
        mb_substr((string)($finding['title'] ?? 'System finding'), 0, 240),
        mb_substr((string)($finding['summary'] ?? 'System attention is required.'), 0, 2000),
        mb_substr((string)($finding['source_reference'] ?? 'global'), 0, 190),
        json_encode(is_array($finding['evidence'] ?? null) ? $finding['evidence'] : [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $scanId,
    ]);

    $idStmt = $pdo->prepare('SELECT id,public_id,status FROM admin_agent_findings WHERE finding_key=? LIMIT 1');
    $idStmt->execute([$key]);
    $row = $idStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($reopened && !empty($row['id'])) {
        mg_admin_agent_finding_action($pdo, (int)$row['id'], null, 'reopened_by_monitor', 'The monitor detected this condition again.', ['scan_id'=>$scanId]);
    }
    return [
        'key'=>$key,
        'id'=>(int)($row['id'] ?? 0),
        'public_id'=>(string)($row['public_id'] ?? ''),
        'created'=>$existing === null,
        'reopened'=>$reopened,
    ];
}

function mg_admin_agent_monitor_security(PDO $pdo): array
{
    if (!mg_admin_schema_has_table($pdo, 'security_logs')) return ['events'=>[],'findings'=>[],'metrics'=>['available'=>false]];
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT id,request_id,user_id,severity,event_type,message,context_json,created_at FROM security_logs WHERE created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR) AND severity IN ("warning","error","critical") ORDER BY id DESC LIMIT 150');
    $events = [];
    $groups = [];
    foreach ($rows as $row) {
        $severity = mg_admin_agent_event_severity((string)$row['severity']);
        $events[] = [
            'monitor_key'=>'security_events','domain'=>'security','severity'=>$severity,
            'event_type'=>(string)$row['event_type'],'title'=>'Security: ' . (string)$row['event_type'],
            'message'=>(string)$row['message'],'source_table'=>'security_logs','source_id'=>(string)$row['id'],
            'actor_user_id'=>$row['user_id'],'entity_type'=>'security_event','entity_id'=>(string)($row['request_id'] ?? ''),
            'evidence'=>mg_admin_agent_json($row['context_json'] ?? null),'occurred_at'=>(string)$row['created_at'],
        ];
        $type = (string)$row['event_type'];
        $groups[$type] ??= ['count'=>0,'critical'=>0,'error'=>0,'latest'=>(string)$row['created_at'],'message'=>(string)$row['message']];
        $groups[$type]['count']++;
        if ($severity === 'critical') $groups[$type]['critical']++;
        if ($severity === 'error') $groups[$type]['error']++;
    }
    $findings = [];
    foreach ($groups as $type => $group) {
        if ($group['critical'] < 1 && $group['error'] < 1) continue;
        $severity = $group['critical'] > 0 ? 'critical' : 'high';
        $findings[] = [
            'monitor_key'=>'security_events','domain'=>'security','finding_type'=>'security_event_pattern','severity'=>$severity,
            'title'=>'Security event requires review: ' . $type,
            'summary'=>$group['count'] . ' warning-or-higher events were recorded in the last 24 hours. Latest: ' . $group['message'],
            'source_reference'=>$type,'evidence'=>$group,
        ];
    }
    return ['events'=>$events,'findings'=>$findings,'metrics'=>['events_24h'=>count($rows),'patterns'=>count($groups)]];
}

function mg_admin_agent_monitor_audit(PDO $pdo): array
{
    if (!mg_admin_schema_has_table($pdo, 'audit_logs')) return ['events'=>[],'findings'=>[],'metrics'=>['available'=>false]];
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT id,user_id,action,entity_type,metadata_json,created_at FROM audit_logs WHERE created_at>=DATE_SUB(NOW(),INTERVAL 6 HOUR) ORDER BY id DESC LIMIT 150');
    $events = [];
    foreach ($rows as $row) {
        $events[] = [
            'monitor_key'=>'audit_activity','domain'=>'governance','severity'=>'info','event_type'=>(string)$row['action'],
            'title'=>'Audit activity: ' . (string)$row['action'],'message'=>'Administrative or platform activity was recorded.',
            'source_table'=>'audit_logs','source_id'=>(string)$row['id'],'actor_user_id'=>$row['user_id'],
            'entity_type'=>(string)$row['entity_type'],'evidence'=>mg_admin_agent_json($row['metadata_json'] ?? null),'occurred_at'=>(string)$row['created_at'],
        ];
    }
    return ['events'=>$events,'findings'=>[],'metrics'=>['events_6h'=>count($rows)]];
}

function mg_admin_agent_monitor_operations(PDO $pdo): array
{
    if (!mg_admin_schema_has_table($pdo, 'admin_ops_incidents')) return ['events'=>[],'findings'=>[],'metrics'=>['available'=>false]];
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT public_id,title,mode_slug,severity,status,impact_summary,declared_at,updated_at FROM admin_ops_incidents WHERE status<>"resolved" ORDER BY declared_at DESC LIMIT 50');
    $events = [];
    $findings = [];
    foreach ($rows as $row) {
        $severity = mg_admin_agent_finding_severity((string)$row['severity']);
        $events[] = [
            'monitor_key'=>'operations_incidents','domain'=>'operations','severity'=>$severity === 'high' ? 'error' : ($severity === 'critical' ? 'critical' : 'warning'),
            'event_type'=>'operations.incident.active','title'=>(string)$row['title'],'message'=>(string)$row['impact_summary'],
            'source_table'=>'admin_ops_incidents','source_id'=>(string)$row['public_id'],'entity_type'=>'operations_incident','entity_id'=>(string)$row['public_id'],
            'evidence'=>['mode_slug'=>$row['mode_slug'],'status'=>$row['status']],'occurred_at'=>(string)($row['updated_at'] ?: $row['declared_at']),
        ];
        $findings[] = [
            'monitor_key'=>'operations_incidents','domain'=>'operations','finding_type'=>'active_operations_incident','severity'=>$severity,
            'title'=>(string)$row['title'],'summary'=>(string)$row['impact_summary'],'source_reference'=>(string)$row['public_id'],
            'evidence'=>['mode_slug'=>$row['mode_slug'],'status'=>$row['status'],'declared_at'=>$row['declared_at']],
        ];
    }
    return ['events'=>$events,'findings'=>$findings,'metrics'=>['active_incidents'=>count($rows)]];
}

function mg_admin_agent_monitor_queue(PDO $pdo): array
{
    if (!mg_admin_schema_has_table($pdo, 'admin_user_notes')) return ['events'=>[],'findings'=>[],'metrics'=>['available'=>false]];
    $row = mg_admin_agent_safe_row($pdo, 'SELECT COUNT(*) active_total,
        SUM(status<>"resolved" AND sla_status="breached") breached,
        SUM(status<>"resolved" AND due_at IS NOT NULL AND due_at<NOW()) overdue,
        SUM(status<>"resolved" AND status="escalated") escalated,
        SUM(status<>"resolved" AND assigned_admin_user_id IS NULL) unassigned,
        SUM(status<>"resolved" AND created_at<DATE_SUB(NOW(),INTERVAL 14 DAY)) aging
        FROM admin_user_notes WHERE status<>"resolved"');
    $findings = [];
    $definitions = [
        'breached'=>['sla_breach','critical','Administrative SLA breaches','Administrative queue items have breached their SLA.'],
        'overdue'=>['overdue_queue','high','Overdue administrative work','Administrative queue work is past its due date.'],
        'escalated'=>['escalated_queue','high','Escalated administrative work','Administrative cases remain escalated.'],
        'unassigned'=>['unassigned_queue','medium','Unassigned administrative work','Administrative cases need an owner.'],
        'aging'=>['aging_queue','medium','Aging administrative work','Administrative cases have remained open for more than 14 days.'],
    ];
    foreach ($definitions as $column => [$type,$severity,$title,$summary]) {
        $count = (int)($row[$column] ?? 0);
        if ($count < 1) continue;
        $findings[] = [
            'monitor_key'=>'support_queue_sla','domain'=>'support','finding_type'=>$type,'severity'=>$severity,
            'title'=>$title,'summary'=>$count . ' item(s). ' . $summary,'source_reference'=>$column,'evidence'=>['count'=>$count],
        ];
    }
    return ['events'=>[],'findings'=>$findings,'metrics'=>array_map('intval', $row)];
}

function mg_admin_agent_monitor_notifications(PDO $pdo): array
{
    if (!mg_admin_schema_has_table($pdo, 'admin_queue_notifications')) return ['events'=>[],'findings'=>[],'metrics'=>['available'=>false]];
    $row = mg_admin_agent_safe_row($pdo, 'SELECT COUNT(*) total,
        SUM(read_at IS NULL) unread,
        SUM(read_at IS NULL AND severity="critical") critical_unread,
        SUM(read_at IS NULL AND notification_type="automation_failed") automation_failed
        FROM admin_queue_notifications');
    $findings = [];
    if ((int)($row['critical_unread'] ?? 0) > 0) {
        $findings[] = [
            'monitor_key'=>'notification_delivery','domain'=>'notifications','finding_type'=>'critical_notifications_unread','severity'=>'high',
            'title'=>'Critical admin notifications are unread','summary'=>(int)$row['critical_unread'] . ' critical notification(s) need review.',
            'source_reference'=>'critical_unread','evidence'=>['count'=>(int)$row['critical_unread']],
        ];
    }
    if ((int)($row['automation_failed'] ?? 0) > 0) {
        $findings[] = [
            'monitor_key'=>'notification_delivery','domain'=>'notifications','finding_type'=>'automation_failure_notifications','severity'=>'high',
            'title'=>'Automation failure notifications are open','summary'=>(int)$row['automation_failed'] . ' unread automation-failure notification(s) were found.',
            'source_reference'=>'automation_failed','evidence'=>['count'=>(int)$row['automation_failed']],
        ];
    }
    return ['events'=>[],'findings'=>$findings,'metrics'=>array_map('intval', $row)];
}

function mg_admin_agent_monitor_automation(PDO $pdo): array
{
    if (!mg_admin_schema_has_table($pdo, 'admin_queue_automation_runs')) return ['events'=>[],'findings'=>[],'metrics'=>['available'=>false]];
    $row = mg_admin_agent_safe_row($pdo, 'SELECT MAX(completed_at) last_completed_at,
        SUM(status="failed" AND started_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)) failed_24h,
        COUNT(*) total_runs FROM admin_queue_automation_runs');
    $findings = [];
    $last = (string)($row['last_completed_at'] ?? '');
    if ($last === '' || strtotime($last . ' UTC') < time() - 86400) {
        $findings[] = [
            'monitor_key'=>'automation_freshness','domain'=>'automation','finding_type'=>'automation_stale','severity'=>'high',
            'title'=>'Admin automation is stale','summary'=>'No completed administrative automation run was found in the last 24 hours.',
            'source_reference'=>'queue_automation','evidence'=>['last_completed_at'=>$last ?: null],
        ];
    }
    if ((int)($row['failed_24h'] ?? 0) > 0) {
        $findings[] = [
            'monitor_key'=>'automation_freshness','domain'=>'automation','finding_type'=>'automation_failed_recently','severity'=>'high',
            'title'=>'Admin automation failed recently','summary'=>(int)$row['failed_24h'] . ' automation run(s) failed in the last 24 hours.',
            'source_reference'=>'queue_automation_failures','evidence'=>['failed_24h'=>(int)$row['failed_24h']],
        ];
    }
    return ['events'=>[],'findings'=>$findings,'metrics'=>['last_completed_at'=>$last ?: null,'failed_24h'=>(int)($row['failed_24h'] ?? 0),'total_runs'=>(int)($row['total_runs'] ?? 0)]];
}

function mg_admin_agent_monitor_ai_accounting(PDO $pdo): array
{
    if (!mg_admin_schema_has_table($pdo, 'ai_credit_reconciliation_incidents')) return ['events'=>[],'findings'=>[],'metrics'=>['available'=>false]];
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT public_id,incident_type,severity,status,source_type,source_reference,provider_tokens,debited_tokens,token_difference,last_detected_at FROM ai_credit_reconciliation_incidents WHERE status IN ("open","under_review") ORDER BY last_detected_at DESC LIMIT 100');
    $findings = [];
    foreach ($rows as $row) {
        $findings[] = [
            'monitor_key'=>'ai_credit_accounting','domain'=>'ai_accounting','finding_type'=>'ai_credit_' . (string)$row['incident_type'],
            'severity'=>mg_admin_agent_finding_severity((string)$row['severity']),
            'title'=>'AI credit accounting: ' . ucwords(str_replace('_', ' ', (string)$row['incident_type'])),
            'summary'=>'Provider tokens ' . (int)$row['provider_tokens'] . ', debited tokens ' . (int)$row['debited_tokens'] . ', difference ' . (int)$row['token_difference'] . '.',
            'source_reference'=>(string)$row['public_id'],
            'evidence'=>['source_type'=>$row['source_type'],'response_reference'=>$row['source_reference'],'status'=>$row['status'],'token_difference'=>(int)$row['token_difference']],
        ];
    }
    return ['events'=>[],'findings'=>$findings,'metrics'=>['active_incidents'=>count($rows)]];
}

function mg_admin_agent_monitor_migrations(PDO $pdo): array
{
    if (!mg_admin_schema_has_table($pdo, 'schema_migrations')) return ['events'=>[],'findings'=>[values], 'metrics'=>['available'=>false]];
    $manifestPath = dirname(__DIR__) . '/config/migrations.php';
    $manifest = is_file($manifestPath) ? require $manifestPath : [];
    $files = is_array($manifest['ordered_files'] ?? null) ? $manifest['ordered_files'] : [];
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT migration_key FROM schema_migrations');
    $applied = [];
    foreach ($rows as $row) $applied[(string)$row['migration_key']] = true;
    $missing = [];
    foreach ($files as $file) {
        $key = preg_replace('/\.sql$/', '', (string)$file);
        if ($key !== '' && empty($applied[$key])) $missing[] = (string)$file;
    }
    $findings = [];
    if ($missing !== []) {
        $findings[] = [
            'monitor_key'=>'migration_readiness','domain'=>'database','finding_type'=>'pending_canonical_migrations','severity'=>'critical',
            'title'=>'Canonical database migrations are pending','summary'=>count($missing) . ' migration file(s) in the canonical manifest are not recorded as applied.',
            'source_reference'=>'canonical_manifest','evidence'=>['missing'=>array_slice($missing, 0, 25),'total_missing'=>count($missing)],
        ];
    }
    return ['events'=>[],'findings'=>$findings,'metrics'=>['manifest_total'=>count($files),'applied_total'=>count($applied),'missing_total'=>count($missing)]];
}

function mg_admin_agent_run_monitor(PDO $pdo, string $monitorKey): array
{
    return match ($monitorKey) {
        'security_events' => mg_admin_agent_monitor_security($pdo),
        'audit_activity' => mg_admin_agent_monitor_audit($pdo),
        'operations_incidents' => mg_admin_agent_monitor_operations($pdo),
        'support_queue_sla' => mg_admin_agent_monitor_queue($pdo),
        'notification_delivery' => mg_admin_agent_monitor_notifications($pdo),
        'automation_freshness' => mg_admin_agent_monitor_automation($pdo),
        'ai_credit_accounting' => mg_admin_agent_monitor_ai_accounting($pdo),
        'migration_readiness' => mg_admin_agent_monitor_migrations($pdo),
        default => ['events'=>[],'findings'=>[],'metrics'=>['unsupported'=>true]],
    };
}

function mg_admin_agent_auto_resolve(PDO $pdo, int $scanId, array $monitorKeys, array $detectedKeys): int
{
    if ($monitorKeys === []) return 0;
    $monitorPlaceholders = implode(',', array_fill(0, count($monitorKeys), '?'));
    $params = $monitorKeys;
    $sql = 'SELECT id,finding_key FROM admin_agent_findings WHERE status IN ("open","acknowledged","under_review") AND monitor_key IN (' . $monitorPlaceholders . ')';
    if ($detectedKeys !== []) {
        $sql .= ' AND finding_key NOT IN (' . implode(',', array_fill(0, count($detectedKeys), '?')) . ')';
        $params = array_merge($params, $detectedKeys);
    }
    $rows = mg_admin_agent_safe_rows($pdo, $sql, $params);
    $update = $pdo->prepare('UPDATE admin_agent_findings SET status="resolved",resolved_at=NOW(),resolution_note="Condition cleared by a later monitor scan.",last_scan_id=?,updated_at=NOW() WHERE id=?');
    foreach ($rows as $row) {
        $update->execute([$scanId, (int)$row['id']]);
        mg_admin_agent_finding_action($pdo, (int)$row['id'], null, 'auto_resolved', 'Condition cleared by a later monitor scan.', ['scan_id'=>$scanId]);
    }
    return count($rows);
}

function mg_admin_agent_health(PDO $pdo): array
{
    $row = mg_admin_agent_safe_row($pdo, 'SELECT
        SUM(status IN ("open","acknowledged","under_review")) active_total,
        SUM(status IN ("open","acknowledged","under_review") AND severity="critical") critical_total,
        SUM(status IN ("open","acknowledged","under_review") AND severity="high") high_total,
        SUM(status IN ("open","acknowledged","under_review") AND severity="medium") medium_total,
        SUM(status IN ("open","acknowledged","under_review") AND severity="low") low_total
        FROM admin_agent_findings');
    $monitor = mg_admin_agent_safe_row($pdo, 'SELECT SUM(enabled=1 AND last_status IN ("failed","critical")) failed_total,SUM(enabled=1) enabled_total FROM admin_agent_monitors');
    $score = 100;
    $score -= min(60, (int)($row['critical_total'] ?? 0) * 20);
    $score -= min(35, (int)($row['high_total'] ?? 0) * 8);
    $score -= min(20, (int)($row['medium_total'] ?? 0) * 3);
    $score -= min(10, (int)($row['low_total'] ?? 0));
    $score -= min(30, (int)($monitor['failed_total'] ?? 0) * 10);
    $score = max(0, $score);
    return [
        'score'=>$score,
        'status'=>$score >= 90 ? 'healthy' : ($score >= 70 ? 'watch' : ($score >= 40 ? 'attention' : 'critical')),
        'active_total'=>(int)($row['active_total'] ?? 0),
        'critical_total'=>(int)($row['critical_total'] ?? 0),
        'high_total'=>(int)($row['high_total'] ?? 0),
        'medium_total'=>(int)($row['medium_total'] ?? 0),
        'low_total'=>(int)($row['low_total'] ?? 0),
        'failed_monitors'=>(int)($monitor['failed_total'] ?? 0),
        'enabled_monitors'=>(int)($monitor['enabled_total'] ?? 0),
    ];
}

function mg_admin_agent_scan(PDO $pdo, array $options = []): array
{
    if (!mg_admin_agent_schema_ready($pdo)) throw new RuntimeException('Main Admin Agent SQL migration is required.');
    $trigger = strtolower(trim((string)($options['trigger_source'] ?? 'scheduled')));
    if (!in_array($trigger, ['scheduled','manual','workspace_load','api'], true)) $trigger = 'scheduled';
    $actorId = isset($options['initiated_by_user_id']) && (int)$options['initiated_by_user_id'] > 0 ? (int)$options['initiated_by_user_id'] : null;
    $scanPublic = mg_public_id();
    $pdo->prepare('INSERT INTO admin_agent_scans (public_id,trigger_source,status,initiated_by_user_id,started_at,created_at) VALUES (?, ?, "running", ?, NOW(), NOW())')->execute([$scanPublic,$trigger,$actorId]);
    $scanId = (int)$pdo->lastInsertId();
    $created = $updated = $eventsIngested = 0;
    $monitorKeys = [];
    $detectedKeys = [];
    $monitorMetrics = [];
    try {
        $monitors = mg_admin_agent_safe_rows($pdo, 'SELECT id,monitor_key,label,domain FROM admin_agent_monitors WHERE enabled=1 ORDER BY id');
        foreach ($monitors as $monitor) {
            $monitorId = (int)$monitor['id'];
            $monitorKey = (string)$monitor['monitor_key'];
            $monitorKeys[] = $monitorKey;
            $pdo->prepare('UPDATE admin_agent_monitors SET last_status="running",last_started_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$monitorId]);
            try {
                $result = mg_admin_agent_run_monitor($pdo, $monitorKey);
                foreach ($result['events'] ?? [] as $event) {
                    if (mg_admin_agent_ingest_event($pdo, $event)) $eventsIngested++;
                }
                foreach ($result['findings'] ?? [] as $finding) {
                    $finding['monitor_key'] = $monitorKey;
                    $upsert = mg_admin_agent_upsert_finding($pdo, $scanId, $finding);
                    $detectedKeys[] = $upsert['key'];
                    $upsert['created'] ? $created++ : $updated++;
                }
                $monitorMetrics[$monitorKey] = $result['metrics'] ?? [];
                $status = ($result['findings'] ?? []) === [] ? 'healthy' : 'warning';
                foreach ($result['findings'] ?? [] as $finding) {
                    if (($finding['severity'] ?? '') === 'critical') $status = 'critical';
                }
                $pdo->prepare('UPDATE admin_agent_monitors SET last_status=?,last_completed_at=NOW(),last_success_at=NOW(),consecutive_failures=0,last_error=NULL,updated_at=NOW() WHERE id=?')->execute([$status,$monitorId]);
            } catch (Throwable $error) {
                $monitorMetrics[$monitorKey] = ['failed'=>true,'exception_class'=>$error::class];
                $pdo->prepare('UPDATE admin_agent_monitors SET last_status="failed",last_completed_at=NOW(),consecutive_failures=consecutive_failures+1,last_error=?,updated_at=NOW() WHERE id=?')->execute([mb_substr($error->getMessage(),0,1000),$monitorId]);
                mg_admin_agent_upsert_finding($pdo, $scanId, [
                    'monitor_key'=>$monitorKey,'domain'=>(string)$monitor['domain'],'finding_type'=>'monitor_execution_failed','severity'=>'high',
                    'title'=>(string)$monitor['label'] . ' monitor failed','summary'=>'The monitor could not complete. Review the security log for the exception class.',
                    'source_reference'=>$monitorKey,'evidence'=>['exception_class'=>$error::class],
                ]);
                $updated++;
                mg_security_log('error', 'admin_agent.monitor_failed', 'Main Admin Agent monitor failed.', ['monitor_key'=>$monitorKey,'exception_class'=>$error::class], $actorId);
            }
        }
        $resolved = mg_admin_agent_auto_resolve($pdo, $scanId, $monitorKeys, array_values(array_unique($detectedKeys)));
        $health = mg_admin_agent_health($pdo);
        $pdo->prepare('UPDATE admin_agent_scans SET status="completed",monitors_run=?,events_ingested=?,findings_created=?,findings_updated=?,findings_resolved=?,health_score=?,metrics_json=?,completed_at=NOW() WHERE id=?')->execute([
            count($monitorKeys),$eventsIngested,$created,$updated,$resolved,$health['score'],
            json_encode($monitorMetrics, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),$scanId,
        ]);
        mg_admin_agent_ingest_event($pdo, [
            'monitor_key'=>'admin_agent','domain'=>'system','severity'=>$health['score'] < 40 ? 'critical' : ($health['score'] < 70 ? 'warning' : 'info'),
            'event_type'=>'admin_agent.scan.completed','title'=>'Main Admin Agent scan completed',
            'message'=>count($monitorKeys) . ' monitors ran; ' . $created . ' findings created; ' . $resolved . ' resolved.',
            'source_table'=>'admin_agent_scans','source_id'=>$scanPublic,'entity_type'=>'admin_agent_scan','entity_id'=>$scanPublic,
            'evidence'=>['health'=>$health,'events_ingested'=>$eventsIngested],'occurred_at'=>gmdate('Y-m-d H:i:s'),
        ]);
        mg_audit('admin_agent_scan_completed', 'system', ['scan_id'=>$scanPublic,'health_score'=>$health['score'],'events_ingested'=>$eventsIngested,'findings_created'=>$created,'findings_resolved'=>$resolved], $actorId);
        return ['id'=>$scanPublic,'status'=>'completed','health'=>$health,'monitors_run'=>count($monitorKeys),'events_ingested'=>$eventsIngested,'findings_created'=>$created,'findings_updated'=>$updated,'findings_resolved'=>$resolved];
    } catch (Throwable $error) {
        $pdo->prepare('UPDATE admin_agent_scans SET status="failed",failure_message=?,completed_at=NOW() WHERE id=?')->execute([mb_substr($error->getMessage(),0,1000),$scanId]);
        mg_security_log('error', 'admin_agent.scan_failed', 'Main Admin Agent scan failed.', ['scan_id'=>$scanPublic,'exception_class'=>$error::class], $actorId);
        throw $error;
    }
}

function mg_admin_agent_monitors(PDO $pdo): array
{
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT public_id,monitor_key,label,domain,description,schedule_seconds,enabled,severity_on_failure,last_status,last_started_at,last_completed_at,last_success_at,consecutive_failures,last_error,updated_at FROM admin_agent_monitors ORDER BY domain,label');
    return array_map(static fn(array $row): array => [
        'id'=>(string)$row['public_id'],'key'=>(string)$row['monitor_key'],'label'=>(string)$row['label'],'domain'=>(string)$row['domain'],
        'description'=>(string)($row['description'] ?? ''),'schedule_seconds'=>(int)$row['schedule_seconds'],'enabled'=>(bool)$row['enabled'],
        'severity_on_failure'=>(string)$row['severity_on_failure'],'status'=>(string)$row['last_status'],'last_started_at'=>$row['last_started_at'],
        'last_completed_at'=>$row['last_completed_at'],'last_success_at'=>$row['last_success_at'],'consecutive_failures'=>(int)$row['consecutive_failures'],
        'last_error'=>$row['last_error'],'updated_at'=>(string)$row['updated_at'],
    ], $rows);
}

function mg_admin_agent_findings(PDO $pdo, array $filters = []): array
{
    $where = [];
    $params = [];
    $status = strtolower(trim((string)($filters['status'] ?? 'active')));
    if ($status === 'active') $where[] = 'f.status IN ("open","acknowledged","under_review")';
    elseif (in_array($status, ['open','acknowledged','under_review','resolved','dismissed'], true)) { $where[] = 'f.status=?'; $params[] = $status; }
    $domain = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($filters['domain'] ?? '')));
    if ($domain !== '') { $where[] = 'f.domain=?'; $params[] = $domain; }
    $limit = max(10, min(200, (int)($filters['limit'] ?? 100)));
    $sql = 'SELECT f.*,assigned.email assigned_email,assigned.display_name assigned_name FROM admin_agent_findings f LEFT JOIN users assigned ON assigned.id=f.assigned_admin_user_id';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY CASE f.severity WHEN "critical" THEN 1 WHEN "high" THEN 2 WHEN "medium" THEN 3 ELSE 4 END,f.last_detected_at DESC LIMIT ' . $limit;
    $rows = mg_admin_agent_safe_rows($pdo, $sql, $params);
    return array_map(static function(array $row): array {
        return [
            'id'=>(string)$row['public_id'],'monitor_key'=>(string)$row['monitor_key'],'domain'=>(string)$row['domain'],'type'=>(string)$row['finding_type'],
            'severity'=>(string)$row['severity'],'status'=>(string)$row['status'],'title'=>(string)$row['title'],'summary'=>(string)$row['summary'],
            'source_reference'=>$row['source_reference'],'evidence'=>mg_admin_agent_json($row['evidence_json'] ?? null),'first_detected_at'=>(string)$row['first_detected_at'],
            'last_detected_at'=>(string)$row['last_detected_at'],'occurrence_count'=>(int)$row['occurrence_count'],'recurrence_count'=>(int)$row['recurrence_count'],
            'assigned'=>$row['assigned_admin_user_id'] !== null ? ['id'=>(int)$row['assigned_admin_user_id'],'email'=>(string)$row['assigned_email'],'display_name'=>(string)($row['assigned_name'] ?: $row['assigned_email'])] : null,
            'acknowledged_at'=>$row['acknowledged_at'],'resolved_at'=>$row['resolved_at'],'resolution_note'=>$row['resolution_note'],'updated_at'=>(string)$row['updated_at'],
        ];
    }, $rows);
}

function mg_admin_agent_events(PDO $pdo, int $afterId = 0, int $limit = 100, string $domain = ''): array
{
    $afterId = max(0, $afterId);
    $limit = max(10, min(200, $limit));
    $where = ['id>?'];
    $params = [$afterId];
    $domain = preg_replace('/[^a-z0-9_]/', '', strtolower($domain));
    if ($domain !== '') { $where[] = 'domain=?'; $params[] = $domain; }
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT id,public_id,monitor_key,domain,severity,event_type,title,message,source_table,source_id,actor_user_id,entity_type,entity_id,evidence_json,occurred_at,ingested_at FROM admin_agent_events WHERE ' . implode(' AND ', $where) . ' ORDER BY id ASC LIMIT ' . $limit, $params);
    return array_map(static fn(array $row): array => [
        'cursor'=>(int)$row['id'],'id'=>(string)$row['public_id'],'monitor_key'=>(string)$row['monitor_key'],'domain'=>(string)$row['domain'],
        'severity'=>(string)$row['severity'],'type'=>(string)$row['event_type'],'title'=>(string)$row['title'],'message'=>(string)($row['message'] ?? ''),
        'source'=>['table'=>$row['source_table'],'id'=>$row['source_id']],'actor_user_id'=>$row['actor_user_id'] !== null ? (int)$row['actor_user_id'] : null,
        'entity'=>['type'=>$row['entity_type'],'id'=>$row['entity_id']],'evidence'=>mg_admin_agent_json($row['evidence_json'] ?? null),
        'occurred_at'=>(string)$row['occurred_at'],'ingested_at'=>(string)$row['ingested_at'],
    ], $rows);
}

function mg_admin_agent_domain_health(PDO $pdo): array
{
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT domain,
        SUM(status IN ("open","acknowledged","under_review")) active_total,
        SUM(status IN ("open","acknowledged","under_review") AND severity="critical") critical_total,
        SUM(status IN ("open","acknowledged","under_review") AND severity="high") high_total,
        MAX(last_detected_at) last_detected_at
        FROM admin_agent_findings GROUP BY domain ORDER BY critical_total DESC,high_total DESC,active_total DESC,domain');
    return array_map(static function(array $row): array {
        $score = max(0, 100 - ((int)$row['critical_total'] * 30) - ((int)$row['high_total'] * 12) - max(0, ((int)$row['active_total'] - (int)$row['critical_total'] - (int)$row['high_total']) * 4));
        return ['domain'=>(string)$row['domain'],'score'=>$score,'status'=>$score >= 90 ? 'healthy' : ($score >= 70 ? 'watch' : 'attention'),'active_total'=>(int)$row['active_total'],'critical_total'=>(int)$row['critical_total'],'high_total'=>(int)$row['high_total'],'last_detected_at'=>$row['last_detected_at']];
    }, $rows);
}

function mg_admin_agent_last_scan(PDO $pdo): ?array
{
    $row = mg_admin_agent_safe_row($pdo, 'SELECT public_id,trigger_source,status,monitors_run,events_ingested,findings_created,findings_updated,findings_resolved,health_score,failure_message,started_at,completed_at FROM admin_agent_scans ORDER BY id DESC LIMIT 1');
    if ($row === []) return null;
    return ['id'=>(string)$row['public_id'],'trigger_source'=>(string)$row['trigger_source'],'status'=>(string)$row['status'],'monitors_run'=>(int)$row['monitors_run'],'events_ingested'=>(int)$row['events_ingested'],'findings_created'=>(int)$row['findings_created'],'findings_updated'=>(int)$row['findings_updated'],'findings_resolved'=>(int)$row['findings_resolved'],'health_score'=>$row['health_score'] !== null ? (int)$row['health_score'] : null,'failure_message'=>$row['failure_message'],'started_at'=>(string)$row['started_at'],'completed_at'=>$row['completed_at']];
}

function mg_admin_agent_action_reviews(PDO $pdo, int $limit = 50): array
{
    $limit = max(10, min(100, $limit));
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT r.public_id,r.action_key,r.domain,r.title,r.rationale,r.payload_json,r.risk_level,r.status,r.review_note,r.reviewed_at,r.executed_at,r.created_at,f.public_id finding_public_id FROM admin_agent_action_reviews r LEFT JOIN admin_agent_findings f ON f.id=r.finding_id ORDER BY CASE r.status WHEN "pending" THEN 1 ELSE 2 END,r.created_at DESC LIMIT ' . $limit);
    return array_map(static fn(array $row): array => ['id'=>(string)$row['public_id'],'action_key'=>(string)$row['action_key'],'domain'=>(string)$row['domain'],'title'=>(string)$row['title'],'rationale'=>(string)($row['rationale'] ?? ''),'payload'=>mg_admin_agent_json($row['payload_json'] ?? null),'risk_level'=>(string)$row['risk_level'],'status'=>(string)$row['status'],'review_note'=>$row['review_note'],'reviewed_at'=>$row['reviewed_at'],'executed_at'=>$row['executed_at'],'created_at'=>(string)$row['created_at'],'finding_id'=>$row['finding_public_id']], $rows);
}

function mg_admin_agent_threads(PDO $pdo, int $adminId): array
{
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT public_id,title,status,last_message_at,created_at,updated_at FROM admin_agent_threads WHERE admin_user_id=? ORDER BY status="active" DESC,updated_at DESC LIMIT 50', [$adminId]);
    return array_map(static fn(array $row): array => ['id'=>(string)$row['public_id'],'title'=>(string)$row['title'],'status'=>(string)$row['status'],'last_message_at'=>$row['last_message_at'],'created_at'=>(string)$row['created_at'],'updated_at'=>(string)$row['updated_at']], $rows);
}

function mg_admin_agent_thread(PDO $pdo, int $adminId, ?string $publicId = null): array
{
    if ($publicId !== null && trim($publicId) !== '') {
        $stmt = $pdo->prepare('SELECT * FROM admin_agent_threads WHERE admin_user_id=? AND public_id=? LIMIT 1');
        $stmt->execute([$adminId, trim($publicId)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
    }
    $stmt = $pdo->prepare('SELECT * FROM admin_agent_threads WHERE admin_user_id=? AND status="active" ORDER BY updated_at DESC LIMIT 1');
    $stmt->execute([$adminId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;
    $public = mg_public_id();
    $pdo->prepare('INSERT INTO admin_agent_threads (public_id,admin_user_id,title,status,created_at,updated_at) VALUES (?, ?, "Current system chat", "active", NOW(), NOW())')->execute([$public,$adminId]);
    return ['id'=>(int)$pdo->lastInsertId(),'public_id'=>$public,'admin_user_id'=>$adminId,'title'=>'Current system chat','status'=>'active'];
}

function mg_admin_agent_messages(PDO $pdo, int $threadId, int $limit = 100): array
{
    $limit = max(10, min(200, $limit));
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT public_id,role,message_type,content,blocks_json,metadata_json,created_at FROM admin_agent_messages WHERE thread_id=? ORDER BY id ASC LIMIT ' . $limit, [$threadId]);
    return array_map(static fn(array $row): array => ['id'=>(string)$row['public_id'],'role'=>(string)$row['role'],'message_type'=>(string)$row['message_type'],'content'=>(string)$row['content'],'blocks'=>mg_admin_agent_json($row['blocks_json'] ?? null),'metadata'=>mg_admin_agent_json($row['metadata_json'] ?? null),'created_at'=>(string)$row['created_at']], $rows);
}

function mg_admin_agent_record_message(PDO $pdo, int $threadId, int $adminId, string $role, string $content, string $type = 'chat', array $blocks = [], array $metadata = []): array
{
    $public = mg_public_id();
    $content = mb_substr(trim($content), 0, 20000);
    $pdo->prepare('INSERT INTO admin_agent_messages (public_id,thread_id,admin_user_id,role,message_type,content,blocks_json,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())')->execute([
        $public,$threadId,$adminId,$role,$type,$content,
        json_encode($blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
    $title = $role === 'user' ? mb_substr($content, 0, 80) : null;
    if ($title !== null) {
        $pdo->prepare('UPDATE admin_agent_threads SET title=IF(title="Current system chat",?,title),last_message_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$title,$threadId]);
    } else {
        $pdo->prepare('UPDATE admin_agent_threads SET last_message_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$threadId]);
    }
    return ['id'=>$public,'role'=>$role,'message_type'=>$type,'content'=>$content,'blocks'=>$blocks,'metadata'=>$metadata,'created_at'=>gmdate('Y-m-d H:i:s')];
}

function mg_admin_agent_chat_mode(string $message): string
{
    $text = strtolower(trim($message));
    if ($text === '' || preg_match('/^(overview|status|system status|admin report)$/', $text)) return 'overview';
    if (str_contains($text, 'what changed') || str_contains($text, 'changes')) return 'changes';
    if (str_contains($text, 'security')) return 'security';
    if (str_contains($text, 'migration') || str_contains($text, 'database')) return 'migrations';
    if (str_contains($text, 'ai credit') || str_contains($text, 'accounting')) return 'ai_accounting';
    if (str_contains($text, 'finding') || str_contains($text, 'attention') || str_contains($text, 'problem')) return 'findings';
    if (str_contains($text, 'operation') || str_contains($text, 'incident') || str_contains($text, 'queue') || str_contains($text, 'sla')) return 'operations';
    if (str_contains($text, 'recent') || str_contains($text, 'activity') || str_contains($text, 'event')) return 'recent';
    if (str_contains($text, 'help') || str_contains($text, 'command')) return 'help';
    return 'overview';
}

function mg_admin_agent_chat_response(PDO $pdo, string $mode): array
{
    $health = mg_admin_agent_health($pdo);
    $domains = mg_admin_agent_domain_health($pdo);
    $findings = mg_admin_agent_findings($pdo, ['status'=>'active','limit'=>25]);
    $lastScan = mg_admin_agent_last_scan($pdo);
    $events = mg_admin_agent_events($pdo, max(0, (int)($lastScan ? 0 : 0)), 40);
    $filteredFindings = $findings;
    $filteredEvents = $events;
    $title = 'Main Admin Agent system overview';
    $intro = 'The platform health score is ' . $health['score'] . '/100 (' . $health['status'] . ').';
    if ($mode === 'security') {
        $title = 'Security monitoring report';
        $filteredFindings = array_values(array_filter($findings, static fn(array $f): bool => $f['domain'] === 'security'));
        $filteredEvents = array_values(array_filter($events, static fn(array $e): bool => $e['domain'] === 'security'));
        $intro = count($filteredFindings) . ' active security finding(s) are currently tracked.';
    } elseif ($mode === 'migrations') {
        $title = 'Database and migration report';
        $filteredFindings = array_values(array_filter($findings, static fn(array $f): bool => $f['domain'] === 'database'));
        $filteredEvents = array_values(array_filter($events, static fn(array $e): bool => $e['domain'] === 'database'));
        $intro = count($filteredFindings) . ' active database finding(s) are currently tracked.';
    } elseif ($mode === 'ai_accounting') {
        $title = 'AI credit accounting report';
        $filteredFindings = array_values(array_filter($findings, static fn(array $f): bool => $f['domain'] === 'ai_accounting'));
        $filteredEvents = array_values(array_filter($events, static fn(array $e): bool => $e['domain'] === 'ai_accounting'));
        $intro = count($filteredFindings) . ' active AI accounting finding(s) are currently tracked.';
    } elseif ($mode === 'operations') {
        $title = 'Operations and queue report';
        $filteredFindings = array_values(array_filter($findings, static fn(array $f): bool => in_array($f['domain'], ['operations','support','automation','notifications'], true)));
        $filteredEvents = array_values(array_filter($events, static fn(array $e): bool => in_array($e['domain'], ['operations','support','automation','notifications'], true)));
        $intro = count($filteredFindings) . ' active operations-related finding(s) are currently tracked.';
    } elseif ($mode === 'findings') {
        $title = 'Active system findings';
        $intro = count($findings) . ' active finding(s): ' . $health['critical_total'] . ' critical and ' . $health['high_total'] . ' high severity.';
    } elseif ($mode === 'recent' || $mode === 'changes') {
        $title = $mode === 'changes' ? 'What changed since recent monitoring' : 'Recent normalized system activity';
        $intro = count($events) . ' recent normalized event(s) are available in this report window.';
    } elseif ($mode === 'help') {
        return [
            'title'=>'Main Admin Agent commands',
            'content'=>'Ask for: Overview, What changed, Active findings, Security, Operations, AI credit accounting, Migrations, or Recent activity. Monitoring and these reports are database-only and use no AI credits. Remediation requests are review-gated and never execute automatically.',
            'blocks'=>[['type'=>'commands','items'=>['Overview','What changed','Active findings','Security report','Operations report','AI credit accounting','Migration report','Recent activity']]],
            'metadata'=>['mode'=>'help','database_only'=>true,'used_ai'=>false],
        ];
    }
    $lines = [$intro];
    if ($filteredFindings !== []) {
        $lines[] = 'Highest-priority findings:';
        foreach (array_slice($filteredFindings, 0, 8) as $finding) {
            $lines[] = strtoupper($finding['severity']) . ' — ' . $finding['title'] . ': ' . $finding['summary'];
        }
    } else {
        $lines[] = 'No active findings match this report.';
    }
    if (in_array($mode, ['recent','changes'], true) && $filteredEvents !== []) {
        $lines[] = 'Recent changes:';
        foreach (array_slice(array_reverse($filteredEvents), 0, 10) as $event) {
            $lines[] = strtoupper($event['severity']) . ' — ' . $event['title'] . ' (' . $event['occurred_at'] . ')';
        }
    }
    return [
        'title'=>$title,
        'content'=>implode("\n", $lines),
        'blocks'=>[
            ['type'=>'health','health'=>$health,'last_scan'=>$lastScan],
            ['type'=>'domains','items'=>$domains],
            ['type'=>'findings','items'=>array_slice($filteredFindings,0,12)],
            ['type'=>'events','items'=>array_slice(array_reverse($filteredEvents),0,12)],
        ],
        'metadata'=>['mode'=>$mode,'database_only'=>true,'used_ai'=>false,'generated_at'=>gmdate('Y-m-d H:i:s')],
    ];
}

function mg_admin_agent_send(PDO $pdo, int $adminId, array $input): array
{
    $message = mb_substr(trim((string)($input['message'] ?? '')), 0, 4000);
    if ($message === '') throw new InvalidArgumentException('Enter a message for the Main Admin Agent.');
    $thread = mg_admin_agent_thread($pdo, $adminId, isset($input['thread_id']) ? (string)$input['thread_id'] : null);
    $userMessage = mg_admin_agent_record_message($pdo, (int)$thread['id'], $adminId, 'user', $message, 'chat', [], ['database_only'=>true]);
    $mode = mg_admin_agent_chat_mode($message);
    $report = mg_admin_agent_chat_response($pdo, $mode);
    $assistant = mg_admin_agent_record_message($pdo, (int)$thread['id'], $adminId, 'assistant', $report['content'], 'system_report', $report['blocks'], $report['metadata'] + ['title'=>$report['title']]);
    mg_audit('admin_agent_chat_report', 'system', ['thread_id'=>$thread['public_id'],'mode'=>$mode,'database_only'=>true], $adminId);
    return ['thread'=>['id'=>(string)$thread['public_id'],'title'=>(string)$thread['title']],'user_message'=>$userMessage,'assistant_message'=>$assistant,'report'=>$report];
}

function mg_admin_agent_apply_finding_action(PDO $pdo, int $adminId, array $input): array
{
    $publicId = trim((string)($input['finding_id'] ?? ''));
    $action = strtolower(trim((string)($input['action_key'] ?? $input['finding_action'] ?? 'acknowledge')));
    $note = mb_substr(trim((string)($input['note'] ?? '')), 0, 1000);
    if (!in_array($action, ['acknowledge','assign_self','under_review','resolve','dismiss','reopen'], true)) throw new InvalidArgumentException('Unknown finding action.');
    if (in_array($action, ['resolve','dismiss'], true) && $note === '') throw new InvalidArgumentException('A resolution or dismissal note is required.');
    $stmt = $pdo->prepare('SELECT id,status FROM admin_agent_findings WHERE public_id=? LIMIT 1 FOR UPDATE');
    $pdo->beginTransaction();
    try {
        $stmt->execute([$publicId]);
        $finding = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$finding) throw new InvalidArgumentException('System finding not found.');
        $sql = match ($action) {
            'acknowledge' => 'UPDATE admin_agent_findings SET status="acknowledged",acknowledged_by_user_id=?,acknowledged_at=NOW(),updated_at=NOW() WHERE id=?',
            'assign_self' => 'UPDATE admin_agent_findings SET assigned_admin_user_id=?,updated_at=NOW() WHERE id=?',
            'under_review' => 'UPDATE admin_agent_findings SET status="under_review",assigned_admin_user_id=COALESCE(assigned_admin_user_id,?),updated_at=NOW() WHERE id=?',
            'resolve' => 'UPDATE admin_agent_findings SET status="resolved",resolved_by_user_id=?,resolved_at=NOW(),resolution_note=?,updated_at=NOW() WHERE id=?',
            'dismiss' => 'UPDATE admin_agent_findings SET status="dismissed",resolved_by_user_id=?,resolved_at=NOW(),resolution_note=?,updated_at=NOW() WHERE id=?',
            'reopen' => 'UPDATE admin_agent_findings SET status="open",resolved_by_user_id=NULL,resolved_at=NULL,resolution_note=NULL,updated_at=NOW() WHERE id=?',
        };
        $update = $pdo->prepare($sql);
        if (in_array($action, ['resolve','dismiss'], true)) $update->execute([$adminId,$note,(int)$finding['id']]);
        else $update->execute([$adminId,(int)$finding['id']]);
        mg_admin_agent_finding_action($pdo, (int)$finding['id'], $adminId, $action, $note ?: null);
        $pdo->commit();
        mg_audit('admin_agent_finding_' . $action, 'system', ['finding_id'=>$publicId,'note_recorded'=>$note !== ''], $adminId);
        mg_security_log('info', 'admin_agent.finding_action', 'Main Admin Agent finding action completed.', ['finding_id'=>$publicId,'action'=>$action], $adminId);
        return ['finding_id'=>$publicId,'action'=>$action,'updated'=>true];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_admin_agent_request_action(PDO $pdo, int $adminId, array $input): array
{
    $actionKey = strtolower(trim((string)($input['action_key'] ?? '')));
    $allowed = [
        'run_queue_automation'=>['operations','Run queue automation','medium'],
        'run_ai_credit_reconciliation'=>['ai_accounting','Run AI credit reconciliation','medium'],
        'retry_failed_notifications'=>['notifications','Retry failed notifications','high'],
        'declare_operations_incident'=>['operations','Declare operations incident','high'],
        'generate_migration_plan'=>['database','Generate migration repair plan','high'],
        'investigate_security_events'=>['security','Open security investigation','high'],
    ];
    if (!isset($allowed[$actionKey])) throw new InvalidArgumentException('This Admin Agent action is not review-enabled.');
    [$domain,$title,$risk] = $allowed[$actionKey];
    $findingPublic = trim((string)($input['finding_id'] ?? ''));
    $findingId = null;
    if ($findingPublic !== '') {
        $stmt = $pdo->prepare('SELECT id FROM admin_agent_findings WHERE public_id=? LIMIT 1');
        $stmt->execute([$findingPublic]);
        $value = $stmt->fetchColumn();
        if ($value !== false) $findingId = (int)$value;
    }
    $rationale = mb_substr(trim((string)($input['rationale'] ?? 'Requested from the Main Admin Agent review queue.')), 0, 2000);
    $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
    $idempotency = hash('sha256', $adminId . '|' . $actionKey . '|' . ($findingPublic ?: 'global') . '|' . json_encode($payload));
    $public = mg_public_id();
    $stmt = $pdo->prepare('INSERT INTO admin_agent_action_reviews (public_id,idempotency_key,requested_by_user_id,finding_id,action_key,domain,title,rationale,payload_json,risk_level,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,"pending",NOW(),NOW()) ON DUPLICATE KEY UPDATE updated_at=NOW()');
    $stmt->execute([$public,$idempotency,$adminId,$findingId,$actionKey,$domain,$title,$rationale,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$risk]);
    $row = mg_admin_agent_safe_row($pdo, 'SELECT public_id,status FROM admin_agent_action_reviews WHERE idempotency_key=? LIMIT 1', [$idempotency]);
    mg_audit('admin_agent_action_requested', 'system', ['action_key'=>$actionKey,'finding_id'=>$findingPublic ?: null,'review_only'=>true], $adminId);
    mg_security_log('info', 'admin_agent.action_requested', 'Main Admin Agent review-gated action requested.', ['action_key'=>$actionKey,'review_only'=>true], $adminId);
    return ['id'=>(string)($row['public_id'] ?? $public),'action_key'=>$actionKey,'status'=>(string)($row['status'] ?? 'pending'),'review_required'=>true,'executed'=>false];
}

function mg_admin_agent_new_thread(PDO $pdo, int $adminId): array
{
    $pdo->prepare('UPDATE admin_agent_threads SET status="saved",updated_at=NOW() WHERE admin_user_id=? AND status="active"')->execute([$adminId]);
    $public = mg_public_id();
    $pdo->prepare('INSERT INTO admin_agent_threads (public_id,admin_user_id,title,status,created_at,updated_at) VALUES (?, ?, "Current system chat", "active", NOW(), NOW())')->execute([$public,$adminId]);
    return ['id'=>$public,'title'=>'Current system chat','status'=>'active'];
}

function mg_admin_agent_state(PDO $pdo, int $adminId, array $options = []): array
{
    $schema = mg_admin_agent_schema_state($pdo);
    if (!$schema['ready']) return ['schema'=>$schema,'schema_ready'=>false];
    $after = max(0, (int)($options['after'] ?? 0));
    $thread = mg_admin_agent_thread($pdo, $adminId, isset($options['thread_id']) ? (string)$options['thread_id'] : null);
    $events = mg_admin_agent_events($pdo, $after, (int)($options['event_limit'] ?? 100), (string)($options['domain'] ?? ''));
    $health = mg_admin_agent_health($pdo);
    return [
        'schema'=>$schema,
        'schema_ready'=>true,
        'health'=>$health,
        'domains'=>mg_admin_agent_domain_health($pdo),
        'monitors'=>mg_admin_agent_monitors($pdo),
        'findings'=>mg_admin_agent_findings($pdo, ['status'=>$options['finding_status'] ?? 'active','domain'=>$options['domain'] ?? '','limit'=>100]),
        'events'=>$events,
        'event_cursor'=>$events !== [] ? (int)end($events)['cursor'] : $after,
        'last_scan'=>mg_admin_agent_last_scan($pdo),
        'threads'=>mg_admin_agent_threads($pdo, $adminId),
        'active_thread'=>['id'=>(string)$thread['public_id'],'title'=>(string)$thread['title'],'status'=>(string)$thread['status']],
        'messages'=>mg_admin_agent_messages($pdo, (int)$thread['id'], 150),
        'action_reviews'=>mg_admin_agent_action_reviews($pdo, 50),
        'systematic'=>['database_only'=>true,'used_ai'=>false,'credits_used'=>0,'realtime'=>'sse_with_polling_fallback'],
        'generated_at'=>gmdate('Y-m-d H:i:s'),
    ];
}
