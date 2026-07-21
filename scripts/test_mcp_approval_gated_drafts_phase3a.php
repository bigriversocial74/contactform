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
putenv('MG_MCP_BRIDGE_ENABLED=true');
putenv('MG_MCP_BRIDGE_SECRET=phase3a-test-bridge-secret-0123456789abcdef');

require_once dirname(__DIR__) . '/includes/mcp-oauth.php';
require_once dirname(__DIR__) . '/includes/mcp-drafts.php';
require_once dirname(__DIR__) . '/api/internal/_mcp_bridge.php';
require_once dirname(__DIR__) . '/api/internal/_mcp_draft_bridge.php';

function phase3a_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function phase3a_uuid(): string
{
    return mg_public_uuid();
}

try {
    $pdo = mg_db();
    $migration = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration_key=? LIMIT 1');
    $migration->execute(['20260720_mcp_approval_gated_drafts_phase3a_v1']);
    phase3a_assert((bool)$migration->fetchColumn(), 'Phase 3A migration is not recorded.');

    $queueCountsBefore = [
        'agent' => (int)$pdo->query('SELECT COUNT(*) FROM agent_workflow_actions')->fetchColumn(),
        'mcp' => (int)$pdo->query('SELECT COUNT(*) FROM mcp_automation_actions')->fetchColumn(),
    ];

    $email = 'mcp-phase3a-' . bin2hex(random_bytes(6)) . '@microgifter.test';
    $pdo->prepare(
        "INSERT INTO users (email,password_hash,full_name,display_name,status,email_verified_at,created_at,updated_at)
         VALUES (?,?,?,?,'active',NOW(),NOW(),NOW())"
    )->execute([$email, password_hash('phase3a-test-password', PASSWORD_DEFAULT), 'Phase 3A Test User', 'Phase 3A']);
    $userId = (int)$pdo->lastInsertId();

    $workspacePublicId = phase3a_uuid();
    $pdo->prepare(
        "INSERT INTO merchant_workspaces
         (public_id,merchant_user_id,display_name,default_currency,timezone,status,eligibility_status,onboarding_percent,created_at,updated_at)
         VALUES (?,?,?,'USD','America/Phoenix','active','eligible',100,NOW(),NOW())"
    )->execute([$workspacePublicId, $userId, 'Phase 3A Merchant']);

    $redirectUri = 'https://client.microgifter.test/oauth/callback';
    $client = mg_mcp_oauth_register_client($pdo, [
        'client_name' => 'Phase 3A Draft Client',
        'client_type' => 'custom',
        'client_uri' => 'https://client.microgifter.test',
        'maximum_operation_class' => 'draft',
        'redirect_uris' => [$redirectUri],
        'grant_types' => ['authorization_code', 'refresh_token'],
        'response_types' => ['code'],
        'token_endpoint_auth_method' => 'none',
    ], $userId, 'preregistered');
    phase3a_assert((string)$client['maximum_operation_class'] === 'draft', 'Draft client authority was not persisted.');

    $verifier = str_repeat('B', 64);
    $scopeString = 'profile:read catalog:read gift:draft campaign:draft reward:draft message:draft';
    $validated = mg_mcp_oauth_validate_authorization_input($pdo, [
        'response_type' => 'code',
        'client_id' => $client['client_id'],
        'redirect_uri' => $redirectUri,
        'resource' => mg_mcp_oauth_resource_uri(),
        'state' => 'phase3a-ci-state',
        'code_challenge' => mg_mcp_oauth_pkce_challenge($verifier),
        'code_challenge_method' => 'S256',
        'scope' => $scopeString,
    ]);
    $authorizationRequest = mg_mcp_oauth_create_authorization_request($pdo, $validated);
    $decision = mg_mcp_oauth_authorization_decision(
        $pdo,
        ['id' => $userId, 'email' => $email, 'status' => 'active'],
        (string)$authorizationRequest['public_id'],
        'approve',
        $workspacePublicId
    );
    $authorizationCode = (string)($decision['parameters']['code'] ?? '');
    phase3a_assert(str_starts_with($authorizationCode, 'mgac_'), 'Authorization code was not issued.');

    $tokens = mg_mcp_oauth_exchange_authorization_code($pdo, [
        'grant_type' => 'authorization_code',
        'client_id' => $client['client_id'],
        'code' => $authorizationCode,
        'redirect_uri' => $redirectUri,
        'resource' => mg_mcp_oauth_resource_uri(),
        'code_verifier' => $verifier,
    ]);
    foreach (['gift:draft', 'campaign:draft', 'reward:draft', 'message:draft'] as $scope) {
        phase3a_assert(str_contains((string)$tokens['scope'], $scope), 'Token is missing ' . $scope . '.');
    }

    $resolved = mg_mcp_oauth_resolve_access_token_hash(
        $pdo,
        mg_mcp_oauth_hash_token((string)$tokens['access_token']),
        mg_mcp_oauth_resource_uri()
    );
    $context = mg_mcp_draft_bridge_connection($pdo, (string)$resolved['connection_public_id']);
    $context['scopes'] = array_values(array_intersect((array)$context['scopes'], (array)$resolved['token_scopes']));
    phase3a_assert((string)$context['maximum_operation_class'] === 'draft', 'Resolved connection is not draft-authorized.');
    phase3a_assert((string)$context['workspace_type'] === 'merchant', 'Merchant workspace authority was not resolved.');

    $giftInput = [
        'type' => 'gift',
        'title' => 'Birthday dinner gift',
        'summary' => 'Prepare one local dinner gift for review.',
        'payload' => [
            'product_id' => phase3a_uuid(),
            'recipient_name' => 'Alex',
            'message' => 'Happy birthday!',
            'quantity' => 1,
        ],
        'idempotency_key' => 'phase3a-gift-001',
        'source_request_id' => phase3a_uuid(),
        'risk_level' => 'medium',
        'requested_reason' => 'The user requested a draft only.',
    ];
    $gift = mg_mcp_draft_create($pdo, $context, $giftInput);
    $campaign = mg_mcp_draft_create($pdo, $context, [
        'type' => 'campaign',
        'title' => 'Summer local campaign',
        'summary' => 'Prepare a campaign concept for merchant review.',
        'payload' => [
            'name' => 'Summer Local',
            'objective' => 'Increase local gifting discovery.',
            'audience_summary' => 'Existing customers and nearby residents.',
            'offer_summary' => 'Feature a selected summer experience.',
            'budget_cents' => 25000,
        ],
        'idempotency_key' => 'phase3a-campaign-001',
        'source_request_id' => phase3a_uuid(),
        'requested_reason' => 'Prepare a campaign proposal without publishing.',
    ]);
    $reward = mg_mcp_draft_create($pdo, $context, [
        'type' => 'reward',
        'title' => 'Repeat visit reward',
        'summary' => 'Prepare a loyalty reward for review.',
        'payload' => [
            'name' => 'Third Visit Thank You',
            'qualification_summary' => 'Customer completes three verified visits.',
            'reward_summary' => 'One complimentary featured item.',
            'quantity_limit' => 100,
        ],
        'idempotency_key' => 'phase3a-reward-001',
        'source_request_id' => phase3a_uuid(),
        'requested_reason' => 'Prepare a reward concept without activation.',
    ]);
    $message = mg_mcp_draft_create($pdo, $context, [
        'type' => 'message',
        'title' => 'Customer thank-you message',
        'summary' => 'Prepare a message for review without sending.',
        'payload' => [
            'audience_summary' => 'Customers who claimed a gift this week.',
            'subject' => 'Thank you for supporting local',
            'body' => 'Thank you for choosing a local gift.',
            'channel' => 'email',
        ],
        'idempotency_key' => 'phase3a-message-001',
        'source_request_id' => phase3a_uuid(),
        'requested_reason' => 'Prepare copy only; do not send.',
    ]);

    foreach ([$gift, $campaign, $reward, $message] as $draft) {
        phase3a_assert((string)$draft['status'] === 'pending_review', 'Draft was not created in pending review.');
        phase3a_assert($draft['execution']['enabled'] === false, 'Draft projection incorrectly enables execution.');
        phase3a_assert((string)$draft['execution']['status'] === 'not_enabled', 'Draft execution status is not disabled.');
    }

    $duplicate = mg_mcp_draft_create($pdo, $context, $giftInput);
    phase3a_assert($duplicate['duplicate'] === true && $duplicate['id'] === $gift['id'], 'Idempotent draft replay did not return the original draft.');
    $conflictDenied = false;
    try {
        $conflicting = $giftInput;
        $conflicting['payload']['quantity'] = 2;
        $conflicting['source_request_id'] = phase3a_uuid();
        mg_mcp_draft_create($pdo, $context, $conflicting);
    } catch (MgMcpDraftException $error) {
        $conflictDenied = $error->httpStatus() === 409 && $error->draftCode() === 'MCP_DRAFT_IDEMPOTENCY_CONFLICT';
    }
    phase3a_assert($conflictDenied, 'Idempotency content mismatch was not denied.');

    $listed = mg_mcp_draft_list_for_connection($pdo, $context, ['limit' => 20]);
    phase3a_assert(count($listed['items']) === 4, 'Connection-scoped draft list is incomplete.');
    $loaded = mg_mcp_draft_get_for_connection($pdo, $context, (string)$gift['id']);
    phase3a_assert((string)$loaded['type'] === 'gift', 'Connection-scoped draft retrieval failed.');

    $otherEmail = 'mcp-phase3a-other-' . bin2hex(random_bytes(4)) . '@microgifter.test';
    $pdo->prepare(
        "INSERT INTO users (email,password_hash,full_name,display_name,status,email_verified_at,created_at,updated_at)
         VALUES (?,?,?,?,'active',NOW(),NOW(),NOW())"
    )->execute([$otherEmail, password_hash('phase3a-other', PASSWORD_DEFAULT), 'Other User', 'Other']);
    $otherUserId = (int)$pdo->lastInsertId();
    $ownerIsolation = false;
    try {
        mg_mcp_draft_owner_decide($pdo, $otherUserId, (string)$campaign['id'], 'approve', 'Unauthorized attempt.');
    } catch (MgMcpDraftException $error) {
        $ownerIsolation = $error->httpStatus() === 404;
    }
    phase3a_assert($ownerIsolation, 'A different user could access the draft decision.');

    $approved = mg_mcp_draft_owner_decide($pdo, $userId, (string)$campaign['id'], 'approve', 'Approved for manual review follow-up.');
    phase3a_assert((string)$approved['status'] === 'approved' && $approved['execution']['enabled'] === false, 'Approval incorrectly enabled execution.');
    $rejected = mg_mcp_draft_owner_decide($pdo, $userId, (string)$reward['id'], 'reject', 'Reward terms need revision.');
    phase3a_assert((string)$rejected['status'] === 'rejected', 'Draft rejection failed.');
    $canceled = mg_mcp_draft_cancel_for_connection($pdo, $context, (string)$message['id'], 'Agent replaced the message concept.');
    phase3a_assert((string)$canceled['status'] === 'canceled', 'Draft cancellation failed.');

    $pdo->prepare("UPDATE mcp_agent_drafts SET approval_expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE public_id=?")
        ->execute([(string)$gift['id']]);
    $pdo->beginTransaction();
    $expiredCount = mg_mcp_draft_expire($pdo, $userId);
    $pdo->commit();
    phase3a_assert($expiredCount === 1, 'Pending draft expiry did not run.');
    $expiredGift = mg_mcp_draft_get_for_connection($pdo, $context, (string)$gift['id']);
    phase3a_assert((string)$expiredGift['status'] === 'expired', 'Expired draft status was not persisted.');

    $events = mg_mcp_draft_events_for_owner($pdo, $userId, (string)$campaign['id']);
    phase3a_assert(array_column($events, 'type') === ['created', 'approved'], 'Campaign draft event history is incomplete.');

    $scopeRevoke = $pdo->prepare(
        "UPDATE mcp_connection_scopes SET revoked_at=NOW() WHERE connection_id=? AND scope_key='message:draft'"
    );
    $scopeRevoke->execute([(int)$context['connection_db_id']]);
    $revokedContext = mg_mcp_draft_bridge_connection($pdo, (string)$context['connection_public_id']);
    $scopeDenied = false;
    try {
        mg_mcp_draft_bridge_dispatch($pdo, $revokedContext, 'draft.get', ['draft_id' => (string)$message['id']]);
    } catch (MgMcpBridgeException $error) {
        $scopeDenied = $error->httpStatus() === 403 && $error->bridgeCode() === 'MCP_DRAFT_SCOPE_DENIED';
    }
    phase3a_assert($scopeDenied, 'Revoked draft scope did not block subsequent access.');

    $queueCountsAfter = [
        'agent' => (int)$pdo->query('SELECT COUNT(*) FROM agent_workflow_actions')->fetchColumn(),
        'mcp' => (int)$pdo->query('SELECT COUNT(*) FROM mcp_automation_actions')->fetchColumn(),
    ];
    phase3a_assert($queueCountsAfter === $queueCountsBefore, 'Draft lifecycle inserted an action into an execution queue.');

    echo "MCP approval-gated drafts Phase 3A executable flow passed.\n";
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'MCP Phase 3A flow failed: ' . $error->getMessage() . "\n");
    exit(1);
}
