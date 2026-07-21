<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once __DIR__ . '/mcp-phase3b/bootstrap.php';
require_once dirname(__DIR__) . '/includes/mcp-automations.php';

function phase4b_assert(bool $condition, string $message): void
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
        'label' => 'Phase 4B gift simulation grant',
        'reason' => 'Verify grant-bound automation definitions and simulation-only run evidence.',
        'playbooks' => ['gift_draft_preparation'],
        'risk_ceiling' => 'low',
        'expires_days' => 30,
        'currency' => 'USD',
        'per_run_quantity_limit' => 2,
        'daily_quantity_limit' => 10,
        'lifetime_quantity_limit' => 50,
        'allowed_product_ids' => $productId,
        'allow_existing_contacts_only' => '1',
    ]);
    phase4b_assert((string)$grant['status'] === 'draft', 'Grant was not created as draft.');
    $grant = mg_mcp_automation_transition_grant($pdo, $user, (string)$grant['id'], 'activate', 'Activate Phase 4B clean-database grant.');
    phase4b_assert((string)$grant['status'] === 'active', 'Grant was not activated.');

    $definition = mg_mcp_automation_create_definition($pdo, $user, [
        'grant_id' => (string)$grant['id'],
        'playbook_key' => 'gift_draft_preparation',
        'name' => 'Phase 4B gift simulation',
        'description' => 'Clean-database simulation-only definition.',
        'objective' => 'Evaluate a bounded gift draft preparation request without executing any Microgifter command.',
        'risk_level' => 'low',
        'proposed_amount_cents' => 0,
        'proposed_quantity' => 1,
        'product_id' => $productId,
        'recipient_is_existing_contact' => '1',
    ]);
    phase4b_assert((string)$definition['status'] === 'draft', 'Definition was not created as draft.');
    $definition = mg_mcp_automation_transition_definition($pdo, $user, (string)$definition['id'], 'activate', 'Activate Phase 4B simulation definition.');
    phase4b_assert((string)$definition['status'] === 'active', 'Definition was not activated.');

    $simulation = mg_mcp_automation_run_simulation($pdo, $user, (string)$definition['id']);
    phase4b_assert((string)$simulation['status'] === 'succeeded', 'Simulation did not succeed.');
    phase4b_assert(($simulation['execution_attempted'] ?? true) === false, 'Simulation attempted execution.');
    phase4b_assert(($simulation['external_effect'] ?? true) === false, 'Simulation reported an external effect.');
    phase4b_assert((int)($simulation['action_receipts_created'] ?? -1) === 0, 'Simulation reported an action receipt.');

    $runStmt = $pdo->prepare('SELECT status,output_summary_json FROM mcp_automation_runs WHERE public_id=? LIMIT 1');
    $runStmt->execute([(string)$simulation['id']]);
    $run = $runStmt->fetch(PDO::FETCH_ASSOC);
    phase4b_assert(is_array($run) && (string)$run['status'] === 'succeeded', 'Durable simulation run was not recorded.');
    $summary = mg_mcp_automation_json_object($run['output_summary_json']);
    phase4b_assert(($summary['mode'] ?? null) === 'manual_simulation_only', 'Run is not marked as a manual simulation.');
    phase4b_assert(($summary['execution_attempted'] ?? true) === false, 'Run summary indicates execution.');
    phase4b_assert((int)($summary['action_receipts_created'] ?? -1) === 0, 'Run summary indicates receipts.');

    $actionStmt = $pdo->prepare(
        "SELECT COUNT(*) AS total,
                SUM(aa.status='proposed') AS proposed_total,
                SUM(aa.approval_required=1) AS approval_total
         FROM mcp_automation_actions aa
         INNER JOIN mcp_automation_runs r ON r.id=aa.run_id
         WHERE r.public_id=?"
    );
    $actionStmt->execute([(string)$simulation['id']]);
    $actions = $actionStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    phase4b_assert((int)($actions['total'] ?? 0) === 3, 'Expected three proposed playbook actions.');
    phase4b_assert((int)($actions['proposed_total'] ?? 0) === 3, 'Simulation actions did not remain proposed.');
    phase4b_assert((int)($actions['approval_total'] ?? 0) === 3, 'Simulation actions are not approval-required.');

    $receiptAfter = (int)$pdo->query('SELECT COUNT(*) FROM mcp_action_receipts')->fetchColumn();
    $agentQueueAfter = (int)$pdo->query('SELECT COUNT(*) FROM agent_workflow_actions')->fetchColumn();
    phase4b_assert($receiptAfter === $receiptBefore, 'Simulation created an action receipt.');
    phase4b_assert($agentQueueAfter === $agentQueueBefore, 'Simulation changed the canonical agent execution queue.');

    echo "MCP automation definitions Phase 4B clean-database lifecycle passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'MCP Phase 4B test failed: ' . $error->getMessage() . "\n");
    exit(1);
}
