<?php
declare(strict_types=1);

function mg_task_agent_shortlist_add_without_overwriting_selection(
    PDO $pdo,
    int $userId,
    int $agentId,
    array $input
): array {
    mg_task_agent_shortlist_require_schema($pdo);
    $productPublicId = mg_task_agent_shortlist_text($input['product_id'] ?? '', 80);
    if ($productPublicId === '') throw new InvalidArgumentException('A published product is required.');

    $stmt = $pdo->prepare(
        "SELECT s.public_id FROM multi_agent_shortlist_items s "
        . "INNER JOIN catalog_products cp ON cp.id=s.product_id "
        . "WHERE cp.public_id=? AND s.owner_user_id=? AND s.agent_id=? "
        . "AND s.status='selected' AND s.plan_id IS NOT NULL LIMIT 1"
    );
    $stmt->execute([$productPublicId, $userId, $agentId]);
    $selectedId = (string)($stmt->fetchColumn() ?: '');
    if ($selectedId !== '') {
        foreach (mg_task_agent_shortlist_list($pdo, $userId, $agentId, 50) as $item) {
            if ((string)($item['id'] ?? '') === $selectedId) return $item;
        }
        throw new RuntimeException('This product is already selected for a gift plan.');
    }

    return mg_task_agent_shortlist_add($pdo, $userId, $agentId, $input);
}

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
