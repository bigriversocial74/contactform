<?php
declare(strict_types=1);
function phase3c_final_verification(array $state): void
{
    $pdo = $state['pdo'];
    $fixture = (array)$state['fixture'];
    $prepared = (array)$state['prepared'];
    $created = (array)$state['created'];
    $context = (array)$fixture['context'];
    phase3c_assert(count(mg_mcp_native_status_list_for_owner($pdo, (int)$fixture['userId'])) === 4, 'Owner handoff list is incomplete.');
    $owner = mg_mcp_native_status_for_owner($pdo, (array)$fixture['user'], (string)$created['gift']['id']);
    phase3c_assert($owner['observation']['changed'] === false, 'Owner refresh duplicated a receipt.');
    $revoked = $context;
    $revoked['scopes'] = array_values(array_filter((array)$context['scopes'], static fn(string $scope): bool => $scope !== 'gift:draft'));
    $denied = false;
    try { mg_mcp_native_status_for_connection($pdo, $revoked, (string)$prepared['drafts']['gift']['id']); }
    catch (MgMcpDraftException $error) { $denied = $error->draftCode() === 'MCP_DRAFT_SCOPE_DENIED'; }
    phase3c_assert($denied, 'Revoked scope did not block status access.');
    $queues = ['agent'=>(int)$pdo->query('SELECT COUNT(*) FROM agent_workflow_actions')->fetchColumn(),'mcp'=>(int)$pdo->query('SELECT COUNT(*) FROM mcp_automation_actions')->fetchColumn()];
    phase3c_assert($queues === (array)$fixture['queueCountsBefore'], 'Phase 3C changed an execution queue.');
}
