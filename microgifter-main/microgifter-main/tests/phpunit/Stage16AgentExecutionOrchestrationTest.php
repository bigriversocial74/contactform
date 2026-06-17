<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage16AgentExecutionOrchestrationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testSchemaDefinesStrategiesRunsActionsApprovalsAndEvents(): void
    {
        $sql = $this->read('database/stage_16_agent_execution_orchestration.sql');
        foreach (['agent_strategies','agent_workflow_runs','agent_workflow_actions','agent_approval_requests','agent_execution_events'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        self::assertStringContainsString('uq_agent_runs_idempotency', $sql);
        self::assertStringContainsString('uq_agent_actions_idempotency', $sql);
    }

    public function testActionCatalogIsExplicitlyAllowlisted(): void
    {
        $source = $this->read('api/agents/_execution.php');
        foreach (['acknowledge_demand_signal','resolve_demand_signal','pause_distribution_program','resume_distribution_program','create_operational_alert'] as $action) {
            self::assertStringContainsString("'{$action}'", $source);
        }
        self::assertStringContainsString('Strategy contains unsupported actions.', $source);
        self::assertStringContainsString('Planned action is not allowed by strategy.', $source);
    }

    public function testWorkflowRunsAreOwnerScopedAndIdempotent(): void
    {
        $source = $this->read('api/agents/_execution.php');
        self::assertStringContainsString('owner_user_id=? AND idempotency_key=?', $source);
        self::assertStringContainsString('Agent strategy is not active.', $source);
        self::assertStringContainsString('run_queued', $source);
    }

    public function testHighRiskActionsRequireApproval(): void
    {
        $source = $this->read('api/agents/_execution.php');
        self::assertStringContainsString("in_array(\$risk,['high','critical'],true)", $source);
        self::assertStringContainsString('agent_approval_requests', $source);
        self::assertStringContainsString("'approval_pending'", $source);
    }

    public function testApprovalEndpointEnforcesOwnershipAndRowLocking(): void
    {
        $endpoint = $this->read('api/agents/approvals.php');
        $service = $this->read('api/agents/_workflow.php');
        self::assertStringContainsString("require_once __DIR__ . '/_workflow.php'", $endpoint);
        self::assertStringContainsString('mg_agent_decide_approval(', $endpoint);
        self::assertStringContainsString('ar.owner_user_id=?', $service);
        self::assertStringContainsString('FOR UPDATE', $service);
        self::assertStringContainsString("['approve','reject']", $service);
        self::assertStringContainsString("'approval_'.$targetStatus", $service);
    }

    public function testExecutionUsesCanonicalDemandDistributionAndCommunicationsAuthorities(): void
    {
        $source = $this->read('api/agents/_execution.php');
        self::assertStringContainsString('demand_agent_signals', $source);
        self::assertStringContainsString('distribution_programs', $source);
        self::assertStringContainsString('mg_create_operational_alert(', $source);
        self::assertStringNotContainsString('microgift_instances SET', $source);
        self::assertStringNotContainsString('wallet_accounts SET', $source);
        self::assertStringNotContainsString('ledger_entries', $source);
    }

    public function testProcessorExecutesOnlyApprovedActions(): void
    {
        $worker = $this->read('scripts/process_agent_actions.php');
        $service = $this->read('api/agents/_workflow.php');
        self::assertStringContainsString('mg_agent_process_next_action(', $worker);
        self::assertStringContainsString("wa.status='approved'", $service);
        self::assertStringContainsString('FOR UPDATE SKIP LOCKED', $service);
        self::assertStringContainsString('mg_agent_execute_action(', $service);
        self::assertStringContainsString('action_execution_completed', $service);
        self::assertStringContainsString('action_execution_failed', $service);
        self::assertStringContainsString('ROLLBACK TO SAVEPOINT agent_action_execution', $service);
    }

    public function testStrategyLifecycleIsExplicit(): void
    {
        $source = $this->read('api/agents/strategy-state.php');
        foreach (['activate','pause','retire','restore'] as $action) {
            self::assertStringContainsString("'{$action}'", $source);
        }
        self::assertStringContainsString('Strategy cannot perform this transition.', $source);
    }
}
