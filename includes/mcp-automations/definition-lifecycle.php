<?php
declare(strict_types=1);

function mg_mcp_automation_lock_owner_definition(PDO $pdo, int $userId, string $publicId): array
{
    $stmt = $pdo->prepare(
        "SELECT a.*,g.public_id AS grant_public_id,g.status AS grant_status,g.allowed_playbooks_json,g.maximum_operation_class AS grant_maximum_operation_class,
                g.risk_ceiling,g.revocation_version,g.connection_id,g.authorizing_user_id,g.workspace_type AS grant_workspace_type,g.workspace_id AS grant_workspace_id,
                g.expires_at AS grant_expires_at,c.public_id AS connection_public_id,c.status AS connection_status,
                c.maximum_operation_class AS connection_maximum_operation_class,c.expires_at AS connection_expires_at,c.client_id,
                cl.status AS client_status,cl.maximum_operation_class AS client_maximum_operation_class,mw.status AS workspace_status
         FROM mcp_automations a
         INNER JOIN mcp_automation_grants g ON g.id=a.grant_id
         INNER JOIN mcp_connections c ON c.id=g.connection_id
         INNER JOIN mcp_clients cl ON cl.id=c.client_id
         LEFT JOIN merchant_workspaces mw ON g.workspace_type IN ('merchant','merchant_workspace') AND mw.id=g.workspace_id
         WHERE a.public_id=? AND a.owner_user_id=? AND g.authorizing_user_id=? LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$publicId, $userId, $userId]);
    $automation = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$automation) {
        throw new MgMcpAutomationGrantException('Automation definition not found.', 404, 'MCP_AUTOMATION_DEFINITION_NOT_FOUND');
    }
    return $automation;
}

function mg_mcp_automation_definition_grant_projection(array $automation): array
{
    return [
        'id' => (int)$automation['grant_id'],
        'public_id' => (string)$automation['grant_public_id'],
        'connection_id' => (int)$automation['connection_id'],
        'authorizing_user_id' => (int)$automation['authorizing_user_id'],
        'workspace_type' => $automation['grant_workspace_type'],
        'workspace_id' => $automation['grant_workspace_id'],
        'status' => (string)$automation['grant_status'],
        'allowed_playbooks_json' => $automation['allowed_playbooks_json'],
        'maximum_operation_class' => (string)$automation['grant_maximum_operation_class'],
        'risk_ceiling' => (string)$automation['risk_ceiling'],
        'revocation_version' => (int)$automation['revocation_version'],
        'expires_at' => $automation['grant_expires_at'],
        'connection_public_id' => (string)$automation['connection_public_id'],
        'connection_status' => (string)$automation['connection_status'],
        'connection_maximum_operation_class' => (string)$automation['connection_maximum_operation_class'],
        'connection_expires_at' => $automation['connection_expires_at'],
        'client_id' => (int)$automation['client_id'],
        'client_status' => (string)$automation['client_status'],
        'client_maximum_operation_class' => (string)$automation['client_maximum_operation_class'],
        'workspace_status' => $automation['workspace_status'],
    ];
}

function mg_mcp_automation_transition_definition(PDO $pdo, array $user, string $automationPublicId, string $transition, string $reason): array
{
    $userId = (int)($user['id'] ?? 0);
    $transition = strtolower(trim($transition));
    $reason = mg_mcp_automation_text($reason, 5, 255, 'Action reason');
    $pdo->beginTransaction();
    try {
        $automation = mg_mcp_automation_lock_owner_definition($pdo, $userId, $automationPublicId);
        $current = (string)$automation['status'];
        $target = match ($transition) {
            'activate' => 'active',
            'pause' => 'paused',
            'resume' => 'active',
            'revoke' => 'revoked',
            default => throw new MgMcpAutomationGrantException('Unknown automation-definition action.'),
        };
        $allowed = [
            'draft' => ['active', 'revoked'],
            'active' => ['paused', 'revoked'],
            'paused' => ['active', 'revoked'],
            'failed' => ['paused', 'revoked'],
            'expired' => ['revoked'],
            'completed' => ['revoked'],
            'revoked' => [],
            'pending_approval' => ['paused', 'revoked'],
        ];
        if (!in_array($target, $allowed[$current] ?? [], true)) {
            throw new MgMcpAutomationGrantException('That automation transition is not allowed from the current state.', 409, 'MCP_AUTOMATION_DEFINITION_TRANSITION_DENIED');
        }
        $grant = mg_mcp_automation_definition_grant_projection($automation);
        if ($target === 'active') {
            if ((string)$automation['grant_status'] !== 'active') {
                throw new MgMcpAutomationGrantException('The parent automation grant must be active.', 409, 'MCP_AUTOMATION_GRANT_INACTIVE');
            }
            mg_mcp_automation_assert_grant_activatable($pdo, $grant);
            mg_mcp_automation_definition_playbook($grant, (string)$automation['playbook_key']);
        }

        $pdo->prepare('UPDATE mcp_automations SET status=?,current_version=current_version+1,updated_at=NOW() WHERE id=?')
            ->execute([$target, (int)$automation['id']]);
        $triggerStatus = match ($target) {
            'active' => 'active',
            'paused' => 'paused',
            'revoked' => 'revoked',
            default => 'paused',
        };
        $pdo->prepare('UPDATE mcp_automation_triggers SET status=?,updated_at=NOW() WHERE automation_id=? AND trigger_type=\'manual\'')
            ->execute([$triggerStatus, (int)$automation['id']]);
        if (in_array($target, ['paused', 'revoked'], true)) {
            $pdo->prepare(
                "UPDATE mcp_automation_runs
                 SET cancellation_requested_at=COALESCE(cancellation_requested_at,NOW()),updated_at=NOW()
                 WHERE automation_id=? AND status IN ('queued','evaluating','waiting_for_approval','approved','executing')"
            )->execute([(int)$automation['id']]);
        }

        $connection = [
            'id' => (int)$automation['connection_id'],
            'client_id' => (int)$automation['client_id'],
            'workspace_type' => $automation['grant_workspace_type'],
            'workspace_id' => $automation['grant_workspace_id'],
        ];
        mg_mcp_automation_insert_security_event($pdo, $connection, $userId, 'mcp.automation_definition.' . $target, 'Owner changed an MCP automation definition state.', [
            'automation_public_id' => $automationPublicId,
            'grant_public_id' => (string)$automation['grant_public_id'],
            'from' => $current,
            'to' => $target,
            'reason' => $reason,
            'simulation_only' => true,
            'execution_enabled' => false,
        ], $target === 'revoked' ? 'medium' : 'info');
        $pdo->commit();

        $metadata = [
            'automation_public_id' => $automationPublicId,
            'grant_public_id' => (string)$automation['grant_public_id'],
            'from' => $current,
            'to' => $target,
            'reason' => $reason,
            'simulation_only' => true,
            'execution_enabled' => false,
        ];
        mg_audit('mcp_automation_definition_' . $target, 'mcp_automation', $metadata, $userId);
        mg_event('mcp.automation_definition.' . $target, $metadata, $userId);
        mg_security_log($target === 'revoked' ? 'warning' : 'info', 'mcp.automation_definition.' . $target, 'Owner changed an MCP automation definition state.', $metadata, $userId);
        return ['id' => $automationPublicId, 'status' => $target];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
