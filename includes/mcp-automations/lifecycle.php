<?php
declare(strict_types=1);

function mg_mcp_automation_lock_owner_grant(PDO $pdo, int $userId, string $publicId): array
{
    $stmt = $pdo->prepare(
        "SELECT g.*,c.public_id AS connection_public_id,c.status AS connection_status,
                c.maximum_operation_class AS connection_maximum_operation_class,c.expires_at AS connection_expires_at,
                c.client_id,cl.status AS client_status,cl.maximum_operation_class AS client_maximum_operation_class,
                mw.status AS workspace_status
         FROM mcp_automation_grants g
         INNER JOIN mcp_connections c ON c.id=g.connection_id
         INNER JOIN mcp_clients cl ON cl.id=c.client_id
         LEFT JOIN merchant_workspaces mw ON g.workspace_type IN ('merchant','merchant_workspace') AND mw.id=g.workspace_id
         WHERE g.public_id=? AND g.authorizing_user_id=? LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$publicId, $userId]);
    $grant = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$grant) {
        throw new MgMcpAutomationGrantException('Automation grant not found.', 404, 'MCP_AUTOMATION_GRANT_NOT_FOUND');
    }
    return $grant;
}

function mg_mcp_automation_assert_grant_activatable(PDO $pdo, array $grant): void
{
    if ((string)$grant['connection_status'] !== 'active' || (string)$grant['client_status'] !== 'active') {
        throw new MgMcpAutomationGrantException('The connection and MCP client must both be active before this grant can be activated.', 409, 'MCP_AUTOMATION_CONNECTION_NOT_ACTIVE');
    }
    if ($grant['connection_expires_at'] !== null && strtotime((string)$grant['connection_expires_at']) <= time()) {
        throw new MgMcpAutomationGrantException('The connection has expired.', 409, 'MCP_AUTOMATION_CONNECTION_EXPIRED');
    }
    if ($grant['expires_at'] !== null && strtotime((string)$grant['expires_at']) <= time()) {
        throw new MgMcpAutomationGrantException('The grant has expired.', 409, 'MCP_AUTOMATION_GRANT_EXPIRED');
    }
    if ($grant['workspace_id'] !== null) {
        if (($grant['workspace_status'] ?? null) === null || in_array((string)$grant['workspace_status'], ['suspended', 'archived'], true)) {
            throw new MgMcpAutomationGrantException('The merchant workspace is unavailable.', 409, 'MCP_AUTOMATION_WORKSPACE_UNAVAILABLE');
        }
        $membership = $pdo->prepare(
            "SELECT 1 FROM merchant_workspaces mw
             LEFT JOIN merchant_team_members mt ON mt.workspace_id=mw.id AND mt.user_id=? AND mt.status='active'
             WHERE mw.id=? AND (mw.merchant_user_id=? OR mt.id IS NOT NULL) LIMIT 1"
        );
        $membership->execute([(int)$grant['authorizing_user_id'], (int)$grant['workspace_id'], (int)$grant['authorizing_user_id']]);
        if (!$membership->fetchColumn()) {
            throw new MgMcpAutomationGrantException('The grant owner no longer has access to the merchant workspace.', 409, 'MCP_AUTOMATION_WORKSPACE_ACCESS_REVOKED');
        }
    }
    $grantClass = (string)$grant['maximum_operation_class'];
    if (mg_mcp_automation_operation_rank($grantClass) > mg_mcp_automation_operation_rank((string)$grant['connection_maximum_operation_class'])
        || mg_mcp_automation_operation_rank($grantClass) > mg_mcp_automation_operation_rank((string)$grant['client_maximum_operation_class'])) {
        throw new MgMcpAutomationGrantException('The grant exceeds the current connection or client operation ceiling.', 409, 'MCP_AUTOMATION_OPERATION_CEILING');
    }

    $scopes = mg_mcp_automation_connection_scopes($pdo, (int)$grant['connection_id']);
    $catalog = mg_mcp_automation_playbook_catalog();
    foreach (mg_mcp_automation_json_list($grant['allowed_playbooks_json']) as $playbookKey) {
        $playbook = $catalog[$playbookKey] ?? null;
        if (!is_array($playbook)) {
            throw new MgMcpAutomationGrantException('The grant contains an unavailable playbook.', 409, 'MCP_AUTOMATION_PLAYBOOK_UNAVAILABLE');
        }
        foreach ((array)$playbook['tools'] as $scope) {
            if (!in_array((string)$scope, $scopes, true)) {
                throw new MgMcpAutomationGrantException('A required scope has been revoked from the connection.', 409, 'MCP_AUTOMATION_SCOPE_REVOKED');
            }
        }
    }
}

function mg_mcp_automation_transition_grant(PDO $pdo, array $user, string $grantPublicId, string $transition, string $reason): array
{
    $userId = (int)($user['id'] ?? 0);
    $transition = strtolower(trim($transition));
    $reason = mg_mcp_automation_text($reason, 5, 255, 'Action reason');
    $pdo->beginTransaction();
    try {
        $grant = mg_mcp_automation_lock_owner_grant($pdo, $userId, $grantPublicId);
        $current = (string)$grant['status'];
        $target = match ($transition) {
            'activate' => 'active',
            'pause' => 'paused',
            'resume' => 'active',
            'revoke' => 'revoked',
            default => throw new MgMcpAutomationGrantException('Unknown grant action.'),
        };
        $allowed = [
            'draft' => ['active', 'revoked'],
            'active' => ['paused', 'revoked'],
            'paused' => ['active', 'revoked'],
            'expired' => ['revoked'],
            'revoked' => [],
        ];
        if (!in_array($target, $allowed[$current] ?? [], true)) {
            throw new MgMcpAutomationGrantException('That grant transition is not allowed from the current state.', 409, 'MCP_AUTOMATION_TRANSITION_DENIED');
        }
        if ($target === 'active') {
            mg_mcp_automation_assert_grant_activatable($pdo, $grant);
        }

        if ($target === 'revoked') {
            $pdo->prepare(
                "UPDATE mcp_automation_grants
                 SET status='revoked',revoked_at=NOW(),revoked_by_user_id=?,revocation_reason=?,
                     revocation_version=revocation_version+1,updated_at=NOW() WHERE id=?"
            )->execute([$userId, $reason, (int)$grant['id']]);
            $pdo->prepare("UPDATE mcp_automations SET status='revoked',updated_at=NOW() WHERE grant_id=? AND status NOT IN ('completed','revoked')")
                ->execute([(int)$grant['id']]);
        } elseif ($target === 'paused') {
            $pdo->prepare(
                "UPDATE mcp_automation_grants SET status='paused',revocation_version=revocation_version+1,updated_at=NOW() WHERE id=?"
            )->execute([(int)$grant['id']]);
            $pdo->prepare("UPDATE mcp_automations SET status='paused',updated_at=NOW() WHERE grant_id=? AND status='active'")
                ->execute([(int)$grant['id']]);
        } else {
            $pdo->prepare('UPDATE mcp_automation_grants SET status=?,updated_at=NOW() WHERE id=?')
                ->execute([$target, (int)$grant['id']]);
        }
        if (in_array($target, ['paused', 'revoked'], true)) {
            $pdo->prepare(
                "UPDATE mcp_automation_runs
                 SET cancellation_requested_at=COALESCE(cancellation_requested_at,NOW()),updated_at=NOW()
                 WHERE grant_id=? AND status IN ('queued','evaluating','waiting_for_approval','approved','executing')"
            )->execute([(int)$grant['id']]);
        }

        $connection = [
            'id' => (int)$grant['connection_id'],
            'client_id' => (int)$grant['client_id'],
            'workspace_type' => $grant['workspace_type'],
            'workspace_id' => $grant['workspace_id'],
        ];
        mg_mcp_automation_insert_security_event($pdo, $connection, $userId, 'mcp.automation_grant.' . $target, 'Owner changed an MCP automation grant state.', [
            'grant_public_id' => $grantPublicId,
            'from' => $current,
            'to' => $target,
            'reason' => $reason,
            'execution_enabled' => false,
        ], $target === 'revoked' ? 'medium' : 'info');
        $pdo->commit();

        $metadata = [
            'grant_public_id' => $grantPublicId,
            'from' => $current,
            'to' => $target,
            'reason' => $reason,
            'execution_enabled' => false,
        ];
        mg_audit('mcp_automation_grant_' . $target, 'mcp_automation_grant', $metadata, $userId);
        mg_event('mcp.automation_grant.' . $target, $metadata, $userId);
        mg_security_log($target === 'revoked' ? 'warning' : 'info', 'mcp.automation_grant.' . $target, 'Owner changed an MCP automation grant state.', $metadata, $userId);
        return ['id' => $grantPublicId, 'status' => $target];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
