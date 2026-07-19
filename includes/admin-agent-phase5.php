<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-agent-phase4.php';

const MG_ADMIN_AGENT_PHASE5_MIGRATION = 'database/20260719_main_admin_agent_phase5.sql';

function mg_admin_agent_phase5_tables(): array
{
    return [
        'admin_agent_recovery_objectives',
        'admin_agent_backup_evidence',
        'admin_agent_restore_drills',
        'admin_agent_recovery_plans',
        'admin_agent_recovery_gaps',
        'admin_agent_continuity_scorecards',
    ];
}

function mg_admin_agent_phase5_schema_state(PDO $pdo): array
{
    $missing = [];
    foreach (mg_admin_agent_phase5_tables() as $table) {
        if (!mg_admin_schema_has_table($pdo, $table)) {
            $missing[] = $table;
        }
    }
    return ['ready' => $missing === [], 'missing_tables' => $missing, 'migration' => MG_ADMIN_AGENT_PHASE5_MIGRATION];
}

function mg_admin_agent_phase5_ready(PDO $pdo): bool
{
    return mg_admin_agent_phase5_schema_state($pdo)['ready'];
}

function mg_admin_agent_phase5_clean_environment(string $value): string
{
    return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)) ?: 'production';
}

function mg_admin_agent_phase5_objectives(PDO $pdo, string $environment = 'production'): array
{
    $environment = mg_admin_agent_phase5_clean_environment($environment);
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT o.public_id,o.environment_key,o.criticality,o.rto_minutes,o.rpo_minutes,o.backup_max_age_minutes,o.drill_interval_days,o.status,o.owner_user_id,o.evidence_json,o.updated_at,s.public_id service_id,s.service_key,s.label service_label,s.domain,s.tier FROM admin_agent_recovery_objectives o JOIN admin_agent_services s ON s.id=o.service_id WHERE o.environment_key=? ORDER BY CASE o.criticality WHEN "critical" THEN 1 WHEN "high" THEN 2 WHEN "medium" THEN 3 ELSE 4 END,s.label', [$environment]);
    return array_map(static fn(array $row): array => [
        'id' => (string) $row['public_id'],
        'environment_key' => (string) $row['environment_key'],
        'criticality' => (string) $row['criticality'],
        'rto_minutes' => (int) $row['rto_minutes'],
        'rpo_minutes' => (int) $row['rpo_minutes'],
        'backup_max_age_minutes' => (int) $row['backup_max_age_minutes'],
        'drill_interval_days' => (int) $row['drill_interval_days'],
        'status' => (string) $row['status'],
        'owner_user_id' => $row['owner_user_id'] !== null ? (int) $row['owner_user_id'] : null,
        'evidence' => mg_admin_agent_json($row['evidence_json'] ?? null),
        'updated_at' => (string) $row['updated_at'],
        'service' => [
            'id' => (string) $row['service_id'],
            'service_key' => (string) $row['service_key'],
            'label' => (string) $row['service_label'],
            'domain' => (string) $row['domain'],
            'tier' => (string) $row['tier'],
        ],
    ], $rows);
}

function mg_admin_agent_phase5_objective_action(PDO $pdo, int $actorId, array $input): array
{
    $publicId = trim((string) ($input['objective_id'] ?? ''));
    $objective = mg_admin_agent_safe_row($pdo, 'SELECT id,public_id FROM admin_agent_recovery_objectives WHERE public_id=? LIMIT 1', [$publicId]);
    if ($objective === []) {
        throw new InvalidArgumentException('Recovery objective not found.');
    }
    $rto = max(5, min(10080, (int) ($input['rto_minutes'] ?? 240)));
    $rpo = max(1, min(10080, (int) ($input['rpo_minutes'] ?? 1440)));
    $backupAge = max(5, min(43200, (int) ($input['backup_max_age_minutes'] ?? 1440)));
    $drillDays = max(7, min(365, (int) ($input['drill_interval_days'] ?? 90)));
    $criticality = strtolower(trim((string) ($input['criticality'] ?? 'medium')));
    if (!in_array($criticality, ['low', 'medium', 'high', 'critical'], true)) {
        $criticality = 'medium';
    }
    $status = strtolower(trim((string) ($input['status'] ?? 'active')));
    if (!in_array($status, ['active', 'needs_review', 'retired'], true)) {
        $status = 'active';
    }
    $ownerId = isset($input['owner_user_id']) && (int) $input['owner_user_id'] > 0 ? (int) $input['owner_user_id'] : null;
    $pdo->prepare('UPDATE admin_agent_recovery_objectives SET criticality=?,rto_minutes=?,rpo_minutes=?,backup_max_age_minutes=?,drill_interval_days=?,status=?,owner_user_id=?,evidence_json=JSON_SET(COALESCE(evidence_json,JSON_OBJECT()),"$.reviewed_by_user_id",?,"$.reviewed_at",NOW()),updated_at=NOW() WHERE id=?')->execute([$criticality, $rto, $rpo, $backupAge, $drillDays, $status, $ownerId, $actorId, (int) $objective['id']]);
    mg_audit('admin_agent_phase5_objective_updated', 'system', ['objective_id' => $publicId, 'criticality' => $criticality, 'rto_minutes' => $rto, 'rpo_minutes' => $rpo], $actorId);
    return ['objective_id' => $publicId, 'updated' => true];
}

function mg_admin_agent_phase5_record_backup_evidence(PDO $pdo, ?int $actorId, array $input): array
{
    $environment = mg_admin_agent_phase5_clean_environment((string) ($input['environment_key'] ?? 'production'));
    $scope = preg_replace('/[^a-z0-9_.-]/', '', strtolower((string) ($input['scope_key'] ?? 'database'))) ?: 'database';
    $source = preg_replace('/[^a-z0-9_.-]/', '', strtolower((string) ($input['source_key'] ?? 'database_backup_restore_validator'))) ?: 'database_backup_restore_validator';
    $runId = mb_substr(trim((string) ($input['run_id'] ?? '')), 0, 160);
    if ($runId === '') {
        throw new InvalidArgumentException('Recovery evidence run_id is required.');
    }
    $status = strtolower(trim((string) ($input['status'] ?? 'incomplete')));
    if ($status === 'pass') {
        $status = 'passed';
    } elseif ($status === 'fail') {
        $status = 'failed';
    }
    if (!in_array($status, ['passed', 'failed', 'incomplete'], true)) {
        $status = 'incomplete';
    }
    $recordedRaw = trim((string) ($input['recorded_at'] ?? $input['restore_completed_at'] ?? ''));
    $recordedAt = $recordedRaw !== '' && strtotime($recordedRaw) !== false ? gmdate('Y-m-d H:i:s', strtotime($recordedRaw)) : gmdate('Y-m-d H:i:s');
    $backupRaw = trim((string) ($input['backup_created_at'] ?? ''));
    $backupAt = $backupRaw !== '' && strtotime($backupRaw) !== false ? gmdate('Y-m-d H:i:s', strtotime($backupRaw)) : $recordedAt;
    $restoreRaw = trim((string) ($input['restore_completed_at'] ?? ''));
    $restoreAt = $restoreRaw !== '' && strtotime($restoreRaw) !== false ? gmdate('Y-m-d H:i:s', strtotime($restoreRaw)) : $recordedAt;
    $checksum = strtolower(trim((string) ($input['backup_sha256'] ?? '')));
    if ($checksum !== '' && !preg_match('/^[a-f0-9]{64}$/', $checksum)) {
        throw new InvalidArgumentException('Backup SHA-256 must contain 64 hexadecimal characters.');
    }
    $details = is_array($input['details'] ?? null) ? $input['details'] : [];
    foreach (['password', 'secret', 'token', 'credential', 'private_key'] as $forbidden) {
        if (array_key_exists($forbidden, $details)) {
            unset($details[$forbidden]);
        }
    }
    $key = hash('sha256', $source . '|' . $environment . '|' . $scope . '|' . $runId);
    $pdo->prepare('INSERT INTO admin_agent_backup_evidence (public_id,evidence_key,source_key,environment_key,scope_key,run_id,status,backup_created_at,restore_completed_at,backup_size_bytes,backup_sha256,source_table_count,restore_table_count,source_migration_count,restore_migration_count,canary_verified,manifest_verified,migration_status_verified,report_path,details_json,recorded_by_user_id,recorded_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status),backup_created_at=VALUES(backup_created_at),restore_completed_at=VALUES(restore_completed_at),backup_size_bytes=VALUES(backup_size_bytes),backup_sha256=VALUES(backup_sha256),source_table_count=VALUES(source_table_count),restore_table_count=VALUES(restore_table_count),source_migration_count=VALUES(source_migration_count),restore_migration_count=VALUES(restore_migration_count),canary_verified=VALUES(canary_verified),manifest_verified=VALUES(manifest_verified),migration_status_verified=VALUES(migration_status_verified),report_path=VALUES(report_path),details_json=VALUES(details_json),recorded_by_user_id=VALUES(recorded_by_user_id),recorded_at=VALUES(recorded_at),updated_at=NOW()')->execute([
        mg_public_id(), $key, $source, $environment, $scope, $runId, $status, $backupAt, $restoreAt,
        isset($input['backup_size_bytes']) ? max(0, (int) $input['backup_size_bytes']) : null,
        $checksum !== '' ? $checksum : null,
        isset($input['source_table_count']) ? max(0, (int) $input['source_table_count']) : null,
        isset($input['restore_table_count']) ? max(0, (int) $input['restore_table_count']) : null,
        isset($input['source_migration_count']) ? max(0, (int) $input['source_migration_count']) : null,
        isset($input['restore_migration_count']) ? max(0, (int) $input['restore_migration_count']) : null,
        !empty($input['canary_verified']) ? 1 : 0,
        !empty($input['canonical_migration_manifest_verified']) || !empty($input['manifest_verified']) ? 1 : 0,
        !empty($input['restored_database_migration_status_verified']) || !empty($input['migration_status_verified']) ? 1 : 0,
        mb_substr(trim((string) ($input['report_path'] ?? '')), 0, 500) ?: null,
        json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $actorId,
        $recordedAt,
    ]);
    $row = mg_admin_agent_safe_row($pdo, 'SELECT public_id,status,recorded_at FROM admin_agent_backup_evidence WHERE evidence_key=? LIMIT 1', [$key]);
    mg_audit('admin_agent_phase5_backup_evidence_recorded', 'system', ['evidence_id' => $row['public_id'] ?? null, 'source_key' => $source, 'scope_key' => $scope, 'status' => $status, 'run_id' => $runId], $actorId);
    return ['id' => (string) ($row['public_id'] ?? ''), 'status' => (string) ($row['status'] ?? $status), 'recorded_at' => (string) ($row['recorded_at'] ?? $recordedAt), 'run_id' => $runId];
}

function mg_admin_agent_phase5_backup_evidence(PDO $pdo, string $environment = 'production', int $limit = 50): array
{
    $environment = mg_admin_agent_phase5_clean_environment($environment);
    $limit = max(5, min(100, $limit));
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT public_id,source_key,environment_key,scope_key,run_id,status,backup_created_at,restore_completed_at,backup_size_bytes,backup_sha256,source_table_count,restore_table_count,source_migration_count,restore_migration_count,canary_verified,manifest_verified,migration_status_verified,report_path,details_json,recorded_by_user_id,recorded_at FROM admin_agent_backup_evidence WHERE environment_key=? ORDER BY recorded_at DESC,id DESC LIMIT ' . $limit, [$environment]);
    return array_map(static fn(array $row): array => [
        'id' => (string) $row['public_id'], 'source_key' => (string) $row['source_key'], 'environment_key' => (string) $row['environment_key'], 'scope_key' => (string) $row['scope_key'], 'run_id' => (string) $row['run_id'], 'status' => (string) $row['status'],
        'backup_created_at' => $row['backup_created_at'], 'restore_completed_at' => $row['restore_completed_at'], 'backup_size_bytes' => $row['backup_size_bytes'] !== null ? (int) $row['backup_size_bytes'] : null, 'backup_sha256' => $row['backup_sha256'],
        'source_table_count' => $row['source_table_count'] !== null ? (int) $row['source_table_count'] : null, 'restore_table_count' => $row['restore_table_count'] !== null ? (int) $row['restore_table_count'] : null,
        'source_migration_count' => $row['source_migration_count'] !== null ? (int) $row['source_migration_count'] : null, 'restore_migration_count' => $row['restore_migration_count'] !== null ? (int) $row['restore_migration_count'] : null,
        'canary_verified' => (bool) $row['canary_verified'], 'manifest_verified' => (bool) $row['manifest_verified'], 'migration_status_verified' => (bool) $row['migration_status_verified'],
        'report_path' => $row['report_path'], 'details' => mg_admin_agent_json($row['details_json'] ?? null), 'recorded_by_user_id' => $row['recorded_by_user_id'] !== null ? (int) $row['recorded_by_user_id'] : null, 'recorded_at' => (string) $row['recorded_at'],
    ], $rows);
}

function mg_admin_agent_phase5_drills(PDO $pdo, string $environment = 'production', int $limit = 50): array
{
    $environment = mg_admin_agent_phase5_clean_environment($environment);
    $limit = max(5, min(100, $limit));
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT d.public_id,d.environment_key,d.scope_key,d.title,d.status,d.target_rto_minutes,d.target_rpo_minutes,d.actual_rto_minutes,d.actual_rpo_minutes,d.executed_externally,d.started_at,d.completed_at,d.summary_text,d.gaps_json,d.created_by_user_id,d.approved_by_user_id,d.approved_at,d.updated_at,o.public_id objective_id,s.public_id service_id,s.service_key,s.label service_label,e.public_id evidence_id,e.status evidence_status,e.canary_verified FROM admin_agent_restore_drills d LEFT JOIN admin_agent_recovery_objectives o ON o.id=d.objective_id LEFT JOIN admin_agent_services s ON s.id=o.service_id LEFT JOIN admin_agent_backup_evidence e ON e.id=d.evidence_id WHERE d.environment_key=? ORDER BY CASE d.status WHEN "review_ready" THEN 1 WHEN "running" THEN 2 WHEN "planned" THEN 3 WHEN "failed" THEN 4 ELSE 5 END,COALESCE(d.completed_at,d.started_at,d.created_at) DESC LIMIT ' . $limit, [$environment]);
    return array_map(static fn(array $row): array => [
        'id' => (string) $row['public_id'], 'environment_key' => (string) $row['environment_key'], 'scope_key' => (string) $row['scope_key'], 'title' => (string) $row['title'], 'status' => (string) $row['status'],
        'target_rto_minutes' => $row['target_rto_minutes'] !== null ? (int) $row['target_rto_minutes'] : null, 'target_rpo_minutes' => $row['target_rpo_minutes'] !== null ? (int) $row['target_rpo_minutes'] : null,
        'actual_rto_minutes' => $row['actual_rto_minutes'] !== null ? (int) $row['actual_rto_minutes'] : null, 'actual_rpo_minutes' => $row['actual_rpo_minutes'] !== null ? (int) $row['actual_rpo_minutes'] : null,
        'executed_externally' => (bool) $row['executed_externally'], 'started_at' => $row['started_at'], 'completed_at' => $row['completed_at'], 'summary_text' => $row['summary_text'], 'gaps' => mg_admin_agent_json($row['gaps_json'] ?? null),
        'created_by_user_id' => $row['created_by_user_id'] !== null ? (int) $row['created_by_user_id'] : null, 'approved_by_user_id' => $row['approved_by_user_id'] !== null ? (int) $row['approved_by_user_id'] : null, 'approved_at' => $row['approved_at'], 'updated_at' => (string) $row['updated_at'],
        'objective_id' => $row['objective_id'], 'service' => $row['service_id'] ? ['id' => (string) $row['service_id'], 'service_key' => (string) $row['service_key'], 'label' => (string) $row['service_label']] : null,
        'evidence' => $row['evidence_id'] ? ['id' => (string) $row['evidence_id'], 'status' => (string) $row['evidence_status'], 'canary_verified' => (bool) $row['canary_verified']] : null,
    ], $rows);
}

function mg_admin_agent_phase5_drill_action(PDO $pdo, int $actorId, array $input): array
{
    $action = strtolower(trim((string) ($input['drill_action'] ?? 'create')));
    if ($action === 'create') {
        $title = mb_substr(trim((string) ($input['title'] ?? '')), 0, 240);
        if ($title === '') {
            throw new InvalidArgumentException('Restore drill title is required.');
        }
        $environment = mg_admin_agent_phase5_clean_environment((string) ($input['environment_key'] ?? 'production'));
        $scope = preg_replace('/[^a-z0-9_.-]/', '', strtolower((string) ($input['scope_key'] ?? 'database'))) ?: 'database';
        $objectiveId = null;
        $objectivePublic = trim((string) ($input['objective_id'] ?? ''));
        $targetRto = isset($input['target_rto_minutes']) ? max(1, (int) $input['target_rto_minutes']) : null;
        $targetRpo = isset($input['target_rpo_minutes']) ? max(1, (int) $input['target_rpo_minutes']) : null;
        if ($objectivePublic !== '') {
            $objective = mg_admin_agent_safe_row($pdo, 'SELECT id,rto_minutes,rpo_minutes FROM admin_agent_recovery_objectives WHERE public_id=? LIMIT 1', [$objectivePublic]);
            if ($objective === []) {
                throw new InvalidArgumentException('Recovery objective not found.');
            }
            $objectiveId = (int) $objective['id'];
            $targetRto ??= (int) $objective['rto_minutes'];
            $targetRpo ??= (int) $objective['rpo_minutes'];
        }
        $publicId = mg_public_id();
        $key = hash('sha256', $environment . '|' . $scope . '|' . $title . '|' . gmdate('c'));
        $pdo->prepare('INSERT INTO admin_agent_restore_drills (public_id,drill_key,objective_id,environment_key,scope_key,title,status,target_rto_minutes,target_rpo_minutes,executed_externally,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,"planned",?,?,1,?,NOW(),NOW())')->execute([$publicId, $key, $objectiveId, $environment, $scope, $title, $targetRto, $targetRpo, $actorId]);
        mg_audit('admin_agent_phase5_drill_created', 'system', ['drill_id' => $publicId, 'scope_key' => $scope, 'environment' => $environment], $actorId);
        return ['id' => $publicId, 'action' => 'created', 'status' => 'planned'];
    }
    $publicId = trim((string) ($input['drill_id'] ?? ''));
    $drill = mg_admin_agent_safe_row($pdo, 'SELECT id,public_id,status FROM admin_agent_restore_drills WHERE public_id=? LIMIT 1', [$publicId]);
    if ($drill === []) {
        throw new InvalidArgumentException('Restore drill not found.');
    }
    if ($action === 'start') {
        $pdo->prepare('UPDATE admin_agent_restore_drills SET status="running",started_at=COALESCE(started_at,NOW()),updated_at=NOW() WHERE id=? AND status="planned"')->execute([(int) $drill['id']]);
    } elseif ($action === 'record_result') {
        $evidencePublic = trim((string) ($input['evidence_id'] ?? ''));
        $evidence = mg_admin_agent_safe_row($pdo, 'SELECT id,status,canary_verified FROM admin_agent_backup_evidence WHERE public_id=? LIMIT 1', [$evidencePublic]);
        if ($evidence === []) {
            throw new InvalidArgumentException('Backup/restore evidence not found.');
        }
        $summary = mb_substr(trim((string) ($input['summary_text'] ?? '')), 0, 4000);
        if ($summary === '') {
            throw new InvalidArgumentException('A drill result summary is required.');
        }
        $actualRto = max(0, (int) ($input['actual_rto_minutes'] ?? 0));
        $actualRpo = max(0, (int) ($input['actual_rpo_minutes'] ?? 0));
        $gaps = is_array($input['gaps'] ?? null) ? $input['gaps'] : [];
        $status = (string) $evidence['status'] === 'passed' && (bool) $evidence['canary_verified'] ? 'review_ready' : 'failed';
        $pdo->prepare('UPDATE admin_agent_restore_drills SET evidence_id=?,status=?,actual_rto_minutes=?,actual_rpo_minutes=?,summary_text=?,gaps_json=?,started_at=COALESCE(started_at,NOW()),completed_at=NOW(),updated_at=NOW() WHERE id=? AND status IN ("planned","running","failed")')->execute([(int) $evidence['id'], $status, $actualRto, $actualRpo, $summary, json_encode($gaps, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (int) $drill['id']]);
    } elseif ($action === 'cancel') {
        $pdo->prepare('UPDATE admin_agent_restore_drills SET status="canceled",completed_at=NOW(),updated_at=NOW() WHERE id=? AND status IN ("planned","running")')->execute([(int) $drill['id']]);
    } else {
        throw new InvalidArgumentException('Unknown restore drill action.');
    }
    mg_audit('admin_agent_phase5_drill_updated', 'system', ['drill_id' => $publicId, 'action' => $action], $actorId);
    return ['id' => $publicId, 'action' => $action, 'updated' => true];
}

function mg_admin_agent_phase5_plans(PDO $pdo, string $environment = 'production'): array
{
    $environment = mg_admin_agent_phase5_clean_environment($environment);
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT p.public_id,p.environment_key,p.plan_version,p.status,p.recovery_order,p.prerequisites_json,p.validation_steps_json,p.runbook_path,p.owner_user_id,p.last_reviewed_by_user_id,p.last_reviewed_at,p.updated_at,s.public_id service_id,s.service_key,s.label service_label,s.domain,s.tier FROM admin_agent_recovery_plans p JOIN admin_agent_services s ON s.id=p.service_id WHERE p.environment_key=? AND p.plan_version=(SELECT MAX(p2.plan_version) FROM admin_agent_recovery_plans p2 WHERE p2.service_id=p.service_id AND p2.environment_key=p.environment_key) ORDER BY p.recovery_order,s.label', [$environment]);
    return array_map(static fn(array $row): array => [
        'id' => (string) $row['public_id'], 'environment_key' => (string) $row['environment_key'], 'plan_version' => (int) $row['plan_version'], 'status' => (string) $row['status'], 'recovery_order' => (int) $row['recovery_order'],
        'prerequisites' => mg_admin_agent_json($row['prerequisites_json'] ?? null), 'validation_steps' => mg_admin_agent_json($row['validation_steps_json'] ?? null), 'runbook_path' => $row['runbook_path'],
        'owner_user_id' => $row['owner_user_id'] !== null ? (int) $row['owner_user_id'] : null, 'last_reviewed_by_user_id' => $row['last_reviewed_by_user_id'] !== null ? (int) $row['last_reviewed_by_user_id'] : null, 'last_reviewed_at' => $row['last_reviewed_at'], 'updated_at' => (string) $row['updated_at'],
        'service' => ['id' => (string) $row['service_id'], 'service_key' => (string) $row['service_key'], 'label' => (string) $row['service_label'], 'domain' => (string) $row['domain'], 'tier' => (string) $row['tier']],
    ], $rows);
}

function mg_admin_agent_phase5_plan_action(PDO $pdo, int $actorId, array $input): array
{
    $publicId = trim((string) ($input['plan_id'] ?? ''));
    $plan = mg_admin_agent_safe_row($pdo, 'SELECT id,public_id FROM admin_agent_recovery_plans WHERE public_id=? LIMIT 1', [$publicId]);
    if ($plan === []) {
        throw new InvalidArgumentException('Recovery plan not found.');
    }
    $status = strtolower(trim((string) ($input['status'] ?? 'needs_review')));
    if (!in_array($status, ['draft', 'ready', 'needs_review', 'retired'], true)) {
        throw new InvalidArgumentException('Invalid recovery plan status.');
    }
    $order = max(1, min(999, (int) ($input['recovery_order'] ?? 100)));
    $runbook = mb_substr(trim((string) ($input['runbook_path'] ?? 'docs/operations/UPGRADE_ROLLBACK_RESTORE_RUNBOOK.md')), 0, 500);
    $ownerId = isset($input['owner_user_id']) && (int) $input['owner_user_id'] > 0 ? (int) $input['owner_user_id'] : null;
    $pdo->prepare('UPDATE admin_agent_recovery_plans SET status=?,recovery_order=?,runbook_path=?,owner_user_id=?,last_reviewed_by_user_id=?,last_reviewed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$status, $order, $runbook, $ownerId, $actorId, (int) $plan['id']]);
    mg_audit('admin_agent_phase5_recovery_plan_updated', 'system', ['plan_id' => $publicId, 'status' => $status, 'recovery_order' => $order], $actorId);
    return ['plan_id' => $publicId, 'updated' => true, 'status' => $status];
}

function mg_admin_agent_phase5_gap_upsert(PDO $pdo, array $gap): string
{
    $key = hash('sha256', implode('|', [(string) ($gap['service_id'] ?? 'platform'), (string) $gap['gap_type'], (string) ($gap['scope'] ?? 'production')]));
    $pdo->prepare('INSERT INTO admin_agent_recovery_gaps (public_id,gap_key,service_id,objective_id,evidence_id,drill_id,gap_type,severity,status,title,details_text,recommendation_text,occurrence_count,evidence_json,first_seen_at,last_seen_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,? ,"open",?,?,?,1,?,NOW(),NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE service_id=VALUES(service_id),objective_id=VALUES(objective_id),evidence_id=VALUES(evidence_id),drill_id=VALUES(drill_id),severity=VALUES(severity),status=IF(status IN ("resolved","dismissed"),"open",status),title=VALUES(title),details_text=VALUES(details_text),recommendation_text=VALUES(recommendation_text),occurrence_count=occurrence_count+1,evidence_json=VALUES(evidence_json),last_seen_at=NOW(),resolved_by_user_id=NULL,resolved_at=NULL,updated_at=NOW()')->execute([
        mg_public_id(), $key, $gap['service_id'] ?? null, $gap['objective_id'] ?? null, $gap['evidence_id'] ?? null, $gap['drill_id'] ?? null, $gap['gap_type'], $gap['severity'], $gap['title'], $gap['details'], $gap['recommendation'] ?? null, json_encode($gap['evidence'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
    return $key;
}

function mg_admin_agent_phase5_evaluate_continuity(PDO $pdo, string $environment = 'production'): array
{
    $environment = mg_admin_agent_phase5_clean_environment($environment);
    $objectives = mg_admin_agent_safe_rows($pdo, 'SELECT o.*,s.service_key,s.label service_label,s.tier FROM admin_agent_recovery_objectives o JOIN admin_agent_services s ON s.id=o.service_id WHERE o.environment_key=? AND o.status<>"retired" ORDER BY s.service_key', [$environment]);
    $latestEvidence = mg_admin_agent_safe_row($pdo, 'SELECT * FROM admin_agent_backup_evidence WHERE environment_key=? AND scope_key="database" ORDER BY recorded_at DESC,id DESC LIMIT 1', [$environment]);
    $seen = [];
    $scorecards = 0;
    $critical = 0;
    foreach ($objectives as $objective) {
        $serviceId = (int) $objective['service_id'];
        $serviceKey = (string) $objective['service_key'];
        $latestDrill = mg_admin_agent_safe_row($pdo, 'SELECT * FROM admin_agent_restore_drills WHERE objective_id=? AND status="passed" ORDER BY completed_at DESC,id DESC LIMIT 1', [(int) $objective['id']]);
        $plan = mg_admin_agent_safe_row($pdo, 'SELECT * FROM admin_agent_recovery_plans WHERE service_id=? AND environment_key=? ORDER BY plan_version DESC LIMIT 1', [$serviceId, $environment]);
        $backupAge = $latestEvidence !== [] ? max(0, (int) floor((time() - strtotime((string) $latestEvidence['recorded_at'] . ' UTC')) / 60)) : null;
        $drillAge = $latestDrill !== [] && $latestDrill['completed_at'] ? max(0, (int) floor((time() - strtotime((string) $latestDrill['completed_at'] . ' UTC')) / 86400)) : null;
        $gaps = [];
        if ((string) $objective['status'] === 'needs_review') {
            $gaps[] = ['gap_type' => 'missing_objective', 'severity' => in_array((string) $objective['criticality'], ['critical', 'high'], true) ? 'high' : 'medium', 'title' => $objective['service_label'] . ' recovery objective needs review', 'details' => 'Seeded RTO, RPO, backup freshness, and drill cadence have not been confirmed by an administrator.', 'recommendation' => 'Review and activate the service recovery objective.'];
        }
        if ($latestEvidence === []) {
            $gaps[] = ['gap_type' => 'evidence_incomplete', 'severity' => 'critical', 'title' => 'No database backup/restore evidence recorded', 'details' => 'The Admin Agent has no imported evidence from the isolated database backup-and-restore validator.', 'recommendation' => 'Run the approved validator and import its JSON evidence.'];
        } elseif ((string) $latestEvidence['status'] !== 'passed' || !(bool) $latestEvidence['canary_verified'] || !(bool) $latestEvidence['manifest_verified'] || !(bool) $latestEvidence['migration_status_verified']) {
            $gaps[] = ['gap_type' => 'failed_backup', 'severity' => 'critical', 'title' => 'Latest backup/restore evidence is not fully passing', 'details' => 'The latest evidence is failed or missing canary, manifest, or restored migration-state verification.', 'recommendation' => 'Investigate the external validation failure before relying on the backup.'];
        } elseif ($backupAge !== null && $backupAge > (int) $objective['backup_max_age_minutes']) {
            $gaps[] = ['gap_type' => 'stale_backup', 'severity' => (string) $objective['criticality'] === 'critical' ? 'critical' : 'high', 'title' => $objective['service_label'] . ' backup evidence is stale', 'details' => 'Latest passing database restore evidence is ' . $backupAge . ' minutes old; objective allows ' . (int) $objective['backup_max_age_minutes'] . ' minutes.', 'recommendation' => 'Run and record a new isolated backup/restore validation.'];
        }
        if ($latestDrill === []) {
            $gaps[] = ['gap_type' => 'missing_drill', 'severity' => in_array((string) $objective['criticality'], ['critical', 'high'], true) ? 'high' : 'medium', 'title' => $objective['service_label'] . ' has no approved restore drill', 'details' => 'No passed drill record is linked to this recovery objective.', 'recommendation' => 'Plan and externally execute a restore drill, then submit evidence for review.'];
        } elseif ($drillAge !== null && $drillAge > (int) $objective['drill_interval_days']) {
            $gaps[] = ['gap_type' => 'overdue_drill', 'severity' => (string) $objective['criticality'] === 'critical' ? 'critical' : 'high', 'title' => $objective['service_label'] . ' restore drill is overdue', 'details' => 'Last approved drill is ' . $drillAge . ' days old; objective requires a drill every ' . (int) $objective['drill_interval_days'] . ' days.', 'recommendation' => 'Schedule and externally execute a new restore drill.'];
        } else {
            if ($latestDrill['actual_rto_minutes'] !== null && (int) $latestDrill['actual_rto_minutes'] > (int) $objective['rto_minutes']) {
                $gaps[] = ['gap_type' => 'rto_miss', 'severity' => 'high', 'title' => $objective['service_label'] . ' drill exceeded RTO', 'details' => 'Observed RTO was ' . (int) $latestDrill['actual_rto_minutes'] . ' minutes against a ' . (int) $objective['rto_minutes'] . '-minute objective.', 'recommendation' => 'Reduce recovery steps or revise the objective with documented approval.'];
            }
            if ($latestDrill['actual_rpo_minutes'] !== null && (int) $latestDrill['actual_rpo_minutes'] > (int) $objective['rpo_minutes']) {
                $gaps[] = ['gap_type' => 'rpo_miss', 'severity' => 'high', 'title' => $objective['service_label'] . ' drill exceeded RPO', 'details' => 'Observed RPO was ' . (int) $latestDrill['actual_rpo_minutes'] . ' minutes against a ' . (int) $objective['rpo_minutes'] . '-minute objective.', 'recommendation' => 'Increase backup frequency or revise the objective with documented approval.'];
            }
        }
        if ($plan === [] || (string) $plan['status'] !== 'ready') {
            $gaps[] = ['gap_type' => $plan === [] ? 'missing_plan' : 'plan_review', 'severity' => in_array((string) $objective['criticality'], ['critical', 'high'], true) ? 'high' : 'medium', 'title' => $objective['service_label'] . ' recovery plan is not ready', 'details' => $plan === [] ? 'No recovery plan exists.' : 'The latest recovery plan is ' . (string) $plan['status'] . '.', 'recommendation' => 'Review dependencies, recovery order, validation steps, owner, and runbook path.'];
        }
        foreach ($gaps as $gap) {
            $gap += ['service_id' => $serviceId, 'objective_id' => (int) $objective['id'], 'evidence_id' => $latestEvidence !== [] ? (int) $latestEvidence['id'] : null, 'drill_id' => $latestDrill !== [] ? (int) $latestDrill['id'] : null, 'scope' => $environment, 'evidence' => ['service_key' => $serviceKey, 'backup_age_minutes' => $backupAge, 'drill_age_days' => $drillAge]];
            $seen[] = mg_admin_agent_phase5_gap_upsert($pdo, $gap);
        }
        $openTotal = count($gaps);
        $criticalTotal = count(array_filter($gaps, static fn(array $gap): bool => $gap['severity'] === 'critical'));
        $score = max(0, 100 - ($criticalTotal * 30) - (count(array_filter($gaps, static fn(array $gap): bool => $gap['severity'] === 'high')) * 18) - (count(array_filter($gaps, static fn(array $gap): bool => $gap['severity'] === 'medium')) * 8));
        $status = $score >= 90 ? 'healthy' : ($score >= 75 ? 'watch' : ($score >= 50 ? 'attention' : 'critical'));
        if ($status === 'critical') {
            $critical++;
        }
        $scoreKey = hash('sha256', $environment . '|' . $serviceKey . '|' . gmdate('Y-m-d-H'));
        $pdo->prepare('INSERT INTO admin_agent_continuity_scorecards (public_id,scorecard_key,service_id,environment_key,continuity_score,status,objective_compliant,backup_fresh,last_backup_age_minutes,last_passed_drill_age_days,open_gap_total,critical_gap_total,evidence_json,generated_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE continuity_score=VALUES(continuity_score),status=VALUES(status),objective_compliant=VALUES(objective_compliant),backup_fresh=VALUES(backup_fresh),last_backup_age_minutes=VALUES(last_backup_age_minutes),last_passed_drill_age_days=VALUES(last_passed_drill_age_days),open_gap_total=VALUES(open_gap_total),critical_gap_total=VALUES(critical_gap_total),evidence_json=VALUES(evidence_json),generated_at=NOW(),updated_at=NOW()')->execute([
            mg_public_id(), $scoreKey, $serviceId, $environment, $score, $status, (string) $objective['status'] === 'active' ? 1 : 0,
            $latestEvidence !== [] && (string) $latestEvidence['status'] === 'passed' && $backupAge !== null && $backupAge <= (int) $objective['backup_max_age_minutes'] ? 1 : 0,
            $backupAge, $drillAge, $openTotal, $criticalTotal,
            json_encode(['service_key' => $serviceKey, 'objective_id' => $objective['public_id'], 'latest_evidence_id' => $latestEvidence['public_id'] ?? null, 'latest_drill_id' => $latestDrill['public_id'] ?? null, 'plan_id' => $plan['public_id'] ?? null], JSON_UNESCAPED_SLASHES),
        ]);
        $scorecards++;
    }
    if ($seen === []) {
        $pdo->exec('UPDATE admin_agent_recovery_gaps SET status="resolved",resolved_at=COALESCE(resolved_at,NOW()),updated_at=NOW() WHERE status IN ("open","acknowledged","under_review")');
    } else {
        $marks = implode(',', array_fill(0, count($seen), '?'));
        $pdo->prepare('UPDATE admin_agent_recovery_gaps SET status="resolved",resolved_at=COALESCE(resolved_at,NOW()),updated_at=NOW() WHERE status IN ("open","acknowledged","under_review") AND gap_key NOT IN (' . $marks . ')')->execute($seen);
    }
    return ['objectives_evaluated' => count($objectives), 'scorecards_generated' => $scorecards, 'active_gap_keys' => count($seen), 'critical_scorecards' => $critical];
}

function mg_admin_agent_phase5_scorecards(PDO $pdo, string $environment = 'production', int $limit = 100): array
{
    $environment = mg_admin_agent_phase5_clean_environment($environment);
    $limit = max(10, min(200, $limit));
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT c.public_id,c.environment_key,c.continuity_score,c.status,c.objective_compliant,c.backup_fresh,c.last_backup_age_minutes,c.last_passed_drill_age_days,c.open_gap_total,c.critical_gap_total,c.evidence_json,c.generated_at,s.public_id service_id,s.service_key,s.label service_label,s.domain,s.tier FROM admin_agent_continuity_scorecards c JOIN admin_agent_services s ON s.id=c.service_id WHERE c.environment_key=? AND c.id IN (SELECT MAX(c2.id) FROM admin_agent_continuity_scorecards c2 WHERE c2.environment_key=? GROUP BY c2.service_id) ORDER BY CASE c.status WHEN "critical" THEN 1 WHEN "attention" THEN 2 WHEN "watch" THEN 3 ELSE 4 END,c.continuity_score,s.label LIMIT ' . $limit, [$environment, $environment]);
    return array_map(static fn(array $row): array => [
        'id' => (string) $row['public_id'], 'environment_key' => (string) $row['environment_key'], 'continuity_score' => (int) $row['continuity_score'], 'status' => (string) $row['status'], 'objective_compliant' => (bool) $row['objective_compliant'], 'backup_fresh' => (bool) $row['backup_fresh'],
        'last_backup_age_minutes' => $row['last_backup_age_minutes'] !== null ? (int) $row['last_backup_age_minutes'] : null, 'last_passed_drill_age_days' => $row['last_passed_drill_age_days'] !== null ? (int) $row['last_passed_drill_age_days'] : null,
        'open_gap_total' => (int) $row['open_gap_total'], 'critical_gap_total' => (int) $row['critical_gap_total'], 'evidence' => mg_admin_agent_json($row['evidence_json'] ?? null), 'generated_at' => (string) $row['generated_at'],
        'service' => ['id' => (string) $row['service_id'], 'service_key' => (string) $row['service_key'], 'label' => (string) $row['service_label'], 'domain' => (string) $row['domain'], 'tier' => (string) $row['tier']],
    ], $rows);
}

function mg_admin_agent_phase5_gaps(PDO $pdo, int $limit = 100): array
{
    $limit = max(10, min(200, $limit));
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT g.public_id,g.gap_type,g.severity,g.status,g.title,g.details_text,g.recommendation_text,g.occurrence_count,g.owner_user_id,g.acknowledged_by_user_id,g.acknowledged_at,g.resolved_by_user_id,g.resolved_at,g.evidence_json,g.first_seen_at,g.last_seen_at,s.public_id service_id,s.service_key,s.label service_label,o.public_id objective_id,e.public_id evidence_id,d.public_id drill_id FROM admin_agent_recovery_gaps g LEFT JOIN admin_agent_services s ON s.id=g.service_id LEFT JOIN admin_agent_recovery_objectives o ON o.id=g.objective_id LEFT JOIN admin_agent_backup_evidence e ON e.id=g.evidence_id LEFT JOIN admin_agent_restore_drills d ON d.id=g.drill_id ORDER BY CASE g.status WHEN "open" THEN 1 WHEN "acknowledged" THEN 2 WHEN "under_review" THEN 3 ELSE 4 END,CASE g.severity WHEN "critical" THEN 1 WHEN "high" THEN 2 WHEN "medium" THEN 3 ELSE 4 END,g.last_seen_at DESC LIMIT ' . $limit);
    return array_map(static fn(array $row): array => [
        'id' => (string) $row['public_id'], 'gap_type' => (string) $row['gap_type'], 'severity' => (string) $row['severity'], 'status' => (string) $row['status'], 'title' => (string) $row['title'], 'details_text' => (string) $row['details_text'], 'recommendation_text' => $row['recommendation_text'], 'occurrence_count' => (int) $row['occurrence_count'],
        'owner_user_id' => $row['owner_user_id'] !== null ? (int) $row['owner_user_id'] : null, 'acknowledged_by_user_id' => $row['acknowledged_by_user_id'] !== null ? (int) $row['acknowledged_by_user_id'] : null, 'acknowledged_at' => $row['acknowledged_at'], 'resolved_by_user_id' => $row['resolved_by_user_id'] !== null ? (int) $row['resolved_by_user_id'] : null, 'resolved_at' => $row['resolved_at'],
        'evidence' => mg_admin_agent_json($row['evidence_json'] ?? null), 'first_seen_at' => (string) $row['first_seen_at'], 'last_seen_at' => (string) $row['last_seen_at'],
        'service' => $row['service_id'] ? ['id' => (string) $row['service_id'], 'service_key' => (string) $row['service_key'], 'label' => (string) $row['service_label']] : null, 'objective_id' => $row['objective_id'], 'evidence_id' => $row['evidence_id'], 'drill_id' => $row['drill_id'],
    ], $rows);
}

function mg_admin_agent_phase5_gap_action(PDO $pdo, int $actorId, array $input): array
{
    $publicId = trim((string) ($input['gap_id'] ?? ''));
    $action = strtolower(trim((string) ($input['gap_action'] ?? 'acknowledge')));
    $gap = mg_admin_agent_safe_row($pdo, 'SELECT id,public_id,status FROM admin_agent_recovery_gaps WHERE public_id=? LIMIT 1', [$publicId]);
    if ($gap === []) {
        throw new InvalidArgumentException('Recovery gap not found.');
    }
    if ($action === 'acknowledge') {
        $pdo->prepare('UPDATE admin_agent_recovery_gaps SET status="acknowledged",acknowledged_by_user_id=?,acknowledged_at=NOW(),updated_at=NOW() WHERE id=? AND status="open"')->execute([$actorId, (int) $gap['id']]);
    } elseif ($action === 'under_review') {
        $pdo->prepare('UPDATE admin_agent_recovery_gaps SET status="under_review",owner_user_id=?,updated_at=NOW() WHERE id=? AND status IN ("open","acknowledged")')->execute([$actorId, (int) $gap['id']]);
    } elseif (in_array($action, ['resolve', 'dismiss'], true)) {
        $note = mb_substr(trim((string) ($input['note'] ?? '')), 0, 1000);
        if ($note === '') {
            throw new InvalidArgumentException('A resolution or dismissal note is required.');
        }
        $status = $action === 'resolve' ? 'resolved' : 'dismissed';
        $pdo->prepare('UPDATE admin_agent_recovery_gaps SET status=?,resolved_by_user_id=?,resolved_at=NOW(),evidence_json=JSON_SET(COALESCE(evidence_json,JSON_OBJECT()),"$.closure_note",?),updated_at=NOW() WHERE id=?')->execute([$status, $actorId, $note, (int) $gap['id']]);
    } else {
        throw new InvalidArgumentException('Unknown recovery gap action.');
    }
    mg_audit('admin_agent_phase5_gap_action', 'system', ['gap_id' => $publicId, 'action' => $action], $actorId);
    return ['gap_id' => $publicId, 'action' => $action, 'updated' => true];
}

function mg_admin_agent_phase5_run(PDO $pdo, array $options = []): array
{
    if (!mg_admin_agent_phase5_ready($pdo)) {
        throw new RuntimeException('Main Admin Agent Phase 5 SQL migration is required.');
    }
    $environment = mg_admin_agent_phase5_clean_environment((string) ($options['environment_key'] ?? 'production'));
    $phase4 = mg_admin_agent_phase4_run($pdo, $options + ['environment_key' => $environment]);
    $continuity = mg_admin_agent_phase5_evaluate_continuity($pdo, $environment);
    $actorId = isset($options['initiated_by_user_id']) && (int) $options['initiated_by_user_id'] > 0 ? (int) $options['initiated_by_user_id'] : null;
    mg_audit('admin_agent_phase5_completed', 'system', ['scan_id' => $phase4['phase3']['phase2']['scan']['id'] ?? null, 'environment' => $environment, 'continuity' => $continuity], $actorId);
    return ['phase4' => $phase4, 'continuity' => $continuity, 'database_only' => true, 'used_ai' => false, 'credits_used' => 0, 'recovery_execution' => false];
}

function mg_admin_agent_phase5_chat_mode(string $message): ?string
{
    $text = strtolower(trim($message));
    if (str_contains($text, 'recovery objective') || str_contains($text, 'rto') || str_contains($text, 'rpo')) return 'objectives';
    if (str_contains($text, 'backup evidence') || str_contains($text, 'restore evidence')) return 'evidence';
    if (str_contains($text, 'restore drill') || str_contains($text, 'recovery drill')) return 'drills';
    if (str_contains($text, 'recovery plan') || str_contains($text, 'recovery order')) return 'plans';
    if (str_contains($text, 'continuity score') || str_contains($text, 'business continuity')) return 'continuity';
    if (str_contains($text, 'recovery gap') || str_contains($text, 'recovery readiness')) return 'gaps';
    return null;
}

function mg_admin_agent_phase5_report(PDO $pdo, string $mode, string $environment = 'production'): array
{
    $objectives = mg_admin_agent_phase5_objectives($pdo, $environment);
    $evidence = mg_admin_agent_phase5_backup_evidence($pdo, $environment);
    $drills = mg_admin_agent_phase5_drills($pdo, $environment);
    $plans = mg_admin_agent_phase5_plans($pdo, $environment);
    $scorecards = mg_admin_agent_phase5_scorecards($pdo, $environment);
    $gaps = mg_admin_agent_phase5_gaps($pdo);
    $title = 'Main Admin Agent Phase 5 recovery assurance';
    $lines = [];
    $blocks = [];
    if ($mode === 'objectives') {
        $title = 'Recovery objectives';
        $review = array_values(array_filter($objectives, static fn(array $item): bool => $item['status'] === 'needs_review'));
        $lines[] = count($objectives) . ' service recovery objective(s) are registered; ' . count($review) . ' need administrator review.';
        $blocks[] = ['type' => 'recovery_objectives', 'items' => $objectives];
    } elseif ($mode === 'evidence') {
        $title = 'Backup and restore evidence';
        $latest = $evidence[0] ?? null;
        $lines[] = $latest ? 'Latest evidence is ' . strtoupper($latest['status']) . ' from ' . $latest['recorded_at'] . '.' : 'No backup/restore evidence has been recorded.';
        $blocks[] = ['type' => 'backup_evidence', 'items' => $evidence];
    } elseif ($mode === 'drills') {
        $title = 'Restore drill readiness';
        $ready = array_values(array_filter($drills, static fn(array $item): bool => $item['status'] === 'review_ready'));
        $lines[] = count($drills) . ' restore drill record(s) exist; ' . count($ready) . ' await approval. Drills are executed externally, never by the Admin Agent.';
        $blocks[] = ['type' => 'restore_drills', 'items' => $drills];
    } elseif ($mode === 'plans') {
        $title = 'Dependency-aware recovery plans';
        $notReady = array_values(array_filter($plans, static fn(array $item): bool => $item['status'] !== 'ready'));
        $lines[] = count($plans) . ' recovery plan(s) are registered; ' . count($notReady) . ' are not ready.';
        $blocks[] = ['type' => 'recovery_plans', 'items' => $plans];
    } elseif ($mode === 'continuity') {
        $title = 'Business continuity scorecards';
        $attention = array_values(array_filter($scorecards, static fn(array $item): bool => in_array($item['status'], ['attention', 'critical'], true)));
        $lines[] = count($scorecards) . ' continuity scorecard(s) are available; ' . count($attention) . ' need attention.';
        $blocks[] = ['type' => 'continuity_scorecards', 'items' => $scorecards];
    } else {
        $title = 'Recovery readiness gaps';
        $active = array_values(array_filter($gaps, static fn(array $item): bool => in_array($item['status'], ['open', 'acknowledged', 'under_review'], true)));
        $critical = array_values(array_filter($active, static fn(array $item): bool => $item['severity'] === 'critical'));
        $lines[] = count($active) . ' active recovery gap(s) remain; ' . count($critical) . ' are critical.';
        $blocks[] = ['type' => 'recovery_gaps', 'items' => $gaps];
    }
    return ['title' => $title, 'content' => implode("\n", $lines), 'blocks' => $blocks, 'metadata' => ['mode' => $mode, 'database_only' => true, 'used_ai' => false, 'credits_used' => 0, 'recovery_execution' => false, 'generated_at' => gmdate('Y-m-d H:i:s')]];
}

function mg_admin_agent_phase5_send(PDO $pdo, int $adminId, array $input): array
{
    $message = mb_substr(trim((string) ($input['message'] ?? '')), 0, 4000);
    if ($message === '') {
        throw new InvalidArgumentException('Enter a message for the Main Admin Agent.');
    }
    $mode = mg_admin_agent_phase5_chat_mode($message);
    if ($mode === null) {
        return mg_admin_agent_phase4_send($pdo, $adminId, $input);
    }
    $thread = mg_admin_agent_thread($pdo, $adminId, isset($input['thread_id']) ? (string) $input['thread_id'] : null);
    $userMessage = mg_admin_agent_record_message($pdo, (int) $thread['id'], $adminId, 'user', $message, 'chat', [], ['database_only' => true]);
    $report = mg_admin_agent_phase5_report($pdo, $mode, (string) ($input['environment_key'] ?? 'production'));
    $assistant = mg_admin_agent_record_message($pdo, (int) $thread['id'], $adminId, 'assistant', $report['content'], 'system_report', $report['blocks'], $report['metadata'] + ['title' => $report['title']]);
    mg_audit('admin_agent_phase5_chat_report', 'system', ['thread_id' => $thread['public_id'], 'mode' => $mode, 'database_only' => true], $adminId);
    return ['thread' => ['id' => (string) $thread['public_id'], 'title' => (string) $thread['title']], 'user_message' => $userMessage, 'assistant_message' => $assistant, 'report' => $report];
}

function mg_admin_agent_phase5_state(PDO $pdo, int $adminId, array $options = []): array
{
    $state = mg_admin_agent_phase4_state($pdo, $adminId, $options);
    $schema = mg_admin_agent_phase5_schema_state($pdo);
    $state['phase5_schema'] = $schema;
    $state['phase5_ready'] = $schema['ready'];
    if (!$schema['ready']) {
        return $state;
    }
    $environment = mg_admin_agent_phase5_clean_environment((string) ($options['environment_key'] ?? 'production'));
    $state['recovery_objectives'] = mg_admin_agent_phase5_objectives($pdo, $environment);
    $state['backup_evidence'] = mg_admin_agent_phase5_backup_evidence($pdo, $environment);
    $state['restore_drills'] = mg_admin_agent_phase5_drills($pdo, $environment);
    $state['recovery_plans'] = mg_admin_agent_phase5_plans($pdo, $environment);
    $state['continuity_scorecards'] = mg_admin_agent_phase5_scorecards($pdo, $environment);
    $state['recovery_gaps'] = mg_admin_agent_phase5_gaps($pdo);
    $state['phase5_systematic'] = ['database_only' => true, 'used_ai' => false, 'credits_used' => 0, 'recovery_execution' => false, 'external_drills_only' => true, 'approval_confirmation' => 'EXECUTE approve_recovery_drill_record'];
    return $state;
}
