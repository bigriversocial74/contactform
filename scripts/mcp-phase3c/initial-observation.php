<?php
declare(strict_types=1);
function phase3c_initial_observation(array $state): void
{
    $pdo = $state['pdo'];
    $context = (array)$state['fixture']['context'];
    foreach ((array)$state['prepared']['drafts'] as $type=>$draft) {
        $first = mg_mcp_native_status_for_connection($pdo, $context, (string)$draft['id']);
        phase3c_assert((string)$first['native']['state_class'] === 'draft', ucfirst($type) . ' initial state was not draft.');
        phase3c_assert($first['execution']['enabled'] === false, ucfirst($type) . ' enabled execution.');
        phase3c_assert($first['observation']['changed'] === true, ucfirst($type) . ' first receipt was not created.');
        $same = mg_mcp_native_status_for_connection($pdo, $context, (string)$draft['id']);
        phase3c_assert($same['observation']['changed'] === false, ucfirst($type) . ' duplicate receipt was created.');
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM events WHERE event_type=?');
    $stmt->execute([mg_mcp_native_status_event_type()]);
    phase3c_assert((int)$stmt->fetchColumn() === (int)$state['receipt_before'] + 4, 'Initial receipt count is incorrect.');
}
