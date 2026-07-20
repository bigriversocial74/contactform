<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class McpPhase0ArchitectureContractV1Test extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function source(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, $path . ' must be readable.');
        return $source;
    }

    public function testReleaseManifestLocksReadOnlyFirstAutomationReadyPosture(): void
    {
        $release = require $this->root . '/config/mcp_phase0_release.php';

        self::assertSame('microgifter_mcp_phase0_contract_v1', $release['release_key']);
        self::assertSame('microgifter_platform_phase5', $release['program']);
        self::assertContains('task_agent_phase4_v1', $release['depends_on']);
        self::assertFalse($release['runtime_enabled']);
        self::assertFalse($release['sql_required']);
        self::assertSame('services/mcp/', $release['service_boundary']['gateway']);
        self::assertSame('typescript_node', $release['service_boundary']['gateway_runtime']);
        self::assertSame('php_canonical_services_only', $release['service_boundary']['database_access']);
    }

    public function testInitialToolsAreExactlyTheThreeReadOnlyContracts(): void
    {
        $release = require $this->root . '/config/mcp_phase0_release.php';
        $tools = $release['initial_tools'];

        self::assertSame([
            'microgifter.account.get_connection_context',
            'microgifter.catalog.search',
            'microgifter.catalog.get_item',
        ], array_keys($tools));

        self::assertSame('profile:read', $tools['microgifter.account.get_connection_context']['scope']);
        self::assertSame('catalog:read', $tools['microgifter.catalog.search']['scope']);
        self::assertSame('catalog:read', $tools['microgifter.catalog.get_item']['scope']);
        self::assertSame(25, $tools['microgifter.catalog.search']['maximum_page_size']);

        foreach ($tools as $tool) {
            self::assertSame('read', $tool['operation_class']);
        }
    }

    public function testOperationClassesAndAutomationFoundationAreComplete(): void
    {
        $release = require $this->root . '/config/mcp_phase0_release.php';

        self::assertSame([
            'read',
            'monitor',
            'recommend',
            'task',
            'draft',
            'approval_gated',
            'bounded_auto',
            'prohibited',
        ], $release['operation_classes']);

        foreach ([
            'durable_grants',
            'automation_definitions',
            'trigger_contracts',
            'scheduler_interfaces',
            'queue_and_worker_interfaces',
            'run_and_action_state_machines',
            'approval_linkage',
            'fresh_state_validation',
            'idempotency',
            'budgets_and_limits',
            'invocation_and_action_receipts',
            'revocation_and_kill_switches',
        ] as $required) {
            self::assertContains($required, $release['automation_foundation']);
        }
    }

    public function testCanonicalAuthoritiesExistAndPhase4IsTheDeclaredDependency(): void
    {
        $release = require $this->root . '/config/mcp_phase0_release.php';
        $phase4 = require $this->root . '/config/task_agent_phase4_release.php';

        self::assertSame('task_agent_phase4_v1', $phase4['release_key']);
        self::assertContains('20260720_task_agent_phase4_v1.sql', $phase4['required_migrations']);

        foreach ([
            'api/bootstrap.php',
            'api/catalog/_catalog.php',
            'includes/public-product-foundation.php',
            'api/public/product.php',
            'api/agents/_execution.php',
            'api/agents/_workflow.php',
            'config/migrations.php',
        ] as $path) {
            self::assertFileExists($this->root . '/' . $path, $path . ' is a required canonical authority.');
        }

        $authorities = array_merge(...array_values($release['canonical_authorities']));
        foreach ([
            'user_recurring_gift_programs',
            'user_group_gifts',
            'distribution_programs',
            'agent_strategies',
            'agent_workflow_runs',
            'agent_workflow_actions',
            'agent_approval_requests',
            'agent_execution_events',
        ] as $authority) {
            self::assertContains($authority, $authorities);
        }
    }

    public function testPhase1MigrationContractIsForwardCompatibleWithManifest(): void
    {
        $release = require $this->root . '/config/mcp_phase0_release.php';
        $migrations = require $this->root . '/config/migrations.php';
        $ordered = array_values($migrations['ordered_files']);

        $phase4 = array_search('20260720_task_agent_phase4_v1.sql', $ordered, true);
        self::assertIsInt($phase4);
        self::assertSame(
            '20260720_microgifter_mcp_automation_foundation_v1.sql',
            $release['planned_phase1_migration']
        );

        $phase1 = array_search($release['planned_phase1_migration'], $ordered, true);
        if ($phase1 !== false) {
            self::assertGreaterThan($phase4, $phase1);
        }
    }

    public function testSpecificationLocksSecurityAndAuthorityBoundaries(): void
    {
        $spec = $this->source('docs/MICROGIFTER_MCP_AUTOMATION_PLATFORM_SPEC.md');
        $contract = $this->source('docs/MICROGIFTER_MCP_PHASE0_ARCHITECTURE_CONTRACT_LOCK.md');

        foreach ([
            'read-only first; automation-capable by design',
            'Microgifter remains the system of record',
            'There is no administrator bypass',
            'or unbounded autonomous mode',
            'The Node service never receives database credentials',
            'No unattended execution is valid without an active grant',
            'The existing Approval Center remains canonical',
        ] as $boundary) {
            self::assertTrue(
                str_contains($spec, $boundary) || str_contains($contract, $boundary),
                $boundary . ' must remain explicit.'
            );
        }
    }

    public function testReleaseBoundaryListIsComplete(): void
    {
        $release = require $this->root . '/config/mcp_phase0_release.php';

        foreach ([
            'read_only_first_automation_capable_by_design',
            'microgifter_remains_domain_and_execution_authority',
            'oauth_scope_is_necessary_but_not_sufficient',
            'unattended_execution_requires_active_durable_grant',
            'node_gateway_has_no_database_credentials',
            'protected_php_bridge_calls_canonical_services',
            'browser_session_and_csrf_controls_remain_unchanged',
            'no_generic_sql_file_shell_callback_webhook_or_url_tools',
            'no_unbounded_autonomous_mode',
            'no_phase0_runtime_endpoint',
            'no_phase0_sql',
        ] as $boundary) {
            self::assertContains($boundary, $release['release_boundaries']);
        }
    }
}
