<?php
declare(strict_types=1);

putenv('MG_MCP_OAUTH_ENABLED=true');
putenv('MG_MCP_OAUTH_ISSUER=https://microgifter.test');
putenv('MG_MCP_OAUTH_RESOURCE_URI=https://mcp.microgifter.test/mcp');
putenv('MG_MCP_OAUTH_ACCESS_TTL_SECONDS=900');
putenv('MG_MCP_OAUTH_REFRESH_TTL_SECONDS=86400');
putenv('MG_MCP_BRIDGE_ENABLED=true');
putenv('MG_MCP_BRIDGE_SECRET=phase3b-test-bridge-secret-0123456789abcdef');

require_once dirname(__DIR__, 2) . '/includes/mcp-oauth.php';
require_once dirname(__DIR__, 2) . '/includes/mcp-drafts.php';
require_once dirname(__DIR__, 2) . '/api/internal/_mcp_bridge.php';
require_once dirname(__DIR__, 2) . '/api/internal/_mcp_draft_bridge.php';

function phase3b_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function phase3b_uuid(): string
{
    return mg_public_uuid();
}

function phase3b_create_agent_draft(PDO $pdo, array $context, string $type, array $payload, string $suffix): array
{
    return mg_mcp_draft_create($pdo, $context, [
        'type' => $type,
        'title' => 'Phase 3B ' . ucfirst($type) . ' ' . $suffix,
        'summary' => 'Prepare a reviewable ' . $type . ' draft for conversion testing.',
        'payload' => $payload,
        'idempotency_key' => 'phase3b-' . $type . '-' . $suffix,
        'source_request_id' => phase3b_uuid(),
        'risk_level' => 'medium',
        'requested_reason' => 'Phase 3B clean-database lifecycle test.',
    ]);
}

function phase3b_build_fixture(PDO $pdo): array
{
    $migration = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration_key=? LIMIT 1');
    $migration->execute(['20260720_mcp_approved_draft_conversion_phase3b_v1']);
    phase3b_assert((bool)$migration->fetchColumn(), 'Phase 3B migration is not recorded.');

    $queueCountsBefore = [
        'agent' => (int)$pdo->query('SELECT COUNT(*) FROM agent_workflow_actions')->fetchColumn(),
        'mcp' => (int)$pdo->query('SELECT COUNT(*) FROM mcp_automation_actions')->fetchColumn(),
    ];

    $email = 'mcp-phase3b-' . bin2hex(random_bytes(6)) . '@microgifter.test';
    $pdo->prepare(
        "INSERT INTO users (email,password_hash,full_name,display_name,status,email_verified_at,created_at,updated_at)
         VALUES (?,?,?,?,'active',NOW(),NOW(),NOW())"
    )->execute([$email, password_hash('phase3b-test-password', PASSWORD_DEFAULT), 'Phase 3B Test User', 'Phase 3B']);
    $userId = (int)$pdo->lastInsertId();
    $user = [
        'id' => $userId,
        'email' => $email,
        'status' => 'active',
        'roles' => ['admin'],
        'permissions' => ['gift.create','merchant.campaigns.manage','merchant.reward_templates.manage'],
    ];

    $workspacePublicId = phase3b_uuid();
    $pdo->prepare(
        "INSERT INTO merchant_workspaces
         (public_id,merchant_user_id,display_name,default_currency,timezone,status,eligibility_status,onboarding_percent,created_at,updated_at)
         VALUES (?,?,?,'USD','America/Phoenix','active','eligible',100,NOW(),NOW())"
    )->execute([$workspacePublicId, $userId, 'Phase 3B Merchant']);

    $productPublicId = phase3b_uuid();
    $versionPublicId = phase3b_uuid();
    $pdo->prepare(
        "INSERT INTO catalog_products
         (public_id,merchant_user_id,product_type,slug,status,current_version_id,created_by_user_id,published_at,created_at,updated_at)
         VALUES (?,?,'gift',?,'draft',NULL,?,NULL,NOW(),NOW())"
    )->execute([$productPublicId, $userId, 'phase-3b-local-dinner', $userId]);
    $productDbId = (int)$pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO catalog_product_versions
         (public_id,product_id,version_number,version_status,title,description,unit_value_cents,currency,checksum,created_by_user_id,published_at,created_at)
         VALUES (?,?,1,'published',?,?,2500,'USD',?,?,NOW(),NOW())"
    )->execute([$versionPublicId, $productDbId, 'Local Dinner for Two', 'A local dining experience.', hash('sha256', 'phase3b-product'), $userId]);
    $versionDbId = (int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE catalog_products SET current_version_id=?,status='published',published_at=NOW(),updated_at=NOW() WHERE id=?")
        ->execute([$versionDbId, $productDbId]);

    $redirectUri = 'https://client.microgifter.test/oauth/callback';
    $client = mg_mcp_oauth_register_client($pdo, [
        'client_name' => 'Phase 3B Draft Client',
        'client_type' => 'custom',
        'client_uri' => 'https://client.microgifter.test',
        'maximum_operation_class' => 'draft',
        'redirect_uris' => [$redirectUri],
        'grant_types' => ['authorization_code', 'refresh_token'],
        'response_types' => ['code'],
        'token_endpoint_auth_method' => 'none',
    ], $userId, 'preregistered');

    $verifier = str_repeat('C', 64);
    $validated = mg_mcp_oauth_validate_authorization_input($pdo, [
        'response_type' => 'code',
        'client_id' => $client['client_id'],
        'redirect_uri' => $redirectUri,
        'resource' => mg_mcp_oauth_resource_uri(),
        'state' => 'phase3b-ci-state',
        'code_challenge' => mg_mcp_oauth_pkce_challenge($verifier),
        'code_challenge_method' => 'S256',
        'scope' => 'profile:read catalog:read gift:draft campaign:draft reward:draft message:draft',
    ]);
    $authorizationRequest = mg_mcp_oauth_create_authorization_request($pdo, $validated);
    $decision = mg_mcp_oauth_authorization_decision($pdo, $user, (string)$authorizationRequest['public_id'], 'approve', $workspacePublicId);
    $tokens = mg_mcp_oauth_exchange_authorization_code($pdo, [
        'grant_type' => 'authorization_code',
        'client_id' => $client['client_id'],
        'code' => (string)$decision['parameters']['code'],
        'redirect_uri' => $redirectUri,
        'resource' => mg_mcp_oauth_resource_uri(),
        'code_verifier' => $verifier,
    ]);
    $resolved = mg_mcp_oauth_resolve_access_token_hash($pdo, mg_mcp_oauth_hash_token((string)$tokens['access_token']), mg_mcp_oauth_resource_uri());
    $context = mg_mcp_draft_bridge_connection($pdo, (string)$resolved['connection_public_id']);
    $context['scopes'] = array_values(array_intersect((array)$context['scopes'], (array)$resolved['token_scopes']));

    return compact('userId','user','productPublicId','context','queueCountsBefore');
}
