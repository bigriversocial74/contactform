<?php
declare(strict_types=1);

function mg_task_agent_shortlist_remove_if_unselected(
    PDO $pdo,
    int $userId,
    int $agentId,
    string $shortlistId
): void {
    mg_task_agent_shortlist_require_schema($pdo);
    $shortlistId = mg_task_agent_shortlist_text($shortlistId, 80);
    if ($shortlistId === '') throw new InvalidArgumentException('A shortlist item is required.');

    $stmt = $pdo->prepare(
        "SELECT status,plan_id FROM multi_agent_shortlist_items "
        . "WHERE public_id=? AND owner_user_id=? AND agent_id=? AND status IN ('active','selected') LIMIT 1"
    );
    $stmt->execute([$shortlistId, $userId, $agentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Shortlist item not found.');
    if (($row['status'] ?? '') === 'selected' || !empty($row['plan_id'])) {
        throw new RuntimeException('Remove this product from its gift plan before removing it from the shortlist.');
    }

    mg_task_agent_shortlist_remove($pdo, $userId, $agentId, $shortlistId);
}
