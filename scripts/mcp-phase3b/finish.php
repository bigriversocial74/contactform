<?php
declare(strict_types=1);

function phase3b_finish_conversions(PDO $pdo, array $fixture, array $state): void
{
    $userId = (int)$fixture['userId'];
    $user = (array)$fixture['user'];
    $queueCountsBefore = (array)$fixture['queueCountsBefore'];
    $drafts = (array)$state['drafts'];
    $pending = (array)$state['pending'];
    $conversions = (array)$state['conversions'];

    $permissionDenied = false;
    try { mg_mcp_conversion_create_native($pdo, ['id' => $userId, 'roles' => [], 'permissions' => []], (string)$conversions['gift']['id']); }
    catch (MgMcpDraftException $error) { $permissionDenied = $error->draftCode() === 'MCP_CONVERSION_PERMISSION_DENIED'; }
    phase3b_assert($permissionDenied, 'Live permission revocation did not block native creation.');

    foreach ($conversions as $type => $conversion) {
        $conversions[$type] = mg_mcp_conversion_create_native($pdo, $user, (string)$conversion['id']);
        phase3b_assert((string)$conversions[$type]['status'] === 'created', ucfirst($type) . ' native draft was not created.');
        phase3b_assert($conversions[$type]['execution']['enabled'] === false, ucfirst($type) . ' native projection enabled execution.');
    }
    $duplicateCreate = mg_mcp_conversion_create_native($pdo, $user, (string)$conversions['gift']['id']);
    phase3b_assert($duplicateCreate['duplicate'] === true && $duplicateCreate['native_public_id'] === $conversions['gift']['native_public_id'], 'Native creation was not idempotent.');

    phase3b_verify_native_drafts($pdo, $userId, $conversions);

    $opened = mg_mcp_conversion_mark_opened($pdo, $user, (string)$conversions['gift']['id']);
    phase3b_assert((string)$opened['status'] === 'opened' && str_starts_with((string)$opened['native_url'], '/'), 'Owner-only native redirect evidence failed.');

    $pendingApproved = mg_mcp_draft_owner_decide($pdo, $userId, (string)$pending['id'], 'approve', 'Approve cancellation test.');
    $cancelConversion = mg_mcp_conversion_prepare($pdo, $user, (string)$pendingApproved['id']);
    $canceled = mg_mcp_conversion_cancel($pdo, $user, (string)$cancelConversion['id']);
    phase3b_assert((string)$canceled['status'] === 'canceled' && $canceled['native_public_id'] === null, 'Prepared conversion cancellation failed.');
    $reprepared = mg_mcp_conversion_prepare($pdo, $user, (string)$pendingApproved['id']);
    phase3b_assert((string)$reprepared['status'] === 'prepared' && $reprepared['id'] === $cancelConversion['id'], 'Canceled conversion could not be prepared again safely.');

    $attached = mg_mcp_conversion_attach_to_drafts($pdo, $userId, array_values($drafts));
    phase3b_assert(count(array_filter($attached, static fn(array $draft): bool => is_array($draft['conversion'] ?? null))) === 4, 'Conversion projections were not attached to owner drafts.');
    $events = mg_mcp_conversion_events_for_owner($pdo, $userId, (string)$conversions['gift']['id']);
    phase3b_assert(array_column($events, 'type') === ['prepared','duplicate_returned','native_created','duplicate_returned','opened'], 'Gift conversion event history is incomplete.');

    $queueCountsAfter = [
        'agent' => (int)$pdo->query('SELECT COUNT(*) FROM agent_workflow_actions')->fetchColumn(),
        'mcp' => (int)$pdo->query('SELECT COUNT(*) FROM mcp_automation_actions')->fetchColumn(),
    ];
    phase3b_assert($queueCountsAfter === $queueCountsBefore, 'Phase 3B inserted an action into an execution queue.');
}
