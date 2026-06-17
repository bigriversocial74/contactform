<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage17MultiAgentSwarmsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    private function read(string $path): string
    {
        $source=file_get_contents($this->root.'/'.$path);
        self::assertIsString($source,$path);
        return $source;
    }

    public function testSchemaDefinesTeamsRoutesRunsTasksDependenciesConflictsAndEvents(): void
    {
        $sql=$this->read('database/stage_17_multi_agent_swarms.sql');
        foreach(['agent_teams','agent_team_members','agent_provider_routes','agent_swarm_runs','agent_swarm_tasks','agent_swarm_task_dependencies','agent_swarm_conflicts','agent_swarm_events'] as $table){
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);
        }
        self::assertStringContainsString('uq_agent_swarm_runs_idempotency',$sql);
        self::assertStringContainsString('chk_agent_swarm_dependency_not_self',$sql);
        self::assertStringContainsString('chk_agent_swarm_budget',$sql);
    }

    public function testTeamsUseOwnedExistingAgentsAndExplicitCapabilities(): void
    {
        $source=$this->read('api/agents/_swarm.php');
        self::assertStringContainsString('mg_agent_owned(',$source);
        self::assertStringContainsString('capabilities_json',$source);
        self::assertStringContainsString('routing_profile_json',$source);
        self::assertStringContainsString('Role key and capabilities are required.',$source);
    }

    public function testDependencyGraphRejectsUnknownSelfAndCycles(): void
    {
        $source=$this->read('api/agents/_swarm.php');
        self::assertStringContainsString('Task dependency graph is invalid.',$source);
        self::assertStringContainsString('Task dependency graph contains a cycle.',$source);
        self::assertStringContainsString('$visiting',$source);
        self::assertStringContainsString('$visited',$source);
    }

    public function testSwarmRunsAreOwnerScopedIdempotentAndBudgetBound(): void
    {
        $source=$this->read('api/agents/_swarm.php');
        self::assertStringContainsString('owner_user_id=? AND idempotency_key=?',$source);
        self::assertStringContainsString('Swarm task estimates exceed the run budget.',$source);
        self::assertStringContainsString('reserved_units',$source);
        self::assertStringContainsString('swarm_queued',$source);
    }

    public function testRoutingUsesCapabilitiesConcurrencyPriorityAndProviderRoutes(): void
    {
        $source=$this->read('api/agents/_swarm.php');
        self::assertStringContainsString('JSON_CONTAINS(tm.capabilities_json,JSON_QUOTE(?))',$source);
        self::assertStringContainsString('max_concurrent_tasks',$source);
        self::assertStringContainsString('agent_provider_routes',$source);
        self::assertStringContainsString('Multiple equally ranked agents can execute this task.',$source);
    }

    public function testSwarmProcessorDelegatesExecutionToStage16(): void
    {
        $worker=$this->read('scripts/process_swarm_tasks.php');
        $service=$this->read('api/agents/_swarm_workflow.php');
        self::assertStringContainsString('mg_swarm_route_next_task(',$worker);
        self::assertStringContainsString('mg_swarm_sync_workflow(',$worker);
        self::assertStringContainsString('agent_workflow_runs',$service);
        self::assertStringContainsString('mg_agent_execution_event(',$service);
        self::assertStringContainsString("'source'=>'stage17_swarm'",$service);
        self::assertStringContainsString('Strategy agent does not match routed team member.',$service);
        self::assertStringNotContainsString('microgift_instances SET',$service);
        self::assertStringNotContainsString('wallet_accounts SET',$service);
        self::assertStringNotContainsString('ledger_entries',$service);
    }

    public function testConflictResolutionIsOwnerScopedAndAudited(): void
    {
        $source=$this->read('api/agents/swarm-conflicts.php');
        self::assertStringContainsString('sr.owner_user_id=?',$source);
        self::assertStringContainsString('FOR UPDATE',$source);
        self::assertStringContainsString('mg_swarm_resolve_conflict(',$source);
        self::assertStringContainsString('mg_audit(',$source);
    }

    public function testObservabilityReturnsTasksEventsConflictsAndBudget(): void
    {
        $source=$this->read('api/agents/swarm-observability.php');
        foreach(['tasks','events','conflicts','budget'] as $contract){self::assertStringContainsString("'{$contract}'",$source);}
        self::assertStringContainsString('remaining',$source);
        self::assertStringContainsString('workflow_run_public_id',$source);
    }
}
