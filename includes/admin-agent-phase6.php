<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-agent-phase5.php';

const MG_ADMIN_AGENT_PHASE6_MIGRATION = 'database/20260719_main_admin_agent_phase6.sql';

function mg_admin_agent_phase6_tables(): array
{
    return [
        'admin_agent_phase6_settings',
        'admin_agent_scheduler_heartbeats',
        'admin_agent_continuity_alerts',
        'admin_agent_drill_schedules',
        'admin_agent_attestations',
        'admin_agent_readiness_checks',
        'admin_agent_continuity_brief_deliveries',
        'admin_agent_readiness_exports',
        'admin_agent_retention_previews',
    ];
}

function mg_admin_agent_phase6_schema_state(PDO $pdo): array
{
    $missing = [];
    foreach (mg_admin_agent_phase6_tables() as $table) {
        if (!mg_admin_schema_has_table($pdo, $table)) {
            $missing[] = $table;
        }
    }
    return ['ready' => $missing === [], 'missing_tables' => $missing, 'migration' => MG_ADMIN_AGENT_PHASE6_MIGRATION];
}

function mg_admin_agent_phase6_ready(PDO $pdo): bool
{
    return mg_admin_agent_phase6_schema_state($pdo)['ready'];
}

function mg_admin_agent_phase6_environment(string $value): string
{
    return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)) ?: 'production';
}

function mg_admin_agent_phase6_settings(PDO $pdo, string $environment = 'production'): array
{
    $environment = mg_admin_agent_phase6_environment($environment);
    $row = mg_admin_agent_safe_row($pdo, 'SELECT public_id,environment_key,continuity_alerts_enabled,daily_brief_enabled,daily_brief_hour_utc,weekly_brief_enabled,weekly_brief_day_utc,weekly_brief_hour_utc,expected_runner_interval_minutes,scheduler_stale_after_minutes,scorecard_retention_days,event_retention_days,resolved_alert_retention_days,updated_by_user_id,created_at,updated_at FROM admin_agent_phase6_settings WHERE environment_key=? LIMIT 1', [$environment]);
    if ($row === []) {
        $pdo->prepare('INSERT INTO admin_agent_phase6_settings (public_id,environment_key,created_at,updated_at) VALUES (?,?,NOW(),NOW())')->execute([mg_public_id(), $environment]);
        return mg_admin_agent_phase6_settings($pdo, $environment);
    }
    return [
        'id' => (string) $row['public_id'],
        'environment_key' => (string) $row['environment_key'],
        'continuity_alerts_enabled' => (bool) $row['continuity_alerts_enabled'],
        'daily_brief_enabled' => (bool) $row['daily_brief_enabled'],
        'daily_brief_hour_utc' => (int) $row['daily_brief_hour_utc'],
        'weekly_brief_enabled' => (bool) $row['weekly_brief_enabled'],
        'weekly_brief_day_utc' => (int) $row['weekly_brief_day_utc'],
        'weekly_brief_hour_utc' => (int) $row['weekly_brief_hour_utc'],
        'expected_runner_interval_minutes' => (int) $row['expected_runner_interval_minutes'],
        'scheduler_stale_after_minutes' => (int) $row['scheduler_stale_after_minutes'],
        'scorecard_retention_days' => (int) $row['scorecard_retention_days'],
        'event_retention_days' => (int) $row['event_retention_days'],
        'resolved_alert_retention_days' => (int) $row['resolved_alert_retention_days'],
        'updated_by_user_id' => $row['updated_by_user_id'] !== null ? (int) $row['updated_by_user_id'] : null,
        'created_at' => (string) $row['created_at'],
        'updated_at' => (string) $row['updated_at'],
    ];
}

function mg_admin_agent_phase6_update_settings(PDO $pdo, int $actorId, array $input): array
{
    $environment = mg_admin_agent_phase6_environment((string) ($input['environment_key'] ?? 'production'));
    mg_admin_agent_phase6_settings($pdo, $environment);
    $dailyHour = max(0, min(23, (int) ($input['daily_brief_hour_utc'] ?? 15)));
    $weeklyDay = max(0, min(6, (int) ($input['weekly_brief_day_utc'] ?? 1)));
    $weeklyHour = max(0, min(23, (int) ($input['weekly_brief_hour_utc'] ?? 15)));
    $interval = max(5, min(60, (int) ($input['expected_runner_interval_minutes'] ?? 5)));
    $stale = max($interval * 2, min(240, (int) ($input['scheduler_stale_after_minutes'] ?? 15)));
    $scoreDays = max(30, min(3650, (int) ($input['scorecard_retention_days'] ?? 365)));
    $eventDays = max(30, min(3650, (int) ($input['event_retention_days'] ?? 180)));
    $alertDays = max(30, min(3650, (int) ($input['resolved_alert_retention_days'] ?? 365)));
    $pdo->prepare('UPDATE admin_agent_phase6_settings SET continuity_alerts_enabled=?,daily_brief_enabled=?,daily_brief_hour_utc=?,weekly_brief_enabled=?,weekly_brief_day_utc=?,weekly_brief_hour_utc=?,expected_runner_interval_minutes=?,scheduler_stale_after_minutes=?,scorecard_retention_days=?,event_retention_days=?,resolved_alert_retention_days=?,updated_by_user_id=?,updated_at=NOW() WHERE environment_key=?')->execute([
        !empty($input['continuity_alerts_enabled']) ? 1 : 0,
        !empty($input['daily_brief_enabled']) ? 1 : 0,
        $dailyHour,
        !empty($input['weekly_brief_enabled']) ? 1 : 0,
        $weeklyDay,
        $weeklyHour,
        $interval,
        $stale,
        $scoreDays,
        $eventDays,
        $alertDays,
        $actorId,
        $environment,
    ]);
    mg_audit('admin_agent_phase6_settings_updated', 'system', ['environment' => $environment, 'runner_interval_minutes' => $interval, 'scheduler_stale_after_minutes' => $stale], $actorId);
    return mg_admin_agent_phase6_settings($pdo, $environment);
}

function mg_admin_agent_phase6_heartbeat_start(PDO $pdo, string $trigger, string $environment, ?int $actorId): array
{
    $trigger = in_array($trigger, ['scheduled', 'manual', 'workspace', 'setup'], true) ? $trigger : 'manual';
    $publicId = mg_public_id();
    $startedAt = gmdate('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO admin_agent_scheduler_heartbeats (public_id,runner_key,environment_key,trigger_source,status,started_at,initiated_by_user_id,created_at,updated_at) VALUES (?,"main_admin_agent_phase6",?,?,"running",?,?,NOW(),NOW())')->execute([$publicId, $environment, $trigger, $startedAt, $actorId]);
    return ['id' => $publicId, 'started_at' => $startedAt, 'started_microtime' => microtime(true), 'trigger_source' => $trigger];
}

function mg_admin_agent_phase6_heartbeat_finish(PDO $pdo, array $heartbeat, string $status, array $summary = [], ?Throwable $error = null): void
{
    $duration = max(0, (int) round((microtime(true) - (float) $heartbeat['started_microtime']) * 1000));
    $pdo->prepare('UPDATE admin_agent_scheduler_heartbeats SET status=?,completed_at=NOW(),duration_ms=?,summary_json=?,error_class=?,updated_at=NOW() WHERE public_id=?')->execute([
        $status,
        $duration,
        json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $error ? $error::class : null,
        (string) $heartbeat['id'],
    ]);
}

function mg_admin_agent_phase6_heartbeat_state(PDO $pdo, string $environment = 'production'): array
{
    $environment = mg_admin_agent_phase6_environment($environment);
    $settings = mg_admin_agent_phase6_settings($pdo, $environment);
    $latestAny = mg_admin_agent_safe_row($pdo, 'SELECT public_id,trigger_source,status,started_at,completed_at,duration_ms,error_class FROM admin_agent_scheduler_heartbeats WHERE runner_key="main_admin_agent_phase6" AND environment_key=? ORDER BY started_at DESC,id DESC LIMIT 1', [$environment]);
    $latestScheduled = mg_admin_agent_safe_row($pdo, 'SELECT public_id,trigger_source,status,started_at,completed_at,duration_ms,error_class FROM admin_agent_scheduler_heartbeats WHERE runner_key="main_admin_agent_phase6" AND environment_key=? AND trigger_source="scheduled" ORDER BY started_at DESC,id DESC LIMIT 1', [$environment]);
    $ageMinutes = null;
    if ($latestScheduled !== []) {
        $reference = (string) ($latestScheduled['completed_at'] ?: $latestScheduled['started_at']);
        $ageMinutes = max(0, (int) floor((time() - strtotime($reference . ' UTC')) / 60));
    }
    $configured = $latestScheduled !== [];
    $healthy = $configured && (string) $latestScheduled['status'] === 'succeeded' && $ageMinutes !== null && $ageMinutes <= (int) $settings['scheduler_stale_after_minutes'];
    return [
        'configured' => $configured,
        'healthy' => $healthy,
        'age_minutes' => $ageMinutes,
        'stale_after_minutes' => (int) $settings['scheduler_stale_after_minutes'],
        'expected_interval_minutes' => (int) $settings['expected_runner_interval_minutes'],
        'latest' => $latestAny === [] ? null : $latestAny,
        'latest_scheduled' => $latestScheduled === [] ? null : $latestScheduled,
        'status' => !$configured ? 'not_configured' : ($healthy ? 'healthy' : 'stale'),
    ];
}

function mg_admin_agent_phase6_notification(PDO $pdo, string $type, string $severity, string $title, string $message, array $metadata = [], ?int $ownerId = null, ?int $actorId = null): int
{
    $type = in_array($type, ['continuity_alert', 'recovery_drill_due', 'recovery_objective_breach', 'continuity_digest', 'scheduler_missed'], true) ? $type : 'continuity_alert';
    $severity = in_array($severity, ['info', 'warning', 'critical'], true) ? $severity : 'warning';
    $stmt = $pdo->prepare('INSERT INTO admin_queue_notifications (public_id,note_id,target_user_id,assigned_admin_user_id,actor_user_id,notification_type,severity,title,message,metadata_json,created_at) VALUES (?,NULL,NULL,?,?,?,?,?,?,?,NOW())');
    $stmt->execute([
        mg_public_id(),
        $ownerId,
        $actorId,
        $type,
        $severity,
        mb_substr($title, 0, 160),
        mb_substr($message, 0, 500),
        json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
    return (int) $pdo->lastInsertId();
}

function mg_admin_agent_phase6_alert_upsert(PDO $pdo, array $alert, bool $notificationsEnabled = true): string
{
    $key = hash('sha256', implode('|', [
        (string) ($alert['environment_key'] ?? 'production'),
        (string) $alert['alert_type'],
        (string) ($alert['source_key'] ?? $alert['gap_id'] ?? $alert['objective_id'] ?? $alert['drill_id'] ?? 'platform'),
    ]));
    $existing = mg_admin_agent_safe_row($pdo, 'SELECT id,public_id,status,notification_id FROM admin_agent_continuity_alerts WHERE alert_key=? LIMIT 1', [$key]);
    $notificationId = $existing['notification_id'] ?? null;
    if ($existing === [] && $notificationsEnabled) {
        $notificationType = match ((string) $alert['alert_type']) {
            'drill_due', 'drill_overdue' => 'recovery_drill_due',
            'objective_breach' => 'recovery_objective_breach',
            'scheduler_missed' => 'scheduler_missed',
            default => 'continuity_alert',
        };
        $notificationId = mg_admin_agent_phase6_notification(
            $pdo,
            $notificationType,
            (string) $alert['severity'],
            (string) $alert['title'],
            (string) $alert['message'],
            $alert['evidence'] ?? [],
            isset($alert['owner_user_id']) ? (int) $alert['owner_user_id'] : null,
            null
        );
    }
    $pdo->prepare('INSERT INTO admin_agent_continuity_alerts (public_id,alert_key,environment_key,gap_id,objective_id,drill_id,alert_type,severity,status,title,message,owner_user_id,notification_id,due_at,occurrence_count,evidence_json,first_seen_at,last_seen_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,? ,"open",?,?,?,?,?,1,?,NOW(),NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE gap_id=VALUES(gap_id),objective_id=VALUES(objective_id),drill_id=VALUES(drill_id),severity=VALUES(severity),status=IF(status IN ("resolved","dismissed"),"open",status),title=VALUES(title),message=VALUES(message),owner_user_id=VALUES(owner_user_id),notification_id=COALESCE(notification_id,VALUES(notification_id)),due_at=VALUES(due_at),occurrence_count=occurrence_count+1,evidence_json=VALUES(evidence_json),last_seen_at=NOW(),resolved_at=NULL,updated_at=NOW()')->execute([
        mg_public_id(),
        $key,
        (string) ($alert['environment_key'] ?? 'production'),
        $alert['gap_id'] ?? null,
        $alert['objective_id'] ?? null,
        $alert['drill_id'] ?? null,
        (string) $alert['alert_type'],
        (string) $alert['severity'],
        mb_substr((string) $alert['title'], 0, 180),
        mb_substr((string) $alert['message'], 0, 500),
        $alert['owner_user_id'] ?? null,
        $notificationId,
        $alert['due_at'] ?? null,
        json_encode($alert['evidence'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
    return $key;
}

function mg_admin_agent_phase6_sync_drill_schedules(PDO $pdo, string $environment = 'production'): int
{
    $environment = mg_admin_agent_phase6_environment($environment);
    $objectives = mg_admin_agent_safe_rows($pdo, 'SELECT o.id,o.public_id,o.environment_key,o.drill_interval_days,o.owner_user_id,o.updated_at,MAX(d.completed_at) last_passed_at FROM admin_agent_recovery_objectives o LEFT JOIN admin_agent_restore_drills d ON d.objective_id=o.id AND d.status="passed" WHERE o.environment_key=? AND o.status<>"retired" GROUP BY o.id,o.public_id,o.environment_key,o.drill_interval_days,o.owner_user_id,o.updated_at', [$environment]);
    $count = 0;
    foreach ($objectives as $objective) {
        $base = (string) ($objective['last_passed_at'] ?: $objective['updated_at']);
        $due = gmdate('Y-m-d H:i:s', strtotime($base . ' UTC +' . max(7, (int) $objective['drill_interval_days']) . ' days'));
        $key = hash('sha256', $environment . '|' . $objective['public_id'] . '|drill_schedule');
        $pdo->prepare('INSERT INTO admin_agent_drill_schedules (public_id,schedule_key,objective_id,environment_key,status,next_due_at,reminder_days_json,owner_user_id,created_at,updated_at) VALUES (?,?,?,? ,"active",?,JSON_ARRAY(30,14,7,1),?,NOW(),NOW()) ON DUPLICATE KEY UPDATE next_due_at=VALUES(next_due_at),owner_user_id=VALUES(owner_user_id),updated_at=NOW()')->execute([
            mg_public_id(), $key, (int) $objective['id'], $environment, $due, $objective['owner_user_id'],
        ]);
        $count++;
    }
    return $count;
}

function mg_admin_agent_phase6_evaluate_alerts(PDO $pdo, string $environment = 'production'): array
{
    $environment = mg_admin_agent_phase6_environment($environment);
    $settings = mg_admin_agent_phase6_settings($pdo, $environment);
    $notificationsEnabled = (bool) $settings['continuity_alerts_enabled'];
    $seen = [];
    $gaps = mg_admin_agent_safe_rows($pdo, 'SELECT g.id,g.public_id,g.gap_type,g.severity,g.status,g.title,g.details_text,g.recommendation_text,g.owner_user_id,g.objective_id,g.drill_id,g.evidence_json FROM admin_agent_recovery_gaps g LEFT JOIN admin_agent_recovery_objectives o ON o.id=g.objective_id WHERE g.status IN ("open","acknowledged","under_review") AND (o.environment_key=? OR o.environment_key IS NULL)', [$environment]);
    foreach ($gaps as $gap) {
        $severity = (string) $gap['severity'] === 'critical' ? 'critical' : ((string) $gap['severity'] === 'high' ? 'warning' : 'info');
        $type = match ((string) $gap['gap_type']) {
            'stale_backup' => 'evidence_stale',
            'failed_backup', 'evidence_incomplete' => 'evidence_failed',
            'rto_miss', 'rpo_miss', 'missing_objective' => 'objective_breach',
            'missing_drill', 'overdue_drill' => 'drill_overdue',
            default => 'recovery_gap',
        };
        $seen[] = mg_admin_agent_phase6_alert_upsert($pdo, [
            'environment_key' => $environment,
            'source_key' => 'gap:' . $gap['public_id'],
            'gap_id' => (int) $gap['id'],
            'objective_id' => $gap['objective_id'] !== null ? (int) $gap['objective_id'] : null,
            'drill_id' => $gap['drill_id'] !== null ? (int) $gap['drill_id'] : null,
            'alert_type' => $type,
            'severity' => $severity,
            'title' => (string) $gap['title'],
            'message' => mb_substr((string) $gap['details_text'] . ($gap['recommendation_text'] ? ' ' . $gap['recommendation_text'] : ''), 0, 500),
            'owner_user_id' => $gap['owner_user_id'] !== null ? (int) $gap['owner_user_id'] : null,
            'due_at' => gmdate('Y-m-d H:i:s', time() + ($severity === 'critical' ? 3600 : ($severity === 'warning' ? 86400 : 259200))),
            'evidence' => mg_admin_agent_json($gap['evidence_json'] ?? null),
        ], $notificationsEnabled);
    }

    $schedules = mg_admin_agent_safe_rows($pdo, 'SELECT s.id,s.public_id,s.objective_id,s.next_due_at,s.owner_user_id,o.public_id objective_public,o.criticality,svc.label service_label FROM admin_agent_drill_schedules s JOIN admin_agent_recovery_objectives o ON o.id=s.objective_id JOIN admin_agent_services svc ON svc.id=o.service_id WHERE s.environment_key=? AND s.status="active" AND s.next_due_at<=DATE_ADD(NOW(),INTERVAL 30 DAY)', [$environment]);
    foreach ($schedules as $schedule) {
        $days = (int) floor((strtotime((string) $schedule['next_due_at'] . ' UTC') - time()) / 86400);
        $overdue = $days < 0;
        $severity = $overdue && (string) $schedule['criticality'] === 'critical' ? 'critical' : ($overdue ? 'warning' : 'info');
        $seen[] = mg_admin_agent_phase6_alert_upsert($pdo, [
            'environment_key' => $environment,
            'source_key' => 'schedule:' . $schedule['public_id'],
            'objective_id' => (int) $schedule['objective_id'],
            'alert_type' => $overdue ? 'drill_overdue' : 'drill_due',
            'severity' => $severity,
            'title' => (string) $schedule['service_label'] . ($overdue ? ' recovery drill is overdue' : ' recovery drill is due soon'),
            'message' => $overdue ? 'The recovery drill due date passed ' . abs($days) . ' day(s) ago.' : 'The recovery drill is due in ' . max(0, $days) . ' day(s).',
            'owner_user_id' => $schedule['owner_user_id'] !== null ? (int) $schedule['owner_user_id'] : null,
            'due_at' => (string) $schedule['next_due_at'],
            'evidence' => ['objective_id' => $schedule['objective_public'], 'next_due_at' => $schedule['next_due_at'], 'days_remaining' => $days],
        ], $notificationsEnabled);
        $pdo->prepare('UPDATE admin_agent_drill_schedules SET last_reminder_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int) $schedule['id']]);
    }

    $criticalCards = mg_admin_agent_safe_rows($pdo, 'SELECT c.id,c.public_id,c.service_id,c.continuity_score,c.status,c.open_gap_total,c.critical_gap_total,c.evidence_json,s.public_id service_public,s.label service_label,o.id objective_id,o.owner_user_id FROM admin_agent_continuity_scorecards c JOIN admin_agent_services s ON s.id=c.service_id LEFT JOIN admin_agent_recovery_objectives o ON o.service_id=c.service_id AND o.environment_key=c.environment_key WHERE c.environment_key=? AND c.status="critical" AND c.id IN (SELECT MAX(c2.id) FROM admin_agent_continuity_scorecards c2 WHERE c2.environment_key=? GROUP BY c2.service_id)', [$environment, $environment]);
    foreach ($criticalCards as $card) {
        $seen[] = mg_admin_agent_phase6_alert_upsert($pdo, [
            'environment_key' => $environment,
            'source_key' => 'scorecard:' . $card['service_public'],
            'objective_id' => $card['objective_id'] !== null ? (int) $card['objective_id'] : null,
            'alert_type' => 'continuity_critical',
            'severity' => 'critical',
            'title' => (string) $card['service_label'] . ' continuity score is critical',
            'message' => 'Continuity score ' . (int) $card['continuity_score'] . '/100 with ' . (int) $card['open_gap_total'] . ' open gap(s) and ' . (int) $card['critical_gap_total'] . ' critical gap(s).',
            'owner_user_id' => $card['owner_user_id'] !== null ? (int) $card['owner_user_id'] : null,
            'due_at' => gmdate('Y-m-d H:i:s', time() + 3600),
            'evidence' => mg_admin_agent_json($card['evidence_json'] ?? null),
        ], $notificationsEnabled);
    }

    $heartbeat = mg_admin_agent_phase6_heartbeat_state($pdo, $environment);
    if ($heartbeat['configured'] && !$heartbeat['healthy']) {
        $seen[] = mg_admin_agent_phase6_alert_upsert($pdo, [
            'environment_key' => $environment,
            'source_key' => 'scheduler:main_admin_agent_phase6',
            'alert_type' => 'scheduler_missed',
            'severity' => 'critical',
            'title' => 'Main Admin Agent scheduled runner is stale',
            'message' => 'The latest scheduled Phase 6 run is ' . (int) ($heartbeat['age_minutes'] ?? 0) . ' minutes old. Expected freshness is ' . (int) $heartbeat['stale_after_minutes'] . ' minutes.',
            'due_at' => gmdate('Y-m-d H:i:s', time() + 1800),
            'evidence' => $heartbeat,
        ], $notificationsEnabled);
    }

    if ($seen === []) {
        $pdo->prepare('UPDATE admin_agent_continuity_alerts SET status="resolved",resolved_at=COALESCE(resolved_at,NOW()),updated_at=NOW() WHERE environment_key=? AND status IN ("open","acknowledged","escalated")')->execute([$environment]);
    } else {
        $marks = implode(',', array_fill(0, count($seen), '?'));
        $params = array_merge([$environment], $seen);
        $pdo->prepare('UPDATE admin_agent_continuity_alerts SET status="resolved",resolved_at=COALESCE(resolved_at,NOW()),updated_at=NOW() WHERE environment_key=? AND status IN ("open","acknowledged","escalated") AND alert_key NOT IN (' . $marks . ')')->execute($params);
    }

    $escalated = $pdo->prepare('UPDATE admin_agent_continuity_alerts SET status="escalated",escalated_at=COALESCE(escalated_at,NOW()),updated_at=NOW() WHERE environment_key=? AND status IN ("open","acknowledged") AND due_at IS NOT NULL AND due_at<NOW()');
    $escalated->execute([$environment]);
    return ['active_alert_keys' => count($seen), 'escalated' => $escalated->rowCount(), 'notifications_enabled' => $notificationsEnabled];
}

function mg_admin_agent_phase6_alerts(PDO $pdo, string $environment = 'production', int $limit = 100): array
{
    $environment = mg_admin_agent_phase6_environment($environment);
    $limit = max(10, min(200, $limit));
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT a.public_id,a.alert_type,a.severity,a.status,a.title,a.message,a.due_at,a.acknowledged_by_user_id,a.acknowledged_at,a.escalated_at,a.resolved_at,a.occurrence_count,a.evidence_json,a.first_seen_at,a.last_seen_at,a.owner_user_id,g.public_id gap_id,o.public_id objective_id,d.public_id drill_id FROM admin_agent_continuity_alerts a LEFT JOIN admin_agent_recovery_gaps g ON g.id=a.gap_id LEFT JOIN admin_agent_recovery_objectives o ON o.id=a.objective_id LEFT JOIN admin_agent_restore_drills d ON d.id=a.drill_id WHERE a.environment_key=? ORDER BY CASE a.status WHEN "escalated" THEN 1 WHEN "open" THEN 2 WHEN "acknowledged" THEN 3 ELSE 4 END,CASE a.severity WHEN "critical" THEN 1 WHEN "warning" THEN 2 ELSE 3 END,a.last_seen_at DESC LIMIT ' . $limit, [$environment]);
    return array_map(static fn(array $row): array => [
        'id' => (string) $row['public_id'],
        'alert_type' => (string) $row['alert_type'],
        'severity' => (string) $row['severity'],
        'status' => (string) $row['status'],
        'title' => (string) $row['title'],
        'message' => (string) $row['message'],
        'due_at' => $row['due_at'],
        'owner_user_id' => $row['owner_user_id'] !== null ? (int) $row['owner_user_id'] : null,
        'acknowledged_by_user_id' => $row['acknowledged_by_user_id'] !== null ? (int) $row['acknowledged_by_user_id'] : null,
        'acknowledged_at' => $row['acknowledged_at'],
        'escalated_at' => $row['escalated_at'],
        'resolved_at' => $row['resolved_at'],
        'occurrence_count' => (int) $row['occurrence_count'],
        'evidence' => mg_admin_agent_json($row['evidence_json'] ?? null),
        'first_seen_at' => (string) $row['first_seen_at'],
        'last_seen_at' => (string) $row['last_seen_at'],
        'gap_id' => $row['gap_id'],
        'objective_id' => $row['objective_id'],
        'drill_id' => $row['drill_id'],
    ], $rows);
}

function mg_admin_agent_phase6_alert_action(PDO $pdo, int $actorId, array $input): array
{
    $publicId = trim((string) ($input['alert_id'] ?? ''));
    $action = strtolower(trim((string) ($input['alert_action'] ?? 'acknowledge')));
    $row = mg_admin_agent_safe_row($pdo, 'SELECT id,public_id,status FROM admin_agent_continuity_alerts WHERE public_id=? LIMIT 1', [$publicId]);
    if ($row === []) {
        throw new InvalidArgumentException('Continuity alert not found.');
    }
    if ($action === 'acknowledge') {
        $pdo->prepare('UPDATE admin_agent_continuity_alerts SET status="acknowledged",acknowledged_by_user_id=?,acknowledged_at=NOW(),updated_at=NOW() WHERE id=? AND status IN ("open","escalated")')->execute([$actorId, (int) $row['id']]);
    } elseif (in_array($action, ['resolve', 'dismiss'], true)) {
        $note = mb_substr(trim((string) ($input['note'] ?? '')), 0, 1000);
        if ($note === '') {
            throw new InvalidArgumentException('A resolution or dismissal note is required.');
        }
        $status = $action === 'resolve' ? 'resolved' : 'dismissed';
        $pdo->prepare('UPDATE admin_agent_continuity_alerts SET status=?,resolved_at=NOW(),evidence_json=JSON_SET(COALESCE(evidence_json,JSON_OBJECT()),"$.closure_note",?,"$.closed_by_user_id",?),updated_at=NOW() WHERE id=?')->execute([$status, $note, $actorId, (int) $row['id']]);
    } else {
        throw new InvalidArgumentException('Unknown continuity alert action.');
    }
    mg_audit('admin_agent_phase6_alert_action', 'system', ['alert_id' => $publicId, 'action' => $action], $actorId);
    return ['alert_id' => $publicId, 'action' => $action, 'updated' => true];
}

function mg_admin_agent_phase6_drill_schedules(PDO $pdo, string $environment = 'production'): array
{
    $environment = mg_admin_agent_phase6_environment($environment);
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT ds.public_id,ds.status,ds.next_due_at,ds.reminder_days_json,ds.last_reminder_at,ds.owner_user_id,ds.updated_at,o.public_id objective_id,o.criticality,o.drill_interval_days,s.public_id service_id,s.service_key,s.label service_label FROM admin_agent_drill_schedules ds JOIN admin_agent_recovery_objectives o ON o.id=ds.objective_id JOIN admin_agent_services s ON s.id=o.service_id WHERE ds.environment_key=? ORDER BY ds.next_due_at,s.label', [$environment]);
    return array_map(static fn(array $row): array => [
        'id' => (string) $row['public_id'],
        'status' => (string) $row['status'],
        'next_due_at' => (string) $row['next_due_at'],
        'days_remaining' => (int) floor((strtotime((string) $row['next_due_at'] . ' UTC') - time()) / 86400),
        'reminder_days' => mg_admin_agent_json($row['reminder_days_json'] ?? null),
        'last_reminder_at' => $row['last_reminder_at'],
        'owner_user_id' => $row['owner_user_id'] !== null ? (int) $row['owner_user_id'] : null,
        'updated_at' => (string) $row['updated_at'],
        'objective' => ['id' => (string) $row['objective_id'], 'criticality' => (string) $row['criticality'], 'drill_interval_days' => (int) $row['drill_interval_days']],
        'service' => ['id' => (string) $row['service_id'], 'service_key' => (string) $row['service_key'], 'label' => (string) $row['service_label']],
    ], $rows);
}

function mg_admin_agent_phase6_schedule_action(PDO $pdo, int $actorId, array $input): array
{
    $publicId = trim((string) ($input['schedule_id'] ?? ''));
    $row = mg_admin_agent_safe_row($pdo, 'SELECT id,public_id FROM admin_agent_drill_schedules WHERE public_id=? LIMIT 1', [$publicId]);
    if ($row === []) {
        throw new InvalidArgumentException('Recovery drill schedule not found.');
    }
    $status = strtolower(trim((string) ($input['status'] ?? 'active')));
    if (!in_array($status, ['active', 'paused', 'completed'], true)) {
        throw new InvalidArgumentException('Invalid drill schedule status.');
    }
    $dueRaw = trim((string) ($input['next_due_at'] ?? ''));
    if ($dueRaw === '' || strtotime($dueRaw) === false) {
        throw new InvalidArgumentException('A valid next drill due date is required.');
    }
    $due = gmdate('Y-m-d H:i:s', strtotime($dueRaw));
    $pdo->prepare('UPDATE admin_agent_drill_schedules SET status=?,next_due_at=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$status, $due, $actorId, (int) $row['id']]);
    mg_audit('admin_agent_phase6_drill_schedule_updated', 'system', ['schedule_id' => $publicId, 'status' => $status, 'next_due_at' => $due], $actorId);
    return ['schedule_id' => $publicId, 'status' => $status, 'next_due_at' => $due];
}

function mg_admin_agent_phase6_attest(PDO $pdo, int $actorId, array $input): array
{
    $subjectType = strtolower(trim((string) ($input['subject_type'] ?? '')));
    if (!in_array($subjectType, ['objective', 'backup_evidence', 'restore_drill', 'recovery_plan', 'readiness_export'], true)) {
        throw new InvalidArgumentException('Invalid attestation subject type.');
    }
    $subjectId = trim((string) ($input['subject_id'] ?? ''));
    if (!preg_match('/^[a-zA-Z0-9_-]{10,40}$/', $subjectId)) {
        throw new InvalidArgumentException('Invalid attestation subject identifier.');
    }
    $type = strtolower(trim((string) ($input['attestation_type'] ?? 'reviewed')));
    if (!in_array($type, ['reviewed', 'approved', 'verified', 'accepted_risk'], true)) {
        throw new InvalidArgumentException('Invalid attestation type.');
    }
    $statement = mb_substr(trim((string) ($input['statement_text'] ?? '')), 0, 500);
    if ($statement === '') {
        throw new InvalidArgumentException('An attestation statement is required.');
    }
    $key = hash('sha256', $subjectType . '|' . $subjectId . '|' . $type . '|' . $actorId . '|' . gmdate('Y-m-d-H-i'));
    $publicId = mg_public_id();
    $pdo->prepare('INSERT INTO admin_agent_attestations (public_id,attestation_key,subject_type,subject_public_id,attestation_type,statement_text,attested_by_user_id,attested_at,evidence_json,created_at) VALUES (?,?,?,?,?,?,?,NOW(),?,NOW())')->execute([
        $publicId,
        $key,
        $subjectType,
        $subjectId,
        $type,
        $statement,
        $actorId,
        json_encode(['ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli')), 'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'cli'), 0, 200)], JSON_UNESCAPED_SLASHES),
    ]);
    mg_audit('admin_agent_phase6_attestation_created', 'system', ['attestation_id' => $publicId, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'attestation_type' => $type], $actorId);
    return ['id' => $publicId, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'attestation_type' => $type];
}

function mg_admin_agent_phase6_attestations(PDO $pdo, int $limit = 100): array
{
    $limit = max(10, min(200, $limit));
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT public_id,subject_type,subject_public_id,attestation_type,statement_text,attested_by_user_id,attested_at,evidence_json FROM admin_agent_attestations ORDER BY attested_at DESC,id DESC LIMIT ' . $limit);
    return array_map(static fn(array $row): array => [
        'id' => (string) $row['public_id'],
        'subject_type' => (string) $row['subject_type'],
        'subject_id' => (string) $row['subject_public_id'],
        'attestation_type' => (string) $row['attestation_type'],
        'statement_text' => (string) $row['statement_text'],
        'attested_by_user_id' => (int) $row['attested_by_user_id'],
        'attested_at' => (string) $row['attested_at'],
        'evidence' => mg_admin_agent_json($row['evidence_json'] ?? null),
    ], $rows);
}

function mg_admin_agent_phase6_check_upsert(PDO $pdo, string $environment, string $key, string $title, string $category, string $status, string $details, ?string $action, array $evidence = [], bool $required = true): void
{
    $pdo->prepare('INSERT INTO admin_agent_readiness_checks (public_id,environment_key,check_key,title,category,required_for_production,status,details_text,action_text,evidence_json,checked_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE title=VALUES(title),category=VALUES(category),required_for_production=VALUES(required_for_production),status=VALUES(status),details_text=VALUES(details_text),action_text=VALUES(action_text),evidence_json=VALUES(evidence_json),checked_at=NOW(),updated_at=NOW()')->execute([
        mg_public_id(), $environment, $key, $title, $category, $required ? 1 : 0, $status, mb_substr($details, 0, 500), $action ? mb_substr($action, 0, 500) : null, json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
}

function mg_admin_agent_phase6_evaluate_readiness(PDO $pdo, string $environment = 'production'): array
{
    $environment = mg_admin_agent_phase6_environment($environment);
    $settings = mg_admin_agent_phase6_settings($pdo, $environment);
    $heartbeat = mg_admin_agent_phase6_heartbeat_state($pdo, $environment);
    $objectives = mg_admin_agent_safe_row($pdo, 'SELECT COUNT(*) total,SUM(status="active") active_total,SUM(status="needs_review") review_total FROM admin_agent_recovery_objectives WHERE environment_key=? AND status<>"retired"', [$environment]);
    $plans = mg_admin_agent_safe_row($pdo, 'SELECT COUNT(*) total,SUM(status="ready") ready_total FROM admin_agent_recovery_plans WHERE environment_key=? AND status<>"retired" AND id IN (SELECT MAX(p2.id) FROM admin_agent_recovery_plans p2 WHERE p2.environment_key=? GROUP BY p2.service_id)', [$environment, $environment]);
    $evidence = mg_admin_agent_safe_row($pdo, 'SELECT public_id,status,recorded_at,canary_verified,manifest_verified,migration_status_verified FROM admin_agent_backup_evidence WHERE environment_key=? AND scope_key="database" ORDER BY recorded_at DESC,id DESC LIMIT 1', [$environment]);
    $criticalHigh = mg_admin_agent_safe_row($pdo, 'SELECT COUNT(*) objective_total,SUM(EXISTS(SELECT 1 FROM admin_agent_restore_drills d WHERE d.objective_id=o.id AND d.status="passed" AND d.completed_at>=DATE_SUB(NOW(),INTERVAL o.drill_interval_days DAY))) verified_total FROM admin_agent_recovery_objectives o WHERE o.environment_key=? AND o.status="active" AND o.criticality IN ("critical","high")', [$environment]);
    $alerts = mg_admin_agent_safe_row($pdo, 'SELECT COUNT(*) active_total,SUM(severity="critical") critical_total,SUM(status="escalated") escalated_total FROM admin_agent_continuity_alerts WHERE environment_key=? AND status IN ("open","acknowledged","escalated")', [$environment]);
    $latestScan = mg_admin_agent_last_scan($pdo);
    $scanAge = null;
    if ($latestScan && !empty($latestScan['completed_at'])) {
        $scanAge = max(0, (int) floor((time() - strtotime((string) $latestScan['completed_at'] . ' UTC')) / 60));
    }

    $checks = [];
    $checks['phase6_schema'] = ['title' => 'Phase 6 database schema', 'category' => 'configuration', 'status' => 'passed', 'details' => 'All Phase 6 readiness tables are available.', 'action' => null, 'evidence' => ['migration' => MG_ADMIN_AGENT_PHASE6_MIGRATION]];
    $checks['analysis_current'] = ['title' => 'System analysis is current', 'category' => 'monitoring', 'status' => $scanAge !== null && $scanAge <= 15 ? 'passed' : ($scanAge !== null && $scanAge <= 60 ? 'warning' : 'failed'), 'details' => $scanAge === null ? 'No completed Admin Agent analysis was found.' : 'Latest completed analysis is ' . $scanAge . ' minute(s) old.', 'action' => 'Use Run final readiness check on the Admin Agent page.', 'evidence' => ['age_minutes' => $scanAge]];
    $checks['scheduler_active'] = ['title' => 'Automatic scheduler is active', 'category' => 'automation', 'status' => $heartbeat['healthy'] ? 'passed' : ($heartbeat['configured'] ? 'failed' : 'not_configured'), 'details' => $heartbeat['healthy'] ? 'Scheduled Phase 6 runs are current.' : ($heartbeat['configured'] ? 'Scheduled runner heartbeat is stale.' : 'No scheduled Phase 6 heartbeat has been recorded. Manual operation remains available.'), 'action' => 'Add the displayed Phase 6 cron command through the hosting control panel, or continue using the one-click manual readiness check.', 'evidence' => $heartbeat];
    $objectiveTotal = (int) ($objectives['total'] ?? 0);
    $objectiveActive = (int) ($objectives['active_total'] ?? 0);
    $checks['objectives_reviewed'] = ['title' => 'Recovery objectives are reviewed', 'category' => 'governance', 'status' => $objectiveTotal > 0 && $objectiveActive === $objectiveTotal ? 'passed' : 'failed', 'details' => $objectiveActive . ' of ' . $objectiveTotal . ' recovery objectives are active.', 'action' => 'Review remaining recovery objectives in the Admin Agent controls.', 'evidence' => $objectives];
    $planTotal = (int) ($plans['total'] ?? 0);
    $planReady = (int) ($plans['ready_total'] ?? 0);
    $checks['plans_ready'] = ['title' => 'Recovery plans are ready', 'category' => 'governance', 'status' => $planTotal > 0 && $planReady === $planTotal ? 'passed' : 'failed', 'details' => $planReady . ' of ' . $planTotal . ' current recovery plans are ready.', 'action' => 'Review recovery order, owner, runbook path, prerequisites, and validation steps.', 'evidence' => $plans];
    $evidencePassed = $evidence !== [] && (string) $evidence['status'] === 'passed' && (bool) $evidence['canary_verified'] && (bool) $evidence['manifest_verified'] && (bool) $evidence['migration_status_verified'];
    $checks['evidence_current'] = ['title' => 'Backup and restore evidence is verified', 'category' => 'evidence', 'status' => $evidencePassed ? 'passed' : 'failed', 'details' => $evidencePassed ? 'Latest imported validator evidence passed canary, manifest, and migration-state verification.' : 'Passing validator evidence has not been imported.', 'action' => 'Use Upload validator JSON on the Admin Agent page.', 'evidence' => $evidence];
    $drillTotal = (int) ($criticalHigh['objective_total'] ?? 0);
    $drillVerified = (int) ($criticalHigh['verified_total'] ?? 0);
    $checks['critical_drills_verified'] = ['title' => 'Critical and high-tier drills are verified', 'category' => 'drills', 'status' => $drillTotal === 0 || $drillVerified === $drillTotal ? 'passed' : 'failed', 'details' => $drillVerified . ' of ' . $drillTotal . ' critical/high recovery objectives have a current approved drill.', 'action' => 'Plan, externally perform, record, and approve overdue recovery drills.', 'evidence' => $criticalHigh];
    $criticalAlerts = (int) ($alerts['critical_total'] ?? 0);
    $checks['alerts_clear'] = ['title' => 'Critical continuity alerts are clear', 'category' => 'alerting', 'status' => $criticalAlerts === 0 ? 'passed' : 'failed', 'details' => $criticalAlerts . ' critical continuity alert(s) are active.', 'action' => 'Open the Continuity Alerts rail and resolve the underlying recovery gaps.', 'evidence' => $alerts];
    $checks['alert_delivery_enabled'] = ['title' => 'Continuity alert delivery is enabled', 'category' => 'alerting', 'status' => (bool) $settings['continuity_alerts_enabled'] ? 'passed' : 'warning', 'details' => (bool) $settings['continuity_alerts_enabled'] ? 'Continuity alerts are delivered to the Admin Notification Center.' : 'Continuity alert delivery is disabled.', 'action' => 'Enable continuity alerts in Phase 6 settings.', 'evidence' => ['enabled' => (bool) $settings['continuity_alerts_enabled']]];

    foreach ($checks as $key => $check) {
        mg_admin_agent_phase6_check_upsert($pdo, $environment, $key, $check['title'], $check['category'], $check['status'], $check['details'], $check['action'], $check['evidence']);
    }
    $rows = mg_admin_agent_phase6_readiness_checks($pdo, $environment);
    $required = array_values(array_filter($rows, static fn(array $row): bool => $row['required_for_production']));
    $passed = count(array_filter($required, static fn(array $row): bool => $row['status'] === 'passed'));
    $score = $required === [] ? 0 : (int) round(($passed / count($required)) * 100);
    $failed = count(array_filter($required, static fn(array $row): bool => in_array($row['status'], ['failed', 'not_configured'], true)));
    $status = $failed === 0 && $score === 100 ? 'production_ready' : ($score >= 70 ? 'attention_required' : 'not_ready');
    return ['status' => $status, 'score' => $score, 'passed' => $passed, 'required' => count($required), 'failed' => $failed, 'checks' => $rows];
}

function mg_admin_agent_phase6_readiness_checks(PDO $pdo, string $environment = 'production'): array
{
    $environment = mg_admin_agent_phase6_environment($environment);
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT public_id,check_key,title,category,required_for_production,status,details_text,action_text,evidence_json,checked_at,updated_at FROM admin_agent_readiness_checks WHERE environment_key=? ORDER BY CASE status WHEN "failed" THEN 1 WHEN "not_configured" THEN 2 WHEN "warning" THEN 3 ELSE 4 END,category,title', [$environment]);
    return array_map(static fn(array $row): array => [
        'id' => (string) $row['public_id'],
        'check_key' => (string) $row['check_key'],
        'title' => (string) $row['title'],
        'category' => (string) $row['category'],
        'required_for_production' => (bool) $row['required_for_production'],
        'status' => (string) $row['status'],
        'details_text' => (string) $row['details_text'],
        'action_text' => $row['action_text'],
        'evidence' => mg_admin_agent_json($row['evidence_json'] ?? null),
        'checked_at' => (string) $row['checked_at'],
        'updated_at' => (string) $row['updated_at'],
    ], $rows);
}

function mg_admin_agent_phase6_generate_brief(PDO $pdo, string $periodType, string $environment = 'production', ?int $actorId = null, bool $deliver = true): array
{
    $environment = mg_admin_agent_phase6_environment($environment);
    $periodType = in_array($periodType, ['daily', 'weekly', 'manual'], true) ? $periodType : 'manual';
    $periodKey = match ($periodType) {
        'daily' => gmdate('Y-m-d'),
        'weekly' => gmdate('o-\WW'),
        default => gmdate('Y-m-d-H-i-s'),
    };
    $key = hash('sha256', $environment . '|' . $periodType . '|' . $periodKey);
    $existing = mg_admin_agent_safe_row($pdo, 'SELECT public_id,status,title,summary_text,generated_at,delivered_at FROM admin_agent_continuity_brief_deliveries WHERE delivery_key=? LIMIT 1', [$key]);
    if ($existing !== []) {
        return ['id' => (string) $existing['public_id'], 'status' => (string) $existing['status'], 'title' => (string) $existing['title'], 'summary_text' => (string) $existing['summary_text'], 'generated_at' => (string) $existing['generated_at'], 'delivered_at' => $existing['delivered_at'], 'already_generated' => true];
    }
    $readiness = mg_admin_agent_phase6_evaluate_readiness($pdo, $environment);
    $alerts = mg_admin_agent_safe_row($pdo, 'SELECT COUNT(*) active_total,SUM(severity="critical") critical_total,SUM(status="escalated") escalated_total FROM admin_agent_continuity_alerts WHERE environment_key=? AND status IN ("open","acknowledged","escalated")', [$environment]);
    $drills = mg_admin_agent_safe_row($pdo, 'SELECT COUNT(*) due_total,SUM(next_due_at<NOW()) overdue_total FROM admin_agent_drill_schedules WHERE environment_key=? AND status="active" AND next_due_at<=DATE_ADD(NOW(),INTERVAL 30 DAY)', [$environment]);
    $title = ucfirst($periodType) . ' continuity readiness brief';
    $summary = 'Readiness ' . (int) $readiness['score'] . '/100 (' . str_replace('_', ' ', (string) $readiness['status']) . '). ' . (int) ($alerts['active_total'] ?? 0) . ' active continuity alert(s), ' . (int) ($alerts['critical_total'] ?? 0) . ' critical, ' . (int) ($alerts['escalated_total'] ?? 0) . ' escalated. ' . (int) ($drills['due_total'] ?? 0) . ' drill(s) due within 30 days, ' . (int) ($drills['overdue_total'] ?? 0) . ' overdue.';
    $payload = ['readiness' => $readiness, 'alerts' => $alerts, 'drills' => $drills, 'database_only' => true, 'used_ai' => false, 'credits_used' => 0];
    $notificationId = null;
    $status = 'generated';
    $deliveredAt = null;
    if ($deliver) {
        $notificationId = mg_admin_agent_phase6_notification($pdo, 'continuity_digest', (int) ($alerts['critical_total'] ?? 0) > 0 ? 'critical' : ((int) ($alerts['active_total'] ?? 0) > 0 ? 'warning' : 'info'), $title, $summary, $payload, null, $actorId);
        $status = 'delivered';
        $deliveredAt = gmdate('Y-m-d H:i:s');
    }
    $publicId = mg_public_id();
    $pdo->prepare('INSERT INTO admin_agent_continuity_brief_deliveries (public_id,delivery_key,environment_key,period_type,period_key,status,notification_id,title,summary_text,payload_json,generated_by_user_id,generated_at,delivered_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW())')->execute([
        $publicId, $key, $environment, $periodType, $periodKey, $status, $notificationId, $title, $summary, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $actorId, $deliveredAt,
    ]);
    mg_audit('admin_agent_phase6_brief_generated', 'system', ['brief_id' => $publicId, 'period_type' => $periodType, 'period_key' => $periodKey, 'delivered' => $deliver], $actorId);
    return ['id' => $publicId, 'status' => $status, 'title' => $title, 'summary_text' => $summary, 'generated_at' => gmdate('Y-m-d H:i:s'), 'delivered_at' => $deliveredAt];
}

function mg_admin_agent_phase6_maybe_generate_scheduled_briefs(PDO $pdo, string $environment = 'production'): array
{
    $settings = mg_admin_agent_phase6_settings($pdo, $environment);
    $generated = [];
    $hour = (int) gmdate('G');
    $weekday = (int) gmdate('w');
    if ((bool) $settings['daily_brief_enabled'] && $hour === (int) $settings['daily_brief_hour_utc']) {
        $generated[] = mg_admin_agent_phase6_generate_brief($pdo, 'daily', $environment, null, true);
    }
    if ((bool) $settings['weekly_brief_enabled'] && $weekday === (int) $settings['weekly_brief_day_utc'] && $hour === (int) $settings['weekly_brief_hour_utc']) {
        $generated[] = mg_admin_agent_phase6_generate_brief($pdo, 'weekly', $environment, null, true);
    }
    return $generated;
}

function mg_admin_agent_phase6_briefs(PDO $pdo, string $environment = 'production', int $limit = 30): array
{
    $limit = max(5, min(100, $limit));
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT public_id,period_type,period_key,status,title,summary_text,payload_json,generated_by_user_id,generated_at,delivered_at FROM admin_agent_continuity_brief_deliveries WHERE environment_key=? ORDER BY generated_at DESC,id DESC LIMIT ' . $limit, [mg_admin_agent_phase6_environment($environment)]);
    return array_map(static fn(array $row): array => [
        'id' => (string) $row['public_id'], 'period_type' => (string) $row['period_type'], 'period_key' => (string) $row['period_key'], 'status' => (string) $row['status'], 'title' => (string) $row['title'], 'summary_text' => (string) $row['summary_text'], 'payload' => mg_admin_agent_json($row['payload_json'] ?? null), 'generated_by_user_id' => $row['generated_by_user_id'] !== null ? (int) $row['generated_by_user_id'] : null, 'generated_at' => (string) $row['generated_at'], 'delivered_at' => $row['delivered_at'],
    ], $rows);
}

function mg_admin_agent_phase6_retention_preview(PDO $pdo, string $environment = 'production', ?int $actorId = null): array
{
    $environment = mg_admin_agent_phase6_environment($environment);
    $settings = mg_admin_agent_phase6_settings($pdo, $environment);
    $scorecards = mg_admin_agent_safe_row($pdo, 'SELECT COUNT(*) total FROM admin_agent_continuity_scorecards WHERE environment_key=? AND generated_at<DATE_SUB(NOW(),INTERVAL ? DAY)', [$environment, (int) $settings['scorecard_retention_days']]);
    $events = mg_admin_agent_safe_row($pdo, 'SELECT COUNT(*) total FROM admin_agent_events WHERE occurred_at<DATE_SUB(NOW(),INTERVAL ? DAY)', [(int) $settings['event_retention_days']]);
    $alerts = mg_admin_agent_safe_row($pdo, 'SELECT COUNT(*) total FROM admin_agent_continuity_alerts WHERE environment_key=? AND status IN ("resolved","dismissed") AND updated_at<DATE_SUB(NOW(),INTERVAL ? DAY)', [$environment, (int) $settings['resolved_alert_retention_days']]);
    $payload = [
        'scorecards_eligible' => (int) ($scorecards['total'] ?? 0),
        'events_eligible' => (int) ($events['total'] ?? 0),
        'resolved_alerts_eligible' => (int) ($alerts['total'] ?? 0),
        'policy' => [
            'scorecard_retention_days' => (int) $settings['scorecard_retention_days'],
            'event_retention_days' => (int) $settings['event_retention_days'],
            'resolved_alert_retention_days' => (int) $settings['resolved_alert_retention_days'],
            'execution' => 'preview_only',
        ],
        'generated_at' => gmdate('Y-m-d H:i:s'),
    ];
    $key = hash('sha256', $environment . '|' . gmdate('Y-m-d-H'));
    $pdo->prepare('INSERT INTO admin_agent_retention_previews (public_id,preview_key,environment_key,scorecards_eligible,events_eligible,resolved_alerts_eligible,policy_json,generated_by_user_id,generated_at,created_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE scorecards_eligible=VALUES(scorecards_eligible),events_eligible=VALUES(events_eligible),resolved_alerts_eligible=VALUES(resolved_alerts_eligible),policy_json=VALUES(policy_json),generated_by_user_id=VALUES(generated_by_user_id),generated_at=NOW()')->execute([
        mg_public_id(), $key, $environment, $payload['scorecards_eligible'], $payload['events_eligible'], $payload['resolved_alerts_eligible'], json_encode($payload['policy'], JSON_UNESCAPED_SLASHES), $actorId,
    ]);
    return $payload;
}

function mg_admin_agent_phase6_generate_export(PDO $pdo, string $environment = 'production', ?int $actorId = null): array
{
    $environment = mg_admin_agent_phase6_environment($environment);
    $readiness = mg_admin_agent_phase6_evaluate_readiness($pdo, $environment);
    $payload = [
        'export_type' => 'microgifter_main_admin_agent_readiness',
        'export_version' => 1,
        'environment' => $environment,
        'generated_at' => gmdate('c'),
        'readiness' => $readiness,
        'scheduler' => mg_admin_agent_phase6_heartbeat_state($pdo, $environment),
        'settings' => mg_admin_agent_phase6_settings($pdo, $environment),
        'continuity_scorecards' => mg_admin_agent_phase5_scorecards($pdo, $environment),
        'active_alerts' => array_values(array_filter(mg_admin_agent_phase6_alerts($pdo, $environment), static fn(array $alert): bool => in_array($alert['status'], ['open', 'acknowledged', 'escalated'], true))),
        'drill_schedules' => mg_admin_agent_phase6_drill_schedules($pdo, $environment),
        'recovery_objectives' => mg_admin_agent_phase5_objectives($pdo, $environment),
        'recovery_plans' => mg_admin_agent_phase5_plans($pdo, $environment),
        'latest_backup_evidence' => array_slice(mg_admin_agent_phase5_backup_evidence($pdo, $environment), 0, 5),
        'latest_restore_drills' => array_slice(mg_admin_agent_phase5_drills($pdo, $environment), 0, 20),
        'attestations' => mg_admin_agent_phase6_attestations($pdo, 100),
        'retention_preview' => mg_admin_agent_phase6_retention_preview($pdo, $environment, $actorId),
        'database_only' => true,
        'used_ai' => false,
        'credits_used' => 0,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    $key = hash('sha256', $environment . '|' . gmdate('c') . '|' . hash('sha256', $json));
    $publicId = mg_public_id();
    $pdo->prepare('INSERT INTO admin_agent_readiness_exports (public_id,export_key,environment_key,export_version,readiness_status,readiness_score,summary_json,export_json,generated_by_user_id,generated_at,created_at) VALUES (?,?,?,1,?,?,?,?,?,NOW(),NOW())')->execute([
        $publicId, $key, $environment, (string) $readiness['status'], (int) $readiness['score'], json_encode(['status' => $readiness['status'], 'score' => $readiness['score'], 'passed' => $readiness['passed'], 'required' => $readiness['required']], JSON_UNESCAPED_SLASHES), $json, $actorId,
    ]);
    mg_audit('admin_agent_phase6_readiness_export_generated', 'system', ['export_id' => $publicId, 'environment' => $environment, 'status' => $readiness['status'], 'score' => $readiness['score']], $actorId);
    return ['id' => $publicId, 'status' => (string) $readiness['status'], 'score' => (int) $readiness['score'], 'json' => $json, 'filename' => 'microgifter-readiness-' . $environment . '-' . gmdate('Ymd-His') . '.json'];
}

function mg_admin_agent_phase6_exports(PDO $pdo, string $environment = 'production', int $limit = 20): array
{
    $limit = max(5, min(100, $limit));
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT public_id,export_version,readiness_status,readiness_score,summary_json,generated_by_user_id,generated_at FROM admin_agent_readiness_exports WHERE environment_key=? ORDER BY generated_at DESC,id DESC LIMIT ' . $limit, [mg_admin_agent_phase6_environment($environment)]);
    return array_map(static fn(array $row): array => [
        'id' => (string) $row['public_id'], 'export_version' => (int) $row['export_version'], 'readiness_status' => (string) $row['readiness_status'], 'readiness_score' => (int) $row['readiness_score'], 'summary' => mg_admin_agent_json($row['summary_json'] ?? null), 'generated_by_user_id' => $row['generated_by_user_id'] !== null ? (int) $row['generated_by_user_id'] : null, 'generated_at' => (string) $row['generated_at'],
    ], $rows);
}

function mg_admin_agent_phase6_export_json(PDO $pdo, string $publicId): array
{
    $row = mg_admin_agent_safe_row($pdo, 'SELECT public_id,environment_key,readiness_status,readiness_score,export_json,generated_at FROM admin_agent_readiness_exports WHERE public_id=? LIMIT 1', [$publicId]);
    if ($row === []) {
        throw new InvalidArgumentException('Readiness export not found.');
    }
    return ['id' => (string) $row['public_id'], 'environment_key' => (string) $row['environment_key'], 'readiness_status' => (string) $row['readiness_status'], 'readiness_score' => (int) $row['readiness_score'], 'generated_at' => (string) $row['generated_at'], 'json' => (string) $row['export_json'], 'filename' => 'microgifter-readiness-' . (string) $row['environment_key'] . '-' . str_replace(['-', ':', ' '], '', (string) $row['generated_at']) . '.json'];
}

function mg_admin_agent_phase6_run(PDO $pdo, array $options = []): array
{
    if (!mg_admin_agent_phase6_ready($pdo)) {
        throw new RuntimeException('Main Admin Agent Phase 6 SQL migration is required.');
    }
    $environment = mg_admin_agent_phase6_environment((string) ($options['environment_key'] ?? 'production'));
    $trigger = strtolower((string) ($options['trigger_source'] ?? 'manual'));
    $actorId = isset($options['initiated_by_user_id']) && (int) $options['initiated_by_user_id'] > 0 ? (int) $options['initiated_by_user_id'] : null;
    $heartbeat = mg_admin_agent_phase6_heartbeat_start($pdo, $trigger, $environment, $actorId);
    try {
        $phase5 = mg_admin_agent_phase5_run($pdo, $options + ['environment_key' => $environment]);
        $schedules = mg_admin_agent_phase6_sync_drill_schedules($pdo, $environment);
        $alerts = mg_admin_agent_phase6_evaluate_alerts($pdo, $environment);
        $readiness = mg_admin_agent_phase6_evaluate_readiness($pdo, $environment);
        $briefs = $trigger === 'scheduled' ? mg_admin_agent_phase6_maybe_generate_scheduled_briefs($pdo, $environment) : [];
        $retention = mg_admin_agent_phase6_retention_preview($pdo, $environment, $actorId);
        $summary = ['phase5' => $phase5, 'schedules_synced' => $schedules, 'alerts' => $alerts, 'readiness' => $readiness, 'briefs' => $briefs, 'retention_preview' => $retention, 'database_only' => true, 'used_ai' => false, 'credits_used' => 0];
        mg_admin_agent_phase6_heartbeat_finish($pdo, $heartbeat, 'succeeded', ['readiness_status' => $readiness['status'], 'readiness_score' => $readiness['score'], 'active_alert_keys' => $alerts['active_alert_keys']]);
        mg_audit('admin_agent_phase6_completed', 'system', ['environment' => $environment, 'trigger_source' => $trigger, 'readiness_status' => $readiness['status'], 'readiness_score' => $readiness['score']], $actorId);
        return $summary;
    } catch (Throwable $error) {
        mg_admin_agent_phase6_heartbeat_finish($pdo, $heartbeat, 'failed', [], $error);
        throw $error;
    }
}

function mg_admin_agent_phase6_chat_mode(string $message): ?string
{
    $text = strtolower(trim($message));
    if (str_contains($text, 'production readiness') || str_contains($text, 'final readiness') || str_contains($text, 'setup checklist')) return 'readiness';
    if (str_contains($text, 'scheduler') || str_contains($text, 'cron') || str_contains($text, 'automatic runner')) return 'scheduler';
    if (str_contains($text, 'continuity alert') || str_contains($text, 'recovery alert')) return 'alerts';
    if (str_contains($text, 'drill calendar') || str_contains($text, 'drill schedule')) return 'schedules';
    if (str_contains($text, 'attestation')) return 'attestations';
    if (str_contains($text, 'readiness export') || str_contains($text, 'audit export')) return 'exports';
    if (str_contains($text, 'retention')) return 'retention';
    return null;
}

function mg_admin_agent_phase6_report(PDO $pdo, string $mode, string $environment = 'production'): array
{
    $environment = mg_admin_agent_phase6_environment($environment);
    $title = 'Final production readiness';
    $content = '';
    $blocks = [];
    if ($mode === 'scheduler') {
        $title = 'Automatic runner status';
        $item = mg_admin_agent_phase6_heartbeat_state($pdo, $environment);
        $content = $item['healthy'] ? 'The Phase 6 scheduled runner is healthy.' : ($item['configured'] ? 'The scheduled runner is configured but stale.' : 'No automatic scheduled runner has been detected. One-click manual analysis remains available.');
        $blocks[] = ['type' => 'scheduler_health', 'items' => [$item]];
    } elseif ($mode === 'alerts') {
        $title = 'Continuity alerts';
        $items = mg_admin_agent_phase6_alerts($pdo, $environment);
        $active = array_values(array_filter($items, static fn(array $item): bool => in_array($item['status'], ['open', 'acknowledged', 'escalated'], true)));
        $content = count($active) . ' active continuity alert(s) are recorded.';
        $blocks[] = ['type' => 'continuity_alerts', 'items' => $items];
    } elseif ($mode === 'schedules') {
        $title = 'Recovery drill calendar';
        $items = mg_admin_agent_phase6_drill_schedules($pdo, $environment);
        $overdue = array_values(array_filter($items, static fn(array $item): bool => $item['days_remaining'] < 0 && $item['status'] === 'active'));
        $content = count($items) . ' drill schedule(s) are registered; ' . count($overdue) . ' are overdue.';
        $blocks[] = ['type' => 'drill_schedules', 'items' => $items];
    } elseif ($mode === 'attestations') {
        $title = 'Evidence attestations';
        $items = mg_admin_agent_phase6_attestations($pdo);
        $content = count($items) . ' evidence attestation(s) are recorded.';
        $blocks[] = ['type' => 'attestations', 'items' => $items];
    } elseif ($mode === 'exports') {
        $title = 'Readiness export history';
        $items = mg_admin_agent_phase6_exports($pdo, $environment);
        $content = count($items) . ' readiness export package(s) are available.';
        $blocks[] = ['type' => 'readiness_exports', 'items' => $items];
    } elseif ($mode === 'retention') {
        $title = 'Retention preview';
        $item = mg_admin_agent_phase6_retention_preview($pdo, $environment, null);
        $content = $item['scorecards_eligible'] . ' scorecard(s), ' . $item['events_eligible'] . ' event(s), and ' . $item['resolved_alerts_eligible'] . ' resolved alert(s) match the current retention windows. Phase 6 previews only and does not delete data automatically.';
        $blocks[] = ['type' => 'retention_preview', 'items' => [$item]];
    } else {
        $readiness = mg_admin_agent_phase6_evaluate_readiness($pdo, $environment);
        $content = 'Production readiness is ' . $readiness['score'] . '/100: ' . str_replace('_', ' ', $readiness['status']) . '. ' . $readiness['passed'] . ' of ' . $readiness['required'] . ' required checks pass.';
        $blocks[] = ['type' => 'readiness_checks', 'items' => $readiness['checks']];
    }
    return ['title' => $title, 'content' => $content, 'blocks' => $blocks, 'metadata' => ['mode' => $mode, 'database_only' => true, 'used_ai' => false, 'credits_used' => 0, 'generated_at' => gmdate('Y-m-d H:i:s')]];
}

function mg_admin_agent_phase6_send(PDO $pdo, int $adminId, array $input): array
{
    $message = mb_substr(trim((string) ($input['message'] ?? '')), 0, 4000);
    if ($message === '') {
        throw new InvalidArgumentException('Enter a message for the Main Admin Agent.');
    }
    $mode = mg_admin_agent_phase6_chat_mode($message);
    if ($mode === null) {
        return mg_admin_agent_phase5_send($pdo, $adminId, $input);
    }
    $thread = mg_admin_agent_thread($pdo, $adminId, isset($input['thread_id']) ? (string) $input['thread_id'] : null);
    $userMessage = mg_admin_agent_record_message($pdo, (int) $thread['id'], $adminId, 'user', $message, 'chat', [], ['database_only' => true]);
    $report = mg_admin_agent_phase6_report($pdo, $mode, (string) ($input['environment_key'] ?? 'production'));
    $assistant = mg_admin_agent_record_message($pdo, (int) $thread['id'], $adminId, 'assistant', $report['content'], 'system_report', $report['blocks'], $report['metadata'] + ['title' => $report['title']]);
    mg_audit('admin_agent_phase6_chat_report', 'system', ['thread_id' => $thread['public_id'], 'mode' => $mode, 'database_only' => true], $adminId);
    return ['thread' => ['id' => (string) $thread['public_id'], 'title' => (string) $thread['title']], 'user_message' => $userMessage, 'assistant_message' => $assistant, 'report' => $report];
}

function mg_admin_agent_phase6_state(PDO $pdo, int $adminId, array $options = []): array
{
    $state = mg_admin_agent_phase5_state($pdo, $adminId, $options);
    $schema = mg_admin_agent_phase6_schema_state($pdo);
    $state['phase6_schema'] = $schema;
    $state['phase6_ready'] = $schema['ready'];
    if (!$schema['ready']) {
        return $state;
    }
    $environment = mg_admin_agent_phase6_environment((string) ($options['environment_key'] ?? 'production'));
    $state['phase6_settings'] = mg_admin_agent_phase6_settings($pdo, $environment);
    $state['scheduler_health'] = mg_admin_agent_phase6_heartbeat_state($pdo, $environment);
    $state['continuity_alerts'] = mg_admin_agent_phase6_alerts($pdo, $environment);
    $state['drill_schedules'] = mg_admin_agent_phase6_drill_schedules($pdo, $environment);
    $state['attestations'] = mg_admin_agent_phase6_attestations($pdo);
    $state['readiness'] = mg_admin_agent_phase6_evaluate_readiness($pdo, $environment);
    $state['continuity_briefs'] = mg_admin_agent_phase6_briefs($pdo, $environment);
    $state['readiness_exports'] = mg_admin_agent_phase6_exports($pdo, $environment);
    $state['retention_preview'] = mg_admin_agent_phase6_retention_preview($pdo, $environment, $adminId);
    $state['phase6_setup'] = [
        'manual_analysis_available' => true,
        'evidence_upload_available' => true,
        'cron_optional_for_manual_operation' => true,
        'cron_required_for_automatic_monitoring' => true,
        'cron_command' => '*/5 * * * * cd /path/to/contactform && php scripts/run_admin_agent_phase6.php --trigger=scheduled --environment=production >> storage/logs/main-admin-agent-phase6.log 2>&1',
        'hosting_steps' => ['Open the hosting control panel.', 'Open Cron Jobs or Scheduled Tasks.', 'Choose Every 5 Minutes.', 'Paste the displayed command after replacing /path/to/contactform with the website project path.', 'Save, then return to this page and wait up to 10 minutes for Scheduler Healthy.'],
        'database_only' => true,
        'used_ai' => false,
        'credits_used' => 0,
    ];
    return $state;
}
