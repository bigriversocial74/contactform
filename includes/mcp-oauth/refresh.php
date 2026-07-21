<?php
declare(strict_types=1);

function mg_mcp_oauth_revoke_family(PDO $pdo, string $familyId, string $reason): void
{
    $pdo->prepare(
        "UPDATE mcp_oauth_tokens SET revoked_at=COALESCE(revoked_at,NOW()),revocation_reason=COALESCE(revocation_reason,?)
         WHERE token_family_id=?"
    )->execute([$reason, $familyId]);
}

function mg_mcp_oauth_exchange_refresh_token(PDO $pdo, array $input): array
{
    mg_mcp_oauth_require_enabled();
    $clientId = trim((string)($input['client_id'] ?? ''));
    $client = mg_mcp_oauth_client_registration($pdo, $clientId);
    $refreshToken = trim((string)($input['refresh_token'] ?? ''));
    $resourceUri = rtrim(trim((string)($input['resource'] ?? '')), '/');
    if ($refreshToken === '' || strlen($refreshToken) > 256) {
        throw new MgMcpOAuthException('Invalid refresh token.', 'invalid_grant', 400);
    }
    if (!hash_equals(mg_mcp_oauth_resource_uri(), $resourceUri)) {
        throw new MgMcpOAuthException('Invalid resource.', 'invalid_target', 400);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM mcp_oauth_tokens
             WHERE token_hash=? AND token_type='refresh' AND client_registration_id=? LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([mg_mcp_oauth_hash_token($refreshToken), (int)$client['id']]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$token) {
            throw new MgMcpOAuthException('Invalid refresh token.', 'invalid_grant', 400);
        }
        if ($token['revoked_at'] !== null) {
            if ((string)$token['revocation_reason'] === 'rotated') {
                $familyId = (string)$token['token_family_id'];
                mg_mcp_oauth_revoke_family($pdo, $familyId, 'refresh_reuse_detected');
                $pdo->commit();
                mg_security_log('warning', 'mcp.oauth_refresh.reuse_detected', 'Rotated MCP refresh token was replayed.', [
                    'token_family_id' => $familyId,
                    'oauth_client_id' => $clientId,
                ]);
                throw new MgMcpOAuthException('Invalid refresh token.', 'invalid_grant', 400);
            }
            throw new MgMcpOAuthException('Invalid refresh token.', 'invalid_grant', 400);
        }
        if (strtotime((string)$token['expires_at']) <= time()
            || !hash_equals((string)$token['resource_uri'], $resourceUri)) {
            throw new MgMcpOAuthException('Expired or invalid refresh token.', 'invalid_grant', 400);
        }
        $connection = mg_mcp_oauth_connection_row($pdo, (int)$token['connection_id'], true);
        if ((int)$token['connection_token_version'] !== (int)$connection['token_version']) {
            throw new MgMcpOAuthException('Refresh token has been invalidated.', 'invalid_grant', 400);
        }
        $scopes = array_values(array_map('strval', mg_mcp_oauth_json_decode($token['scope_json'])));
        $pdo->prepare(
            "UPDATE mcp_oauth_tokens SET revoked_at=NOW(),revocation_reason='rotated',last_used_at=NOW() WHERE id=?"
        )->execute([(int)$token['id']]);
        $tokens = mg_mcp_oauth_issue_tokens(
            $pdo,
            (int)$client['id'],
            $connection,
            $resourceUri,
            $scopes,
            (string)$token['token_family_id'],
            (int)$token['id']
        );
        $pdo->commit();

        mg_security_log('info', 'mcp.oauth_refresh.rotated', 'MCP OAuth refresh token rotated.', [
            'oauth_client_id' => $clientId,
            'connection_public_id' => (string)$connection['public_id'],
            'token_family_id' => (string)$token['token_family_id'],
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

function mg_mcp_oauth_token_endpoint(PDO $pdo, array $input): array
{
    $grantType = trim((string)($input['grant_type'] ?? ''));
    return match ($grantType) {
        'authorization_code' => mg_mcp_oauth_exchange_authorization_code($pdo, $input),
        'refresh_token' => mg_mcp_oauth_exchange_refresh_token($pdo, $input),
        default => throw new MgMcpOAuthException('Unsupported grant type.', 'unsupported_grant_type', 400),
    };
}

function mg_mcp_oauth_revoke_token(PDO $pdo, array $input): void
{
    mg_mcp_oauth_require_enabled();
    $client = mg_mcp_oauth_client_registration($pdo, trim((string)($input['client_id'] ?? '')));
    $rawToken = trim((string)($input['token'] ?? ''));
    if ($rawToken === '' || strlen($rawToken) > 256) {
        return;
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM mcp_oauth_tokens WHERE token_hash=? AND client_registration_id=? LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([mg_mcp_oauth_hash_token($rawToken), (int)$client['id']]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($token) {
            mg_mcp_oauth_revoke_family($pdo, (string)$token['token_family_id'], 'client_revocation');
            mg_security_log('info', 'mcp.oauth_token.revoked', 'MCP OAuth token family revoked by client.', [
                'oauth_client_id' => (string)$client['client_id'],
                'token_family_id' => (string)$token['token_family_id'],
            ]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
