<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/mcp-drafts.php';
require_once __DIR__ . '/_mcp_creator_campaign_draft_bridge.php';

function mg_mcp_draft_bridge_connection(PDO $pdo, string $connectionPublicId): array
{
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $connectionPublicId) !== 1) {
        throw new MgMcpBridgeException('Invalid connection.', 401, 'MCP_CONNECTION_INVALID');
    }
    $stmt = $pdo->prepare(
        "SELECT c.id AS connection_db_id,c.public_id AS connection_public_id,c.user_id,c.workspace_type,c.workspace_id,
                c.status AS connection_status,c.maximum_operation_class,c.token_version,c.expires_at,
                cl.id AS client_db_id,cl.public_id AS client_public_id,cl.client_key,cl.status AS client_status,
                cl.maximum_operation_class AS client_maximum_operation_class,u.status AS user_status
         FROM mcp_connections c
         INNER JOIN mcp_clients cl ON cl.id=c.client_id
         INNER JOIN users u ON u.id=c.user_id
         WHERE c.public_id=? LIMIT 1"
    );
    $stmt->execute([$connectionPublicId]);
    $context = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$context) throw new MgMcpBridgeException('Connection is unavailable.', 401, 'MCP_CONNECTION_UNAVAILABLE');
    if ((string)$context['connection_status'] !== 'active') throw new MgMcpBridgeException('Connection is not active.', 403, 'MCP_CONNECTION_INACTIVE');
    if (!in_array((string)$context['client_status'], ['development', 'active'], true)) throw new MgMcpBridgeException('Client is not active.', 403, 'MCP_CLIENT_INACTIVE');
    if ((string)$context['user_status'] !== 'active') throw new MgMcpBridgeException('Account is not active.', 403, 'MCP_ACCOUNT_INACTIVE');
    if (!empty($context['expires_at']) && strtotime((string)$context['expires_at']) <= time()) throw new MgMcpBridgeException('Connection has expired.', 403, 'MCP_CONNECTION_EXPIRED');

    $connectionClass = (string)$context['maximum_operation_class'];
    $clientClass = (string)$context['client_maximum_operation_class'];
    if (!in_array($connectionClass, ['read', 'draft'], true)
        || !in_array($clientClass, ['read', 'draft'], true)
        || mg_mcp_draft_operation_rank($connectionClass) > mg_mcp_draft_operation_rank($clientClass)) {
        throw new MgMcpBridgeException('Connection exceeds the review-only operation boundary.', 403, 'MCP_OPERATION_CLASS_DENIED');
    }

    $allowedClasses = $connectionClass === 'draft' ? ['read', 'draft'] : ['read'];
    $placeholders = implode(',', array_fill(0, count($allowedClasses), '?'));
    $scopeStmt = $pdo->prepare(
        "SELECT cs.scope_key
         FROM mcp_connection_scopes cs
         INNER JOIN mcp_scope_catalog sc ON sc.scope_key=cs.scope_key AND sc.active=1 AND sc.grantable=1
         WHERE cs.connection_id=? AND cs.revoked_at IS NULL AND sc.operation_class IN ($placeholders)
         ORDER BY cs.scope_key ASC"
    );
    $scopeStmt->execute(array_merge([(int)$context['connection_db_id']], $allowedClasses));
    $context['scopes'] = array_values(array_map('strval', $scopeStmt->fetchAll(PDO::FETCH_COLUMN)));

    $workspace = null;
    $workspaceType = trim((string)($context['workspace_type'] ?? ''));
    $workspaceId = isset($context['workspace_id']) ? (int)$context['workspace_id'] : 0;
    if ($workspaceType !== '' && $workspaceId > 0) {
        if (in_array($workspaceType, ['merchant', 'merchant_workspace'], true)) {
            $workspaceStmt = $pdo->prepare(
                "SELECT mw.public_id,mw.status
                 FROM merchant_workspaces mw
                 LEFT JOIN merchant_team_members mt ON mt.workspace_id=mw.id AND mt.user_id=? AND mt.status='active'
                 WHERE mw.id=? AND (mw.merchant_user_id=? OR mt.id IS NOT NULL) LIMIT 1"
            );
            $workspaceStmt->execute([(int)$context['user_id'], $workspaceId, (int)$context['user_id']]);
            $workspaceRow = $workspaceStmt->fetch(PDO::FETCH_ASSOC);
            if (!$workspaceRow || in_array((string)$workspaceRow['status'], ['suspended', 'archived'], true)) {
                throw new MgMcpBridgeException('Workspace access is unavailable.', 403, 'MCP_WORKSPACE_UNAVAILABLE');
            }
            $workspace = ['type' => 'merchant', 'id' => (string)$workspaceRow['public_id']];
        } else {
            $workspace = ['type' => $workspaceType, 'id' => (string)$workspaceId];
        }
    }
    $context['workspace'] = $workspace;
    return $context;
}

function mg_mcp_draft_bridge_projection(array $context): array
{
    return [
        'connectionId' => (string)$context['connection_public_id'],
        'clientKey' => (string)$context['client_key'],
        'userId' => (string)$context['user_id'],
        'workspace' => $context['workspace'] ?? null,
        'scopes' => array_values((array)$context['scopes']),
        'maximumOperationClass' => (string)$context['maximum_operation_class'],
        'tokenVersion' => (int)$context['token_version'],
        'expiresAt' => !empty($context['expires_at']) ? (string)$context['expires_at'] : null,
    ];
}

function mg_mcp_draft_bridge_authenticate(PDO $pdo, string $rawBody, array $payload): array
{
    if (!mg_mcp_bridge_enabled()) throw new MgMcpBridgeException('Bridge is disabled.', 404, 'MCP_BRIDGE_DISABLED');
    $secret = mg_mcp_bridge_secret();
    if (strlen($secret) < 32) throw new MgMcpBridgeException('Bridge is unavailable.', 503, 'MCP_BRIDGE_SECRET_INVALID');
    $timestamp = mg_mcp_bridge_header('X-Microgifter-MCP-Timestamp');
    $nonce = mg_mcp_bridge_header('X-Microgifter-MCP-Nonce');
    $signature = strtolower(mg_mcp_bridge_header('X-Microgifter-MCP-Signature'));
    if (preg_match('/^[0-9]{10}$/', $timestamp) !== 1 || abs(time() - (int)$timestamp) > 300) throw new MgMcpBridgeException('Bridge request timestamp is invalid.', 401, 'MCP_BRIDGE_TIMESTAMP_INVALID');
    if (preg_match('/^[A-Za-z0-9_-]{16,128}$/', $nonce) !== 1 || preg_match('/^[a-f0-9]{64}$/', $signature) !== 1) throw new MgMcpBridgeException('Bridge authentication is invalid.', 401, 'MCP_BRIDGE_AUTH_INVALID');
    if (!hash_equals(mg_mcp_bridge_expected_signature($secret, $timestamp, $nonce, $rawBody), $signature)) throw new MgMcpBridgeException('Bridge authentication is invalid.', 401, 'MCP_BRIDGE_AUTH_INVALID');
    $connectionId = mg_mcp_bridge_text($payload['connection_id'] ?? '', 36, 'connection');
    $context = mg_mcp_draft_bridge_connection($pdo, $connectionId);
    mg_mcp_bridge_reserve_nonce($pdo, $context, $nonce, hash('sha256', $rawBody));
    return $context;
}

function mg_mcp_draft_bridge_allowed_types(array $context): array
{
    $types = [];
    foreach (MG_MCP_DRAFT_SCOPE_BY_TYPE as $type => $scope) {
        if (in_array($scope, (array)$context['scopes'], true)) $types[] = $type;
    }
    return $types;
}

function mg_mcp_draft_bridge_dispatch(PDO $pdo, array $context, string $operation, array $arguments): array
{
    try {
        if ($operation === 'draft.create' && mg_mcp_creator_campaign_proposal_requested($arguments)) {
            return mg_mcp_creator_campaign_proposal_create($pdo, $context, $arguments);
        }
        if ($operation === 'draft.create') return mg_mcp_draft_create($pdo, $context, $arguments);
        if ($operation === 'draft.get') {
            $draft = mg_mcp_draft_get_for_connection($pdo, $context, (string)($arguments['draft_id'] ?? ''));
            mg_mcp_draft_require_context($context, (string)$draft['type']);
            return $draft;
        }
        if ($operation === 'draft.cancel') {
            $draft = mg_mcp_draft_get_for_connection($pdo, $context, (string)($arguments['draft_id'] ?? ''));
            mg_mcp_draft_require_context($context, (string)$draft['type']);
            return mg_mcp_draft_cancel_for_connection($pdo, $context, (string)$draft['id'], (string)($arguments['reason'] ?? ''));
        }
        if ($operation === 'draft.list') {
            $requestedType = trim((string)($arguments['type'] ?? ''));
            if ($requestedType !== '') mg_mcp_draft_require_context($context, mg_mcp_draft_type($requestedType));
            $result = mg_mcp_draft_list_for_connection($pdo, $context, $arguments);
            $allowed = mg_mcp_draft_bridge_allowed_types($context);
            $result['items'] = array_values(array_filter((array)$result['items'], static fn(array $item): bool => in_array((string)$item['type'], $allowed, true)));
            return $result;
        }
        throw new MgMcpBridgeException('Draft bridge operation is not allowed.', 404, 'MCP_DRAFT_OPERATION_UNKNOWN');
    } catch (MgMcpDraftException $error) {
        throw new MgMcpBridgeException($error->getMessage(), $error->httpStatus(), $error->draftCode());
    }
}
