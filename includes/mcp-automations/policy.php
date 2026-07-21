<?php
declare(strict_types=1);

function mg_mcp_automation_normalize_playbooks(array $input, array $connection, array $scopes): array
{
    $requested = is_array($input['playbooks'] ?? null) ? $input['playbooks'] : [];
    $requested = array_values(array_unique(array_filter(array_map(
        static fn(mixed $value): string => strtolower(trim((string)$value)),
        $requested
    ))));
    if ($requested === []) {
        throw new MgMcpAutomationGrantException('Select at least one approved playbook.');
    }

    $catalog = mg_mcp_automation_playbook_catalog();
    $tools = [];
    $maximumClass = 'read';
    foreach ($requested as $playbookKey) {
        $playbook = $catalog[$playbookKey] ?? null;
        if (!is_array($playbook)) {
            throw new MgMcpAutomationGrantException('An unrecognized playbook was selected.');
        }
        if (!empty($playbook['workspace_required']) && $connection['workspace_id'] === null) {
            throw new MgMcpAutomationGrantException($playbook['label'] . ' requires a merchant-workspace connection.');
        }
        foreach ((array)$playbook['tools'] as $tool => $scope) {
            if (!in_array((string)$scope, $scopes, true)) {
                throw new MgMcpAutomationGrantException($playbook['label'] . ' requires the active ' . $scope . ' scope.');
            }
            $tools[(string)$tool] = true;
        }
        if (mg_mcp_automation_operation_rank((string)$playbook['operation_class']) > mg_mcp_automation_operation_rank($maximumClass)) {
            $maximumClass = (string)$playbook['operation_class'];
        }
    }

    $connectionCeiling = (string)$connection['maximum_operation_class'];
    $clientCeiling = (string)$connection['client_maximum_operation_class'];
    if (mg_mcp_automation_operation_rank($maximumClass) > mg_mcp_automation_operation_rank($connectionCeiling)
        || mg_mcp_automation_operation_rank($maximumClass) > mg_mcp_automation_operation_rank($clientCeiling)) {
        throw new MgMcpAutomationGrantException('The selected playbooks exceed the connection or client operation ceiling.', 409, 'MCP_AUTOMATION_OPERATION_CEILING');
    }

    sort($requested);
    $toolKeys = array_keys($tools);
    sort($toolKeys);
    return [
        'playbooks' => $requested,
        'tools' => $toolKeys,
        'maximum_operation_class' => $maximumClass,
    ];
}

function mg_mcp_automation_insert_security_event(PDO $pdo, array $connection, int $userId, string $eventType, string $message, array $evidence = [], string $severity = 'info'): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO mcp_security_events
         (public_id,connection_id,client_id,user_id,workspace_type,workspace_id,severity,event_type,message,evidence_json,created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,NOW())'
    );
    $stmt->execute([
        mg_public_uuid(),
        (int)$connection['id'],
        (int)$connection['client_id'],
        $userId,
        $connection['workspace_type'] !== null ? (string)$connection['workspace_type'] : null,
        $connection['workspace_id'] !== null ? (int)$connection['workspace_id'] : null,
        $severity,
        $eventType,
        mb_substr($message, 0, 500),
        json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
    ]);
}
