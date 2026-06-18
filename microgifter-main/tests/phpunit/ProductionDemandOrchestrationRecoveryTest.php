<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionDemandOrchestrationRecoveryTest extends TestCase
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

    public function testRecoveryBehaviorAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')==='')self::markTestSkipped('Database-backed Stage 18C validation requires MG_DB_HOST.');
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($this->root.'/scripts/validate_demand_orchestration_recovery_behavior.php').' 2>&1';
        $output=[];$exitCode=0;exec($command,$output,$exitCode);$raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);
        $result=json_decode($raw,true);self::assertIsArray($result,$raw);
        self::assertSame('demand_orchestration_retry_incident_retention',$result['suite']??null);
        foreach([
            'failed_retry_dispatched','initial_attempt_preserved','retry_replay_safe','retry_completed',
            'review_retry_promoted','downstream_failure_rolls_back','critical_incident_opened_once',
            'critical_incident_resolved','completed_events_retained_safely','active_events_preserved',
            'business_truth_preserved','permissions_admin_only','fixtures_clean',
        ] as $key){self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);}
    }

    public function testSchemaAddsAttemptsIncidentLinksAndAdministratorRetryPermission(): void
    {
        $recovery=$this->read('database/stage_18c_demand_orchestration_recovery.sql');
        $retention=$this->read('database/stage_18c2_demand_orchestration_retention.sql');
        foreach([
            'CREATE TABLE IF NOT EXISTS demand_signal_orchestration_attempts',
            'CREATE TABLE IF NOT EXISTS demand_signal_orchestration_incidents',
            'uq_demand_orchestration_attempts_request',
            'operations.orchestrations.retry',
            "r.slug IN ('admin','super_admin')",
            'stage_18c_demand_orchestration_recovery',
        ] as $needle)self::assertStringContainsString($needle,$recovery);
        foreach(['demand_orchestration_events_365d','completed_orchestrations_only','preserve_latest_event','stage_18c2_demand_orchestration_retention'] as $needle){
            self::assertStringContainsString($needle,$retention);
        }
    }

    public function testRetryDelegatesToCanonicalWorkflowAndSwarmAuthorities(): void
    {
        $service=$this->read('api/operations/_orchestration_recovery.php');
        $coordinator=$this->read('api/operations/_orchestration_retry.php');
        $attempts=$this->read('api/operations/_orchestration_attempts.php');
        foreach(['mg_agent_create_run(','mg_agent_plan_run(','mg_swarm_create_run(','mg_operations_create_incident(','mg_operations_transition_incident('] as $needle){
            self::assertStringContainsString($needle,$service);
        }
        foreach(['mg_operations_ensure_initial_orchestration_attempt(','request_idempotency_key',"'duplicate'=>true"] as $needle){
            self::assertStringContainsString($needle,$attempts);
        }
        self::assertStringContainsString('orchestration_type=?',$coordinator);
        foreach(['mg_ledger_post(','INSERT INTO ledger_entries','UPDATE payment_intents','UPDATE tips','UPDATE subscriptions','UPDATE microgift_instances'] as $forbidden){
            self::assertStringNotContainsString($forbidden,$service);
            self::assertStringNotContainsString($forbidden,$coordinator);
        }
    }

    public function testRetryAndIncidentEndpointsArePermissionedCsrfProtectedWrites(): void
    {
        $retry=$this->read('api/admin/operations-orchestration-retry.php');
        $incidents=$this->read('api/admin/operations-orchestration-incidents-reconcile.php');
        self::assertStringContainsString("mg_require_method('POST')",$retry);
        self::assertStringContainsString("mg_require_permission('operations.orchestrations.retry')",$retry);
        self::assertStringContainsString('mg_require_csrf_for_write(',$retry);
        self::assertStringContainsString('mg_operations_retry_demand_orchestration_coordinated(',$retry);
        self::assertStringContainsString("mg_require_method('POST')",$incidents);
        self::assertStringContainsString("mg_require_permission('operations.incidents.manage')",$incidents);
        self::assertStringContainsString('mg_require_csrf_for_write(',$incidents);
        self::assertStringContainsString('mg_operations_reconcile_demand_incidents(',$incidents);
    }

    public function testRetentionDeletesOnlySupersededEventsForCompletedRoots(): void
    {
        $service=$this->read('api/operations/_retention.php');
        foreach([
            "'demand_signal_orchestration_events'=>['created_at']",
            "o.status='completed'",
            'MAX(id) keep_id',
            'e.id<>latest.keep_id',
            'mg_retention_delete_orchestration_events(',
        ] as $needle)self::assertStringContainsString($needle,$service);
        self::assertStringNotContainsString('DELETE FROM demand_signal_orchestrations',$service);
        self::assertStringNotContainsString('DELETE FROM demand_agent_signals',$service);
        self::assertStringNotContainsString('DELETE FROM agent_workflow_runs',$service);
        self::assertStringNotContainsString('DELETE FROM agent_swarm_runs',$service);
    }

    public function testStage18RegistrationAndFocusedValidation(): void
    {
        $builder=$this->read('scripts/build_full_upgrade_sql.php');
        $runner=$this->read('scripts/stage18.php');
        $smoke=$this->read('scripts/stage18c_smoke.php');
        $composer=$this->read('composer.json');
        $workflow=$this->read('.github/workflows/demand-orchestration-recovery-validation.yml');
        $base=strpos($builder,"'stage_18b_demand_orchestration_operations.sql'");
        $recovery=strpos($builder,"'stage_18c_demand_orchestration_recovery.sql'");
        $retention=strpos($builder,"'stage_18c2_demand_orchestration_retention.sql'");
        self::assertIsInt($base);self::assertIsInt($recovery);self::assertIsInt($retention);
        self::assertLessThan($recovery,$base);self::assertLessThan($retention,$recovery);
        self::assertStringContainsString('stage_18c_demand_orchestration_recovery.sql',$runner);
        self::assertStringContainsString('stage_18c2_demand_orchestration_retention.sql',$runner);
        self::assertStringContainsString('operations.orchestrations.retry',$smoke);
        self::assertStringContainsString('"test-demand-orchestration-recovery": "php scripts/validate_demand_orchestration_recovery_behavior.php"',$composer);
        self::assertStringContainsString('composer test-demand-orchestration-recovery',$workflow);
        self::assertStringContainsString('php scripts/stage18c_smoke.php',$workflow);
        self::assertStringContainsString('php scripts/validate_launch_readiness.php',$workflow);
    }
}
