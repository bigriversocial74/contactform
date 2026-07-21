<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

putenv('MG_MCP_OAUTH_ENABLED=true');
putenv('MG_MCP_OAUTH_ISSUER=https://microgifter.test');
putenv('MG_MCP_OAUTH_RESOURCE_URI=https://mcp.microgifter.test/mcp');
putenv('MG_MCP_OAUTH_ACCESS_TTL_SECONDS=900');
putenv('MG_MCP_OAUTH_REFRESH_TTL_SECONDS=86400');

require_once dirname(__DIR__) . '/includes/mcp-oauth.php';

function phase2a_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = mg_db();
    $migration = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration_key=? LIMIT 1');
    $migration->execute(['20260720_mcp_external_agent_authorization_phase2a_v1']);
    phase2a_assert((bool)$migration->fetchColumn(), 'Phase 2A migration is not recorded.');

    $email = 'mcp-phase2a-' . bin2hex(random_bytes(6)) . '@microgifter.test';
    $pdo->prepare(
        "INSERT INTO users (email,password_hash,full_name,display_name,status,email_verified_at,created_at,updated_at)
         VALUES (?,?,?,?,'active',NOW(),NOW(),NOW())"
    )->execute([$email, password_hash('phase2a-test-password', PASSWORD_DEFAULT), 'Phase 2A Test User', 'Phase 2A']);
    $userId = (int)$pdo->lastInsertId();

    $redirectUri = 'https://client.microgifter.test/oauth/callback';
    $client = mg_mcp_oauth_register_client($pdo, [
        'client_name' => 'Phase 2A CI Client',
        'client_type' => 'custom',
        'client_uri' => 'https://client.microgifter.test',
        'redirect_uris' => [$redirectUri],
        'grant_types' => ['authorization_code', 'refresh_token'],
        'response_types' => ['code'],
        'token_endpoint_auth_method' => 'none',
    ], $userId, 'preregistered');
    phase2a_assert(!isset($client['client_secret']), 'Public PKCE clients must not receive a client secret.');
    phase2a_assert(!isset($client['registration_access_token']), 'Unsupported registration-management credentials must not be advertised.');

    $verifier = str_repeat('A', 64);
    $validated = mg_mcp_oauth_validate_authorization_input($pdo, [
        'response_type' => 'code',
        'client_id' => $client['client_id'],
        'redirect_uri' => $redirectUri,
        'resource' => mg_mcp_oauth_resource_uri(),
        'state' => 'phase2a-ci-state',
        'code_challenge' => mg_mcp_oauth_pkce_challenge($verifier),
        'code_challenge_method' => 'S256',
        'scope' => 'profile:read catalog:read',
    ]);
    $authorizationRequest = mg_mcp_oauth_create_authorization_request($pdo, $validated);
    $decision = mg_mcp_oauth_authorization_decision(
        $pdo,
        ['id' => $userId, 'email' => $email, 'status' => 'active'],
        (string)$authorizationRequest['public_id'],
        'approve',
        ''
    );
    $authorizationCode = (string)($decision['parameters']['code'] ?? '');
    phase2a_assert(str_starts_with($authorizationCode, 'mgac_'), 'Authorization code was not issued.');

    $tokens = mg_mcp_oauth_exchange_authorization_code($pdo, [
        'grant_type' => 'authorization_code',
        'client_id' => $client['client_id'],
        'code' => $authorizationCode,
        'redirect_uri' => $redirectUri,
        'resource' => mg_mcp_oauth_resource_uri(),
        'code_verifier' => $verifier,
    ]);
    phase2a_assert(str_starts_with((string)$tokens['access_token'], 'mgat_'), 'Access token was not issued.');
    phase2a_assert(str_starts_with((string)$tokens['refresh_token'], 'mgrt_'), 'Refresh token was not issued.');
    phase2a_assert((string)$tokens['scope'] === 'catalog:read profile:read', 'Granted scopes were not deterministic.');

    $codeState = $pdo->prepare(
        "SELECT ac.consumed_at,ar.status
         FROM mcp_oauth_authorization_codes ac
         INNER JOIN mcp_oauth_authorization_requests ar ON ar.id=ac.authorization_request_id
         WHERE ac.code_hash=? LIMIT 1"
    );
    $codeState->execute([mg_mcp_oauth_hash_token($authorizationCode)]);
    $codeEvidence = $codeState->fetch(PDO::FETCH_ASSOC);
    phase2a_assert($codeEvidence && $codeEvidence['consumed_at'] !== null && $codeEvidence['status'] === 'consumed', 'Authorization code/request consumption evidence is incomplete.');

    $tokenRows = $pdo->prepare(
        "SELECT id,token_family_id,token_type,token_hash,revoked_at,revocation_reason,replaced_by_token_id
         FROM mcp_oauth_tokens WHERE token_hash IN (?,?) ORDER BY token_type"
    );
    $tokenRows->execute([
        mg_mcp_oauth_hash_token((string)$tokens['access_token']),
        mg_mcp_oauth_hash_token((string)$tokens['refresh_token']),
    ]);
    $stored = $tokenRows->fetchAll(PDO::FETCH_ASSOC);
    phase2a_assert(count($stored) === 2, 'Expected hashed access and refresh token rows.');
    foreach ($stored as $row) {
        phase2a_assert(!hash_equals((string)$row['token_hash'], (string)$tokens['access_token']), 'Raw access token was persisted.');
        phase2a_assert(!hash_equals((string)$row['token_hash'], (string)$tokens['refresh_token']), 'Raw refresh token was persisted.');
    }

    $resolved = mg_mcp_oauth_resolve_access_token_hash(
        $pdo,
        mg_mcp_oauth_hash_token((string)$tokens['access_token']),
        mg_mcp_oauth_resource_uri()
    );
    phase2a_assert((string)$resolved['oauth_client_id'] === (string)$client['client_id'], 'Access token did not resolve its OAuth client.');
    phase2a_assert(in_array('profile:read', $resolved['token_scopes'], true), 'Resolved access token lacks profile scope.');

    $originalRefresh = (string)$tokens['refresh_token'];
    $rotated = mg_mcp_oauth_exchange_refresh_token($pdo, [
        'grant_type' => 'refresh_token',
        'client_id' => $client['client_id'],
        'refresh_token' => $originalRefresh,
        'resource' => mg_mcp_oauth_resource_uri(),
    ]);
    phase2a_assert(!hash_equals($originalRefresh, (string)$rotated['refresh_token']), 'Refresh token did not rotate.');

    $oldRefreshStmt = $pdo->prepare(
        "SELECT id,token_family_id,revoked_at,revocation_reason,replaced_by_token_id
         FROM mcp_oauth_tokens WHERE token_hash=? AND token_type='refresh' LIMIT 1"
    );
    $oldRefreshStmt->execute([mg_mcp_oauth_hash_token($originalRefresh)]);
    $oldRefresh = $oldRefreshStmt->fetch(PDO::FETCH_ASSOC);
    phase2a_assert($oldRefresh && $oldRefresh['revoked_at'] !== null, 'Rotated refresh token was not revoked.');
    phase2a_assert((string)$oldRefresh['revocation_reason'] === 'rotated', 'Rotated refresh token lacks rotation evidence.');
    phase2a_assert((int)$oldRefresh['replaced_by_token_id'] > 0, 'Refresh replacement linkage is missing.');

    $reuseDenied = false;
    try {
        mg_mcp_oauth_exchange_refresh_token($pdo, [
            'grant_type' => 'refresh_token',
            'client_id' => $client['client_id'],
            'refresh_token' => $originalRefresh,
            'resource' => mg_mcp_oauth_resource_uri(),
        ]);
    } catch (MgMcpOAuthException $error) {
        $reuseDenied = $error->oauthError() === 'invalid_grant';
    }
    phase2a_assert($reuseDenied, 'Rotated refresh-token replay was not denied.');

    $familyStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM mcp_oauth_tokens WHERE token_family_id=? AND revoked_at IS NULL'
    );
    $familyStmt->execute([(string)$oldRefresh['token_family_id']]);
    phase2a_assert((int)$familyStmt->fetchColumn() === 0, 'Refresh replay did not revoke the full token family.');

    $rotatedAccessDenied = false;
    try {
        mg_mcp_oauth_resolve_access_token_hash(
            $pdo,
            mg_mcp_oauth_hash_token((string)$rotated['access_token']),
            mg_mcp_oauth_resource_uri()
        );
    } catch (MgMcpOAuthException $error) {
        $rotatedAccessDenied = $error->oauthError() === 'invalid_token';
    }
    phase2a_assert($rotatedAccessDenied, 'Family revocation did not invalidate the rotated access token.');

    echo "MCP external agent authorization Phase 2A executable flow passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'MCP Phase 2A flow failed: ' . $error->getMessage() . "\n");
    exit(1);
}
