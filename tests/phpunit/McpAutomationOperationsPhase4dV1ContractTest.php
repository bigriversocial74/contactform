<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class McpAutomationOperationsPhase4dV1ContractTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = dirname(__DIR__, 2); }
    public function testReleaseManifestRemainsExecutionDisabled(): void
    {
        $release = require $this->root . '/config/mcp_automation_operations_phase4d_release.php';
        self::assertSame('microgifter_mcp_automation_operations_phase4d_v1', $release['release_key']);
        self::assertSame('owner_operations_and_emergency_control_only', $release['operation_ceiling']);
        self::assertFalse($release['runtime_execution_enabled']);
        self::assertFalse($release['background_scheduler_enabled']);
        self::assertFalse($release['worker_enabled']);
        self::assertFalse($release['public_mcp_transport_enabled']);
        self::assertSame([], $release['new_migrations']);
        self::assertSame(0, $release['action_receipts_expected']);
    }
    public function testEmergencyControlsAreOwnerScopedAndFailClosed(): void
    {
        $service = (string)file_get_contents($this->root . '/includes/mcp-automations/operations.php');
        self::assertStringContainsString('function mg_mcp_automation_emergency_pause_all', $service);
        self::assertStringContainsString('function mg_mcp_automation_pause_connection', $service);
        self::assertStringContainsString('authorizing_user_id=?', $service);
        self::assertStringContainsString('revocation_version=revocation_version+1', $service);
        self::assertStringContainsString('next_due_at=NULL', $service);
        self::assertStringContainsString('cancellation_requested_at=COALESCE', $service);
        self::assertStringNotContainsString('INSERT INTO mcp_action_receipts', $service);
    }
    public function testOperationsPageRequiresCsrfAndExplicitConfirmation(): void
    {
        $page = (string)file_get_contents($this->root . '/account-agent-automation-operations.php') . "\n" . (string)file_get_contents($this->root . '/includes/mcp-automations/operations-page-view.php');
        self::assertStringContainsString('mg_verify_csrf', $page);
        self::assertStringContainsString('MCP_AUTOMATION_CONTROL_CONFIRMATION_REQUIRED', $page);
        self::assertStringContainsString('Emergency pause all automation', $page);
        self::assertStringContainsString('No Node.js runtime', $page);
    }
    public function testRunCancellationPreservesProposedActionEvidence(): void
    {
        $service = (string)file_get_contents($this->root . '/includes/mcp-automations/operations.php');
        self::assertStringContainsString('function mg_mcp_automation_request_run_cancellation', $service);
        self::assertStringNotContainsString("UPDATE mcp_automation_actions SET status='cancelled'", $service);
        self::assertStringContainsString('MCP_AUTOMATION_MUTABLE_RUN_STATUSES', $service);
    }
}
