<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
final class McpAutomationDefinitionsPhase4bV1ContractTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = dirname(__DIR__, 2); }
    private function authority(): string
    {
        $files = array_merge([$this->root . '/includes/mcp-automations.php'], glob($this->root . '/includes/mcp-automations/*.php') ?: []);
        return implode("\n", array_map(static fn(string $file): string => (string)file_get_contents($file), $files));
    }
    public function testReleaseBoundary(): void
    {
        $release = require $this->root . '/config/mcp_automation_definitions_phase4b_release.php';
        self::assertSame('owner_definition_and_manual_simulation_only', $release['operation_ceiling']);
        self::assertFalse($release['runtime_execution_enabled']);
        self::assertFalse($release['scheduler_enabled']);
        self::assertFalse($release['worker_enabled']);
        self::assertSame([], $release['new_migrations']);
        self::assertSame(['manual'], $release['allowed_trigger_types']);
        self::assertContains('canonical_command_invocation', $release['prohibited']);
    }
    public function testUsesExistingFoundationWithoutNewSql(): void
    {
        self::assertFileDoesNotExist($this->root . '/database/20260721_mcp_automation_definitions_phase4b_v1.sql');
        $sql = (string)file_get_contents($this->root . '/database/20260720_microgifter_mcp_automation_foundation_v1.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS mcp_automations', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS mcp_automation_triggers', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS mcp_automation_runs', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS mcp_automation_actions', $sql);
    }
    public function testDefinitionsAreOwnerAndGrantScoped(): void
    {
        $service = $this->authority();
        self::assertStringContainsString('function mg_mcp_automation_create_definition', $service);
        self::assertStringContainsString('g.authorizing_user_id=?', $service);
        self::assertStringContainsString('a.owner_user_id=?', $service);
        self::assertStringContainsString('MCP_AUTOMATION_PLAYBOOK_DENIED', $service);
        self::assertStringContainsString("'manual','paused'", $service);
    }
    public function testSimulationRecordsProposalsWithoutExecution(): void
    {
        $service = $this->authority();
        self::assertStringContainsString('function mg_mcp_automation_run_simulation', $service);
        self::assertStringContainsString("'succeeded'", $service);
        self::assertStringContainsString("'proposed',1", $service);
        self::assertStringContainsString("'execution_attempted' => false", $service);
        self::assertStringContainsString("'action_receipts_created' => 0", $service);
        self::assertStringNotContainsString('INSERT INTO mcp_action_receipts', (string)file_get_contents($this->root . '/includes/mcp-automations/simulations.php'));
    }
    public function testLifecycleAndPageRemainManualOnly(): void
    {
        $service = $this->authority();
        $page = (string)file_get_contents($this->root . '/account-agent-automation-definitions.php') . "\n" . (string)file_get_contents($this->root . '/includes/mcp-automations/definitions-page-view.php');
        self::assertStringContainsString('MCP_AUTOMATION_DEFINITION_TRANSITION_DENIED', $service);
        self::assertStringContainsString("trigger_type='manual'", $service);
        self::assertStringContainsString('Simulation-only deployment state', $page);
        self::assertStringContainsString('No scheduler or canonical action path exists in Phase 4B', $page);
        self::assertStringContainsString('mg_verify_csrf', $page);
    }
}
