<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found.'); }
require_once __DIR__ . '/mcp-phase3b/bootstrap.php';
require_once dirname(__DIR__) . '/includes/mcp-automations.php';

function phase4d_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
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
        'label' => 'Phase 4D operations grant',
        'reason' => 'Verify owner operations and emergency controls without runtime execution.',
        'playbooks' => ['gift_draft_preparation'],
        'risk_ceiling' => 'low',
        'expires_days' => 30,
        'currency' => 'USD',
        'minimum_frequency_seconds' => 3600,
        'per_run_quantity_limit' => 2,
        'allowed_product_ids' => $productId,
        'allow_existing_contacts_only' => '1',
    ]);
    mg_mcp_automation_update_schedule_authority($pdo, $user, (string)$grant['id'], [
        'fixed_schedule' => '1',
        'reason' => 'Authorize a Phase 4D control-plane schedule fixture.',
    ]);
    $grant = mg_mcp_automation_transition_grant($pdo, $user, (string)$grant['id'], 'activate', 'Activate the Phase 4D control fixture.');

    $definition = mg_mcp_automation_create_definition($pdo, $user, [
        'grant_id' => (string)$grant['id'],
        'playbook_key' => 'gift_draft_preparation',
        'name' => 'Phase 4D emergency control simulation',
        'description' => 'Clean-database operations control fixture.',
        'objective' => 'Verify emergency pause and cancellation evidence without executing commands.',
        'risk_level' => 'low',
        'proposed_amount_cents' => 0,
        'proposed_quantity' => 1,
        'product_id' => $productId,
        'recipient_is_existing_contact' => '1',
    ]);
    $definition = mg_mcp_automation_transition_definition($pdo, $user, (string)$definition['id'], 'activate', 'Activate Phase 4D operations definition.');

    $schedule = mg_mcp_automation_configure_schedule($pdo, $user, (string)$definition['id'], [
        'trigger_type' => 'fixed_schedule',
        'timezone' => 'UTC',
        'first_due_at' => gmdate('Y-m-d\TH:i', time() + 600),
    ]);

    $ids = $pdo->prepare('SELECT a.id AS automation_id,a.grant_id,t.id AS trigger_id FROM mcp_automations a INNER JOIN mcp_automation_triggers t ON t.automation_id=a.id WHERE a.public_id=? AND t.public_id=? LIMIT 1');
    $ids->execute([(string)$definition['id'], (string)$schedule['trigger_public_id']]);
    $row = $ids->fetch(PDO::FETCH_ASSOC) ?: [];
    phase4d_assert((int)($row['automation_id'] ?? 0) > 0, 'Automation fixture was not found.');

    $queuedRunId = mg_public_uuid();
    $pdo->prepare("INSERT INTO mcp_automation_runs
        (public_id,automation_id,grant_id,trigger_id,status,idempotency_key,input_fingerprint,scheduled_at,queued_at,attempt,maximum_attempts,created_at,updated_at)
        VALUES (?,?,?,?,'queued',?,?,NOW(),NOW(),1,1,NOW(),NOW())")->execute([
        $queuedRunId,
        (int)$row['automation_id'],
        (int)$row['grant_id'],
        (int)$row['trigger_id'],
        'phase4d-queued-' . $queuedRunId,
        hash('sha256', $queuedRunId),
    ]);

    $before = mg_mcp_automation_owner_operations_snapshot($pdo, (int)$user['id']);
    phase4d_assert((int)($before['counts']['grants']['active'] ?? 0) === 1, 'Operations snapshot did not show the active grant.');
    phase4d_assert((int)($before['counts']['runs']['queued'] ?? 0) === 1, 'Operations snapshot did not show the queued run.');
    phase4d_assert((int)$before['counts']['receipts'] === 0, 'Operations snapshot found unexpected receipts.');

    $paused = mg_mcp_automation_emergency_pause_all($pdo, $user, 'Clean-database Phase 4D emergency pause validation.');
    phase4d_assert((int)$paused['grants_paused'] === 1, 'Emergency pause did not pause the active grant.');
    phase4d_assert((int)$paused['definitions_paused'] === 1, 'Emergency pause did not pause the active definition.');
    phase4d_assert((int)$paused['triggers_paused'] >= 1, 'Emergency pause did not pause the active trigger.');
    phase4d_assert((int)$paused['runs_cancel_requested'] === 1, 'Emergency pause did not request queued-run cancellation.');

    $state = $pdo->prepare('SELECT g.status AS grant_status,g.revocation_version,a.status AS automation_status,a.next_run_at,t.status AS trigger_status,t.next_due_at,r.cancellation_requested_at
        FROM mcp_automation_grants g
        INNER JOIN mcp_automations a ON a.grant_id=g.id
        INNER JOIN mcp_automation_triggers t ON t.automation_id=a.id AND t.public_id=?
        INNER JOIN mcp_automation_runs r ON r.grant_id=g.id AND r.public_id=?
        WHERE g.public_id=? LIMIT 1');
    $state->execute([(string)$schedule['trigger_public_id'], $queuedRunId, (string)$grant['id']]);
    $current = $state->fetch(PDO::FETCH_ASSOC) ?: [];
    phase4d_assert((string)($current['grant_status'] ?? '') === 'paused', 'Grant did not remain paused.');
    phase4d_assert((int)($current['revocation_version'] ?? 0) >= 1, 'Grant revocation version was not incremented.');
    phase4d_assert((string)($current['automation_status'] ?? '') === 'paused', 'Definition did not remain paused.');
    phase4d_assert($current['next_run_at'] === null, 'Definition retained a next run time.');
    phase4d_assert((string)($current['trigger_status'] ?? '') === 'paused', 'Trigger did not remain paused.');
    phase4d_assert($current['next_due_at'] === null, 'Trigger retained a due time.');
    phase4d_assert($current['cancellation_requested_at'] !== null, 'Queued run lacks a cancellation request.');

    $cancel = mg_mcp_automation_request_run_cancellation($pdo, $user, $queuedRunId, 'Confirm durable Phase 4D cancellation evidence.');
    phase4d_assert($cancel['cancellation_requested'] === true, 'Run cancellation control did not recognize the mutable run.');

    $after = mg_mcp_automation_owner_operations_snapshot($pdo, (int)$user['id']);
    phase4d_assert((int)($after['counts']['grants']['active'] ?? 0) === 0, 'Active grant remained after emergency pause.');
    phase4d_assert((int)($after['counts']['grants']['paused'] ?? 0) === 1, 'Paused grant was not visible after emergency pause.');
    phase4d_assert((int)$after['counts']['cancellation_requests'] >= 1, 'Cancellation request was not visible in operations snapshot.');
    phase4d_assert((int)$after['counts']['receipts'] === 0, 'Phase 4D created an action receipt.');

    $receiptAfter = (int)$pdo->query('SELECT COUNT(*) FROM mcp_action_receipts')->fetchColumn();
    $agentQueueAfter = (int)$pdo->query('SELECT COUNT(*) FROM agent_workflow_actions')->fetchColumn();
    phase4d_assert($receiptAfter === $receiptBefore, 'Phase 4D changed action receipts.');
    phase4d_assert($agentQueueAfter === $agentQueueBefore, 'Phase 4D changed the canonical agent execution queue.');
    echo "MCP automation operations Phase 4D clean-database lifecycle passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'MCP Phase 4D test failed: ' . $error->getMessage() . "\n");
    exit(1);
}
