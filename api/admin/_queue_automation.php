<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_schema.php';

function mg_queue_automation_rules(): array
{
    return [
        ['slug' => 'seed_due_alerts', 'label' => 'Due/overdue alert seeding', 'cadence' => 'hourly', 'score' => 10],
        ['slug' => 'sla_recalculation', 'label' => 'SLA recalculation', 'cadence' => 'hourly', 'score' => 10],
        ['slug' => 'auto_escalation', 'label' => 'Auto-escalation', 'cadence' => 'hourly', 'score' => 10],
        ['slug' => 'stale_waiting_review', 'label' => 'Stale waiting review', 'cadence' => 'daily', 'score' => 10],
        ['slug' => 'unresolved_aging_review', 'label' => 'Unresolved aging review', 'cadence' => 'daily', 'score' => 10],
        ['slug' => 'quality_flag_review', 'label' => 'Incomplete notes and follow-up review', 'cadence' => 'daily', 'score' => 10],
    ];
}

function mg_queue_automation_mode(mixed $value): string
{
    $mode = strtolower(trim((string)$value));
    return in_array($mode, ['manual','scheduled','system'], true) ? $mode : 'manual';
}

function mg_queue_automation_run_table_missing(PDO $pdo): array
{
    if (!mg_admin_schema_has_table($pdo, 'admin_queue_automation_runs')) {
        return ['admin_queue_automation_runs'];
    }
    $required = ['public_id','actor_user_id','run_mode','status','processed_count','alerts_created_count','sla_updated_count','auto_routed_count','auto_escalated_count','quality_flags_count','unresolved_aging_count','summary_json','error_message','started_at','completed_at'];
    $missing = mg_admin_schema_missing_columns($pdo, 'admin_queue_automation_runs', $required);
    return array_map(static fn(string $column): string => 'admin_queue_automation_runs.' . $column, $missing);
}

function mg_queue_automation_start(PDO $pdo, int $actorId, string $mode): array
{
    $missing = mg_queue_automation_run_table_missing($pdo);
    if ($missing) {
        throw new RuntimeException('Queue automation SQL migration required: ' . implode(', ', $missing));
    }
    $publicId = mg_public_uuid();
    $stmt = $pdo->prepare('INSERT INTO admin_queue_automation_runs (public_id,actor_user_id,run_mode,status,started_at) VALUES (?,?,?,"started",NOW())');
    $stmt->execute([$publicId, $actorId > 0 ? $actorId : null, $mode]);
    return ['id' => (int)$pdo->lastInsertId(), 'public_id' => $publicId];
}

function mg_queue_automation_quality_flags(PDO $pdo, int $actorId): array
{
    if (!mg_admin_schema_has_table($pdo, 'admin_user_notes')) {
        return ['quality_flags' => 0, 'unresolved_aging' => 0, 'schema_required' => ['admin_user_notes']];
    }
    $required = ['id','public_id','target_user_id','assigned_admin_user_id','status','note','reason','due_at','sla_status','followup_required','notes_incomplete','created_at','updated_at'];
    $missing = mg_admin_schema_missing_columns($pdo, 'admin_user_notes', $required);
    if ($missing) {
        return ['quality_flags' => 0, 'unresolved_aging' => 0, 'schema_required' => array_map(static fn(string $column): string => 'admin_user_notes.' . $column, $missing)];
    }

    $quality = $pdo->prepare('UPDATE admin_user_notes SET notes_incomplete = 1, updated_at = updated_at WHERE status <> "resolved" AND (note IS NULL OR CHAR_LENGTH(TRIM(note)) < 12 OR reason IS NULL OR CHAR_LENGTH(TRIM(reason)) < 12)');
    $quality->execute();
    $followup = $pdo->prepare('UPDATE admin_user_notes SET followup_required = 1, updated_at = updated_at WHERE status <> "resolved" AND ((due_at IS NOT NULL AND due_at < NOW()) OR status IN ("waiting_on_merchant","waiting_on_customer") OR sla_status IN ("at_risk","breached"))');
    $followup->execute();
    $aging = $pdo->prepare('SELECT id, public_id, target_user_id, assigned_admin_user_id, created_at FROM admin_user_notes WHERE status <> "resolved" AND created_at < DATE_SUB(NOW(), INTERVAL 14 DAY) ORDER BY created_at ASC LIMIT 100');
    $aging->execute();
    $agingRows = $aging->fetchAll(PDO::FETCH_ASSOC);
    foreach ($agingRows as $note) {
        mg_queue_notice_create($pdo, [
            'note_id' => (int)$note['id'],
            'target_user_id' => (int)$note['target_user_id'],
            'assigned_admin_user_id' => $note['assigned_admin_user_id'] !== null ? (int)$note['assigned_admin_user_id'] : null,
            'actor_user_id' => $actorId,
            'notification_type' => 'quality_review',
            'severity' => 'warning',
            'title' => 'Unresolved queue item needs review',
            'message' => 'A follow-up queue item has aged past the automation review threshold.',
            'metadata' => ['note_public_id' => (string)$note['public_id'], 'created_at' => (string)$note['created_at']],
        ]);
    }
    return ['quality_flags' => $quality->rowCount() + $followup->rowCount(), 'unresolved_aging' => count($agingRows), 'schema_required' => []];
}

function mg_queue_automation_last_run(PDO $pdo): ?array
{
    if (mg_queue_automation_run_table_missing($pdo)) {
        return null;
    }
    $stmt = $pdo->query('SELECT public_id,status,run_mode,processed_count,alerts_created_count,sla_updated_count,auto_routed_count,auto_escalated_count,quality_flags_count,unresolved_aging_count,error_message,started_at,completed_at FROM admin_queue_automation_runs ORDER BY started_at DESC,id DESC LIMIT 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    return [
        'id' => (string)$row['public_id'],
        'status' => (string)$row['status'],
        'run_mode' => (string)$row['run_mode'],
        'processed_count' => (int)$row['processed_count'],
        'alerts_created_count' => (int)$row['alerts_created_count'],
        'sla_updated_count' => (int)$row['sla_updated_count'],
        'auto_routed_count' => (int)$row['auto_routed_count'],
        'auto_escalated_count' => (int)$row['auto_escalated_count'],
        'quality_flags_count' => (int)$row['quality_flags_count'],
        'unresolved_aging_count' => (int)$row['unresolved_aging_count'],
        'error_message' => $row['error_message'] !== null ? (string)$row['error_message'] : null,
        'started_at' => (string)$row['started_at'],
        'completed_at' => $row['completed_at'] !== null ? (string)$row['completed_at'] : null,
    ];
}

function mg_queue_automation_summary(PDO $pdo): array
{
    $schemaRequired = mg_queue_automation_run_table_missing($pdo);
    $last = mg_queue_automation_last_run($pdo);
    $stats = [];
    if (!$schemaRequired) {
        $stats = $pdo->query('SELECT COUNT(*) total_runs, SUM(status="failed") failed_runs, MAX(completed_at) last_completed_at FROM admin_queue_automation_runs')->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    return [
        'rules' => mg_queue_automation_rules(),
        'last_run' => $last,
        'summary' => [
            'total_runs' => (int)($stats['total_runs'] ?? 0),
            'failed_runs' => (int)($stats['failed_runs'] ?? 0),
            'last_completed_at' => ($stats['last_completed_at'] ?? null) !== null ? (string)$stats['last_completed_at'] : null,
            'next_recommended_run' => $last && $last['completed_at'] ? gmdate('Y-m-d H:i:s', strtotime($last['completed_at'] . ' UTC') + 3600) : gmdate('Y-m-d H:i:s'),
            'schema_required' => $schemaRequired,
            'score' => ['section' => 'Queue automation', 'score' => $schemaRequired ? 7 : 10, 'max' => 10, 'status' => $schemaRequired ? 'schema_required' : 'cleared'],
        ],
    ];
}

function mg_queue_automation_run(PDO $pdo, int $actorId, string $mode): array
{
    $schemaRequired = mg_queue_automation_run_table_missing($pdo);
    if ($schemaRequired) {
        return ['run_id' => null, 'status' => 'schema_required', 'processed_count' => 0, 'alerts_created_count' => 0, 'sla_updated_count' => 0, 'auto_routed_count' => 0, 'auto_escalated_count' => 0, 'quality_flags_count' => 0, 'unresolved_aging_count' => 0, 'schema_required' => $schemaRequired];
    }

    $run = mg_queue_automation_start($pdo, $actorId, $mode);
    try {
        $alertsCreated = mg_queue_seed_due_notices($pdo, $actorId);
        $sla = mg_queue_sla_recalculate($pdo, $actorId, 500);
        $quality = mg_queue_automation_quality_flags($pdo, $actorId);
        $report = mg_queue_reporting_read($pdo, 30);
        $schemaRequired = array_values(array_unique(array_merge($sla['schema_required'] ?? [], $quality['schema_required'] ?? [], $report['schema_required'] ?? [])));
        $summary = [
            'processed_count' => (int)($sla['processed'] ?? 0),
            'alerts_created_count' => $alertsCreated,
            'sla_updated_count' => (int)($sla['updated'] ?? 0),
            'auto_routed_count' => (int)($sla['auto_routed'] ?? 0),
            'auto_escalated_count' => (int)($sla['auto_escalated'] ?? 0),
            'quality_flags_count' => (int)($quality['quality_flags'] ?? 0),
            'unresolved_aging_count' => (int)($quality['unresolved_aging'] ?? 0),
            'schema_required' => $schemaRequired,
            'reporting' => $report['summary'] ?? [],
        ];
        $update = $pdo->prepare('UPDATE admin_queue_automation_runs SET status="completed", processed_count=?, alerts_created_count=?, sla_updated_count=?, auto_routed_count=?, auto_escalated_count=?, quality_flags_count=?, unresolved_aging_count=?, summary_json=?, completed_at=NOW() WHERE id=?');
        $update->execute([$summary['processed_count'], $summary['alerts_created_count'], $summary['sla_updated_count'], $summary['auto_routed_count'], $summary['auto_escalated_count'], $summary['quality_flags_count'], $summary['unresolved_aging_count'], json_encode($summary, JSON_UNESCAPED_SLASHES), $run['id']]);
        mg_queue_notice_create($pdo, [
            'note_id' => null,
            'target_user_id' => null,
            'assigned_admin_user_id' => null,
            'actor_user_id' => $actorId,
            'notification_type' => 'automation_summary',
            'severity' => $schemaRequired ? 'warning' : ($summary['auto_escalated_count'] > 0 || $summary['unresolved_aging_count'] > 0 ? 'warning' : 'info'),
            'title' => $schemaRequired ? 'Queue automation completed with schema warnings' : 'Queue automation completed',
            'message' => $schemaRequired ? 'Queue automation completed, but some admin queue SQL columns still need to be imported.' : 'Queue automation completed with alerts, SLA updates, routing, quality flags, and reporting refresh.',
            'metadata' => ['run_id' => $run['public_id']] + $summary,
        ]);
        return ['run_id' => $run['public_id'], 'status' => $schemaRequired ? 'schema_required' : 'completed'] + $summary;
    } catch (Throwable $error) {
        $message = mb_substr($error->getMessage(), 0, 500);
        $stmt = $pdo->prepare('UPDATE admin_queue_automation_runs SET status="failed", error_message=?, completed_at=NOW() WHERE id=?');
        $stmt->execute([$message, $run['id']]);
        mg_queue_notice_create($pdo, [
            'note_id' => null,
            'target_user_id' => null,
            'assigned_admin_user_id' => null,
            'actor_user_id' => $actorId,
            'notification_type' => 'automation_failed',
            'severity' => 'critical',
            'title' => 'Queue automation failed',
            'message' => 'Queue automation failed before completion.',
            'metadata' => ['run_id' => $run['public_id'], 'error' => $message],
        ]);
        throw $error;
    }
}
