<?php
declare(strict_types=1);

require_once __DIR__ . '/_agent.php';

mg_require_method('POST');
$user = mg_require_permission('agent.runtime.manage');
$input = mg_input();
mg_require_csrf_for_write($input);
$id = mg_agent_request_id($input);
$status = trim((string) ($input['status'] ?? ''));

if (!in_array($status, ['running', 'paused'], true)) {
    mg_fail('Invalid runtime status.', 422, ['status' => 'Choose running or paused.']);
}

$pdo = mg_db();
try {
    $pdo->beginTransaction();
    $agent = mg_agent_require_owned((int) $user['id'], $id, true);
    if (($agent['lifecycle_status'] ?? '') !== 'active') {
        mg_fail('Archived agents cannot be started or paused.', 409);
    }

    if (($agent['runtime_status'] ?? '') === $status) {
        $pdo->commit();
        mg_ok(['agent' => mg_agent_row_to_public($agent)], 'Agent status unchanged.');
    }

    if ($status === 'running') {
        $stmt = $pdo->prepare(
            "UPDATE agents SET runtime_status = 'running', started_at = NOW(), version_no = version_no + 1, updated_at = NOW() WHERE id = ? AND user_id = ?"
        );
    } else {
        $stmt = $pdo->prepare(
            "UPDATE agents SET runtime_status = 'paused', paused_at = NOW(), version_no = version_no + 1, updated_at = NOW() WHERE id = ? AND user_id = ?"
        );
    }
    $stmt->execute([(int) $agent['id'], (int) $user['id']]);

    $updated = mg_agent_find_owned((int) $user['id'], $id, true);
    if (!$updated) {
        throw new RuntimeException('Agent status update could not be loaded.');
    }
    mg_agent_history($pdo, $updated, $status === 'running' ? 'started' : 'paused');
    $pdo->commit();

    $event = $status === 'running' ? 'agent.started' : 'agent.paused';
    mg_audit($event, 'agent', ['agent_id' => $id], (int) $user['id']);
    mg_event($event, ['agent_id' => $id], (int) $user['id']);
    mg_ok(['agent' => mg_agent_row_to_public($updated)], $status === 'running' ? 'Agent started.' : 'Agent paused.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'agent.status_failed', 'Agent status update failed.', ['agent_id' => $id, 'exception_type' => get_class($e)], (int) $user['id']);
    mg_fail('Unable to update agent status right now.', 500);
}
