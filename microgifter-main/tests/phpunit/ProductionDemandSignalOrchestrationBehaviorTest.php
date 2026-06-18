<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionDemandSignalOrchestrationBehaviorTest extends TestCase
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

    public function testDemandSignalWorkflowAndSwarmBehaviorAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')==='')self::markTestSkipped('Database-backed demand orchestration validation requires MG_DB_HOST.');
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($this->root.'/scripts/validate_demand_orchestration_behavior.php').' 2>&1';
        $output=[];$exitCode=0;exec($command,$output,$exitCode);$raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);
        $result=json_decode($raw,true);self::assertIsArray($result,$raw);
        self::assertSame('demand_signal_agent_orchestration_behavior',$result['suite']??null);
        foreach([
            'workflow_dispatched','merchant_strategy_scoped','critical_requires_approval','dispatch_replay_safe',
            'workflow_completed','signal_acknowledged_once','unsupported_review_alert','swarm_dispatched',
            'swarm_completed','budget_consumed_once','failed_execution_leaves_signal_open','failure_reconciled',
            'downstream_failure_rolls_back','events_and_alerts_once','fixtures_clean',
        ] as $key){self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);}
    }

    public function testSchemaAddsReplaySafeBridgeWithoutNewBusinessAuthority(): void
    {
        $sql=$this->read('database/stage_17b_demand_signal_agent_orchestration.sql');
        foreach([
            'CREATE TABLE IF NOT EXISTS demand_signal_orchestrations',
            'CREATE TABLE IF NOT EXISTS demand_signal_orchestration_events',
            'uq_demand_signal_orchestrations_signal',
            'uq_demand_signal_orchestrations_dispatch',
            'fk_demand_signal_orchestrations_signal',
            'fk_demand_signal_orchestrations_workflow',
            'fk_demand_signal_orchestrations_swarm',
            'stage_17b_demand_signal_agent_orchestration',
        ] as $needle)self::assertStringContainsString($needle,$sql);
        foreach(['CREATE TABLE IF NOT EXISTS wallets','CREATE TABLE IF NOT EXISTS payment_intents','INSERT INTO ledger_entries','INSERT INTO tips','INSERT INTO microgift_instances'] as $forbidden){
            self::assertStringNotContainsString($forbidden,$sql);
        }
    }

    public function testBridgeDelegatesOnlyToCanonicalStage16Stage17AndCommunicationsServices(): void
    {
        $service=$this->read('api/agents/_demand_orchestration.php');
        foreach([
            'mg_agent_create_run(','mg_agent_plan_run(','mg_swarm_create_run(','mg_create_operational_alert(',
            'FOR UPDATE SKIP LOCKED','acknowledge_demand_signal','create_operational_alert',
            "'after_orchestration_created'",'demand_signal_orchestration_events',
        ] as $needle)self::assertStringContainsString($needle,$service);
        foreach(['mg_ledger_post(','mg_ledger_reverse(','INSERT INTO ledger_entries','UPDATE payment_intents','UPDATE tips','UPDATE microgift_instances'] as $forbidden){
            self::assertStringNotContainsString($forbidden,$service);
        }
    }

    public function testRecommendationsAreTranslatedToReviewAndAcknowledgementOnly(): void
    {
        $service=$this->read('api/agents/_demand_orchestration.php');
        self::assertStringContainsString("'create_operational_alert'",$service);
        self::assertStringContainsString("'acknowledge_demand_signal'",$service);
        self::assertStringContainsString('Review the demand dashboard before taking any business action.',$service);
        self::assertStringContainsString('No active merchant-owned demand strategy can safely translate this recommendation.',$service);
        self::assertStringNotContainsString("'increase_capacity'=>",$service);
        self::assertStringNotContainsString("'prepare_inventory'=>",$service);
        self::assertStringNotContainsString("'schedule_staffing'=>",$service);
    }

    public function testWorkerMigrationAndValidationAreRegistered(): void
    {
        $composer=$this->read('composer.json');
        $builder=$this->read('scripts/build_full_upgrade_sql.php');
        $stage17=$this->read('scripts/stage17.php');
        $smoke=$this->read('scripts/stage17_smoke.php');
        $workflow=$this->read('.github/workflows/demand-signal-orchestration-validation.yml');
        self::assertStringContainsString('"test-demand-orchestration-behavior": "php scripts/validate_demand_orchestration_behavior.php"',$composer);
        $base=strpos($builder,"'stage_17_multi_agent_swarms.sql'");
        $bridge=strpos($builder,"'stage_17b_demand_signal_agent_orchestration.sql'");
        $stage18=strpos($builder,"'stage_18_production_hardening_launch_readiness.sql'");
        self::assertIsInt($base);self::assertIsInt($bridge);self::assertIsInt($stage18);
        self::assertLessThan($bridge,$base);self::assertLessThan($stage18,$bridge);
        self::assertStringContainsString('stage_17b_demand_signal_agent_orchestration.sql',$stage17);
        self::assertStringContainsString('demand_signal_orchestrations',$smoke);
        self::assertStringContainsString('composer test-demand-orchestration-behavior',$workflow);
        self::assertStringContainsString('php scripts/stage15.php',$workflow);
        self::assertStringContainsString('php scripts/stage16.php',$workflow);
        self::assertStringContainsString('php scripts/stage17.php',$workflow);
    }

    public function testBehaviorRunnerCoversApprovalReplaySwarmFailureAndRollback(): void
    {
        $source=$this->read('scripts/validate_demand_orchestration_behavior.php');
        foreach([
            "'critical_requires_approval'=>false","'dispatch_replay_safe'=>false","'unsupported_review_alert'=>false",
            "'swarm_completed'=>false","'budget_consumed_once'=>false","'failed_execution_leaves_signal_open'=>false",
            "'downstream_failure_rolls_back'=>false",'SAVEPOINT demand_orchestration_failure',
            'ROLLBACK TO SAVEPOINT demand_orchestration_failure','mg_swarm_route_next_task(',
            'mg_agent_process_next_action(','mg_demand_reconcile_next_orchestration(',
        ] as $needle)self::assertStringContainsString($needle,$source);
    }
}
