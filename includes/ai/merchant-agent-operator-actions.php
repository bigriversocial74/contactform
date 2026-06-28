<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-plan-actions.php';

function mg_ai_operator_execute_item(PDO $pdo, int $merchantId, int $actorId, array $item): array
{
    return mg_ai_plan_execute_item($pdo, $merchantId, $actorId, $item);
}
