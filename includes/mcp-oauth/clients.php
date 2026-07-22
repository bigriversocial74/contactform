<?php
declare(strict_types=1);

function mg_mcp_oauth_client_registration(PDO $pdo, string $clientId, bool $lock = false): array
{
    $clientId = mg_mcp_oauth_text($clientId, 1, 255, 'client_id');
    $sql = "SELECT r.*,c.public_id AS mcp_client_public_id,c.client_key,c.display_name AS mcp_client_display_name,
                   c.status AS mcp_client_status,c.client_type,c.maximum_operation_class
            FROM mcp_oauth_client_registrations r
            INNER JOIN mcp_clients c ON c.id=r.mcp_client_id
            WHERE r.client_id=? LIMIT 1";
    if ($lock) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$clientId]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$registration
        || (string)$registration['status'] !== 'active'
        || !in_array((string)$registration['mcp_client_status'], ['development', 'active'], true)
        || !in_array((string)$registration['maximum_operation_class'], ['read', 'draft', 'approval_gated'], true)) {
        throw new MgMcpOAuthException('OAuth client is unavailable.', 'invalid_client', 401);
    }
    $registration['redirect_uris'] = mg_mcp_oauth_json_decode($registration['redirect_uris_json']);
    return $registration;
}

function mg_mcp_oauth_register_client(PDO $pdo, array $input, ?int $actorId = null, string $registrationType = 'dynamic'): array
{
    if (!in_array($registrationType, ['dynamic', 'preregistered'], true)) {
        throw new MgMcpOAuthException('Invalid registration type.', 'invalid_request', 422);
    }
    if ($registrationType === 'dynamic') mg_mcp_oauth_require_enabled();

    $name = mg_mcp_oauth_text($input['client_name'] ?? '', 3, 180, 'client_name');
    $redirectUris = mg_mcp_oauth_redirect_uris($input['redirect_uris'] ?? []);
    $clientUri = trim((string)($input['client_uri'] ?? ''));
    $logoUri = trim((string)($input['logo_uri'] ?? ''));
    if ($clientUri !== '') $clientUri = mg_mcp_oauth_https_url($clientUri, 'client_uri');
    if ($logoUri !== '') $logoUri = mg_mcp_oauth_https_url($logoUri, 'logo_uri');
    $grantTypes = is_array($input['grant_types'] ?? null) ? $input['grant_types'] : ['authorization_code', 'refresh_token'];
    $grantTypes = array_values(array_unique(array_map('strval', $grantTypes)));
    sort($grantTypes);
    if (array_diff($grantTypes, ['authorization_code', 'refresh_token']) !== []
        || !in_array('authorization_code', $grantTypes, true)) {
        throw new MgMcpOAuthException('Only authorization_code and refresh_token grants are supported.', 'invalid_client_metadata', 422);
    }
    $responseTypes = is_array($input['response_types'] ?? null) ? $input['response_types'] : ['code'];
    $responseTypes = array_values(array_unique(array_map('strval', $responseTypes)));
    if ($responseTypes !== ['code']) throw new MgMcpOAuthException('Only the code response type is supported.', 'invalid_client_metadata', 422);
    $authMethod = strtolower(trim((string)($input['token_endpoint_auth_method'] ?? 'none')));
    if ($authMethod !== 'none') {
        throw new MgMcpOAuthException('Public clients must use token_endpoint_auth_method=none.', 'invalid_client_metadata', 422);
    }
    $clientType = strtolower(trim((string)($input['client_type'] ?? 'custom')));
    if (!in_array($clientType, ['first_party', 'chatgpt', 'claude', 'custom', 'enterprise'], true)) $clientType = 'custom';
    $maximumOperationClass = $registrationType === 'dynamic'
        ? 'read'
        : mg_mcp_oauth_maximum_operation_class($input['maximum_operation_class'] ?? 'read');

    $pdo->beginTransaction();
    try {
        $clientPublicId = mg_public_uuid();
        $clientKey = 'oauth-' . substr(str_replace('-', '', $clientPublicId), 0, 24);
        $clientStmt = $pdo->prepare(
            "INSERT INTO mcp_clients
             (public_id,client_key,display_name,status,client_type,redirect_uris_json,maximum_operation_class,metadata_json,created_by_user_id,created_at,updated_at)
             VALUES (?,?,?,'active',?,?,?,?,?,NOW(),NOW())"
        );
        $clientStmt->execute([
            $clientPublicId,
            $clientKey,
            $name,
            $clientType,
            json_encode($redirectUris, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $maximumOperationClass,
            json_encode([
                'oauth_phase' => '13C',
                'registration_type' => $registrationType,
                'review_only_drafts' => in_array($maximumOperationClass, ['draft', 'approval_gated'], true),
                'approval_gated_requests' => $maximumOperationClass === 'approval_gated',
                'owner_execution_required' => $maximumOperationClass === 'approval_gated',
                'execution_enabled' => false,
            ], JSON_THROW_ON_ERROR),
            $actorId,
        ]);
        $mcpClientId = (int)$pdo->lastInsertId();

        $registrationPublicId = mg_public_uuid();
        $clientId = $clientPublicId;
        $stmt = $pdo->prepare(
            "INSERT INTO mcp_oauth_client_registrations
             (public_id,mcp_client_id,client_id,client_name,client_uri,logo_uri,status,registration_type,
              redirect_uris_json,grant_types_json,response_types_json,token_endpoint_auth_method,
              registration_access_token_hash,created_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,?,?,'active',?,?,?,?, 'none',NULL,?,NOW(),NOW())"
        );
        $stmt->execute([
            $registrationPublicId,
            $mcpClientId,
            $clientId,
            $name,
            $clientUri !== '' ? $clientUri : null,
            $logoUri !== '' ? $logoUri : null,
            $registrationType,
            json_encode($redirectUris, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            json_encode($grantTypes, JSON_THROW_ON_ERROR),
            json_encode($responseTypes, JSON_THROW_ON_ERROR),
            $actorId,
        ]);
        $pdo->commit();

        $metadata = [
            'oauth_client_id' => $clientId,
            'client_name' => $name,
            'registration_type' => $registrationType,
            'redirect_uri_count' => count($redirectUris),
            'maximum_operation_class' => $maximumOperationClass,
            'execution_enabled' => false,
            'owner_execution_required' => $maximumOperationClass === 'approval_gated',
        ];
        mg_audit('mcp_oauth_client_registered', 'mcp_oauth_client', $metadata, $actorId);
        mg_event('mcp.oauth.client.registered', $metadata, $actorId);
        mg_security_log('info', 'mcp.oauth_client.registered', 'MCP OAuth client registered.', $metadata, $actorId);

        return [
            'client_id' => $clientId,
            'client_name' => $name,
            'client_uri' => $clientUri !== '' ? $clientUri : null,
            'logo_uri' => $logoUri !== '' ? $logoUri : null,
            'redirect_uris' => $redirectUris,
            'grant_types' => $grantTypes,
            'response_types' => $responseTypes,
            'token_endpoint_auth_method' => 'none',
            'maximum_operation_class' => $maximumOperationClass,
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error instanceof PDOException && (string)$error->getCode() === '23000') {
            throw new MgMcpOAuthException('OAuth client registration conflicts with an existing client.', 'invalid_client_metadata', 409);
        }
        throw $error;
    }
}

function mg_mcp_oauth_validate_authorization_input(PDO $pdo, array $input): array
{
    mg_mcp_oauth_require_enabled();
    if (trim((string)($input['response_type'] ?? '')) !== 'code') {
        throw new MgMcpOAuthException('Only response_type=code is supported.', 'unsupported_response_type', 400);
    }
    $client = mg_mcp_oauth_client_registration($pdo, trim((string)($input['client_id'] ?? '')));
    $redirectUri = mg_mcp_oauth_redirect_uri((string)($input['redirect_uri'] ?? ''));
    if (!in_array($redirectUri, (array)$client['redirect_uris'], true)) {
        throw new MgMcpOAuthException('The redirect URI is not registered for this client.', 'invalid_request', 400);
    }
    $resource = rtrim(trim((string)($input['resource'] ?? '')), '/');
    if ($resource === '' || !hash_equals(mg_mcp_oauth_resource_uri(), $resource)) {
        throw new MgMcpOAuthException('The resource parameter must identify the Microgifter MCP server.', 'invalid_target', 400);
    }
    $state = mg_mcp_oauth_text($input['state'] ?? '', 1, 512, 'state');
    $challenge = trim((string)($input['code_challenge'] ?? ''));
    if (preg_match('/^[A-Za-z0-9_-]{43,128}$/', $challenge) !== 1
        || strtoupper(trim((string)($input['code_challenge_method'] ?? ''))) !== 'S256') {
        throw new MgMcpOAuthException('PKCE with code_challenge_method=S256 is required.', 'invalid_request', 400);
    }
    $scopes = mg_mcp_oauth_scope_keys_for_class(
        $pdo,
        $input['scope'] ?? '',
        (string)$client['maximum_operation_class']
    );
    return [
        'client' => $client,
        'redirect_uri' => $redirectUri,
        'resource_uri' => $resource,
        'state' => $state,
        'code_challenge' => $challenge,
        'scopes' => $scopes,
    ];
}

function mg_mcp_oauth_create_authorization_request(PDO $pdo, array $validated): array
{
    $publicId = mg_public_uuid();
    $stmt = $pdo->prepare(
        "INSERT INTO mcp_oauth_authorization_requests
         (public_id,client_registration_id,redirect_uri,resource_uri,state_value,scope_json,
          code_challenge,code_challenge_method,status,expires_at,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,'S256','pending',DATE_ADD(NOW(),INTERVAL 10 MINUTE),NOW(),NOW())"
    );
    $stmt->execute([
        $publicId,
        (int)$validated['client']['id'],
        (string)$validated['redirect_uri'],
        (string)$validated['resource_uri'],
        (string)$validated['state'],
        json_encode($validated['scopes'], JSON_THROW_ON_ERROR),
        (string)$validated['code_challenge'],
    ]);
    return mg_mcp_oauth_authorization_request($pdo, $publicId, false);
}

function mg_mcp_oauth_authorization_request(PDO $pdo, string $publicId, bool $lock = false): array
{
    if (preg_match('/^[0-9a-f-]{36}$/i', $publicId) !== 1) {
        throw new MgMcpOAuthException('Invalid authorization request.', 'invalid_request', 400);
    }
    $sql = "SELECT ar.*,r.client_id,r.client_name,r.client_uri,r.logo_uri,r.redirect_uris_json,
                   c.client_type,c.status AS mcp_client_status,c.maximum_operation_class
            FROM mcp_oauth_authorization_requests ar
            INNER JOIN mcp_oauth_client_registrations r ON r.id=ar.client_registration_id
            INNER JOIN mcp_clients c ON c.id=r.mcp_client_id
            WHERE ar.public_id=? LIMIT 1";
    if ($lock) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$publicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (string)$row['status'] !== 'pending' || strtotime((string)$row['expires_at']) <= time()) {
        throw new MgMcpOAuthException('Authorization request is unavailable or expired.', 'invalid_request', 400);
    }
    $row['scopes'] = mg_mcp_oauth_json_decode($row['scope_json']);
    return $row;
}
