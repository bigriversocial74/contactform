<?php
declare(strict_types=1);

function mg_mcp_automation_expire_owner_grants(PDO $pdo, int $userId): void
{
    $pdo->prepare(
        "UPDATE mcp_automation_grants
         SET status='expired',revocation_version=revocation_version+1,updated_at=NOW()
         WHERE authorizing_user_id=? AND status IN ('draft','active','paused') AND expires_at IS NOT NULL AND expires_at<=NOW()"
    )->execute([$userId]);
}

function mg_mcp_automation_owner_grants(PDO $pdo, int $userId): array
{
    if (!mg_mcp_automation_schema_ready($pdo)) {
        throw new MgMcpAutomationGrantException('The MCP automation foundation has not been imported.', 503, 'MCP_AUTOMATION_SCHEMA_MISSING');
    }
    mg_mcp_automation_expire_owner_grants($pdo, $userId);
    $stmt = $pdo->prepare(
        "SELECT g.*,c.public_id AS connection_public_id,c.display_name AS connection_name,c.status AS connection_status,
                cl.public_id AS client_public_id,cl.display_name AS client_name,cl.status AS client_status,
                mw.public_id AS workspace_public_id,
                COALESCE(a.automation_count,0) AS automation_count,
                COALESCE(r.run_count,0) AS run_count,
                COALESCE(rc.receipt_count,0) AS receipt_count
         FROM mcp_automation_grants g
         INNER JOIN mcp_connections c ON c.id=g.connection_id
         INNER JOIN mcp_clients cl ON cl.id=c.client_id
         LEFT JOIN merchant_workspaces mw ON g.workspace_type IN ('merchant','merchant_workspace') AND mw.id=g.workspace_id
         LEFT JOIN (SELECT grant_id,COUNT(*) AS automation_count FROM mcp_automations GROUP BY grant_id) a ON a.grant_id=g.id
         LEFT JOIN (SELECT grant_id,COUNT(*) AS run_count FROM mcp_automation_runs GROUP BY grant_id) r ON r.grant_id=g.id
         LEFT JOIN (SELECT grant_id,COUNT(*) AS receipt_count FROM mcp_action_receipts GROUP BY grant_id) rc ON rc.grant_id=g.id
         WHERE g.authorizing_user_id=?
         ORDER BY FIELD(g.status,'active','draft','paused','expired','revoked'),g.updated_at DESC,g.id DESC"
    );
    $stmt->execute([$userId]);
    return array_map(static function (array $row): array {
        $target = mg_mcp_automation_json_object($row['target_policy_json']);
        return [
            'database_id' => (int)$row['id'],
            'id' => (string)$row['public_id'],
            'label' => (string)($target['owner_label'] ?? 'Automation grant'),
            'reason' => (string)($target['owner_reason'] ?? ''),
            'status' => (string)$row['status'],
            'maximum_operation_class' => (string)$row['maximum_operation_class'],
            'playbooks' => mg_mcp_automation_json_list($row['allowed_playbooks_json']),
            'tools' => mg_mcp_automation_json_list($row['allowed_tools_json']),
            'trigger_types' => mg_mcp_automation_json_list($row['allowed_trigger_types_json']),
            'approval_policy' => (string)$row['approval_policy'],
            'risk_ceiling' => (string)$row['risk_ceiling'],
            'currency' => $row['currency'] !== null ? (string)$row['currency'] : null,
            'limits' => [
                'per_run_amount_limit_cents' => $row['per_run_amount_limit_cents'] !== null ? (int)$row['per_run_amount_limit_cents'] : null,
                'daily_amount_limit_cents' => $row['daily_amount_limit_cents'] !== null ? (int)$row['daily_amount_limit_cents'] : null,
                'lifetime_amount_limit_cents' => $row['lifetime_amount_limit_cents'] !== null ? (int)$row['lifetime_amount_limit_cents'] : null,
                'per_run_quantity_limit' => $row['per_run_quantity_limit'] !== null ? (int)$row['per_run_quantity_limit'] : null,
                'daily_quantity_limit' => $row['daily_quantity_limit'] !== null ? (int)$row['daily_quantity_limit'] : null,
                'lifetime_quantity_limit' => $row['lifetime_quantity_limit'] !== null ? (int)$row['lifetime_quantity_limit'] : null,
                'minimum_frequency_seconds' => $row['minimum_frequency_seconds'] !== null ? (int)$row['minimum_frequency_seconds'] : null,
                'maximum_concurrent_runs' => (int)$row['maximum_concurrent_runs'],
            ],
            'target_policy' => $target,
            'starts_at' => $row['starts_at'] !== null ? (string)$row['starts_at'] : null,
            'expires_at' => $row['expires_at'] !== null ? (string)$row['expires_at'] : null,
            'last_used_at' => $row['last_used_at'] !== null ? (string)$row['last_used_at'] : null,
            'revocation_version' => (int)$row['revocation_version'],
            'connection' => [
                'id' => (string)$row['connection_public_id'],
                'name' => (string)$row['connection_name'],
                'status' => (string)$row['connection_status'],
            ],
            'client' => [
                'id' => (string)$row['client_public_id'],
                'name' => (string)$row['client_name'],
                'status' => (string)$row['client_status'],
            ],
            'workspace_id' => $row['workspace_public_id'] !== null ? (string)$row['workspace_public_id'] : null,
            'automation_count' => (int)$row['automation_count'],
            'run_count' => (int)$row['run_count'],
            'receipt_count' => (int)$row['receipt_count'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_mcp_automation_grant_summary(array $grants): array
{
    $summary = array_fill_keys(MG_MCP_AUTOMATION_GRANT_STATUSES, 0);
    $summary['total'] = count($grants);
    foreach ($grants as $grant) {
        $status = (string)($grant['status'] ?? '');
        if (array_key_exists($status, $summary)) {
            $summary[$status]++;
        }
    }
    return $summary;
}

/**
 * Fail-closed policy evaluator for future schedulers/workers. Phase 4A exposes no
 * scheduler, queue, worker, automation definition, or execution endpoint.
 */
