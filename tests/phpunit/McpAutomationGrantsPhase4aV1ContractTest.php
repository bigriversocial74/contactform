<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class McpAutomationGrantsPhase4aV1ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function authority(): string
    {
        $files = array_merge(
            [$this->root . '/includes/mcp-automations.php'],
            glob($this->root . '/includes/mcp-automations/*.php') ?: []
        );
        return implode("\n", array_map(static fn(string $file): string => (string)file_get_contents($file), $files));
    }

    public function testReleaseManifestLocksBuildOnlyBoundary(): void
    {
        $release = require $this->root . '/config/mcp_automation_grants_phase4a_release.php';
        self::assertSame('microgifter_mcp_automation_grants_phase4a_v1', $release['release_key']);
        self::assertSame('owner_grant_configuration_only', $release['operation_ceiling']);
        self::assertFalse($release['runtime_execution_enabled']);
        self::assertSame([], $release['new_migrations']);
        self::assertSame(['manual'], $release['allowed_trigger_types']);
        self::assertSame('always', $release['approval_policy']);
        self::assertContains('queue_or_worker_execution', $release['prohibited']);
        self::assertContains('production_key_generation', $release['prohibited']);
    }

    public function testUsesExistingAutomationFoundationWithoutNewSql(): void
    {
        self::assertFileExists($this->root . '/database/20260720_microgifter_mcp_automation_foundation_v1.sql');
        self::assertFileDoesNotExist($this->root . '/database/20260721_mcp_automation_grants_phase4a_v1.sql');
        $sql = (string)file_get_contents($this->root . '/database/20260720_microgifter_mcp_automation_foundation_v1.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS mcp_automation_grants', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS mcp_automation_runs', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS mcp_action_receipts', $sql);
    }

    public function testGrantCreationIsOwnerScopedAndAllowlisted(): void
    {
        $service = $this->authority();
        self::assertStringContainsString('function mg_mcp_automation_playbook_catalog', $service);
        self::assertStringContainsString('An unrecognized playbook was selected.', $service);
        self::assertStringContainsString('WHERE c.public_id=? AND c.user_id=?', $service);
        self::assertStringContainsString('WHERE g.public_id=? AND g.authorizing_user_id=?', $service);
        self::assertStringContainsString("json_encode(['manual']", $service);
        self::assertStringContainsString("'always'", $service);
    }

    public function testActivationRevalidatesCanonicalAuthority(): void
    {
        $service = $this->authority();
        self::assertStringContainsString('MCP_AUTOMATION_CONNECTION_NOT_ACTIVE', $service);
        self::assertStringContainsString('MCP_AUTOMATION_OPERATION_CEILING', $service);
        self::assertStringContainsString('MCP_AUTOMATION_SCOPE_REVOKED', $service);
        self::assertStringContainsString('MCP_AUTOMATION_WORKSPACE_ACCESS_REVOKED', $service);
        self::assertStringContainsString('merchant_team_members', $service);
    }

    public function testPolicyEvaluatorChecksLimitsAndTargets(): void
    {
        $service = $this->authority();
        self::assertStringContainsString('function mg_mcp_automation_authorize_grant_action', $service);
        self::assertStringContainsString('MCP_AUTOMATION_DAILY_AMOUNT_LIMIT', $service);
        self::assertStringContainsString('MCP_AUTOMATION_LIFETIME_AMOUNT_LIMIT', $service);
        self::assertStringContainsString('MCP_AUTOMATION_DAILY_QUANTITY_LIMIT', $service);
        self::assertStringContainsString('MCP_AUTOMATION_LIFETIME_QUANTITY_LIMIT', $service);
        self::assertStringContainsString('MCP_AUTOMATION_FREQUENCY_LIMIT', $service);
        self::assertStringContainsString('MCP_AUTOMATION_CONCURRENCY_LIMIT', $service);
        self::assertStringContainsString('MCP_AUTOMATION_TARGET_DENIED', $service);
        self::assertStringContainsString("'execution_enabled' => false", $service);
    }

    public function testPauseAndRevokePreventFutureExecution(): void
    {
        $service = $this->authority();
        self::assertStringContainsString('revocation_version=revocation_version+1', $service);
        self::assertStringContainsString('cancellation_requested_at=COALESCE', $service);
        self::assertStringContainsString("UPDATE mcp_automations SET status='revoked'", $service);
        self::assertStringContainsString('INSERT INTO mcp_security_events', $service);
    }

    public function testOwnerPageStatesRuntimeIsDisabled(): void
    {
        $page = (string)file_get_contents($this->root . '/account-agent-automations.php')
            . "\n" . (string)file_get_contents($this->root . '/includes/mcp-automations/account-page-view.php');
        self::assertStringContainsString('Build-only deployment state', $page);
        self::assertStringContainsString('Runtime execution remains disabled in Phase 4A', $page);
        self::assertStringContainsString('No scheduler or execution path exists in Phase 4A', $page);
        self::assertStringContainsString('mg_verify_csrf', $page);
    }
}
