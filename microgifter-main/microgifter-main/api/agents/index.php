<?php
declare(strict_types=1);

require_once __DIR__ . '/_agent.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $user = mg_require_api_user();
    $lifecycle = trim((string) ($_GET['lifecycle'] ?? 'active'));
    if (!in_array($lifecycle, ['active', 'archived'], true)) {
        mg_fail('Invalid lifecycle filter.', 422);
    }

    $stmt = mg_db()->prepare(
        'SELECT * FROM agents WHERE user_id = ? AND lifecycle_status = ? ORDER BY updated_at DESC, id DESC'
    );
    $stmt->execute([(int) $user['id'], $lifecycle]);
    $agents = array_map('mg_agent_row_to_public', $stmt->fetchAll());
    mg_ok(['agents' => $agents]);
}

if ($method === 'POST') {
    $user = mg_require_permission('agent.create');
    $input = mg_input();
    mg_require_csrf_for_write($input);

    $name = mg_agent_validate_name($input['name'] ?? '');
    $category = mg_agent_validate_category($input['category'] ?? null);
    $config = mg_agent_validate_config($input['config'] ?? []);
    $publicId = mg_agent_public_id();
    $configJson = $config ? json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

    $pdo = mg_db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO agents (public_id, user_id, name, category, config_json, runtime_status, lifecycle_status, version_no, paused_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW(), NOW())'
        );
        $stmt->execute([$publicId, (int) $user['id'], $name, $category, $configJson, 'paused', 'active']);
        $agent = mg_agent_find_owned((int) $user['id'], $publicId, true);
        if (!$agent) {
            throw new RuntimeException('Created agent could not be loaded.');
        }
        mg_agent_history($pdo, $agent, 'created');
        $pdo->commit();

        mg_audit('agent.created', 'agent', ['agent_id' => $publicId], (int) $user['id']);
        mg_event('agent.created', ['agent_id' => $publicId], (int) $user['id']);
        mg_ok(['agent' => mg_agent_row_to_public($agent)], 'Agent created.', 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        mg_security_log('error', 'agent.create_failed', 'Agent creation failed.', ['exception_type' => get_class($e)], (int) $user['id']);
        mg_fail('Unable to create agent right now.', 500);
    }
}

mg_fail('Method not allowed.', 405);
