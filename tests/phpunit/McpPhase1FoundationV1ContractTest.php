<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class McpPhase1FoundationV1ContractTest extends TestCase
{
    private string $root;
    private array $release;
    private string $sql;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $this->release = require $this->root . '/config/mcp_phase1_foundation_release.php';
        $this->sql = (string)file_get_contents(
            $this->root . '/database/20260720_microgifter_mcp_automation_foundation_v1.sql'
        );
    }

    public function testReleaseIsDisabledAndDependsOnPhaseZeroAndTaskAgentPhaseFour(): void
    {
        self::assertSame('microgifter_mcp_phase1_foundation_v1', $this->release['release_key']);
        self::assertContains('microgifter_mcp_phase0_contract_v1', $this->release['depends_on']);
        self::assertContains('task_agent_phase4_v1', $this->release['depends_on']);

        foreach ([
            'enabled_by_default',
            'public_http_enabled',
            'oauth_enabled',
            'scheduler_enabled',
            'worker_enabled',
            'write_tools_enabled',
            'bounded_automation_enabled',
        ] as $flag) {
            self::assertFalse($this->release['runtime'][$flag], $flag . ' must be disabled.');
        }
    }

    public function testOneMigrationCreatesAllControlPlaneTables(): void
    {
        self::assertSame(
            ['20260720_microgifter_mcp_automation_foundation_v1.sql'],
            $this->release['required_migrations']
        );
        self::assertCount(16, $this->release['control_plane_tables']);

        foreach ($this->release['control_plane_tables'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $this->sql);
        }
    }

    public function testFoundationMigrationFollowsTaskAgentPhaseFour(): void
    {
        $manifest = require $this->root . '/config/migrations.php';
        $ordered = array_values($manifest['ordered_files']);
        $phase4 = array_search('20260720_task_agent_phase4_v1.sql', $ordered, true);
        $mcp = array_search('20260720_microgifter_mcp_automation_foundation_v1.sql', $ordered, true);

        self::assertIsInt($phase4);
        self::assertIsInt($mcp);
        self::assertSame($phase4 + 1, $mcp);
    }

    public function testMigrationSeedsOnlyInitialReadScopesAsActive(): void
    {
        self::assertStringContainsString(
            "('profile:read','Read account context'",
            $this->sql
        );
        self::assertStringContainsString(
            "('catalog:read','Read published catalog'",
            $this->sql
        );
        self::assertStringContainsString(
            "('automation:manage','Manage automations'",
            $this->sql
        );
        self::assertSame(['profile:read', 'catalog:read'], $this->release['initial_active_scopes']);
    }

    public function testNoSecretCredentialColumnsAreIntroduced(): void
    {
        self::assertDoesNotMatchRegularExpression(
            '/(?:password|client_secret|access_token|refresh_token)\s+(?:VARCHAR|TEXT|JSON)/i',
            $this->sql
        );
    }

    public function testTypeScriptFoundationIsPinnedAndLockfileBacked(): void
    {
        $package = json_decode(
            (string)file_get_contents($this->root . '/services/mcp/package.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $lock = json_decode(
            (string)file_get_contents($this->root . '/services/mcp/package-lock.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame('>=20', $package['engines']['node']);
        self::assertSame('5.8.3', $package['devDependencies']['typescript']);
        self::assertSame('5.8.3', $lock['packages']['node_modules/typescript']['version']);
    }

    public function testTypedPortsKeepNodeOutsideTheDatabase(): void
    {
        $contracts = (string)file_get_contents($this->root . '/services/mcp/src/contracts.ts');

        self::assertStringContainsString('interface CanonicalBridge', $contracts);
        self::assertStringContainsString('interface AutomationQueue', $contracts);
        self::assertStringContainsString('interface WorkerLeaseRepository', $contracts);
        self::assertStringContainsString('interface AutomationRepository', $contracts);
        self::assertNotContains('database_credentials', $this->release['boundaries']);
        self::assertContains('no_node_database_credentials', $this->release['boundaries']);
    }
}
