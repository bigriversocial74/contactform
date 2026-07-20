<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

final class MgAdminMcpProvisioningException extends RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}

function mg_admin_mcp_require_user(): array
{
    return mg_require_permission('admin.settings.manage');
}

function mg_admin_mcp_text(mixed $value, int $min, int $max, string $label, bool $required = true): string
{
    $text = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    $length = mb_strlen($text);
    if (($required && $length < $min) || $length > $max) {
        throw new MgAdminMcpProvisioningException($label . ' must be between ' . $min . ' and ' . $max . ' characters.');
    }
    return $text;
}

function mg_admin_mcp_env_enabled(string $key): bool
{
    return in_array(strtolower(trim((string)(getenv($key) ?: ''))), ['1', 'true', 'yes', 'on'], true);
}

function mg_admin_mcp_scopes(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT scope_key,display_name,description,operation_class,active,grantable
         FROM mcp_scope_catalog
         WHERE operation_class='read'
         ORDER BY scope_key"
    );
    return array_map(static fn(array $row): array => [
        'key' => (string)$row['scope_key'],
        'display_name' => (string)$row['display_name'],
        'description' => (string)$row['description'],
        'operation_class' => (string)$row['operation_class'],
        'active' => (bool)$row['active'],
        'grantable' => (bool)$row['grantable'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_admin_mcp_scope_keys(PDO $pdo, mixed $value): array
{
    $requested = is_array($value) ? $value : [];
    $requested = array_values(array_unique(array_filter(array_map(
        static fn(mixed $scope): string => strtolower(trim((string)$scope)),
        $requested
    ))));
    if ($requested === []) {
        $requested = ['profile:read', 'catalog:read'];
    }
    if (!in_array('profile:read', $requested, true)) {
        throw new MgAdminMcpProvisioningException('The profile:read scope is required for Phase 1 connections.');
    }

    $placeholders = implode(',', array_fill(0, count($requested), '?'));
    $stmt = $pdo->prepare(
        "SELECT scope_key FROM mcp_scope_catalog
         WHERE scope_key IN ($placeholders) AND active=1 AND grantable=1 AND operation_class='read'"
    );
    $stmt->execute($requested);
    $allowed = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    sort($allowed);
    $expected = $requested;
    sort($expected);
    if ($allowed !== $expected) {
        throw new MgAdminMcpProvisioningException('One or more selected scopes are not currently grantable.');
    }
    return $allowed;
}

function mg_admin_mcp_user(PDO $pdo, mixed $reference): array
{
    $value = trim((string)$reference);
    if ($value === '') {
        throw new MgAdminMcpProvisioningException('Enter an active user ID or email address.');
    }
    if (preg_match('/^[1-9][0-9]{0,18}$/', $value) === 1) {
        $stmt = $pdo->prepare('SELECT id,email,full_name,display_name,status FROM users WHERE id=? LIMIT 1');
        $stmt->execute([(int)$value]);
    } else {
        $email = strtolower($value);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new MgAdminMcpProvisioningException('Enter a valid user ID or email address.');
        }
        $stmt = $pdo->prepare('SELECT id,email,full_name,display_name,status FROM users WHERE email=? LIMIT 1');
        $stmt->execute([$email]);
    }
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || (string)$user['status'] !== 'active') {
        throw new MgAdminMcpProvisioningException('The selected user is unavailable or inactive.', 409);
    }
    return $user;
}

function mg_admin_mcp_workspace(PDO $pdo, int $userId, mixed $publicId): ?array
{
    $value = trim((string)$publicId);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^[0-9a-f-]{36}$/i', $value) !== 1) {
        throw new MgAdminMcpProvisioningException('Invalid merchant workspace UUID.');
    }
    $stmt = $pdo->prepare(
        "SELECT mw.id,mw.public_id,mw.status
         FROM merchant_workspaces mw
         LEFT JOIN merchant_team_members mt ON mt.workspace_id=mw.id AND mt.user_id=? AND mt.status='active'
         WHERE mw.public_id=? AND (mw.merchant_user_id=? OR mt.id IS NOT NULL)
         LIMIT 1"
    );
    $stmt->execute([$userId, $value, $userId]);
    $workspace = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$workspace || in_array((string)$workspace['status'], ['suspended', 'archived'], true)) {
        throw new MgAdminMcpProvisioningException('The selected user does not have an active relationship with that workspace.', 409);
    }
    return $workspace;
}

function mg_admin_mcp_client(PDO $pdo, array $input, int $actorId): array
{
    $publicId = trim((string)($input['client_public_id'] ?? ''));
    if ($publicId !== '') {
        $stmt = $pdo->prepare('SELECT * FROM mcp_clients WHERE public_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$publicId]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$client || !in_array((string)$client['status'], ['development', 'active'], true)) {
            throw new MgAdminMcpProvisioningException('The selected MCP client is unavailable.', 409);
        }
        if ((string)$client['maximum_operation_class'] !== 'read') {
            throw new MgAdminMcpProvisioningException('The selected MCP client exceeds the Phase 1 read-only boundary.', 409);
        }
        return $client;
    }

    $clientKey = strtolower(mg_admin_mcp_text($input['client_key'] ?? '', 3, 120, 'Client key'));
    if (preg_match('/^[a-z0-9][a-z0-9._-]{2,119}$/', $clientKey) !== 1) {
        throw new MgAdminMcpProvisioningException('Client key may contain lowercase letters, numbers, dots, underscores, and hyphens.');
    }
    $displayName = mg_admin_mcp_text($input['client_display_name'] ?? '', 3, 180, 'Client display name');
    $clientType = strtolower(trim((string)($input['client_type'] ?? 'custom')));
    if (!in_array($clientType, ['first_party', 'chatgpt', 'claude', 'custom', 'enterprise'], true)) {
        throw new MgAdminMcpProvisioningException('Invalid client type.');
    }
    $status = strtolower(trim((string)($input['client_status'] ?? 'development')));
    if (!in_array($status, ['development', 'active'], true)) {
        throw new MgAdminMcpProvisioningException('Invalid client status.');
    }

    $publicId = mg_public_uuid();
    $stmt = $pdo->prepare(
        "INSERT INTO mcp_clients
         (public_id,client_key,display_name,status,client_type,maximum_operation_class,metadata_json,created_by_user_id,created_at,updated_at)
         VALUES (?,?,?,?,?,'read',?, ?,NOW(),NOW())"
    );
    $stmt->execute([
        $publicId,
        $clientKey,
        $displayName,
        $status,
        $clientType,
        json_encode(['provisioned_by' => 'admin_mcp_console'], JSON_THROW_ON_ERROR),
        $actorId,
    ]);
    $id = (int)$pdo->lastInsertId();
    return [
        'id' => $id,
        'public_id' => $publicId,
        'client_key' => $clientKey,
        'display_name' => $displayName,
        'status' => $status,
        'client_type' => $clientType,
        'maximum_operation_class' => 'read',
    ];
}

function mg_admin_mcp_connection_projection(array $row): array
{
    $scopes = array_values(array_filter(array_map('trim', explode(',', (string)($row['scope_keys'] ?? '')))));
    return [
        'id' => (string)$row['public_id'],
        'display_name' => (string)$row['display_name'],
        'status' => (string)$row['status'],
        'maximum_operation_class' => (string)$row['maximum_operation_class'],
        'token_version' => (int)$row['token_version'],
        'consented_at' => $row['consented_at'] !== null ? (string)$row['consented_at'] : null,
        'expires_at' => $row['expires_at'] !== null ? (string)$row['expires_at'] : null,
        'last_activity_at' => $row['last_activity_at'] !== null ? (string)$row['last_activity_at'] : null,
        'created_at' => (string)$row['created_at'],
        'user' => [
            'id' => (int)$row['user_id'],
            'email' => (string)$row['user_email'],
            'display_name' => (string)($row['user_display_name'] ?: $row['user_full_name'] ?: $row['user_email']),
            'status' => (string)$row['user_status'],
        ],
        'client' => [
            'id' => (string)$row['client_public_id'],
            'key' => (string)$row['client_key'],
            'display_name' => (string)$row['client_display_name'],
            'type' => (string)$row['client_type'],
            'status' => (string)$row['client_status'],
        ],
        'workspace' => $row['workspace_type'] !== null ? [
            'type' => (string)$row['workspace_type'],
            'database_id' => $row['workspace_id'] !== null ? (int)$row['workspace_id'] : null,
            'id' => $row['workspace_public_id'] !== null ? (string)$row['workspace_public_id'] : null,
            'status' => $row['workspace_status'] !== null ? (string)$row['workspace_status'] : null,
        ] : null,
        'scopes' => $scopes,
    ];
}

function mg_admin_mcp_connection_detail(PDO $pdo, string $publicId, bool $lock = false): array
{
    $suffix = $lock ? ' FOR UPDATE' : '';
    $stmt = $pdo->prepare(
        "SELECT c.*,u.email AS user_email,u.full_name AS user_full_name,u.display_name AS user_display_name,u.status AS user_status,
                cl.public_id AS client_public_id,cl.client_key,cl.display_name AS client_display_name,cl.client_type,cl.status AS client_status,
                mw.public_id AS workspace_public_id,mw.status AS workspace_status,
                GROUP_CONCAT(CASE WHEN cs.revoked_at IS NULL THEN cs.scope_key END ORDER BY cs.scope_key SEPARATOR ',') AS scope_keys
         FROM mcp_connections c
         INNER JOIN users u ON u.id=c.user_id
         INNER JOIN mcp_clients cl ON cl.id=c.client_id
         LEFT JOIN merchant_workspaces mw ON c.workspace_type IN ('merchant','merchant_workspace') AND mw.id=c.workspace_id
         LEFT JOIN mcp_connection_scopes cs ON cs.connection_id=c.id
         WHERE c.public_id=?
         GROUP BY c.id,u.id,cl.id,mw.id" . $suffix
    );
    $stmt->execute([$publicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new MgAdminMcpProvisioningException('MCP connection not found.', 404);
    }
    return $row;
}

function mg_admin_mcp_read(PDO $pdo): array
{
    $clientsStmt = $pdo->query(
        "SELECT public_id,client_key,display_name,status,client_type,maximum_operation_class,created_at,updated_at
         FROM mcp_clients ORDER BY id DESC LIMIT 100"
    );
    $clients = array_map(static fn(array $row): array => [
        'id' => (string)$row['public_id'],
        'key' => (string)$row['client_key'],
        'display_name' => (string)$row['display_name'],
        'status' => (string)$row['status'],
        'type' => (string)$row['client_type'],
        'maximum_operation_class' => (string)$row['maximum_operation_class'],
        'created_at' => (string)$row['created_at'],
        'updated_at' => (string)$row['updated_at'],
    ], $clientsStmt->fetchAll(PDO::FETCH_ASSOC));

    $connectionsStmt = $pdo->query(
        "SELECT c.*,u.email AS user_email,u.full_name AS user_full_name,u.display_name AS user_display_name,u.status AS user_status,
                cl.public_id AS client_public_id,cl.client_key,cl.display_name AS client_display_name,cl.client_type,cl.status AS client_status,
                mw.public_id AS workspace_public_id,mw.status AS workspace_status,
                GROUP_CONCAT(CASE WHEN cs.revoked_at IS NULL THEN cs.scope_key END ORDER BY cs.scope_key SEPARATOR ',') AS scope_keys
         FROM mcp_connections c
         INNER JOIN users u ON u.id=c.user_id
         INNER JOIN mcp_clients cl ON cl.id=c.client_id
         LEFT JOIN merchant_workspaces mw ON c.workspace_type IN ('merchant','merchant_workspace') AND mw.id=c.workspace_id
         LEFT JOIN mcp_connection_scopes cs ON cs.connection_id=c.id
         GROUP BY c.id,u.id,cl.id,mw.id
         ORDER BY c.id DESC LIMIT 200"
    );
    $connections = array_map('mg_admin_mcp_connection_projection', $connectionsStmt->fetchAll(PDO::FETCH_ASSOC));

    $migrationStmt = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration_key=? LIMIT 1');
    $migrationStmt->execute(['20260720_microgifter_mcp_automation_foundation_v1']);
    $migrationImported = (bool)$migrationStmt->fetchColumn();
    $bridgeSecretLength = strlen(trim((string)(getenv('MG_MCP_BRIDGE_SECRET') ?: '')));
    $activeReady = 0;
    foreach ($connections as $connection) {
        if ($connection['status'] === 'active'
            && $connection['client']['status'] === 'active'
            && in_array('profile:read', $connection['scopes'], true)
            && in_array('catalog:read', $connection['scopes'], true)) {
            $activeReady++;
        }
    }

    return [
        'clients' => $clients,
        'connections' => $connections,
        'scopes' => mg_admin_mcp_scopes($pdo),
        'readiness' => [
            'foundation_migration_imported' => $migrationImported,
            'php_bridge_enabled' => mg_admin_mcp_env_enabled('MG_MCP_BRIDGE_ENABLED'),
            'php_bridge_secret_configured' => $bridgeSecretLength >= 32,
            'php_bridge_secret_length' => $bridgeSecretLength,
            'active_ready_connections' => $activeReady,
            'endpoint' => '/api/internal/mcp-bridge.php',
            'node_runtime_verification' => 'external_process_required',
        ],
        'summary' => [
            'clients' => count($clients),
            'connections' => count($connections),
            'active_connections' => count(array_filter($connections, static fn(array $item): bool => $item['status'] === 'active')),
            'ready_connections' => $activeReady,
        ],
    ];
}

function mg_admin_mcp_provision(PDO $pdo, array $actor, array $input): array
{
    $actorId = (int)$actor['id'];
    $reason = mg_admin_mcp_text($input['reason'] ?? '', 8, 240, 'Action reason');
    $user = mg_admin_mcp_user($pdo, $input['user_reference'] ?? '');
    $workspace = mg_admin_mcp_workspace($pdo, (int)$user['id'], $input['workspace_public_id'] ?? '');
    $scopes = mg_admin_mcp_scope_keys($pdo, $input['scopes'] ?? []);
    $displayName = mg_admin_mcp_text($input['connection_display_name'] ?? '', 3, 180, 'Connection display name');
    $expiresDays = filter_var($input['expires_days'] ?? null, FILTER_VALIDATE_INT, ['options' => ['default' => 90]]);
    $expiresDays = max(1, min((int)$expiresDays, 365));

    $pdo->beginTransaction();
    try {
        $client = mg_admin_mcp_client($pdo, $input, $actorId);
        $connectionPublicId = mg_public_uuid();
        $stmt = $pdo->prepare(
            "INSERT INTO mcp_connections
             (public_id,client_id,user_id,workspace_type,workspace_id,display_name,status,maximum_operation_class,token_version,consented_at,expires_at,created_at,updated_at)
             VALUES (?,?,?,?,?,?,'active','read',1,NOW(),DATE_ADD(NOW(),INTERVAL ? DAY),NOW(),NOW())"
        );
        $stmt->execute([
            $connectionPublicId,
            (int)$client['id'],
            (int)$user['id'],
            $workspace ? 'merchant' : null,
            $workspace ? (int)$workspace['id'] : null,
            $displayName,
            $expiresDays,
        ]);
        $connectionDbId = (int)$pdo->lastInsertId();
        $scopeStmt = $pdo->prepare(
            'INSERT INTO mcp_connection_scopes (connection_id,scope_key,granted_at,revoked_at,created_at) VALUES (?,?,NOW(),NULL,NOW())'
        );
        foreach ($scopes as $scope) {
            $scopeStmt->execute([$connectionDbId, $scope]);
        }

        $metadata = [
            'connection_public_id' => $connectionPublicId,
            'client_public_id' => (string)$client['public_id'],
            'client_key' => (string)$client['client_key'],
            'target_user_id' => (int)$user['id'],
            'workspace_public_id' => $workspace ? (string)$workspace['public_id'] : null,
            'scopes' => $scopes,
            'expires_days' => $expiresDays,
            'reason' => $reason,
        ];
        mg_audit('admin_mcp_connection_provision', 'mcp_connection', $metadata, $actorId);
        mg_event('admin.mcp.connection.provisioned', $metadata + ['admin_user_id' => $actorId], $actorId);
        mg_security_log('info', 'admin.mcp_connection.provisioned', 'Admin provisioned an MCP connection.', $metadata, $actorId);
        $detail = mg_admin_mcp_connection_detail($pdo, $connectionPublicId);
        $pdo->commit();
        return mg_admin_mcp_connection_projection($detail);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($error instanceof PDOException && (string)$error->getCode() === '23000') {
            throw new MgAdminMcpProvisioningException('The client key or connection already exists.', 409);
        }
        throw $error;
    }
}

function mg_admin_mcp_action(PDO $pdo, array $actor, array $input): array
{
    $actorId = (int)$actor['id'];
    $action = strtolower(trim((string)($input['action'] ?? '')));
    $allowed = ['pause', 'resume', 'revoke', 'rotate_token', 'grant_scope', 'revoke_scope'];
    if (!in_array($action, $allowed, true)) {
        throw new MgAdminMcpProvisioningException('Invalid MCP connection action.');
    }
    $connectionPublicId = mg_admin_mcp_text($input['connection_public_id'] ?? '', 36, 36, 'Connection UUID');
    $reason = mg_admin_mcp_text($input['reason'] ?? '', 8, 240, 'Action reason');

    $pdo->beginTransaction();
    try {
        $connection = mg_admin_mcp_connection_detail($pdo, $connectionPublicId, true);
        $connectionDbId = (int)$connection['id'];
        $metadata = ['connection_public_id' => $connectionPublicId, 'action' => $action, 'reason' => $reason];

        if ($action === 'pause') {
            if ((string)$connection['status'] !== 'active') {
                throw new MgAdminMcpProvisioningException('Only active connections can be paused.', 409);
            }
            $pdo->prepare("UPDATE mcp_connections SET status='paused',paused_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$connectionDbId]);
        } elseif ($action === 'resume') {
            if (!in_array((string)$connection['status'], ['paused', 'pending'], true)) {
                throw new MgAdminMcpProvisioningException('Only paused or pending connections can be resumed.', 409);
            }
            if ((string)$connection['user_status'] !== 'active' || !in_array((string)$connection['client_status'], ['development', 'active'], true)) {
                throw new MgAdminMcpProvisioningException('The account or client is not eligible for activation.', 409);
            }
            $pdo->prepare("UPDATE mcp_connections SET status='active',paused_at=NULL,consented_at=COALESCE(consented_at,NOW()),updated_at=NOW() WHERE id=?")->execute([$connectionDbId]);
        } elseif ($action === 'revoke') {
            if ((string)$connection['status'] === 'revoked') {
                throw new MgAdminMcpProvisioningException('Connection is already revoked.', 409);
            }
            $pdo->prepare("UPDATE mcp_connections SET status='revoked',token_version=token_version+1,revoked_at=NOW(),revoked_by_user_id=?,revocation_reason=?,updated_at=NOW() WHERE id=?")
                ->execute([$actorId, $reason, $connectionDbId]);
        } elseif ($action === 'rotate_token') {
            if ((string)$connection['status'] === 'revoked') {
                throw new MgAdminMcpProvisioningException('Revoked connections cannot rotate token version.', 409);
            }
            $pdo->prepare('UPDATE mcp_connections SET token_version=token_version+1,updated_at=NOW() WHERE id=?')->execute([$connectionDbId]);
        } else {
            $scope = strtolower(trim((string)($input['scope_key'] ?? '')));
            $validScopes = mg_admin_mcp_scope_keys($pdo, [$scope, 'profile:read']);
            if (!in_array($scope, $validScopes, true)) {
                throw new MgAdminMcpProvisioningException('Invalid scope.');
            }
            $metadata['scope_key'] = $scope;
            if ($action === 'grant_scope') {
                $pdo->prepare(
                    'INSERT INTO mcp_connection_scopes (connection_id,scope_key,granted_at,revoked_at,created_at) VALUES (?,?,NOW(),NULL,NOW())
                     ON DUPLICATE KEY UPDATE granted_at=NOW(),revoked_at=NULL'
                )->execute([$connectionDbId, $scope]);
            } else {
                $pdo->prepare('UPDATE mcp_connection_scopes SET revoked_at=NOW() WHERE connection_id=? AND scope_key=? AND revoked_at IS NULL')
                    ->execute([$connectionDbId, $scope]);
            }
        }

        mg_audit('admin_mcp_connection_action', 'mcp_connection', $metadata, $actorId);
        mg_event('admin.mcp.connection.action', $metadata + ['admin_user_id' => $actorId], $actorId);
        mg_security_log('info', 'admin.mcp_connection.action', 'Admin changed an MCP connection.', $metadata, $actorId);
        $detail = mg_admin_mcp_connection_detail($pdo, $connectionPublicId);
        $pdo->commit();
        return mg_admin_mcp_connection_projection($detail);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function mg_admin_mcp_base64url(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function mg_admin_mcp_runtime_credentials(PDO $pdo, array $actor, array $input): array
{
    $actorId = (int)$actor['id'];
    $connectionPublicId = mg_admin_mcp_text($input['connection_public_id'] ?? '', 36, 36, 'Connection UUID');
    $reason = mg_admin_mcp_text($input['reason'] ?? '', 8, 240, 'Action reason');
    $bridgeUrl = trim((string)($input['bridge_url'] ?? 'https://microgifter.com/api/internal/mcp-bridge.php'));
    $parts = parse_url($bridgeUrl);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
        throw new MgAdminMcpProvisioningException('Bridge URL must use HTTPS.');
    }
    $connection = mg_admin_mcp_connection_detail($pdo, $connectionPublicId);
    if ((string)$connection['status'] !== 'active') {
        throw new MgAdminMcpProvisioningException('Deployment credentials require an active connection.', 409);
    }

    $bearerToken = mg_admin_mcp_base64url(random_bytes(32));
    $bearerHash = hash('sha256', $bearerToken);
    $bridgeSecret = mg_admin_mcp_base64url(random_bytes(48));
    $clientKey = (string)$connection['client_key'];
    $userId = (string)$connection['user_id'];

    $phpEnvironment = implode("\n", [
        'MG_MCP_BRIDGE_ENABLED=true',
        'MG_MCP_BRIDGE_SECRET=' . $bridgeSecret,
    ]);
    $nodeEnvironment = implode("\n", [
        'MICROGIFTER_MCP_ENABLED=true',
        'MICROGIFTER_MCP_INTERNAL_HTTP_ENABLED=true',
        'MICROGIFTER_MCP_INTERNAL_HOST=127.0.0.1',
        'MICROGIFTER_MCP_INTERNAL_PORT=8787',
        'MICROGIFTER_MCP_INTERNAL_TOKEN_SHA256=' . $bearerHash,
        'MICROGIFTER_MCP_INTERNAL_CONNECTION_ID=' . $connectionPublicId,
        'MICROGIFTER_MCP_INTERNAL_CLIENT_KEY=' . $clientKey,
        'MICROGIFTER_MCP_INTERNAL_USER_ID=' . $userId,
        'MICROGIFTER_MCP_BRIDGE_ENABLED=true',
        'MICROGIFTER_MCP_BRIDGE_URL=' . $bridgeUrl,
        'MICROGIFTER_MCP_BRIDGE_SECRET=' . $bridgeSecret,
    ]);

    $metadata = [
        'connection_public_id' => $connectionPublicId,
        'client_key' => $clientKey,
        'bridge_host' => (string)$parts['host'],
        'bearer_hash_prefix' => substr($bearerHash, 0, 12),
        'reason' => $reason,
        'secrets_persisted' => false,
    ];
    mg_audit('admin_mcp_runtime_credentials_generate', 'mcp_connection', $metadata, $actorId);
    mg_event('admin.mcp.runtime_credentials.generated', $metadata + ['admin_user_id' => $actorId], $actorId);
    mg_security_log('medium', 'admin.mcp_runtime_credentials.generated', 'Admin generated one-time MCP deployment credentials.', $metadata, $actorId);

    return [
        'connection_public_id' => $connectionPublicId,
        'bearer_token' => $bearerToken,
        'bearer_token_sha256' => $bearerHash,
        'bridge_secret' => $bridgeSecret,
        'php_environment' => $phpEnvironment,
        'node_environment' => $nodeEnvironment,
        'persisted' => false,
        'warning' => 'Copy these credentials now. They are not stored and cannot be retrieved later.',
    ];
}
