<?php
declare(strict_types=1);

require_once __DIR__ . '/_agent.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);
$id = mg_agent_request_id($input);
$pdo = mg_db();

try {
    $pdo->beginTransaction();
    $agent = mg_agent_require_owned((int) $user['id'], $id, true);
    if (($agent['lifecycle_status'] ?? '') === 'archived') {
        $pdo->commit();
        mg_ok(['agent' => mg_agent_row_to_public($agent)], 'Agent already archived.');
    }
    $stmt = $pdo->prepare("UPDATE agents SET lifecycle_status='archived', runtime_status='paused', archived_at=NOW(), paused_at=NOW(), version_no=version_no+1, updated_at=NOW() WHERE id=? AND user_id=?");
    $stmt->execute([(int) $agent['id'], (int) $user['id']]);
    $updated = mg_agent_find_owned((int) $user['id'], $id, true);
    if (!$updated) throw new RuntimeException('Archived agent could not be loaded.');
    mg_agent_history($pdo, $updated, 'archived');
    $pdo->commit();
    mg_audit('agent.archived', 'agent', ['agent_id' => $id], (int) $user['id']);
    mg_event('agent.archived', ['agent_id' => $id], (int) $user['id']);
    mg_ok(['agent' => mg_agent_row_to_public($updated)], 'Agent archived.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to archive agent right now.', 500);
}