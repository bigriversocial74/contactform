<?php
declare(strict_types=1);

const MG_MCP_AUTOMATION_MUTABLE_RUN_STATUSES = [
    'queued',
    'evaluating',
    'waiting_for_approval',
    'approved',
    'executing',
];

function mg_mcp_automation_grouped_counts(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $counts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[(string)$row['status']] = (int)$row['total'];
    }
    return $counts;
}

function mg_mcp_automation_owner_operations_snapshot(PDO $pdo, int $userId): array
{
    if (!mg_mcp_automation_schema_ready($pdo)) {
        throw new MgMcpAutomationGrantException('The MCP automation foundation has not been imported.', 503, 'MCP_AUTOMATION_SCHEMA_MISSING');
    }
    mg_mcp_automation_expire_owner_grants($pdo, $userId);

    $grantCounts = mg_mcp_automation_grouped_counts($pdo, 'SELECT status,COUNT(*) AS total FROM mcp_automation_grants WHERE authorizing_user_id=? GROUP BY status', [$userId]);
    $definitionCounts = mg_mcp_automation_grouped_counts($pdo, 'SELECT a.status,COUNT(*) AS total FROM mcp_automations a INNER JOIN mcp_automation_grants g ON g.id=a.grant_id WHERE g.authorizing_user_id=? GROUP BY a.status', [$userId]);
    $triggerCounts = mg_mcp_automation_grouped_counts($pdo, 'SELECT t.status,COUNT(*) AS total FROM mcp_automation_triggers t INNER JOIN mcp_automations a ON a.id=t.automation_id INNER JOIN mcp_automation_grants g ON g.id=a.grant_id WHERE g.authorizing_user_id=? GROUP BY t.status', [$userId]);
    $runCounts = mg_mcp_automation_grouped_counts($pdo, 'SELECT r.status,COUNT(*) AS total FROM mcp_automation_runs r INNER JOIN mcp_automation_grants g ON g.id=r.grant_id WHERE g.authorizing_user_id=? GROUP BY r.status', [$userId]);
    $actionCounts = mg_mcp_automation_grouped_counts($pdo, 'SELECT aa.status,COUNT(*) AS total FROM mcp_automation_actions aa INNER JOIN mcp_automation_runs r ON r.id=aa.run_id INNER JOIN mcp_automation_grants g ON g.id=r.grant_id WHERE g.authorizing_user_id=? GROUP BY aa.status', [$userId]);

    $summaryStmt = $pdo->prepare("SELECT
        (SELECT COUNT(*) FROM mcp_action_receipts ar INNER JOIN mcp_automation_grants g ON g.id=ar.grant_id WHERE g.authorizing_user_id=?) AS receipt_count,
        (SELECT COUNT(*) FROM mcp_automation_runs r INNER JOIN mcp_automation_grants g ON g.id=r.grant_id WHERE g.authorizing_user_id=? AND r.cancellation_requested_at IS NOT NULL) AS cancellation_count,
        (SELECT COUNT(*) FROM mcp_automation_triggers t INNER JOIN mcp_automations a ON a.id=t.automation_id INNER JOIN mcp_automation_grants g ON g.id=a.grant_id WHERE g.authorizing_user_id=? AND t.status='active' AND t.next_due_at IS NOT NULL AND t.next_due_at<=NOW()) AS due_count");
    $summaryStmt->execute([$userId, $userId, $userId]);
    $totals = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $runStmt = $pdo->prepare("SELECT r.public_id,r.status,r.scheduled_at,r.queued_at,r.started_at,r.completed_at,r.cancellation_requested_at,r.output_summary_json,r.created_at,
        a.public_id AS automation_public_id,a.name AS automation_name,g.public_id AS grant_public_id,t.public_id AS trigger_public_id,t.trigger_type,
        COUNT(DISTINCT aa.id) AS action_count,COUNT(DISTINCT ar.id) AS receipt_count
        FROM mcp_automation_runs r
        INNER JOIN mcp_automation_grants g ON g.id=r.grant_id
        INNER JOIN mcp_automations a ON a.id=r.automation_id
        LEFT JOIN mcp_automation_triggers t ON t.id=r.trigger_id
        LEFT JOIN mcp_automation_actions aa ON aa.run_id=r.id
        LEFT JOIN mcp_action_receipts ar ON ar.run_id=r.id
        WHERE g.authorizing_user_id=? GROUP BY r.id,a.id,g.id,t.id ORDER BY r.id DESC LIMIT 40");
    $runStmt->execute([$userId]);
    $runs = array_map(static function (array $row): array {
        return [
            'id' => (string)$row['public_id'],
            'status' => (string)$row['status'],
            'scheduled_at' => $row['scheduled_at'] !== null ? (string)$row['scheduled_at'] : null,
            'queued_at' => $row['queued_at'] !== null ? (string)$row['queued_at'] : null,
            'started_at' => $row['started_at'] !== null ? (string)$row['started_at'] : null,
            'completed_at' => $row['completed_at'] !== null ? (string)$row['completed_at'] : null,
            'cancellation_requested_at' => $row['cancellation_requested_at'] !== null ? (string)$row['cancellation_requested_at'] : null,
            'created_at' => (string)$row['created_at'],
            'summary' => mg_mcp_automation_json_object($row['output_summary_json']),
            'action_count' => (int)$row['action_count'],
            'receipt_count' => (int)$row['receipt_count'],
            'automation' => ['id' => (string)$row['automation_public_id'], 'name' => (string)$row['automation_name']],
            'grant_id' => (string)$row['grant_public_id'],
            'trigger' => $row['trigger_public_id'] !== null ? ['id' => (string)$row['trigger_public_id'], 'type' => (string)$row['trigger_type']] : null,
            'cancellable' => in_array((string)$row['status'], MG_MCP_AUTOMATION_MUTABLE_RUN_STATUSES, true) && $row['cancellation_requested_at'] === null,
        ];
    }, $runStmt->fetchAll(PDO::FETCH_ASSOC));

    $eventStmt = $pdo->prepare("SELECT public_id,severity,event_type,message,evidence_json,created_at FROM mcp_security_events WHERE user_id=? AND event_type LIKE 'mcp.automation%' ORDER BY id DESC LIMIT 30");
    $eventStmt->execute([$userId]);
    $events = array_map(static fn(array $row): array => [
        'id' => (string)$row['public_id'],
        'severity' => (string)$row['severity'],
        'type' => (string)$row['event_type'],
        'message' => (string)$row['message'],
        'evidence' => mg_mcp_automation_json_object($row['evidence_json']),
        'created_at' => (string)$row['created_at'],
    ], $eventStmt->fetchAll(PDO::FETCH_ASSOC));

    $connections = mg_mcp_automation_owner_connections($pdo, $userId);
    $health = [];
    foreach ($connections as $connection) {
        if ((string)$connection['status'] !== 'active') {
            $health[] = ['severity' => 'warning', 'label' => (string)$connection['display_name'], 'message' => 'Connection status is ' . (string)$connection['status'] . '.'];
        }
        if ((string)$connection['client']['status'] !== 'active') {
            $health[] = ['severity' => 'warning', 'label' => (string)$connection['display_name'], 'message' => 'Client status is ' . (string)$connection['client']['status'] . '.'];
        }
        if ($connection['expires_at'] !== null && strtotime((string)$connection['expires_at']) <= time()) {
            $health[] = ['severity' => 'critical', 'label' => (string)$connection['display_name'], 'message' => 'Connection authorization has expired.'];
        }
    }
    if ((int)($totals['receipt_count'] ?? 0) > 0) {
        $health[] = ['severity' => 'critical', 'label' => 'Action receipts', 'message' => 'Receipts exist. Phase 4D remains execution-disabled, so review this evidence before future activation.'];
    }

    return [
        'counts' => [
            'grants' => $grantCounts,
            'definitions' => $definitionCounts,
            'triggers' => $triggerCounts,
            'runs' => $runCounts,
            'actions' => $actionCounts,
            'receipts' => (int)($totals['receipt_count'] ?? 0),
            'cancellation_requests' => (int)($totals['cancellation_count'] ?? 0),
            'due_schedules' => (int)($totals['due_count'] ?? 0),
        ],
        'connections' => $connections,
        'runs' => $runs,
        'events' => $events,
        'health' => $health,
        'execution_enabled' => false,
        'scheduler_enabled' => false,
        'worker_enabled' => false,
    ];
}

function mg_mcp_automation_emergency_pause_all(PDO $pdo, array $user, string $reason): array
{
    $userId = (int)($user['id'] ?? 0);
    $reason = mg_mcp_automation_text($reason, 8, 255, 'Emergency-pause reason');
    $pdo->beginTransaction();
    try {
        $connectionStmt = $pdo->prepare('SELECT c.id,c.client_id,c.workspace_type,c.workspace_id,c.public_id,c.display_name FROM mcp_connections c WHERE c.user_id=? AND EXISTS (SELECT 1 FROM mcp_automation_grants g WHERE g.connection_id=c.id AND g.authorizing_user_id=?) FOR UPDATE');
        $connectionStmt->execute([$userId, $userId]);
        $connections = $connectionStmt->fetchAll(PDO::FETCH_ASSOC);

        $grantStmt = $pdo->prepare("UPDATE mcp_automation_grants SET status='paused',revocation_version=revocation_version+1,updated_at=NOW() WHERE authorizing_user_id=? AND status='active'");
        $grantStmt->execute([$userId]);
        $definitionStmt = $pdo->prepare("UPDATE mcp_automations a INNER JOIN mcp_automation_grants g ON g.id=a.grant_id SET a.status='paused',a.next_run_at=NULL,a.updated_at=NOW() WHERE g.authorizing_user_id=? AND a.status='active'");
        $definitionStmt->execute([$userId]);
        $triggerStmt = $pdo->prepare("UPDATE mcp_automation_triggers t INNER JOIN mcp_automations a ON a.id=t.automation_id INNER JOIN mcp_automation_grants g ON g.id=a.grant_id SET t.status='paused',t.next_due_at=NULL,t.updated_at=NOW() WHERE g.authorizing_user_id=? AND t.status='active'");
        $triggerStmt->execute([$userId]);
        $runStmt = $pdo->prepare("UPDATE mcp_automation_runs r INNER JOIN mcp_automation_grants g ON g.id=r.grant_id SET r.cancellation_requested_at=COALESCE(r.cancellation_requested_at,NOW()),r.updated_at=NOW() WHERE g.authorizing_user_id=? AND r.status IN ('queued','evaluating','waiting_for_approval','approved','executing')");
        $runStmt->execute([$userId]);

        $counts = ['grants_paused' => $grantStmt->rowCount(), 'definitions_paused' => $definitionStmt->rowCount(), 'triggers_paused' => $triggerStmt->rowCount(), 'runs_cancel_requested' => $runStmt->rowCount()];
        foreach ($connections as $connection) {
            mg_mcp_automation_insert_security_event($pdo, $connection, $userId, 'mcp.automation_emergency_pause.activated', 'Owner activated the MCP automation emergency pause.', $counts + [
                'reason' => $reason,
                'connection_public_id' => (string)$connection['public_id'],
                'scheduler_enabled' => false,
                'worker_enabled' => false,
                'execution_enabled' => false,
            ], 'critical');
        }
        $pdo->commit();
        $metadata = $counts + ['reason' => $reason, 'scheduler_enabled' => false, 'worker_enabled' => false, 'execution_enabled' => false];
        mg_audit('mcp_automation_emergency_pause_activated', 'mcp_automation_control', $metadata, $userId);
        mg_event('mcp.automation_emergency_pause.activated', $metadata, $userId);
        mg_security_log('critical', 'mcp.automation_emergency_pause.activated', 'Owner activated the MCP automation emergency pause.', $metadata, $userId);
        return $metadata;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_mcp_automation_pause_connection(PDO $pdo, array $user, string $connectionPublicId, string $reason): array
{
    $userId = (int)($user['id'] ?? 0);
    $reason = mg_mcp_automation_text($reason, 8, 255, 'Connection-pause reason');
    $pdo->beginTransaction();
    try {
        $connection = mg_mcp_automation_lock_owner_connection($pdo, $userId, $connectionPublicId);
        $grantStmt = $pdo->prepare("UPDATE mcp_automation_grants SET status='paused',revocation_version=revocation_version+1,updated_at=NOW() WHERE authorizing_user_id=? AND connection_id=? AND status='active'");
        $grantStmt->execute([$userId, (int)$connection['id']]);
        $definitionStmt = $pdo->prepare("UPDATE mcp_automations a INNER JOIN mcp_automation_grants g ON g.id=a.grant_id SET a.status='paused',a.next_run_at=NULL,a.updated_at=NOW() WHERE g.authorizing_user_id=? AND g.connection_id=? AND a.status='active'");
        $definitionStmt->execute([$userId, (int)$connection['id']]);
        $triggerStmt = $pdo->prepare("UPDATE mcp_automation_triggers t INNER JOIN mcp_automations a ON a.id=t.automation_id INNER JOIN mcp_automation_grants g ON g.id=a.grant_id SET t.status='paused',t.next_due_at=NULL,t.updated_at=NOW() WHERE g.authorizing_user_id=? AND g.connection_id=? AND t.status='active'");
        $triggerStmt->execute([$userId, (int)$connection['id']]);
        $runStmt = $pdo->prepare("UPDATE mcp_automation_runs r INNER JOIN mcp_automation_grants g ON g.id=r.grant_id SET r.cancellation_requested_at=COALESCE(r.cancellation_requested_at,NOW()),r.updated_at=NOW() WHERE g.authorizing_user_id=? AND g.connection_id=? AND r.status IN ('queued','evaluating','waiting_for_approval','approved','executing')");
        $runStmt->execute([$userId, (int)$connection['id']]);
        $counts = [
            'connection_public_id' => $connectionPublicId,
            'grants_paused' => $grantStmt->rowCount(),
            'definitions_paused' => $definitionStmt->rowCount(),
            'triggers_paused' => $triggerStmt->rowCount(),
            'runs_cancel_requested' => $runStmt->rowCount(),
            'reason' => $reason,
            'execution_enabled' => false,
        ];
        mg_mcp_automation_insert_security_event($pdo, $connection, $userId, 'mcp.automation_connection_pause.activated', 'Owner paused all automation for one MCP connection.', $counts, 'critical');
        $pdo->commit();
        mg_audit('mcp_automation_connection_pause_activated', 'mcp_connection', $counts, $userId);
        mg_event('mcp.automation_connection_pause.activated', $counts, $userId);
        mg_security_log('critical', 'mcp.automation_connection_pause.activated', 'Owner paused all automation for one MCP connection.', $counts, $userId);
        return $counts;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_mcp_automation_request_run_cancellation(PDO $pdo, array $user, string $runPublicId, string $reason): array
{
    $userId = (int)($user['id'] ?? 0);
    $reason = mg_mcp_automation_text($reason, 8, 255, 'Cancellation reason');
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT r.*,g.public_id AS grant_public_id,g.authorizing_user_id,c.id AS connection_id,c.client_id,c.workspace_type,c.workspace_id,c.public_id AS connection_public_id FROM mcp_automation_runs r INNER JOIN mcp_automation_grants g ON g.id=r.grant_id INNER JOIN mcp_connections c ON c.id=g.connection_id WHERE r.public_id=? AND g.authorizing_user_id=? LIMIT 1 FOR UPDATE");
        $stmt->execute([$runPublicId, $userId]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$run) throw new MgMcpAutomationGrantException('Automation run not found.', 404, 'MCP_AUTOMATION_RUN_NOT_FOUND');
        $mutable = in_array((string)$run['status'], MG_MCP_AUTOMATION_MUTABLE_RUN_STATUSES, true);
        if ($mutable) {
            $pdo->prepare('UPDATE mcp_automation_runs SET cancellation_requested_at=COALESCE(cancellation_requested_at,NOW()),updated_at=NOW() WHERE id=?')->execute([(int)$run['id']]);
        }
        $connection = ['id' => (int)$run['connection_id'], 'client_id' => (int)$run['client_id'], 'workspace_type' => $run['workspace_type'], 'workspace_id' => $run['workspace_id']];
        $metadata = [
            'run_public_id' => $runPublicId,
            'grant_public_id' => (string)$run['grant_public_id'],
            'run_status' => (string)$run['status'],
            'cancellation_requested' => $mutable,
            'reason' => $reason,
            'execution_enabled' => false,
        ];
        mg_mcp_automation_insert_security_event($pdo, $connection, $userId, 'mcp.automation_run.cancellation_requested', 'Owner requested cancellation of an MCP automation run.', $metadata, $mutable ? 'medium' : 'info');
        $pdo->commit();
        mg_audit('mcp_automation_run_cancellation_requested', 'mcp_automation_run', $metadata, $userId);
        mg_event('mcp.automation_run.cancellation_requested', $metadata, $userId);
        mg_security_log($mutable ? 'warning' : 'info', 'mcp.automation_run.cancellation_requested', 'Owner requested cancellation of an MCP automation run.', $metadata, $userId);
        return $metadata;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
