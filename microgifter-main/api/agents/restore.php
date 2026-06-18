<?php
declare(strict_types=1);

require_once __DIR__ . '/_agent.php';

mg_require_method('POST');
$user = mg_require_permission('agent.archive');
$input = mg_input();
mg_require_csrf_for_write($input);
$id = mg_agent_request_id($input);
$pdo = mg_db();

try {
    $pdo->beginTransaction();
    $agent = mg_agent_require_owned((int) $user['id'], $id, true);
    if (($agent['lifecycle_status'] ?? '') === 'active') {
        $pdo->commit();
        mg_ok(['agent' => mg_agent_row_to_public($agent)], 'Agent already active.');
    }
    if (($agent['lifecycle_status'] ?? '') !== 'archived') {
        mg_fail('Deleted agents cannot be restored.', 409);
    }

    $stmt = $pdo->prepare(
        "UPDATE agents SET lifecycle_status = 'active', runtime_status = 'paused', restored_at = NOW(), archived_at = NULL, paused_at = NOW(), version_no = version_no + 1, updated_at = NOW()
         WHERE id = ? AND user_id = ?"
    );
    $stmt->execute([(int) $agent['id'], (int) $user['id']]);
    $updated = mg_agent_find_owned((int) $user['id'], $id, true);
    if (!$updated) {
        throw new RuntimeException('Restored agent could not be loaded.');
    }
    mg_agent_history($pdo, $updated, 'restored');
    $pdo->commit();

    mg_audit('agent.restored', 'agent', ['agent_id' => $id], (int) $user['id']);
    mg_event('agent.restored', ['agent_id' => $id], (int) $user['id']);
    mg_ok(['agent' => mg_agent_row_to_public($updated)], 'Agent restored.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'agent.restore_failed', 'Agent restore failed.', ['agent_id' => $id, 'exception_type' => get_class($e)], (int) $user['id']);
    mg_fail('Unable to restore agent right now.', 500);
}
