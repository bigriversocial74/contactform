<?php
declare(strict_types=1);

function mg_mcp_oauth_user_workspaces(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT DISTINCT mw.id,mw.public_id,mw.display_name,mw.status
         FROM merchant_workspaces mw
         LEFT JOIN merchant_team_members mt ON mt.workspace_id=mw.id AND mt.user_id=? AND mt.status='active'
         WHERE (mw.merchant_user_id=? OR mt.id IS NOT NULL)
           AND mw.status NOT IN ('suspended','archived')
         ORDER BY mw.display_name,mw.id"
    );
    $stmt->execute([$userId, $userId]);
    return array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'public_id' => (string)$row['public_id'],
        'name' => (string)($row['display_name'] ?: 'Merchant workspace'),
        'status' => (string)$row['status'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_mcp_oauth_select_workspace(PDO $pdo, int $userId, mixed $publicId): ?array
{
    $value = trim((string)$publicId);
    if ($value === '') return null;
    foreach (mg_mcp_oauth_user_workspaces($pdo, $userId) as $workspace) {
        if (hash_equals((string)$workspace['public_id'], $value)) return $workspace;
    }
    throw new MgMcpOAuthException('The selected workspace is unavailable.', 'access_denied', 403);
}

function mg_mcp_oauth_workspace_key(?array $workspace): string
{
    return $workspace ? 'merchant:' . (int)$workspace['id'] : 'account';
}

function mg_mcp_oauth_connection_for_consent(PDO $pdo, array $request, int $userId, ?array $workspace, array $scopes): array
{
    $workspaceKey = mg_mcp_oauth_workspace_key($workspace);
    $maximumOperationClass = mg_mcp_oauth_operation_class_for_scopes($pdo, $scopes);
    $clientMaximum = mg_mcp_oauth_maximum_operation_class($request['maximum_operation_class'] ?? 'read');
    if (mg_mcp_oauth_operation_rank($maximumOperationClass) > mg_mcp_oauth_operation_rank($clientMaximum)) {
        throw new MgMcpOAuthException('The requested scopes exceed the registered client authority.', 'invalid_scope', 422);
    }
    $stmt = $pdo->prepare(
        "SELECT oc.*,c.public_id AS connection_public_id FROM mcp_oauth_consents oc
         INNER JOIN mcp_connections c ON c.id=oc.connection_id
         WHERE oc.client_registration_id=? AND oc.user_id=? AND oc.workspace_key=? LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([(int)$request['client_registration_id'], $userId, $workspaceKey]);
    $consent = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($consent) {
        $connectionId = (int)$consent['connection_id'];
        $pdo->prepare(
            "UPDATE mcp_connections SET status='active',maximum_operation_class=?,consented_at=NOW(),
             expires_at=DATE_ADD(NOW(),INTERVAL 365 DAY),paused_at=NULL,revoked_at=NULL,revoked_by_user_id=NULL,
             revocation_reason=NULL,updated_at=NOW() WHERE id=?"
        )->execute([$maximumOperationClass, $connectionId]);
    } else {
        $connectionPublicId = mg_public_uuid();
        $displayName = mb_substr((string)$request['client_name'] . ($workspace ? ' · ' . $workspace['name'] : ' · Account'), 0, 180);
        $connectionStmt = $pdo->prepare(
            "INSERT INTO mcp_connections
             (public_id,client_id,user_id,workspace_type,workspace_id,display_name,status,maximum_operation_class,
              token_version,consented_at,expires_at,created_at,updated_at)
             SELECT ?,r.mcp_client_id,?,?,?,?,'active',?,1,NOW(),DATE_ADD(NOW(),INTERVAL 365 DAY),NOW(),NOW()
             FROM mcp_oauth_client_registrations r WHERE r.id=?"
        );
        $connectionStmt->execute([
            $connectionPublicId,
            $userId,
            $workspace ? 'merchant' : null,
            $workspace ? (int)$workspace['id'] : null,
            $displayName,
            $maximumOperationClass,
            (int)$request['client_registration_id'],
        ]);
        $connectionId = (int)$pdo->lastInsertId();
        $consent = ['public_id' => mg_public_uuid()];
    }
    $scopeStmt = $pdo->prepare(
        "INSERT INTO mcp_connection_scopes (connection_id,scope_key,granted_at,revoked_at,created_at)
         VALUES (?,?,NOW(),NULL,NOW()) ON DUPLICATE KEY UPDATE granted_at=NOW(),revoked_at=NULL"
    );
    foreach ($scopes as $scope) $scopeStmt->execute([$connectionId, $scope]);
    $placeholders = implode(',', array_fill(0, count($scopes), '?'));
    $pdo->prepare("UPDATE mcp_connection_scopes SET revoked_at=NOW() WHERE connection_id=? AND revoked_at IS NULL AND scope_key NOT IN ($placeholders)")
        ->execute(array_merge([$connectionId], $scopes));
    $scopeFingerprint = hash('sha256', implode(' ', $scopes));
    if (isset($consent['id'])) {
        $pdo->prepare(
            "UPDATE mcp_oauth_consents SET connection_id=?,workspace_type=?,workspace_id=?,scope_json=?,scope_fingerprint=?,
             status='active',consented_at=NOW(),revoked_at=NULL,revocation_reason=NULL,updated_at=NOW() WHERE id=?"
        )->execute([$connectionId,$workspace ? 'merchant' : null,$workspace ? (int)$workspace['id'] : null,json_encode($scopes, JSON_THROW_ON_ERROR),$scopeFingerprint,(int)$consent['id']]);
    } else {
        $pdo->prepare(
            "INSERT INTO mcp_oauth_consents
             (public_id,client_registration_id,connection_id,user_id,workspace_key,workspace_type,workspace_id,scope_json,
              scope_fingerprint,status,consented_at,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,'active',NOW(),NOW(),NOW())"
        )->execute([(string)$consent['public_id'],(int)$request['client_registration_id'],$connectionId,$userId,$workspaceKey,$workspace ? 'merchant' : null,$workspace ? (int)$workspace['id'] : null,json_encode($scopes, JSON_THROW_ON_ERROR),$scopeFingerprint]);
    }
    $detail = $pdo->prepare('SELECT * FROM mcp_connections WHERE id=? LIMIT 1');
    $detail->execute([$connectionId]);
    $connection = $detail->fetch(PDO::FETCH_ASSOC);
    if (!$connection) throw new MgMcpOAuthException('Unable to create the MCP connection.', 'server_error', 500);
    return $connection;
}

function mg_mcp_oauth_authorization_decision(PDO $pdo, array $user, string $requestPublicId, string $decision, mixed $workspacePublicId): array
{
    $userId = (int)$user['id'];
    $pdo->beginTransaction();
    try {
        $request = mg_mcp_oauth_authorization_request($pdo, $requestPublicId, true);
        $redirectUri = (string)$request['redirect_uri'];
        $state = (string)$request['state_value'];
        if ($decision !== 'approve') {
            $pdo->prepare("UPDATE mcp_oauth_authorization_requests SET user_id=?,status='denied',decided_at=NOW(),updated_at=NOW() WHERE id=?")
                ->execute([$userId, (int)$request['id']]);
            $pdo->commit();
            return ['redirect_uri' => $redirectUri, 'parameters' => ['error' => 'access_denied', 'state' => $state]];
        }
        $workspace = mg_mcp_oauth_select_workspace($pdo, $userId, $workspacePublicId);
        $scopes = array_values(array_map('strval', (array)$request['scopes']));
        $connection = mg_mcp_oauth_connection_for_consent($pdo, $request, $userId, $workspace, $scopes);
        $rawCode = mg_mcp_oauth_random_token('mgac_', 32);
        $pdo->prepare(
            "INSERT INTO mcp_oauth_authorization_codes
             (public_id,authorization_request_id,client_registration_id,connection_id,code_hash,redirect_uri,resource_uri,
              scope_json,code_challenge,code_challenge_method,expires_at,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,'S256',DATE_ADD(NOW(),INTERVAL 10 MINUTE),NOW())"
        )->execute([mg_public_uuid(),(int)$request['id'],(int)$request['client_registration_id'],(int)$connection['id'],mg_mcp_oauth_hash_token($rawCode),$redirectUri,(string)$request['resource_uri'],json_encode($scopes, JSON_THROW_ON_ERROR),(string)$request['code_challenge']]);
        $pdo->prepare(
            "UPDATE mcp_oauth_authorization_requests SET user_id=?,workspace_type=?,workspace_id=?,status='approved',
             decided_at=NOW(),updated_at=NOW() WHERE id=?"
        )->execute([$userId,$workspace ? 'merchant' : null,$workspace ? (int)$workspace['id'] : null,(int)$request['id']]);
        $pdo->prepare('UPDATE mcp_oauth_client_registrations SET last_used_at=NOW(),updated_at=NOW() WHERE id=?')
            ->execute([(int)$request['client_registration_id']]);
        $pdo->commit();
        $metadata = [
            'oauth_client_id' => (string)$request['client_id'],
            'connection_public_id' => (string)$connection['public_id'],
            'workspace_key' => mg_mcp_oauth_workspace_key($workspace),
            'scopes' => $scopes,
            'maximum_operation_class' => (string)$connection['maximum_operation_class'],
            'execution_enabled' => false,
        ];
        mg_audit('mcp_oauth_consent_approved', 'mcp_connection', $metadata, $userId);
        mg_event('mcp.oauth.consent.approved', $metadata, $userId);
        mg_security_log('info', 'mcp.oauth_consent.approved', 'User approved an MCP OAuth connection.', $metadata, $userId);
        return ['redirect_uri'=>$redirectUri,'parameters'=>['code'=>$rawCode,'state'=>$state]];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_mcp_oauth_redirect_location(string $redirectUri, array $parameters): string
{
    return $redirectUri . (str_contains($redirectUri, '?') ? '&' : '?') . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
}

function mg_mcp_oauth_pkce_challenge(string $verifier): string
{
    return mg_mcp_oauth_base64url(hash('sha256', $verifier, true));
}

function mg_mcp_oauth_connection_row(PDO $pdo, int $connectionId, bool $lock = false): array
{
    $sql = "SELECT c.*,u.status AS user_status,cl.status AS client_status,cl.client_key,
                   cl.maximum_operation_class AS client_maximum_operation_class
            FROM mcp_connections c INNER JOIN users u ON u.id=c.user_id INNER JOIN mcp_clients cl ON cl.id=c.client_id
            WHERE c.id=? LIMIT 1" . ($lock ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$connectionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $connectionClass = (string)($row['maximum_operation_class'] ?? '');
    $clientClass = (string)($row['client_maximum_operation_class'] ?? '');
    if (!$row || (string)$row['status'] !== 'active' || (string)$row['user_status'] !== 'active'
        || !in_array((string)$row['client_status'], ['development','active'], true)
        || !in_array($connectionClass, ['read', 'draft'], true)
        || !in_array($clientClass, ['read', 'draft'], true)
        || mg_mcp_oauth_operation_rank($connectionClass) > mg_mcp_oauth_operation_rank($clientClass)
        || (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) <= time())) {
        throw new MgMcpOAuthException('The MCP connection is unavailable.', 'invalid_grant', 400);
    }
    return $row;
}
