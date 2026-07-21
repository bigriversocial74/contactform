<?php
declare(strict_types=1);

function mg_mcp_automation_owner_connections(PDO $pdo, int $userId): array
{
    if (!mg_mcp_automation_schema_ready($pdo)) {
        throw new MgMcpAutomationGrantException('The MCP automation foundation has not been imported.', 503, 'MCP_AUTOMATION_SCHEMA_MISSING');
    }

    $stmt = $pdo->prepare(
        "SELECT c.id,c.public_id,c.display_name,c.status,c.maximum_operation_class,c.expires_at,
                c.workspace_type,c.workspace_id,cl.id AS client_id,cl.public_id AS client_public_id,
                cl.display_name AS client_name,cl.status AS client_status,
                cl.maximum_operation_class AS client_maximum_operation_class,
                mw.public_id AS workspace_public_id,mw.status AS workspace_status,
                GROUP_CONCAT(CASE WHEN cs.revoked_at IS NULL THEN cs.scope_key END ORDER BY cs.scope_key SEPARATOR ',') AS scope_keys
         FROM mcp_connections c
         INNER JOIN mcp_clients cl ON cl.id=c.client_id
         LEFT JOIN merchant_workspaces mw ON c.workspace_type IN ('merchant','merchant_workspace') AND mw.id=c.workspace_id
         LEFT JOIN mcp_connection_scopes cs ON cs.connection_id=c.id
         WHERE c.user_id=?
         GROUP BY c.id,cl.id,mw.id
         ORDER BY FIELD(c.status,'active','paused','pending','expired','revoked','disabled'),c.updated_at DESC"
    );
    $stmt->execute([$userId]);

    return array_map(static function (array $row): array {
        return [
            'database_id' => (int)$row['id'],
            'id' => (string)$row['public_id'],
            'display_name' => (string)$row['display_name'],
            'status' => (string)$row['status'],
            'maximum_operation_class' => (string)$row['maximum_operation_class'],
            'expires_at' => $row['expires_at'] !== null ? (string)$row['expires_at'] : null,
            'scopes' => array_values(array_filter(array_map('trim', explode(',', (string)($row['scope_keys'] ?? ''))))),
            'client' => [
                'database_id' => (int)$row['client_id'],
                'id' => (string)$row['client_public_id'],
                'name' => (string)$row['client_name'],
                'status' => (string)$row['client_status'],
                'maximum_operation_class' => (string)$row['client_maximum_operation_class'],
            ],
            'workspace' => $row['workspace_type'] !== null ? [
                'type' => (string)$row['workspace_type'],
                'database_id' => $row['workspace_id'] !== null ? (int)$row['workspace_id'] : null,
                'id' => $row['workspace_public_id'] !== null ? (string)$row['workspace_public_id'] : null,
                'status' => $row['workspace_status'] !== null ? (string)$row['workspace_status'] : null,
            ] : null,
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_mcp_automation_lock_owner_connection(PDO $pdo, int $userId, string $publicId): array
{
    $stmt = $pdo->prepare(
        "SELECT c.*,cl.public_id AS client_public_id,cl.display_name AS client_name,
                cl.status AS client_status,cl.maximum_operation_class AS client_maximum_operation_class,
                mw.public_id AS workspace_public_id,mw.status AS workspace_status
         FROM mcp_connections c
         INNER JOIN mcp_clients cl ON cl.id=c.client_id
         LEFT JOIN merchant_workspaces mw ON c.workspace_type IN ('merchant','merchant_workspace') AND mw.id=c.workspace_id
         WHERE c.public_id=? AND c.user_id=? LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$publicId, $userId]);
    $connection = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$connection) {
        throw new MgMcpAutomationGrantException('The selected AI connection was not found.', 404, 'MCP_AUTOMATION_CONNECTION_NOT_FOUND');
    }
    if (in_array((string)$connection['status'], ['expired', 'revoked', 'disabled'], true)) {
        throw new MgMcpAutomationGrantException('The selected AI connection cannot authorize a new grant.', 409, 'MCP_AUTOMATION_CONNECTION_UNAVAILABLE');
    }
    if (in_array((string)$connection['client_status'], ['disabled', 'revoked'], true)) {
        throw new MgMcpAutomationGrantException('The selected MCP client is unavailable.', 409, 'MCP_AUTOMATION_CLIENT_UNAVAILABLE');
    }
    if ($connection['workspace_id'] !== null) {
        if (($connection['workspace_status'] ?? null) === null || in_array((string)$connection['workspace_status'], ['suspended', 'archived'], true)) {
            throw new MgMcpAutomationGrantException('The selected merchant workspace is unavailable.', 409, 'MCP_AUTOMATION_WORKSPACE_UNAVAILABLE');
        }
        $membership = $pdo->prepare(
            "SELECT 1 FROM merchant_workspaces mw
             LEFT JOIN merchant_team_members mt ON mt.workspace_id=mw.id AND mt.user_id=? AND mt.status='active'
             WHERE mw.id=? AND (mw.merchant_user_id=? OR mt.id IS NOT NULL) LIMIT 1"
        );
        $membership->execute([$userId, (int)$connection['workspace_id'], $userId]);
        if (!$membership->fetchColumn()) {
            throw new MgMcpAutomationGrantException('You no longer have access to the selected merchant workspace.', 409, 'MCP_AUTOMATION_WORKSPACE_ACCESS_REVOKED');
        }
    }
    return $connection;
}

function mg_mcp_automation_connection_scopes(PDO $pdo, int $connectionId): array
{
    $stmt = $pdo->prepare('SELECT scope_key FROM mcp_connection_scopes WHERE connection_id=? AND revoked_at IS NULL ORDER BY scope_key');
    $stmt->execute([$connectionId]);
    return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
}

function mg_mcp_automation_text(mixed $value, int $minimum, int $maximum, string $label): string
{
    $text = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    $length = mb_strlen($text);
    if ($length < $minimum || $length > $maximum) {
        throw new MgMcpAutomationGrantException($label . ' must be between ' . $minimum . ' and ' . $maximum . ' characters.');
    }
    return $text;
}

function mg_mcp_automation_optional_uint(mixed $value, int $maximum, string $label): ?int
{
    $text = trim((string)$value);
    if ($text === '') {
        return null;
    }
    if (preg_match('/^[0-9]+$/', $text) !== 1) {
        throw new MgMcpAutomationGrantException($label . ' must be a whole number.');
    }
    $number = (int)$text;
    if ($number < 0 || $number > $maximum) {
        throw new MgMcpAutomationGrantException($label . ' is outside the allowed range.');
    }
    return $number;
}

function mg_mcp_automation_target_ids(mixed $value, string $label): array
{
    $text = trim((string)$value);
    if ($text === '') {
        return [];
    }
    $parts = preg_split('/[\s,]+/', $text) ?: [];
    $ids = [];
    foreach ($parts as $part) {
        $id = strtolower(trim((string)$part));
        if ($id === '') {
            continue;
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id) !== 1) {
            throw new MgMcpAutomationGrantException($label . ' contains an invalid UUID.');
        }
        $ids[] = $id;
    }
    $ids = array_values(array_unique($ids));
    if (count($ids) > 50) {
        throw new MgMcpAutomationGrantException($label . ' may contain at most 50 UUIDs.');
    }
    return $ids;
}
