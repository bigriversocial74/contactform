<?php
declare(strict_types=1);

function mg_mcp_oauth_user_connections(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT oc.public_id AS consent_public_id,oc.status AS consent_status,oc.scope_json,oc.workspace_key,
                oc.consented_at,oc.revoked_at,c.public_id AS connection_public_id,c.display_name,c.status AS connection_status,
                c.last_activity_at,c.expires_at,r.client_id,r.client_name,r.client_uri,r.logo_uri,
                COALESCE(active_tokens.active_count,0) AS active_token_count
         FROM mcp_oauth_consents oc
         INNER JOIN mcp_connections c ON c.id=oc.connection_id
         INNER JOIN mcp_oauth_client_registrations r ON r.id=oc.client_registration_id
         LEFT JOIN (
           SELECT connection_id,COUNT(*) AS active_count
           FROM mcp_oauth_tokens
           WHERE revoked_at IS NULL AND expires_at>NOW()
           GROUP BY connection_id
         ) active_tokens ON active_tokens.connection_id=c.id
         WHERE oc.user_id=?
         ORDER BY oc.updated_at DESC"
    );
    $stmt->execute([$userId]);
    return array_map(static fn(array $row): array => [
        'id' => (string)$row['consent_public_id'],
        'connection_id' => (string)$row['connection_public_id'],
        'display_name' => (string)$row['display_name'],
        'status' => (string)$row['consent_status'] === 'active' ? (string)$row['connection_status'] : 'revoked',
        'client' => [
            'id' => (string)$row['client_id'],
            'name' => (string)$row['client_name'],
            'uri' => $row['client_uri'] !== null ? (string)$row['client_uri'] : null,
            'logo_uri' => $row['logo_uri'] !== null ? (string)$row['logo_uri'] : null,
        ],
        'workspace_key' => (string)$row['workspace_key'],
        'scopes' => array_values(array_map('strval', mg_mcp_oauth_json_decode($row['scope_json']))),
        'active_token_count' => (int)$row['active_token_count'],
        'consented_at' => (string)$row['consented_at'],
        'last_activity_at' => $row['last_activity_at'] !== null ? (string)$row['last_activity_at'] : null,
        'expires_at' => $row['expires_at'] !== null ? (string)$row['expires_at'] : null,
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_mcp_oauth_revoke_user_connection(PDO $pdo, int $userId, string $consentPublicId, string $reason): void
{
    $reason = mb_substr(trim($reason) !== '' ? trim($reason) : 'User revoked external AI access.', 0, 255);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "SELECT oc.*,c.public_id AS connection_public_id
             FROM mcp_oauth_consents oc
             INNER JOIN mcp_connections c ON c.id=oc.connection_id
             WHERE oc.public_id=? AND oc.user_id=? LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$consentPublicId, $userId]);
        $consent = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$consent) {
            throw new MgMcpOAuthException('AI connection not found.', 'invalid_request', 404);
        }
        $pdo->prepare(
            "UPDATE mcp_oauth_consents
             SET status='revoked',revoked_at=NOW(),revocation_reason=?,updated_at=NOW() WHERE id=?"
        )->execute([$reason, (int)$consent['id']]);
        $pdo->prepare(
            "UPDATE mcp_connections
             SET status='revoked',token_version=token_version+1,revoked_at=NOW(),revoked_by_user_id=?,
                 revocation_reason=?,updated_at=NOW() WHERE id=?"
        )->execute([$userId, $reason, (int)$consent['connection_id']]);
        $pdo->prepare(
            "UPDATE mcp_oauth_tokens
             SET revoked_at=COALESCE(revoked_at,NOW()),revocation_reason=COALESCE(revocation_reason,'user_revocation')
             WHERE connection_id=?"
        )->execute([(int)$consent['connection_id']]);
        $pdo->commit();

        $metadata = [
            'consent_public_id' => $consentPublicId,
            'connection_public_id' => (string)$consent['connection_public_id'],
            'reason' => $reason,
        ];
        mg_audit('mcp_oauth_connection_revoked', 'mcp_connection', $metadata, $userId);
        mg_event('mcp.oauth.connection.revoked', $metadata, $userId);
        mg_security_log('warning', 'mcp.oauth_connection.revoked', 'User revoked an external MCP connection.', $metadata, $userId);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function mg_mcp_oauth_authorization_server_metadata(PDO $pdo): array
{
    $issuer = mg_mcp_oauth_issuer();
    return [
        'issuer' => $issuer,
        'authorization_endpoint' => $issuer . '/oauth/authorize.php',
        'token_endpoint' => $issuer . '/oauth/token.php',
        'registration_endpoint' => $issuer . '/oauth/register.php',
        'revocation_endpoint' => $issuer . '/oauth/revoke.php',
        'response_types_supported' => ['code'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
        'token_endpoint_auth_methods_supported' => ['none'],
        'code_challenge_methods_supported' => ['S256'],
        'scopes_supported' => mg_mcp_oauth_scopes_supported($pdo),
        'resource_indicators_supported' => true,
        'client_id_metadata_document_supported' => false,
    ];
}

function mg_mcp_oauth_json_error(MgMcpOAuthException $error): never
{
    mg_json([
        'error' => $error->oauthError(),
        'error_description' => $error->getMessage(),
    ], $error->httpStatus());
}
