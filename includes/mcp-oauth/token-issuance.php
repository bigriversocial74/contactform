<?php
declare(strict_types=1);

function mg_mcp_oauth_issue_tokens(
    PDO $pdo,
    int $registrationId,
    array $connection,
    string $resourceUri,
    array $scopes,
    ?string $familyId = null,
    ?int $parentRefreshTokenId = null
): array {
    $familyId = $familyId ?: mg_public_uuid();
    $access = mg_mcp_oauth_random_token('mgat_', 32);
    $refresh = mg_mcp_oauth_random_token('mgrt_', 48);
    $scopeJson = json_encode(array_values($scopes), JSON_THROW_ON_ERROR);
    $accessTtl = mg_mcp_oauth_access_ttl();
    $refreshTtl = mg_mcp_oauth_refresh_ttl();

    $stmt = $pdo->prepare(
        "INSERT INTO mcp_oauth_tokens
         (public_id,client_registration_id,connection_id,token_family_id,token_type,token_hash,parent_token_id,
          resource_uri,scope_json,connection_token_version,issued_at,expires_at,created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),DATE_ADD(NOW(),INTERVAL ? SECOND),NOW())"
    );
    $stmt->execute([
        mg_public_uuid(),
        $registrationId,
        (int)$connection['id'],
        $familyId,
        'access',
        mg_mcp_oauth_hash_token($access),
        $parentRefreshTokenId,
        $resourceUri,
        $scopeJson,
        (int)$connection['token_version'],
        $accessTtl,
    ]);
    $accessTokenId = (int)$pdo->lastInsertId();

    $stmt->execute([
        mg_public_uuid(),
        $registrationId,
        (int)$connection['id'],
        $familyId,
        'refresh',
        mg_mcp_oauth_hash_token($refresh),
        $parentRefreshTokenId,
        $resourceUri,
        $scopeJson,
        (int)$connection['token_version'],
        $refreshTtl,
    ]);
    $refreshTokenId = (int)$pdo->lastInsertId();

    if ($parentRefreshTokenId !== null) {
        $pdo->prepare('UPDATE mcp_oauth_tokens SET replaced_by_token_id=? WHERE id=?')
            ->execute([$refreshTokenId, $parentRefreshTokenId]);
    }

    return [
        'access_token' => $access,
        'token_type' => 'Bearer',
        'expires_in' => $accessTtl,
        'refresh_token' => $refresh,
        'scope' => implode(' ', $scopes),
        'resource' => $resourceUri,
        '_evidence' => [
            'access_token_id' => $accessTokenId,
            'refresh_token_id' => $refreshTokenId,
            'family_id' => $familyId,
        ],
    ];
}

function mg_mcp_oauth_exchange_authorization_code(PDO $pdo, array $input): array
{
    mg_mcp_oauth_require_enabled();
    $clientId = trim((string)($input['client_id'] ?? ''));
    $client = mg_mcp_oauth_client_registration($pdo, $clientId);
    $code = trim((string)($input['code'] ?? ''));
    $redirectUri = mg_mcp_oauth_redirect_uri((string)($input['redirect_uri'] ?? ''));
    $resourceUri = rtrim(trim((string)($input['resource'] ?? '')), '/');
    $verifier = trim((string)($input['code_verifier'] ?? ''));
    if ($code === '' || strlen($code) > 256 || preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $verifier) !== 1) {
        throw new MgMcpOAuthException('Invalid authorization code exchange.', 'invalid_grant', 400);
    }
    if (!hash_equals(mg_mcp_oauth_resource_uri(), $resourceUri)) {
        throw new MgMcpOAuthException('Invalid resource.', 'invalid_target', 400);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "SELECT ac.* FROM mcp_oauth_authorization_codes ac
             WHERE ac.code_hash=? AND ac.client_registration_id=? LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([mg_mcp_oauth_hash_token($code), (int)$client['id']]);
        $authorizationCode = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$authorizationCode
            || $authorizationCode['consumed_at'] !== null
            || strtotime((string)$authorizationCode['expires_at']) <= time()
            || !hash_equals((string)$authorizationCode['redirect_uri'], $redirectUri)
            || !hash_equals((string)$authorizationCode['resource_uri'], $resourceUri)
            || !hash_equals((string)$authorizationCode['code_challenge'], mg_mcp_oauth_pkce_challenge($verifier))) {
            throw new MgMcpOAuthException('Invalid or expired authorization code.', 'invalid_grant', 400);
        }
        $connection = mg_mcp_oauth_connection_row($pdo, (int)$authorizationCode['connection_id'], true);
        $scopes = mg_mcp_oauth_json_decode($authorizationCode['scope_json']);
        $pdo->prepare('UPDATE mcp_oauth_authorization_codes SET consumed_at=NOW() WHERE id=?')
            ->execute([(int)$authorizationCode['id']]);
        $pdo->prepare("UPDATE mcp_oauth_authorization_requests SET status='consumed',updated_at=NOW() WHERE id=? AND status='approved'")
            ->execute([(int)$authorizationCode['authorization_request_id']]);
        $tokens = mg_mcp_oauth_issue_tokens(
            $pdo,
            (int)$client['id'],
            $connection,
            $resourceUri,
            array_values(array_map('strval', $scopes))
        );
        $pdo->commit();

        mg_security_log('info', 'mcp.oauth_token.issued', 'MCP OAuth token family issued.', [
            'oauth_client_id' => $clientId,
            'connection_public_id' => (string)$connection['public_id'],
            'token_family_id' => (string)$tokens['_evidence']['family_id'],
        ], (int)$connection['user_id']);
        unset($tokens['_evidence']);
        return $tokens;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
