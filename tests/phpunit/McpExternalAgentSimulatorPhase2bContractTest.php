<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class McpExternalAgentSimulatorPhase2bContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testSimulatorIsLoopbackOnlyAndRefusesProductionMode(): void
    {
        $simulator = file_get_contents($this->root . '/services/mcp/scripts/simulate-external-agent.mjs');
        self::assertIsString($simulator);
        self::assertStringContainsString('127.0.0.1', $simulator);
        self::assertStringContainsString('ephemeral port', file_get_contents($this->root . '/docs/MICROGIFTER_MCP_EXTERNAL_AGENT_SIMULATOR_PHASE2B.md'));
        self::assertStringContainsString('refuses to run in production mode', $simulator);
        self::assertStringContainsString('createInternalMcpApp', $simulator);
        self::assertStringNotContainsString('0.0.0.0', $simulator);
    }

    public function testSimulatorExercisesReadOnlyProtocolAndRevocationLifecycle(): void
    {
        $simulator = (string)file_get_contents($this->root . '/services/mcp/scripts/simulate-external-agent.mjs');
        self::assertStringContainsString('2025-11-25', $simulator);
        self::assertStringContainsString('tools/list', $simulator);
        self::assertStringContainsString('microgifter.catalog.search', $simulator);
        self::assertStringContainsString('rotateRefreshToken', $simulator);
        self::assertStringContainsString('replay detected', $simulator);
        self::assertStringContainsString('revokeByAccessToken', $simulator);
        self::assertStringNotContainsString('campaign.create', $simulator);
        self::assertStringNotContainsString('purchase.create', $simulator);
    }

    public function testReadinessReportDoesNotExposeSecretValues(): void
    {
        $readiness = (string)file_get_contents($this->root . '/services/mcp/scripts/external-agent-readiness.mjs');
        self::assertStringContainsString('value not displayed', $readiness);
        self::assertStringContainsString('production_ready: false', $readiness);
        self::assertStringContainsString('--strict', $readiness);
        self::assertStringNotContainsString('console.log(process.env.MICROGIFTER_MCP_BRIDGE_SECRET)', $readiness);
    }

    public function testInstallationReferenceIncludesPhase2BAndNoNewSql(): void
    {
        $install = (string)file_get_contents($this->root . '/docs/MICROGIFTER_MCP_INSTALLATION_AND_ACTIVATION.md');
        $phase2b = (string)file_get_contents($this->root . '/docs/MICROGIFTER_MCP_EXTERNAL_AGENT_SIMULATOR_PHASE2B.md');
        self::assertStringContainsString('MICROGIFTER_MCP_EXTERNAL_AGENT_SIMULATOR_PHASE2B.md', $install);
        self::assertStringContainsString('node scripts/simulate-external-agent.mjs', $install);
        self::assertStringContainsString('No new SQL is required for Phase 2B', $phase2b);
        self::assertFileDoesNotExist($this->root . '/database/20260720_mcp_external_agent_simulator_phase2b.sql');
    }

    public function testPhase2AAuthorityTestRemainsPartOfTheContract(): void
    {
        self::assertFileExists($this->root . '/scripts/test_mcp_external_agent_authorization_phase2a.php');
        self::assertFileExists($this->root . '/database/20260720_mcp_external_agent_authorization_phase2a_v1.sql');
    }
}
