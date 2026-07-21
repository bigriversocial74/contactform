<?php
declare(strict_types=1);

function mg_mcp_automation_owner_definitions(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT a.*,g.public_id AS grant_public_id,g.status AS grant_status,g.target_policy_json,
                c.public_id AS connection_public_id,c.display_name AS connection_name,c.status AS connection_status,
                cl.display_name AS client_name,cl.status AS client_status,
                t.public_id AS trigger_public_id,t.status AS trigger_status,t.trigger_type,t.fire_count,
                COALESCE(r.run_count,0) AS run_count,COALESCE(ac.action_count,0) AS action_count
         FROM mcp_automations a
         INNER JOIN mcp_automation_grants g ON g.id=a.grant_id
         INNER JOIN mcp_connections c ON c.id=g.connection_id
         INNER JOIN mcp_clients cl ON cl.id=c.client_id
         LEFT JOIN mcp_automation_triggers t ON t.automation_id=a.id AND t.trigger_type='manual'
         LEFT JOIN (SELECT automation_id,COUNT(*) AS run_count FROM mcp_automation_runs GROUP BY automation_id) r ON r.automation_id=a.id
         LEFT JOIN (SELECT ru.automation_id,COUNT(*) AS action_count FROM mcp_automation_actions aa INNER JOIN mcp_automation_runs ru ON ru.id=aa.run_id GROUP BY ru.automation_id) ac ON ac.automation_id=a.id
         WHERE a.owner_user_id=? AND g.authorizing_user_id=?
         ORDER BY FIELD(a.status,'active','draft','paused','failed','completed','expired','revoked'),a.updated_at DESC,a.id DESC"
    );
    $stmt->execute([$userId, $userId]);
    return array_map(static function (array $row): array {
        $config = mg_mcp_automation_json_object($row['configuration_json']);
        return [
            'database_id' => (int)$row['id'],
            'id' => (string)$row['public_id'],
            'name' => (string)$row['name'],
            'description' => $row['description'] !== null ? (string)$row['description'] : '',
            'playbook_key' => (string)$row['playbook_key'],
            'status' => (string)$row['status'],
            'timezone' => (string)$row['timezone'],
            'current_version' => (int)$row['current_version'],
            'last_run_at' => $row['last_run_at'] !== null ? (string)$row['last_run_at'] : null,
            'next_run_at' => $row['next_run_at'] !== null ? (string)$row['next_run_at'] : null,
            'configuration' => $config,
            'grant' => ['id' => (string)$row['grant_public_id'], 'status' => (string)$row['grant_status']],
            'connection' => ['id' => (string)$row['connection_public_id'], 'name' => (string)$row['connection_name'], 'status' => (string)$row['connection_status']],
            'client' => ['name' => (string)$row['client_name'], 'status' => (string)$row['client_status']],
            'trigger' => $row['trigger_public_id'] !== null ? [
                'id' => (string)$row['trigger_public_id'],
                'type' => (string)$row['trigger_type'],
                'status' => (string)$row['trigger_status'],
                'fire_count' => (int)$row['fire_count'],
            ] : null,
            'run_count' => (int)$row['run_count'],
            'action_count' => (int)$row['action_count'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_mcp_automation_recent_simulations(PDO $pdo, int $userId, int $limit = 25): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(
        "SELECT r.public_id,r.status,r.input_fingerprint,r.queued_at,r.started_at,r.completed_at,r.output_summary_json,
                a.public_id AS automation_public_id,a.name AS automation_name,a.playbook_key,
                g.public_id AS grant_public_id,COUNT(aa.id) AS action_count
         FROM mcp_automation_runs r
         INNER JOIN mcp_automations a ON a.id=r.automation_id
         INNER JOIN mcp_automation_grants g ON g.id=r.grant_id
         LEFT JOIN mcp_automation_actions aa ON aa.run_id=r.id
         WHERE a.owner_user_id=? AND g.authorizing_user_id=?
           AND JSON_UNQUOTE(JSON_EXTRACT(r.output_summary_json,'$.mode'))='manual_simulation_only'
         GROUP BY r.id,a.id,g.id
         ORDER BY r.created_at DESC,r.id DESC
         LIMIT " . $limit
    );
    $stmt->execute([$userId, $userId]);
    return array_map(static fn(array $row): array => [
        'id' => (string)$row['public_id'],
        'status' => (string)$row['status'],
        'input_fingerprint' => (string)$row['input_fingerprint'],
        'queued_at' => (string)$row['queued_at'],
        'started_at' => $row['started_at'] !== null ? (string)$row['started_at'] : null,
        'completed_at' => $row['completed_at'] !== null ? (string)$row['completed_at'] : null,
        'summary' => mg_mcp_automation_json_object($row['output_summary_json']),
        'automation' => ['id' => (string)$row['automation_public_id'], 'name' => (string)$row['automation_name']],
        'playbook_key' => (string)$row['playbook_key'],
        'grant_id' => (string)$row['grant_public_id'],
        'action_count' => (int)$row['action_count'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_mcp_automation_definition_summary(array $definitions, array $runs): array
{
    $summary = ['total' => count($definitions), 'active' => 0, 'draft' => 0, 'paused' => 0, 'revoked' => 0, 'simulations' => count($runs)];
    foreach ($definitions as $definition) {
        $status = (string)($definition['status'] ?? '');
        if (array_key_exists($status, $summary)) {
            $summary[$status]++;
        }
    }
    return $summary;
}
