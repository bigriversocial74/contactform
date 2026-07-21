<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class McpAutomationSchedulesPhase4cV1ContractTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = dirname(__DIR__, 2); }
    private function authority(): string
    {
        $files = array_merge([$this->root . '/includes/mcp-automations.php'], glob($this->root . '/includes/mcp-automations/*.php') ?: []);
        return implode("\n", array_map(static fn(string $file): string => (string)file_get_contents($file), $files));
    }
    public function testReleaseManifestLocksManualDueBoundary(): void
    {
        $release = require $this->root . '/config/mcp_automation_schedules_phase4c_release.php';
        self::assertSame('microgifter_mcp_automation_schedules_phase4c_v1', $release['release_key']);
        self::assertSame('owner_scheduled_simulation_control_only', $release['operation_ceiling']);
        self::assertFalse($release['runtime_execution_enabled']);
        self::assertFalse($release['background_scheduler_enabled']);
        self::assertFalse($release['worker_enabled']);
        self::assertSame([], $release['new_migrations']);
        self::assertSame(['manual','fixed_schedule','recurring_schedule'], $release['allowed_trigger_types']);
        self::assertSame(0, $release['action_receipts_created']);
    }
    public function testScheduleAuthorityAndConfigurationAreOwnerControlled(): void
    {
        $service = $this->authority();
        self::assertStringContainsString('function mg_mcp_automation_update_schedule_authority', $service);
        self::assertStringContainsString('function mg_mcp_automation_configure_schedule', $service);
        self::assertStringContainsString('WHERE a.public_id=? AND a.owner_user_id=? AND g.authorizing_user_id=?', $service);
        self::assertStringContainsString('MCP_AUTOMATION_TRIGGER_DENIED', $service);
        self::assertStringContainsString('MG_MCP_AUTOMATION_SCHEDULE_INTERVALS', $service);
    }
    public function testDueEvaluatorRemainsSimulationOnly(): void
    {
        $service = $this->authority();
        self::assertStringContainsString('function mg_mcp_automation_evaluate_due_schedules', $service);
        self::assertStringContainsString('function mg_mcp_automation_run_scheduled_simulation', $service);
        self::assertStringContainsString("'scheduled_simulation_only'", $service);
        self::assertStringContainsString("'execution_attempted' => false", $service);
        self::assertStringContainsString("'action_receipts_created' => 0", $service);
        self::assertStringNotContainsString('INSERT INTO mcp_action_receipts', (string)file_get_contents($this->root . '/includes/mcp-automations/simulations.php'));
    }
    public function testFixedAndRecurringLifecycleIsExplicit(): void
    {
        $service = $this->authority();
        self::assertStringContainsString('mg_mcp_automation_next_recurring_due', $service);
        self::assertStringContainsString("nextTriggerStatus = 'expired'", $service);
        self::assertStringContainsString('fire_count=fire_count+1', $service);
        self::assertStringContainsString('next_run_at=?', $service);
    }
    public function testOwnerPageStatesNoBackgroundScheduler(): void
    {
        $page = (string)file_get_contents($this->root . '/account-agent-automation-schedules.php')
            . "\n" . (string)file_get_contents($this->root . '/includes/mcp-automations/schedules-page-view.php');
        self::assertStringContainsString('Manual due evaluation only', $page);
        self::assertStringContainsString('No background scheduler exists in Phase 4C', $page);
        self::assertStringContainsString('mg_verify_csrf', $page);
    }
}
