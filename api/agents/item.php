<?php
declare(strict_types=1);

require_once __DIR__ . '/_agent.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$input = $method === 'GET' ? [] : mg_input();
$id = mg_agent_request_id($input);

if ($method === 'GET') {
    $user = mg_require_api_user();
    $agent = mg_agent_require_owned((int) $user['id'], $id);
    mg_ok(['agent' => mg_agent_row_to_public($agent)]);
}

if ($method === 'PATCH' || $method === 'POST') {
    $user = mg_require_permission('agent.update');
    mg_require_csrf_for_write($input);
    $pdo = mg_db();

    try {
        $pdo->beginTransaction();
        $agent = mg_agent_require_owned((int) $user['id'], $id, true);
        if (($agent['lifecycle_status'] ?? '') !== 'active') {
            mg_fail('Archived agents must be restored before editing.', 409);
        }

        $name = array_key_exists('name', $input) ? mg_agent_validate_name($input['name']) : (string) $agent['name'];
        $category = array_key_exists('category', $input) ? mg_agent_validate_category($input['category']) : ($agent['category'] ?? null);
        $config = array_key_exists('config', $input) ? mg_agent_validate_config($input['config']) : (json_decode((string) ($agent['config_json'] ?? '{}'), true) ?: []);
        $configJson = $config ? json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        $stmt = $pdo->prepare('UPDATE agents SET name = ?, category = ?, config_json = ?, version_no = version_no + 1, updated_at = NOW() WHERE id = ? AND user_id = ?');
        $stmt->execute([$name, $category, $configJson, (int) $agent['id'], (int) $user['id']]);
        $updated = mg_agent_find_owned((int) $user['id'], $id, true);
        if (!$updated) throw new RuntimeException('Updated agent could not be loaded.');
        mg_agent_history($pdo, $updated, 'updated');
        $pdo->commit();

        mg_audit('agent.updated', 'agent', ['agent_id' => $id], (int) $user['id']);
        mg_event('agent.updated', ['agent_id' => $id], (int) $user['id']);
        mg_ok(['agent' => mg_agent_row_to_public($updated)], 'Agent updated.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof RuntimeException) throw $e;
        mg_security_log('error', 'agent.update_failed', 'Agent update failed.', ['agent_id' => $id, 'exception_type' => get_class($e)], (int) $user['id']);
        mg_fail('Unable to update agent right now.', 500);
    }
}

if ($method === 'DELETE') {
    $user = mg_require_permission('agent.delete');
    mg_require_csrf_for_write($input);
    $pdo = mg_db();

    try {
        $pdo->beginTransaction();
        $agent = mg_agent_require_owned((int) $user['id'], $id, true);
        $stmt = $pdo->prepare("UPDATE agents SET lifecycle_status = 'deleted', runtime_status = 'paused', deleted_at = NOW(), archived_at = COALESCE(archived_at, NOW()), paused_at = NOW(), version_no = version_no + 1, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([(int) $agent['id'], (int) $user['id']]);
        $deleted = mg_agent_find_owned((int) $user['id'], $id, true);
        if (!$deleted) throw new RuntimeException('Deleted agent could not be loaded.');
        mg_agent_history($pdo, $deleted, 'deleted', ['retention' => 'soft_delete_preserves_financial_history']);
        $pdo->commit();

        mg_audit('agent.deleted', 'agent', ['agent_id' => $id, 'agent_name_snapshot' => $deleted['name']], (int) $user['id']);
        mg_event('agent.deleted', ['agent_id' => $id], (int) $user['id']);
        mg_ok(['deleted' => true, 'agent_id' => $id], 'Agent deleted.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_security_log('error', 'agent.delete_failed', 'Agent deletion failed.', ['agent_id' => $id, 'exception_type' => get_class($e)], (int) $user['id']);
        mg_fail('Unable to delete agent right now.', 500);
    }
}

mg_fail('Method not allowed.', 405);