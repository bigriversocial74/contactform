<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-agent-phase5.php';
require_once __DIR__ . '/admin-agent-phase4-remediation.php';

function mg_admin_agent_phase5_execute_adapter(PDO $pdo, string $adapterKey, int $adminId, array $payload): array
{
    if ($adapterKey !== 'approve_recovery_drill_record') {
        return mg_admin_agent_phase4_execute_adapter($pdo, $adapterKey, $adminId, $payload);
    }

    $drillPublic = trim((string) ($payload['drill_id'] ?? ''));
    if ($drillPublic === '') {
        throw new InvalidArgumentException('Recovery drill identifier is required.');
    }
    $drill = mg_admin_agent_safe_row($pdo, 'SELECT d.id,d.public_id,d.status,d.target_rto_minutes,d.target_rpo_minutes,d.actual_rto_minutes,d.actual_rpo_minutes,d.executed_externally,d.evidence_id,e.public_id evidence_public,e.status evidence_status,e.canary_verified,e.manifest_verified,e.migration_status_verified FROM admin_agent_restore_drills d LEFT JOIN admin_agent_backup_evidence e ON e.id=d.evidence_id WHERE d.public_id=? LIMIT 1', [$drillPublic]);
    if ($drill === []) {
        throw new InvalidArgumentException('Recovery drill record not found.');
    }
    if ((string) $drill['status'] === 'passed') {
        return ['adapter' => 'approve_recovery_drill_record', 'drill_id' => $drillPublic, 'status' => 'passed', 'already_completed' => true];
    }
    if ((string) $drill['status'] !== 'review_ready' || !(bool) $drill['executed_externally']) {
        throw new InvalidArgumentException('The external recovery drill record is not ready for approval.');
    }
    if ($drill['evidence_id'] === null || (string) $drill['evidence_status'] !== 'passed' || !(bool) $drill['canary_verified'] || !(bool) $drill['manifest_verified'] || !(bool) $drill['migration_status_verified']) {
        throw new InvalidArgumentException('Passing evidence with canary, manifest, and migration-state verification is required.');
    }

    $gaps = [];
    if ($drill['target_rto_minutes'] !== null && $drill['actual_rto_minutes'] !== null && (int) $drill['actual_rto_minutes'] > (int) $drill['target_rto_minutes']) {
        $gaps[] = 'Observed RTO exceeded the target.';
    }
    if ($drill['target_rpo_minutes'] !== null && $drill['actual_rpo_minutes'] !== null && (int) $drill['actual_rpo_minutes'] > (int) $drill['target_rpo_minutes']) {
        $gaps[] = 'Observed RPO exceeded the target.';
    }

    $pdo->prepare('UPDATE admin_agent_restore_drills SET status="passed",approved_by_user_id=?,approved_at=NOW(),gaps_json=JSON_MERGE_PRESERVE(COALESCE(gaps_json,JSON_ARRAY()),?),updated_at=NOW() WHERE id=?')->execute([$adminId, json_encode($gaps, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (int) $drill['id']]);
    mg_audit('admin_agent_phase5_recovery_drill_approved', 'system', ['drill_id' => $drillPublic, 'evidence_id' => $drill['evidence_public']], $adminId);
    mg_security_log('info', 'admin_agent.phase5_recovery_drill_approved', 'Approved external recovery drill evidence was recorded.', ['drill_id' => $drillPublic, 'evidence_id' => $drill['evidence_public']], $adminId);

    return ['adapter' => 'approve_recovery_drill_record', 'drill_id' => $drillPublic, 'evidence_id' => (string) $drill['evidence_public'], 'status' => 'passed', 'observed_gaps' => $gaps, 'changes_applied' => true];
}

function mg_admin_agent_phase5_execute_action(PDO $pdo, int $adminId, array $input): array
{
    $executionPublic = trim((string) ($input['execution_id'] ?? ''));
    $confirmation = trim((string) ($input['confirmation'] ?? ''));
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT x.*,a.adapter_key,a.enabled,a.execution_mode,a.requires_confirmation,r.public_id review_public_id,r.payload_json,r.status review_status FROM admin_agent_remediation_executions x JOIN admin_agent_remediation_adapters a ON a.id=x.adapter_id JOIN admin_agent_action_reviews r ON r.id=x.review_id WHERE x.public_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$executionPublic]);
        $execution = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$execution) throw new InvalidArgumentException('Approved remediation execution not found.');
        if ((string) $execution['status'] === 'succeeded') {
            $pdo->commit();
            return ['execution_id' => $executionPublic, 'status' => 'succeeded', 'already_completed' => true];
        }
        if ((string) $execution['status'] !== 'approved' || (string) $execution['review_status'] !== 'approved') throw new InvalidArgumentException('This remediation is not in an approved state.');
        if (!(bool) $execution['enabled'] || (string) $execution['execution_mode'] !== 'in_process') throw new InvalidArgumentException('This remediation adapter is disabled.');
        $expected = 'EXECUTE ' . (string) $execution['adapter_key'];
        if ((bool) $execution['requires_confirmation'] && !hash_equals($expected, $confirmation)) throw new InvalidArgumentException('Type the exact execution confirmation: ' . $expected);
        $pdo->prepare('UPDATE admin_agent_remediation_executions SET status="running",executed_by_user_id=?,started_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$adminId, (int) $execution['id']]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    try {
        $payload = mg_admin_agent_json($execution['payload_json'] ?? null);
        $result = mg_admin_agent_phase5_execute_adapter($pdo, (string) $execution['adapter_key'], $adminId, $payload);
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE admin_agent_remediation_executions SET status="succeeded",result_json=?,completed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (int) $execution['id']]);
        $pdo->prepare('UPDATE admin_agent_action_reviews SET status="executed",executed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int) $execution['review_id']]);
        $pdo->commit();
        mg_audit('admin_agent_phase5_remediation_executed', 'system', ['execution_id' => $executionPublic, 'review_id' => $execution['review_public_id'], 'action_key' => $execution['adapter_key'], 'success' => true], $adminId);
        return ['execution_id' => $executionPublic, 'review_id' => (string) $execution['review_public_id'], 'action_key' => (string) $execution['adapter_key'], 'status' => 'succeeded', 'result' => $result];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $pdo->prepare('UPDATE admin_agent_remediation_executions SET status="failed",failure_code=?,failure_message=?,completed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$error::class, mb_substr($error->getMessage(), 0, 1000), (int) $execution['id']]);
        mg_security_log('error', 'admin_agent.phase5_remediation_failed', 'Approved Main Admin Agent Phase 5 action failed.', ['execution_id' => $executionPublic, 'action_key' => $execution['adapter_key'], 'exception_class' => $error::class], $adminId);
        throw $error;
    }
}
