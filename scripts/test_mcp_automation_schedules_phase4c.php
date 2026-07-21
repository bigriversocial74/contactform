<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once __DIR__ . '/mcp-phase3b/bootstrap.php';
require_once dirname(__DIR__) . '/includes/mcp-automations.php';

function phase4c_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = mg_db();
    $fixture = phase3b_build_fixture($pdo);
    $user = (array)$fixture['user'];
    $context = (array)$fixture['context'];
    $productId = (string)$fixture['productPublicId'];

    $receiptBefore = (int)$pdo->query('SELECT COUNT(*) FROM mcp_action_receipts')->fetchColumn();
    $agentQueueBefore = (int)$pdo->query('SELECT COUNT(*) FROM agent_workflow_actions')->fetchColumn();

    $grant = mg_mcp_automation_create_grant($pdo, $user, [
        'connection_id' => (string)$context['connection_public_id'],
        'label' => 'Phase 4C scheduled simulation grant',
        'reason' => 'Verify fixed and recurring owner-evaluated scheduled simulations without runtime execution.',
        'playbooks' => ['gift_draft_preparation'],
        'risk_ceiling' => 'low',
        'expires_days' => 30,
        'currency' => 'USD',
        'minimum_frequency_seconds' => 3600,
        'per_run_quantity_limit' => 2,
        'daily_quantity_limit' => 10,
        'lifetime_quantity_limit' => 50,
        'allowed_product_ids' => $productId,
        'allow_existing_contacts_only' => '1',
    ]);
    phase4c_assert((string)$grant['status'] === 'draft', 'Grant was not created as draft.');

    $authority = mg_mcp_automation_update_schedule_authority($pdo, $user, (string)$grant['id'], [
        'fixed_schedule' => '1',
        'recurring_schedule' => '1',
        'reason' => 'Authorize Phase 4C clean-database schedule simulations.',
    ]);
    phase4c_assert(in_array('fixed_schedule', $authority['allowed_trigger_types'], true), 'Fixed schedule authority missing.');
    phase4c_assert(in_array('recurring_schedule', $authority['allowed_trigger_types'], true), 'Recurring schedule authority missing.');

    $grant = mg_mcp_automation_transition_grant($pdo, $user, (string)$grant['id'], 'activate', 'Activate Phase 4C clean-database grant.');
    phase4c_assert((string)$grant['status'] === 'active', 'Grant was not activated.');

    $definition = mg_mcp_automation_create_definition($pdo, $user, [
        'grant_id' => (string)$grant['id'],
        'playbook_key' => 'gift_draft_preparation',
        'name' => 'Phase 4C gift schedule simulation',
        'description' => 'Clean-database fixed and recurring simulation definition.',
        'objective' => 'Evaluate bounded gift draft preparation on owner-operated due schedules without executing commands.',
        'risk_level' => 'low',
        'proposed_amount_cents' => 0,
        'proposed_quantity' => 1,
        'product_id' => $productId,
        'recipient_is_existing_contact' => '1',
    ]);
    $definition = mg_mcp_automation_transition_definition($pdo, $user, (string)$definition['id'], 'activate', 'Activate Phase 4C simulation definition.');
    phase4c_assert((string)$definition['status'] === 'active', 'Definition was not activated.');

    $firstDueLocal = gmdate('Y-m-d\TH:i', time() + 600);
    $recurring = mg_mcp_automation_configure_schedule($pdo, $user, (string)$definition['id'], [
        'trigger_type' => 'recurring_schedule',
        'timezone' => 'UTC',
        'first_due_at' => $firstDueLocal,
        'interval_seconds' => 3600,
    ]);
    phase4c_assert((string)$recurring['trigger_type'] === 'recurring_schedule', 'Recurring schedule was not configured.');

    $pdo->prepare("UPDATE mcp_automation_triggers SET next_due_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE public_id=?")
        ->execute([(string)$recurring['trigger_public_id']]);
    $evaluated = mg_mcp_automation_evaluate_due_schedules($pdo, $user, 10);
    phase4c_assert(count($evaluated['completed']) === 1, 'Recurring due simulation did not complete.');
    phase4c_assert(count($evaluated['failed']) === 0, 'Recurring due simulation failed.');
    $recurringRunId = (string)$evaluated['completed'][0]['run_public_id'];

    $recurringStmt = $pdo->prepare('SELECT status,next_due_at,fire_count FROM mcp_automation_triggers WHERE public_id=? LIMIT 1');
    $recurringStmt->execute([(string)$recurring['trigger_public_id']]);
    $recurringRow = $recurringStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    phase4c_assert((string)($recurringRow['status'] ?? '') === 'active', 'Recurring trigger did not remain active.');
    phase4c_assert((int)($recurringRow['fire_count'] ?? 0) === 1, 'Recurring trigger fire count was not incremented.');
    phase4c_assert(strtotime((string)($recurringRow['next_due_at'] ?? '') . ' UTC') > time(), 'Recurring trigger did not advance to a future due time.');

    $fixed = mg_mcp_automation_configure_schedule($pdo, $user, (string)$definition['id'], [
        'trigger_type' => 'fixed_schedule',
        'timezone' => 'UTC',
        'first_due_at' => $firstDueLocal,
    ]);
    phase4c_assert((string)$fixed['trigger_type'] === 'fixed_schedule', 'Fixed schedule was not configured.');
    $pdo->prepare("UPDATE mcp_automation_triggers SET next_due_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE public_id=?")
        ->execute([(string)$fixed['trigger_public_id']]);
    $evaluated = mg_mcp_automation_evaluate_due_schedules($pdo, $user, 10);
    phase4c_assert(count($evaluated['completed']) === 1, 'Fixed due simulation did not complete.');
    phase4c_assert(count($evaluated['failed']) === 0, 'Fixed due simulation failed.');
    $fixedRunId = (string)$evaluated['completed'][0]['run_public_id'];

    $fixedStmt = $pdo->prepare('SELECT status,next_due_at,fire_count FROM mcp_automation_triggers WHERE public_id=? LIMIT 1');
    $fixedStmt->execute([(string)$fixed['trigger_public_id']]);
    $fixedRow = $fixedStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    phase4c_assert((string)($fixedRow['status'] ?? '') === 'expired', 'Fixed trigger did not expire.');
    phase4c_assert($fixedRow['next_due_at'] === null, 'Fixed trigger retained a due time.');
    phase4c_assert((int)($fixedRow['fire_count'] ?? 0) === 1, 'Fixed trigger fire count was not incremented.');

    $runStmt = $pdo->prepare(
        "SELECT r.public_id,r.status,r.output_summary_json,COUNT(aa.id) AS action_count
         FROM mcp_automation_runs r
         LEFT JOIN mcp_automation_actions aa ON aa.run_id=r.id
         WHERE r.public_id IN (?,?) GROUP BY r.id ORDER BY r.id"
    );
    $runStmt->execute([$recurringRunId, $fixedRunId]);
    $runs = $runStmt->fetchAll(PDO::FETCH_ASSOC);
    phase4c_assert(count($runs) === 2, 'Expected two scheduled simulation runs.');
    foreach ($runs as $run) {
        $summary = mg_mcp_automation_json_object($run['output_summary_json']);
        phase4c_assert((string)$run['status'] === 'succeeded', 'Scheduled simulation run did not succeed.');
        phase4c_assert((int)$run['action_count'] === 3, 'Expected three proposed actions per scheduled simulation.');
        phase4c_assert(($summary['mode'] ?? null) === 'scheduled_simulation_only', 'Run mode was not scheduled simulation only.');
        phase4c_assert(($summary['scheduler_enabled'] ?? true) === false, 'Run summary enabled a scheduler.');
        phase4c_assert(($summary['execution_attempted'] ?? true) === false, 'Run summary indicates execution.');
        phase4c_assert((int)($summary['action_receipts_created'] ?? -1) === 0, 'Run summary indicates action receipts.');
    }

    $receiptAfter = (int)$pdo->query('SELECT COUNT(*) FROM mcp_action_receipts')->fetchColumn();
    $agentQueueAfter = (int)$pdo->query('SELECT COUNT(*) FROM agent_workflow_actions')->fetchColumn();
    phase4c_assert($receiptAfter === $receiptBefore, 'Scheduled simulation created an action receipt.');
    phase4c_assert($agentQueueAfter === $agentQueueBefore, 'Scheduled simulation changed the canonical agent execution queue.');

    echo "MCP automation schedules Phase 4C clean-database lifecycle passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'MCP Phase 4C test failed: ' . $error->getMessage() . "\n");
    exit(1);
}
