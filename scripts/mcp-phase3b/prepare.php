<?php
declare(strict_types=1);

function phase3b_prepare_conversions(PDO $pdo, array $fixture): array
{
    $userId = (int)$fixture['userId'];
    $user = (array)$fixture['user'];
    $productPublicId = (string)$fixture['productPublicId'];
    $context = (array)$fixture['context'];

    $drafts = [
        'gift' => phase3b_create_agent_draft($pdo, $context, 'gift', [
            'product_id' => $productPublicId,
            'recipient_name' => 'Alex',
            'recipient_reference' => 'alex@example.test',
            'message' => 'Happy birthday!',
            'quantity' => 2,
            'deliver_after' => '2026-08-01 12:00:00',
        ], 'gift-001'),
        'campaign' => phase3b_create_agent_draft($pdo, $context, 'campaign', [
            'name' => 'Summer Local',
            'objective' => 'Increase local gifting discovery.',
            'audience_summary' => 'Existing customers and nearby residents.',
            'offer_summary' => 'Feature a summer dining experience.',
            'starts_at' => '2026-08-01 00:00:00',
            'ends_at' => '2026-08-31 23:59:59',
            'budget_cents' => 25000,
        ], 'campaign-001'),
        'reward' => phase3b_create_agent_draft($pdo, $context, 'reward', [
            'name' => 'Third Visit Thank You',
            'qualification_summary' => 'Customer completes three verified visits.',
            'reward_summary' => 'One complimentary featured item.',
            'quantity_limit' => 100,
            'ends_at' => '2026-12-31 23:59:59',
        ], 'reward-001'),
        'message' => phase3b_create_agent_draft($pdo, $context, 'message', [
            'audience_summary' => 'Customers who claimed a gift this week.',
            'subject' => 'Thank you for supporting local',
            'body' => 'Thank you for choosing a local gift.',
            'channel' => 'email',
        ], 'message-001'),
    ];
    $pending = phase3b_create_agent_draft($pdo, $context, 'message', [
        'audience_summary' => 'Pending test audience.',
        'subject' => 'Pending conversion test',
        'body' => 'This draft should not convert until approved.',
        'channel' => 'in_app',
    ], 'pending-001');

    $pendingDenied = false;
    try { mg_mcp_conversion_prepare($pdo, $user, (string)$pending['id']); }
    catch (MgMcpDraftException $error) { $pendingDenied = $error->draftCode() === 'MCP_CONVERSION_DRAFT_NOT_APPROVED'; }
    phase3b_assert($pendingDenied, 'A pending draft could be prepared for conversion.');

    foreach ($drafts as $key => $draft) {
        $drafts[$key] = mg_mcp_draft_owner_decide($pdo, $userId, (string)$draft['id'], 'approve', 'Approved for Phase 3B conversion testing.');
    }

    $otherEmail = 'mcp-phase3b-other-' . bin2hex(random_bytes(4)) . '@microgifter.test';
    $pdo->prepare(
        "INSERT INTO users (email,password_hash,full_name,display_name,status,email_verified_at,created_at,updated_at)
         VALUES (?,?,?,?,'active',NOW(),NOW(),NOW())"
    )->execute([$otherEmail, password_hash('phase3b-other', PASSWORD_DEFAULT), 'Other User', 'Other']);
    $otherUser = ['id' => (int)$pdo->lastInsertId(), 'roles' => ['admin'], 'permissions' => []];
    $ownerDenied = false;
    try { mg_mcp_conversion_prepare($pdo, $otherUser, (string)$drafts['campaign']['id']); }
    catch (MgMcpDraftException $error) { $ownerDenied = $error->httpStatus() === 404; }
    phase3b_assert($ownerDenied, 'A different user could prepare the conversion.');

    $conversions = [];
    foreach ($drafts as $type => $draft) {
        $conversions[$type] = mg_mcp_conversion_prepare($pdo, $user, (string)$draft['id']);
        phase3b_assert((string)$conversions[$type]['status'] === 'prepared', ucfirst($type) . ' conversion was not prepared.');
        phase3b_assert($conversions[$type]['execution']['enabled'] === false, ucfirst($type) . ' conversion enabled execution.');
    }
    $duplicatePrepare = mg_mcp_conversion_prepare($pdo, $user, (string)$drafts['gift']['id']);
    phase3b_assert($duplicatePrepare['duplicate'] === true && $duplicatePrepare['id'] === $conversions['gift']['id'], 'Conversion prepare was not idempotent.');

    return compact('drafts','pending','conversions');
}
