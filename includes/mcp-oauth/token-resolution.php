<?php
declare(strict_types=1);

function mg_mcp_oauth_resolve_access_token_hash(PDO $pdo, string $tokenHash, string $resourceUri): array
{
    if (preg_match('/^[a-f0-9]{64}$/', $tokenHash) !== 1) {
        throw new MgMcpOAuthException('Invalid access token.', 'invalid_token', 401);
    }
    $resourceUri = rtrim(trim($resourceUri), '/');
    if (!hash_equals(mg_mcp_oauth_resource_uri(), $resourceUri)) {
        throw new MgMcpOAuthException('Invalid access token audience.', 'invalid_token', 401);
    }
    $stmt = $pdo->prepare(
        "SELECT t.*,c.public_id AS connection_public_id,c.token_version AS current_token_version,
                oc.status AS consent_status,r.client_id
         FROM mcp_oauth_tokens t
         INNER JOIN mcp_connections c ON c.id=t.connection_id
         INNER JOIN mcp_oauth_consents oc ON oc.connection_id=c.id
         INNER JOIN mcp_oauth_client_registrations r ON r.id=t.client_registration_id
         WHERE t.token_hash=? AND t.token_type='access' LIMIT 1"
    );
    $stmt->execute([$tokenHash]);
    $token = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$token
        || $token['revoked_at'] !== null
        || strtotime((string)$token['expires_at']) <= time()
        || (string)$token['consent_status'] !== 'active'
        || (int)$token['connection_token_version'] !== (int)$token['current_token_version']
        || !hash_equals((string)$token['resource_uri'], $resourceUri)) {
        throw new MgMcpOAuthException('Invalid or expired access token.', 'invalid_token', 401);
    }

    $pdo->prepare('UPDATE mcp_oauth_tokens SET last_used_at=NOW() WHERE id=?')->execute([(int)$token['id']]);
    return [
        'connection_public_id' => (string)$token['connection_public_id'],
        'token_scopes' => array_values(array_map('strval', mg_mcp_oauth_json_decode($token['scope_json']))),
        'oauth_client_id' => (string)$token['client_id'],
        'token_family_id' => (string)$token['token_family_id'],
    ];
}
